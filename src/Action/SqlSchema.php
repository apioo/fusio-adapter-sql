<?php
/*
 * Fusio
 * A web-application to create dynamically RESTful APIs
 *
 * Copyright (C) 2015-2019 Christoph Kappestein <christoph.kappestein@gmail.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace Fusio\Adapter\Sql\Action;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\SchemaException;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Type;
use Fusio\Engine\ActionAbstract;
use Fusio\Engine\ContextInterface;
use Fusio\Engine\Exception\ConfigurationException;
use Fusio\Engine\Form\BuilderInterface;
use Fusio\Engine\Form\ElementFactoryInterface;
use Fusio\Engine\ParametersInterface;
use Fusio\Engine\RequestInterface;
use PSX\Http\Exception as StatusCode;
use PSX\Record\RecordInterface;

/**
 * Class which provides an action to inspect and modify the schema of a SQL
 * connection
 * 
 * @author  Christoph Kappestein <christoph.kappestein@gmail.com>
 * @license http://www.gnu.org/licenses/agpl-3.0
 * @link    http://fusio-project.org
 */
class SqlSchema extends ActionAbstract
{
    public function getName()
    {
        return 'SQL-Schema';
    }

    public function handle(RequestInterface $request, ParametersInterface $configuration, ContextInterface $context)
    {
        $connection = $this->connector->getConnection($configuration->get('connection'));

        if ($connection instanceof Connection) {
            $schema    = $connection->getSchemaManager()->createSchema();
            $body      = $request->getBody();
            $tableName = $request->getUriFragment('table');
            $preview   = $request->getParameter('preview') == 1;

            if (!empty($tableName) && !preg_match('/^[A-z0-9\_]{3,32}$/', $tableName)) {
                throw new StatusCode\BadRequestException('Invalid table name');
            }

            switch ($request->getMethod()) {
                case 'HEAD':
                case 'GET':
                    if (empty($tableName)) {
                        return $this->doGetCollection($schema);
                    } else {
                        return $this->doGetEntity($schema, $tableName);
                    }
                    break;

                case 'POST':
                    if (!empty($tableName)) {
                        throw new StatusCode\MethodNotAllowedException('Method not allowed', ['GET', 'POST']);
                    }

                    return $this->doPost($connection, $schema, $body, $preview);
                    break;

                case 'PUT':
                    if (empty($tableName)) {
                        throw new StatusCode\MethodNotAllowedException('Method not allowed', ['GET', 'PUT', 'DELETE']);
                    }

                    return $this->doPut($connection, $schema, $tableName, $body, $preview);
                    break;

                case 'DELETE':
                    if (empty($tableName)) {
                        throw new StatusCode\MethodNotAllowedException('Method not allowed', ['GET', 'PUT', 'DELETE']);
                    }

                    return $this->doDelete($connection, $schema, $tableName, $preview);
                    break;
            }

            if (empty($tableName)) {
                throw new StatusCode\MethodNotAllowedException('Method not allowed', ['GET', 'POST']);
            } else {
                throw new StatusCode\MethodNotAllowedException('Method not allowed', ['GET', 'PUT', 'DELETE']);
            }
        } else {
            throw new ConfigurationException('Given connection must be a DBAL connection');
        }
    }

    public function configure(BuilderInterface $builder, ElementFactoryInterface $elementFactory)
    {
        $builder->add($elementFactory->newConnection('connection', 'Connection', 'The target SQL connection. NOTE through this it is possible to inspect and modify the schema on this connection so it is recommended to use this action only on private routes'));
    }

    protected function doGetCollection(Schema $schema)
    {
        $tables = $schema->getTables();
        $names  = [];

        foreach ($tables as $table) {
            $names[] = $table->getName();
        }

        return $this->response->build(200, [], [
            'tables' => $names,
        ]);
    }

    protected function doGetEntity(Schema $schema, $tableName)
    {
        try {
            $table = $schema->getTable($tableName);
        } catch (SchemaException $e) {
            if ($e->getCode() == SchemaException::TABLE_DOESNT_EXIST) {
                throw new StatusCode\NotFoundException('Provided table ' . $tableName . ' does not exist');
            } else {
                throw $e;
            }
        }

        $data  = [
            'name'    => $table->getName(),
            'columns' => $this->getColumns($table),
        ];

        $indexes = $this->getIndexes($table);
        if (!empty($indexes)) {
            $data['indexes'] = $indexes;
        }

        return $this->response->build(200, [], $data);
    }

    protected function doPost(Connection $connection, Schema $schema, $body, $preview)
    {
        $tableName = $body->name ?? null;

        if (!empty($tableName) && !preg_match('/^[A-z0-9\_]{3,32}$/', $tableName)) {
            throw new StatusCode\BadRequestException('Invalid table name');
        }

        $table = $schema->createTable($tableName);

        $this->buildTable($body, $table);

        $fromSchema = $connection->getSchemaManager()->createSchema();
        $queries    = $fromSchema->getMigrateToSql($schema, $connection->getDatabasePlatform());

        if (!$preview) {
            foreach ($queries as $query) {
                $connection->query($query);
            }
        }

        return $this->response->build(201, [], [
            'executed' => !$preview,
            'table'    => $tableName,
            'columns'  => $this->getColumns($table),
            'queries'  => $queries,
        ]);
    }

