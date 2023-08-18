<?php
/*
 * Fusio is an open source API management platform which helps to create innovative API solutions.
 * For the current version and information visit <https://www.fusio-project.org/>
 *
 * Copyright 2015-2023 Christoph Kappestein <christoph.kappestein@gmail.com>
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace Fusio\Adapter\Sql\Generator;

use Doctrine\DBAL\Connection;
use Fusio\Adapter\Sql\Action\SqlBuilder;
use Fusio\Adapter\Sql\Action\SqlDelete;
use Fusio\Adapter\Sql\Action\SqlInsert;
use Fusio\Adapter\Sql\Action\SqlUpdate;
use Fusio\Engine\ConnectorInterface;
use Fusio\Engine\Form\BuilderInterface;
use Fusio\Engine\Form\ElementFactoryInterface;
use Fusio\Engine\Generator\ExecutableInterface;
use Fusio\Engine\Generator\ProviderInterface;
use Fusio\Engine\Generator\SetupInterface;
use Fusio\Engine\ParametersInterface;
use Fusio\Engine\Schema\SchemaName;
use Fusio\Model\Backend\ActionConfig;
use Fusio\Model\Backend\ActionCreate;
use Fusio\Model\Backend\OperationCreate;
use Fusio\Model\Backend\SchemaCreate;
use Fusio\Model\Backend\SchemaSource;
use TypeAPI\Editor\Generator;
use TypeAPI\Editor\Model\Document;
use TypeAPI\Editor\Model\Type;

/**
 * SqlEntity
 *
 * @author  Christoph Kappestein <christoph.kappestein@gmail.com>
 * @license http://www.apache.org/licenses/LICENSE-2.0
 * @link    https://www.fusio-project.org/
 */
class SqlEntity implements ProviderInterface, ExecutableInterface
{
    private const SCHEMA_GET_ALL = 'SQL_GetAll';
    private const SCHEMA_GET = 'SQL_Get';
    private const ACTION_GET_ALL = 'SQL_GetAll';
    private const ACTION_GET = 'SQL_Get';
    private const ACTION_INSERT = 'SQL_Insert';
    private const ACTION_UPDATE = 'SQL_Update';
    private const ACTION_DELETE = 'SQL_Delete';

    private ConnectorInterface $connector;
    private EntityExecutor $entityExecutor;
    private EntityBuilder $entityBuilder;
    private JqlBuilder $jqlBuilder;

    public function __construct(ConnectorInterface $connector)
    {
        $this->connector = $connector;
        $this->entityExecutor = new EntityExecutor();
        $this->entityBuilder = new EntityBuilder();
        $this->jqlBuilder = new JqlBuilder();
    }

    public function getName(): string
    {
        return 'SQL-Entity';
    }

    public function setup(SetupInterface $setup, ParametersInterface $configuration): void
    {
        $connection = $this->getConnection($configuration->get('connection'));
        $document = Document::from($configuration->get('schema'));

        $schemaManager = $connection->createSchemaManager();

        $typeSchema = \json_decode((new Generator())->generate($document), true);

        $types = $document->getTypes();
        $tableNames = $this->entityExecutor->getTableNames($document, $schemaManager);
        $typeMapping = $this->entityExecutor->getTypeMapping($document, $tableNames);

        foreach ($types as $type) {
            $tableName = $tableNames[$type->getName() ?? ''] ?? '';
            $basePath = $this->entityExecutor->getRouteName($type);
            $mapping = $this->entityExecutor->getMapping($type, $tableNames);

            $prefix = substr($tableName, 4);
            $schemaPrefix = ucfirst($prefix) . '_';
            $actionPrefix = ucfirst($prefix) . '_';
            $operationPrefix = $prefix . '.';

            $setup->addSchema($this->makeGetAllSchema($schemaPrefix));
            $setup->addSchema($this->makeGetSchema($type, $typeSchema, $typeMapping, $schemaPrefix));

            $setup->addAction($this->makeGetAllAction($configuration, $type, $tableNames, $document, $actionPrefix));
            $setup->addAction($this->makeGetAction($configuration, $type, $tableNames, $document, $actionPrefix));
            $setup->addAction($this->makeInsertAction($configuration, $tableName, $mapping, $actionPrefix));
            $setup->addAction($this->makeUpdateAction($configuration, $tableName, $mapping, $actionPrefix));
            $setup->addAction($this->makeDeleteAction($configuration, $tableName, $mapping, $actionPrefix));

            $setup->addOperation($this->makeGetAllOperation($basePath, $type, $operationPrefix, $actionPrefix, $schemaPrefix));
            $setup->addOperation($this->makeGetOperation($basePath, $type, $operationPrefix, $actionPrefix, $schemaPrefix));
            $setup->addOperation($this->makeInsertOperation($basePath, $type, $operationPrefix, $actionPrefix, $schemaPrefix));
            $setup->addOperation($this->makeUpdateOperation($basePath, $type, $operationPrefix, $actionPrefix, $schemaPrefix));
            $setup->addOperation($this->makeDeleteOperation($basePath, $type, $operationPrefix, $actionPrefix));
        }
    }

