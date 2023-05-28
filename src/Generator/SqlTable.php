<?php
/*
 * Fusio
 * A web-application to create dynamically RESTful APIs
 *
 * Copyright (C) 2015-2023 Christoph Kappestein <christoph.kappestein@gmail.com>
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

namespace Fusio\Adapter\Sql\Generator;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Table;
use Fusio\Adapter\Sql\Action\SqlDelete;
use Fusio\Adapter\Sql\Action\SqlInsert;
use Fusio\Adapter\Sql\Action\SqlSelectAll;
use Fusio\Adapter\Sql\Action\SqlSelectRow;
use Fusio\Adapter\Sql\Action\SqlUpdate;
use Fusio\Engine\ConnectorInterface;
use Fusio\Engine\Exception\ConfigurationException;
use Fusio\Engine\Factory\Resolver\PhpClass;
use Fusio\Engine\Form\BuilderInterface;
use Fusio\Engine\Form\ElementFactoryInterface;
use Fusio\Engine\Generator\ProviderInterface;
use Fusio\Engine\Generator\SetupInterface;
use Fusio\Engine\ParametersInterface;
use Fusio\Engine\Schema\SchemaBuilder;
use Fusio\Engine\Schema\SchemaName;
use Fusio\Model\Backend\Action;
use Fusio\Model\Backend\ActionConfig;
use Fusio\Model\Backend\Operation;
use Fusio\Model\Backend\Schema;
use Fusio\Model\Backend\SchemaSource;

/**
 * SqlTable
 *
 * @author  Christoph Kappestein <christoph.kappestein@gmail.com>
 * @license http://www.gnu.org/licenses/agpl-3.0
 * @link    https://www.fusio-project.org/
 */
class SqlTable implements ProviderInterface
{
    private const SCHEMA_GET_ALL = 'SQL_GetAll';
    private const SCHEMA_GET = 'SQL_Get';
    private const ACTION_GET_ALL = 'SQL_GetAll';
    private const ACTION_GET = 'SQL_Get';
    private const ACTION_INSERT = 'SQL_Insert';
    private const ACTION_UPDATE = 'SQL_Update';
    private const ACTION_DELETE = 'SQL_Delete';

    private ConnectorInterface $connector;
    private TableBuilder $tableBuilder;

    public function __construct(ConnectorInterface $connector)
    {
        $this->connector = $connector;
        $this->tableBuilder = new TableBuilder();
    }

    public function getName(): string
    {
        return 'SQL-Table';
    }

    public function setup(SetupInterface $setup, string $basePath, ParametersInterface $configuration): void
    {
        $connectionName = $configuration->get('connection') ?? throw new ConfigurationException('No connection provided');
        $tableName = $configuration->get('table') ?? throw new ConfigurationException('No table provided');
        $schemaManager = $this->getConnection($connectionName)->createSchemaManager();

        $this->generateForTable($schemaManager, $connectionName, $tableName, $setup);
    }

    public function configure(BuilderInterface $builder, ElementFactoryInterface $elementFactory): void
    {
        $builder->add($elementFactory->newConnection('connection', 'Connection', 'The SQL connection which should be used'));
        $builder->add($elementFactory->newInput('table', 'Table', 'text', 'Name of the database table'));
    }

    protected function generateForTable(AbstractSchemaManager $schemaManager, string $connectionName, string $tableName, SetupInterface $setup, ?string $name = null): void
    {
        if (!$schemaManager->tablesExist([$tableName])) {
            throw new \RuntimeException('Provided table does not exist');
        }

        $table = $schemaManager->introspectTable($tableName);

        $basePath = '';
        $schemaPrefix = '';
        $actionPrefix = '';
        $operationPrefix = '';
        if (!empty($name)) {
            $basePath = '/' . $name;
            $schemaPrefix = ucfirst($name) . '_';
            $actionPrefix = ucfirst($name) . '_';
            $operationPrefix = $name . '.';
        }

        $setup->addSchema($this->makeGetAllSchema($table, $schemaPrefix));
        $setup->addSchema($this->makeGetSchema($table, $schemaPrefix));

        $setup->addAction($this->makeGetAllAction($connectionName, $tableName, $actionPrefix));
        $setup->addAction($this->makeGetAction($connectionName, $tableName, $actionPrefix));
        $setup->addAction($this->makeInsertAction($connectionName, $tableName, $actionPrefix));
        $setup->addAction($this->makeUpdateAction($connectionName, $tableName, $actionPrefix));
        $setup->addAction($this->makeDeleteAction($connectionName, $tableName, $actionPrefix));

        $setup->addOperation($this->makeGetAllOperation($basePath, $operationPrefix));
        $setup->addOperation($this->makeGetOperation($basePath, $operationPrefix));
        $setup->addOperation($this->makeInsertOperation($basePath, $operationPrefix));
        $setup->addOperation($this->makeUpdateOperation($basePath, $operationPrefix));
        $setup->addOperation($this->makeDeleteOperation($basePath, $operationPrefix));
    }

    protected function getConnection(mixed $connectionId): Connection
    {
        $connection = $this->connector->getConnection($connectionId);
        if ($connection instanceof Connection) {
            return $connection;
        } else {
            throw new ConfigurationException('Invalid selected connection');
        }
    }