    protected function doPut(Connection $connection, Schema $schema, $tableName, $body, $preview)
    {
        $table = $schema->getTable($tableName);

        $this->buildTable($body, $table);

        $fromSchema = $connection->getSchemaManager()->createSchema();
        $queries    = $fromSchema->getMigrateToSql($schema, $connection->getDatabasePlatform());

        if (!$preview) {
            foreach ($queries as $query) {
                $connection->query($query);
            }
        }

        return $this->response->build(200, [], [
            'executed' => !$preview,
            'table'    => $tableName,
            'columns'  => $this->getColumns($table),
            'queries'  => $queries,
        ]);
    }

    protected function doDelete(Connection $connection, Schema $schema, $tableName, $preview)
    {
        $schema->dropTable($tableName);

        $fromSchema = $connection->getSchemaManager()->createSchema();
        $queries    = $fromSchema->getMigrateToSql($schema, $connection->getDatabasePlatform());

        if (!$preview) {
            foreach ($queries as $query) {
                $connection->query($query);
            }
        }

        return $this->response->build(200, [], [
            'executed' => !$preview,
            'queries'  => $queries,
        ]);
    }

    protected function getColumns(Table $table)
    {
        $columns = [];
        foreach ($table->getColumns() as $column) {
            $columns[$column->getName()] = $this->getColumnToArray($column);
        }

        return $columns;
    }

    protected function getIndexes(Table $table)
    {
        $indexes = [];
        foreach ($table->getIndexes() as $index) {
            $indexes[$index->getName()] = $this->getIndexToArray($index);
        }

        return $indexes;
    }

    protected function getColumnToArray(Column $column)
    {
        return [
            'type'          => $column->getType()->getName(),
            'default'       => $column->getDefault(),
            'notnull'       => $column->getNotnull(),
            'length'        => $column->getLength(),
            'precision'     => $column->getPrecision(),
            'scale'         => $column->getScale(),
            'fixed'         => $column->getFixed(),
            'unsigned'      => $column->getUnsigned(),
            'autoincrement' => $column->getAutoincrement(),
            'comment'       => $column->getComment(),
        ];
    }

    protected function getIndexToArray(Index $index)
    {
        return [
            'columns' => $index->getColumns(),
            'unique'  => $index->isUnique(),
            'primary' => $index->isPrimary(),
        ];
    }

    protected function buildTable(RecordInterface $data, Table $table)
    {
        $allowedOptions = [
            'default',
            'notnull',
            'length',
            'precision',
            'scale',
            'fixed',
            'unsigned',
            'autoincrement',
            'comment',
        ];

        if (!isset($data->columns)) {
            throw new StatusCode\BadRequestException('No columns provided');
        }

        $columnNames = [];
        foreach ($data->columns as $name => $property) {
            $type = isset($property->type) ? $property->type : 'string';

            $options = [];
            foreach ($allowedOptions as $option) {
                if (isset($property->{$option})) {
                    $options[$option] = $property->{$option};
                }
            }

            if ($table->hasColumn($name)) {
                $table->getColumn($name)
                    ->setType(Type::getType($type))
                    ->setOptions($options);
            } else {
                $table->addColumn($name, $type, $options);
            }

            $columnNames[] = $name;
        }

        // remove undefined columns
        $availableColumns = $table->getColumns();
        foreach ($availableColumns as $column) {
            if (!in_array($column->getName(), $columnNames)) {
                $table->dropColumn($column->getName());
            }
        }

        $indexNames = [];
        if (isset($data->indexes)) {
            foreach ($data->indexes as $name => $index) {
                $columns = $index->columns ?? [];
                $unique  = $index->unique ?? null;
                $primary = $index->primary ?? null;
                
                if ($primary) {
                    if ($table->hasPrimaryKey()) {
                        $table->dropPrimaryKey();
                    }

                    $table->setPrimaryKey($columns, $name);

                    $indexNames[] = $name;
                } elseif ($unique) {
                    if ($table->hasIndex($name)) {
                        $table->dropIndex($name);
                    }

                    $table->addUniqueIndex($columns, $name);

                    $indexNames[] = $name;
                } else {
                    if ($table->hasIndex($name)) {
                        $table->dropIndex($name);
                    }

                    $table->addIndex($columns, $name);

                    $indexNames[] = $name;
                }
            }
        }

        $availableIndex = $table->getIndexes();
        foreach ($availableIndex as $index) {
            if (!in_array($index->getName(), $indexNames)) {
                $table->dropIndex($index->getName());
            }
        }

        if (isset($data->constraint)) {
            foreach ($data->constraint as $tableName => $constraint) {
                if (isset($constraint->localColumn) && is_array($constraint->localColumn)) {
                    $localColumnNames = $constraint->localColumn;
                }

                if (isset($constraint->foreignColumn) && is_array($constraint->foreignColumn)) {
                    $foreignColumnNames = $constraint->foreignColumn;
                }

                if (isset($constraint->options) && is_array($constraint->options)) {
                    $options = $constraint->foreignColumn;
                }

                if (!empty($localColumnNames) && !empty($foreignColumnNames)) {
                    $table->addForeignKeyConstraint($tableName, $localColumnNames, $foreignColumnNames, $options);
                }
            }
        }

        if (isset($data->option)) {
            foreach ($data->option as $name => $value) {
                $table->addOption($name, $value);
            }
        }
    }
}