    public function execute(ParametersInterface $configuration): void
    {
        $connection = $this->getConnection($configuration->get('connection'));
        $document = Document::from($configuration->get('schema'));

        $this->entityExecutor->execute($connection, $document);
    }

    public function configure(BuilderInterface $builder, ElementFactoryInterface $elementFactory): void
    {
        $builder->add($elementFactory->newConnection('connection', 'Connection', 'The SQL connection which should be used'));
        $builder->add($elementFactory->newTypeSchema('schema', 'Schema', 'TypeSchema specification'));
    }

    private function makeGetAllSchema(string $prefix): SchemaCreate
    {
        $name = $prefix . self::SCHEMA_GET_ALL;
        $type = $this->entityBuilder->getCollection($name, $prefix . self::SCHEMA_GET);

        $schema = new SchemaCreate();
        $schema->setName($name);
        $schema->setSource(SchemaSource::fromObject($type));
        return $schema;
    }

    private function makeGetSchema(Type $type, array $typeSchema, array $typeMapping, string $prefix): SchemaCreate
    {
        $name = $prefix . self::SCHEMA_GET;
        $type = $this->entityBuilder->getEntity($type, $name, $typeSchema, $typeMapping);

        $schema = new SchemaCreate();
        $schema->setName($name);
        $schema->setSource(SchemaSource::fromObject($type));
        return $schema;
    }

    private function makeGetAllAction(ParametersInterface $configuration, Type $type, array $tableNames, Document $document, string $prefix): ActionCreate
    {
        $action = new ActionCreate();
        $action->setName($prefix . self::ACTION_GET_ALL);
        $action->setClass(SqlBuilder::class);
        $action->setConfig(ActionConfig::fromIterable([
            'connection' => $configuration->get('connection'),
            'jql' => $this->jqlBuilder->getCollection($type, $tableNames, $document),
        ]));
        return $action;
    }

    private function makeGetAction(ParametersInterface $configuration, Type $type, array $tableNames, Document $document, string $prefix): ActionCreate
    {
        $action = new ActionCreate();
        $action->setName($prefix . self::ACTION_GET);
        $action->setClass(SqlBuilder::class);
        $action->setConfig(ActionConfig::fromIterable([
            'connection' => $configuration->get('connection'),
            'jql' => $this->jqlBuilder->getEntity($type, $tableNames, $document),
        ]));
        return $action;
    }

    private function makeInsertAction(ParametersInterface $configuration, string $tableName, array $mapping, string $prefix): ActionCreate
    {
        $action = new ActionCreate();
        $action->setName($prefix . self::ACTION_INSERT);
        $action->setClass(SqlInsert::class);
        $action->setConfig(ActionConfig::fromIterable([
            'connection' => $configuration->get('connection'),
            'table' => $tableName,
            'mapping' => $mapping,
        ]));
        return $action;
    }