    private function makeGetAllSchema(Table $table, string $prefix): Schema
    {
        $type = $this->tableBuilder->getEntity($table, self::SCHEMA_GET_ALL);

        $schema = new Schema();
        $schema->setName($prefix . self::SCHEMA_GET_ALL);
        $schema->setSource(SchemaBuilder::makeCollectionResponse(self::SCHEMA_GET_ALL, $type));
        return $schema;
    }

    private function makeGetSchema(Table $table, string $prefix): Schema
    {
        $type = $this->tableBuilder->getEntity($table, self::SCHEMA_GET);

        $schema = new Schema();
        $schema->setName($prefix . self::SCHEMA_GET);
        $schema->setSource(SchemaSource::fromObject($type));
        return $schema;
    }

    private function makeGetAllAction(string $connectionName, string $tableName, string $prefix): Action
    {
        $action = new Action();
        $action->setName($prefix . self::ACTION_GET_ALL);
        $action->setClass(SqlSelectAll::class);
        $action->setEngine(PhpClass::class);
        $action->setConfig(ActionConfig::fromIterable([
            'connection' => $connectionName,
            'table' => $tableName,
        ]));
        return $action;
    }

    private function makeGetAction(string $connectionName, string $tableName, string $prefix): Action
    {
        $action = new Action();
        $action->setName($prefix . self::ACTION_GET);
        $action->setClass(SqlSelectRow::class);
        $action->setEngine(PhpClass::class);
        $action->setConfig(ActionConfig::fromIterable([
            'connection' => $connectionName,
            'table' => $tableName,
        ]));
        return $action;
    }

    private function makeInsertAction(string $connectionName, string $tableName, string $prefix): Action
    {
        $action = new Action();
        $action->setName($prefix . self::ACTION_INSERT);
        $action->setClass(SqlInsert::class);
        $action->setEngine(PhpClass::class);
        $action->setConfig(ActionConfig::fromIterable([
            'connection' => $connectionName,
            'table' => $tableName,
        ]));
        return $action;
    }

    private function makeUpdateAction(string $connectionName, string $tableName, string $prefix): Action
    {
        $action = new Action();
        $action->setName($prefix . self::ACTION_UPDATE);
        $action->setClass(SqlUpdate::class);
        $action->setEngine(PhpClass::class);
        $action->setConfig(ActionConfig::fromIterable([
            'connection' => $connectionName,
            'table' => $tableName,
        ]));
        return $action;
    }

    private function makeDeleteAction(string $connectionName, string $tableName, string $prefix): Action
    {
        $action = new Action();
        $action->setName($prefix . self::ACTION_DELETE);
        $action->setClass(SqlDelete::class);
        $action->setEngine(PhpClass::class);
        $action->setConfig(ActionConfig::fromIterable([
            'connection' => $connectionName,
            'table' => $tableName,
        ]));
        return $action;
    }

    private function makeGetAllOperation(string $basePath, string $prefix): Operation
    {
        $operation = new Operation();
        $operation->setName($prefix . 'getAll');
        $operation->setDescription('Returns a collection of rows');
        $operation->setHttpMethod('GET');
        $operation->setHttpPath($basePath . '/');
        $operation->setHttpCode(200);
        $operation->setOutgoing(self::SCHEMA_GET_ALL);
        $operation->setAction(self::ACTION_GET_ALL);
        return $operation;
    }

    private function makeGetOperation(string $basePath, string $prefix): Operation
    {
        $operation = new Operation();
        $operation->setName($prefix . 'get');
        $operation->setDescription('Returns a single row');
        $operation->setHttpMethod('GET');
        $operation->setHttpPath($basePath . '/:id');
        $operation->setHttpCode(200);
        $operation->setOutgoing(self::SCHEMA_GET);
        $operation->setAction(self::ACTION_GET);
        return $operation;
    }

    private function makeInsertOperation(string $basePath, string $prefix): Operation
    {
        $operation = new Operation();
        $operation->setName($prefix . 'create');
        $operation->setDescription('Creates a new row');
        $operation->setHttpMethod('POST');
        $operation->setHttpPath($basePath . '/');
        $operation->setHttpCode(201);
        $operation->setOutgoing(SchemaName::MESSAGE);
        $operation->setAction(self::ACTION_INSERT);
        return $operation;
    }

    private function makeUpdateOperation(string $basePath, string $prefix): Operation
    {
        $operation = new Operation();
        $operation->setName($prefix . 'update');
        $operation->setDescription('Updates an existing row');
        $operation->setHttpMethod('PUT');
        $operation->setHttpPath($basePath . '/:id');
        $operation->setHttpCode(200);
        $operation->setOutgoing(SchemaName::MESSAGE);
        $operation->setAction(self::ACTION_UPDATE);
        return $operation;
    }

    private function makeDeleteOperation(string $basePath, string $prefix): Operation
    {
        $operation = new Operation();
        $operation->setName($prefix . 'delete');
        $operation->setDescription('Deletes an existing row');
        $operation->setHttpMethod('DELETE');
        $operation->setHttpPath($basePath . '/:id');
        $operation->setHttpCode(200);
        $operation->setOutgoing(SchemaName::MESSAGE);
        $operation->setAction(self::ACTION_DELETE);
        return $operation;
    }
}
