<?php
/*
 * Fusio
 * A web-application to create dynamically RESTful APIs
 *
 * Copyright (C) 2015-2022 Christoph Kappestein <christoph.kappestein@gmail.com>
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
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types;
use Fusio\Engine\ActionAbstract;
use Fusio\Engine\Exception\ConfigurationException;
use Fusio\Engine\Form\BuilderInterface;
use Fusio\Engine\Form\ElementFactoryInterface;
use Fusio\Engine\ParametersInterface;
use Fusio\Engine\RequestInterface;
use PSX\Http\Exception as StatusCode;
use PSX\Record\Record;

/**
 * Action which allows you to create an API endpoint based on any database
 * table
 *
 * @author  Christoph Kappestein <christoph.kappestein@gmail.com>
 * @license http://www.gnu.org/licenses/agpl-3.0
 * @link    https://www.fusio-project.org/
 */
abstract class SqlActionAbstract extends ActionAbstract
{
    public function configure(BuilderInterface $builder, ElementFactoryInterface $elementFactory): void
    {
        $builder->add($elementFactory->newConnection('connection', 'Connection', 'The SQL connection which should be used'));
        $builder->add($elementFactory->newInput('table', 'Table', 'text', 'Name of the database table'));
    }

    protected function getTable(Connection $connection, string $tableName): Table
    {
        $key   = md5(__CLASS__ . $tableName);
        $table = $this->cache->get($key);

        if ($table === null) {
            $sm = $connection->getSchemaManager();

            if ($sm->tablesExist([$tableName])) {
                $table = $sm->listTableDetails($tableName);
                $this->cache->set($key, $table);
            } else {
                throw new StatusCode\InternalServerErrorException('Table ' . $tableName . ' does not exist on connection');
            }
        }

        return $table;
    }

    protected function getData(RequestInterface $request, Connection $connection, Table $table, ?bool $validateNull = false): array
    {
        $body = Record::from($request->getPayload());
        $data = [];

        $columns = $table->getColumns();
        foreach ($columns as $column) {
            if ($column->getAutoincrement()) {
                // in case the column is autoincrement we dont need it
                continue;
            }

            $value = null;
            if ($body->hasProperty($column->getName())) {
                $value = $body->getProperty($column->getName());
            } elseif (!$validateNull) {
                continue;
            } elseif ($column->getDefault()) {
                continue;
            }

            if ($value === null && $column->getNotnull() && $validateNull) {
                throw new StatusCode\BadRequestException('Column ' . $column->getName() . ' must not be null');
            }

            if ($value instanceof \DateTime) {
                $platform = $connection->getDatabasePlatform();
                if ($column->getType() instanceof Types\DateType) {
                    $value = $value->format($platform->getDateFormatString());
                } elseif ($column->getType() instanceof Types\DateTimeType) {
                    $value = $value->format($platform->getDateTimeFormatString());
                } elseif ($column->getType() instanceof Types\DateTimeTzType) {
                    $value = $value->format($platform->getDateTimeTzFormatString());
                } elseif ($column->getType() instanceof Types\TimeType) {
                    $value = $value->format($platform->getTimeFormatString());
                }
            }

            $data[$column->getName()] = $value;
        }

        return $data;
    }

    protected function getAvailableColumns(Table $table): array
    {
        return array_keys($table->getColumns());
    }

    protected function getColumns(Table $table, ?array $columns): array
    {
        $allColumns = $this->getAvailableColumns($table);
        if (!empty($columns)) {
            $allColumns = array_intersect($allColumns, $columns);
        }

        return $allColumns;
    }

    protected function getPrimaryKey(Table $table)
    {
        $primaryKey = $table->getPrimaryKey();
        if ($primaryKey instanceof Index) {
            $columns = $primaryKey->getColumns();
            return reset($columns);
        } else {
            throw new StatusCode\InternalServerErrorException('Primary column not available');
        }
    }

    /**
     * Converts a raw database row to the correct PHP types
     */
    protected function convertRow(array $row, Connection $connection, Table $table): array
    {
        $result = [];
        foreach ($row as $key => $value) {
            $type = $table->getColumn($key)->getType();
            $val  = $type->convertToPHPValue($value, $connection->getDatabasePlatform());

            if ($val === null) {
            } elseif ($type instanceof Types\DateType) {
                $val = $val->format('Y-m-d');
            } elseif ($type instanceof Types\DateTimeType) {
                $val = $val->format(\DateTime::RFC3339);
            } elseif ($type instanceof Types\DateTimeTzType) {
                $val = $val->format(\DateTime::RFC3339_EXTENDED);
            } elseif ($type instanceof Types\TimeType) {
                $val = $val->format('H:i:s');
            } elseif ($type instanceof Types\BinaryType || $type instanceof Types\BlobType) {
                $val = base64_encode(stream_get_contents($val));
            }

            $result[$key] = $val;
        }

        return $result;
    }

    protected function getConnection(ParametersInterface $configuration): Connection
    {
        $connection = $this->connector->getConnection($configuration->get('connection'));
        if (!$connection instanceof Connection) {
            throw new ConfigurationException('Given connection must be a DBAL connection');
        }

        return $connection;
    }

    protected function getTableName(ParametersInterface $configuration): string
    {
        $tableName = $configuration->get('table');
        if (empty($tableName)) {
            throw new ConfigurationException('No table name provided');
        }

        return $tableName;
    }

    protected function fetchRow(Connection $connection, array $columns, string $tableName, string $primaryKey, int $id)
    {
        $qb = $connection->createQueryBuilder();
        $qb->select($columns);
        $qb->from($tableName);
        $qb->where($primaryKey . ' = :id');
        $qb->setParameter('id', $id);

        return $connection->fetchAssociative($qb->getSQL(), $qb->getParameters());
    }
}