    private function makeUpdateAction(ParametersInterface $configuration, string $tableName, array $mapping, string $prefix): ActionCreate
    {
        $action = new ActionCreate();
        $action->setName($prefix . self::ACTION_UPDATE);
        $action->setClass(SqlUpdate::class);
        $action->setConfig(ActionConfig::fromIterable([
            'connection' => $configuration->get('connection'),
            'table' => $tableName,
            'mapping' => $mapping,
        ]));
        return $action;
    }

    private function makeDeleteAction(ParametersInterface $configuration, string $tableName, array $mapping, string $prefix): ActionCreate
    {
        $action = new ActionCreate();
        $action->setName($prefix . self::ACTION_DELETE);
        $action->setClass(SqlDelete::class);
        $action->setConfig(ActionConfig::fromIterable([
            'connection' => $configuration->get('connection'),
            'table' => $tableName,
            'mapping' => $mapping,
        ]));
        return $action;
    }

    private function makeGetAllOperation(string $basePath, Type $type, string $prefix, string $actionPrefix, string $schemaPrefix): OperationCreate
    {
        $operation = new OperationCreate();
        $operation->setName($prefix . 'getAll');
        $operation->setDescription('Returns a collection of ' . $type->getName());
        $operation->setHttpMethod('GET');
        $operation->setHttpPath($basePath . '/');
        $operation->setHttpCode(200);
        $operation->setOutgoing($schemaPrefix . self::SCHEMA_GET_ALL);
        $operation->setAction($actionPrefix . self::ACTION_GET_ALL);
        return $operation;
    }

    private function makeGetOperation(string $basePath, Type $type, string $prefix, string $actionPrefix, string $schemaPrefix): OperationCreate
    {
        $operation = new OperationCreate();
        $operation->setName($prefix . 'get');
        $operation->setDescription('Returns a single ' . $type->getName());
        $operation->setHttpMethod('GET');
        $operation->setHttpPath($basePath . '/:id');
        $operation->setHttpCode(200);
        $operation->setOutgoing($schemaPrefix . self::SCHEMA_GET);
        $operation->setAction($actionPrefix . self::ACTION_GET);
        return $operation;
    }

    private function makeInsertOperation(string $basePath, Type $type, string $prefix, string $actionPrefix, string $schemaPrefix): OperationCreate
    {
        $operation = new OperationCreate();
        $operation->setName($prefix . 'create');
        $operation->setDescription('Creates a new ' . $type->getName());
        $operation->setHttpMethod('POST');
        $operation->setHttpPath($basePath . '/');
        $operation->setHttpCode(200);
        $operation->setIncoming($schemaPrefix . self::SCHEMA_GET);
        $operation->setOutgoing(SchemaName::MESSAGE);
        $operation->setAction($actionPrefix . self::ACTION_INSERT);
        return $operation;
    }

    private function makeUpdateOperation(string $basePath, Type $type, string $prefix, string $actionPrefix, string $schemaPrefix): OperationCreate
    {
        $operation = new OperationCreate();
        $operation->setName($prefix . 'update');
        $operation->setDescription('Updates an existing ' . $type->getName());
        $operation->setHttpMethod('PUT');
        $operation->setHttpPath($basePath . '/:id');
        $operation->setHttpCode(200);
        $operation->setIncoming($schemaPrefix . self::SCHEMA_GET);
        $operation->setOutgoing(SchemaName::MESSAGE);
        $operation->setAction($actionPrefix . self::ACTION_UPDATE);
        return $operation;
    }

    private function makeDeleteOperation(string $basePath, Type $type, string $prefix, string $actionPrefix): OperationCreate
    {
        $operation = new OperationCreate();
        $operation->setName($prefix . 'delete');
        $operation->setDescription('Deletes an existing ' . $type->getName());
        $operation->setHttpMethod('DELETE');
        $operation->setHttpPath($basePath . '/:id');
        $operation->setHttpCode(200);
        $operation->setOutgoing(SchemaName::MESSAGE);
        $operation->setAction($actionPrefix . self::ACTION_DELETE);
        return $operation;
    }

    private function getConnection(mixed $connectionId): Connection
    {
        $connection = $this->connector->getConnection($connectionId);
        if ($connection instanceof Connection) {
            return $connection;
        } else {
            throw new \RuntimeException('Invalid selected connection');
        }
    }
}
