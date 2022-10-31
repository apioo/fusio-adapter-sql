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

namespace Fusio\Adapter\Sql\Generator;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Fusio\Adapter\Sql\Action\SqlDelete;
use Fusio\Adapter\Sql\Action\SqlInsert;
use Fusio\Adapter\Sql\Action\SqlSelectAll;
use Fusio\Adapter\Sql\Action\SqlSelectRow;
use Fusio\Adapter\Sql\Action\SqlUpdate;
use Fusio\Engine\ConnectorInterface;
use Fusio\Engine\Factory\Resolver\PhpClass;
use Fusio\Engine\Form\BuilderInterface;
use Fusio\Engine\Form\ElementFactoryInterface;
use Fusio\Engine\Generator\ProviderInterface;
use Fusio\Engine\Generator\SetupInterface;
use Fusio\Engine\ParametersInterface;

/**
 * SqlTable
 *
 * @author  Christoph Kappestein <christoph.kappestein@gmail.com>
 * @license http://www.gnu.org/licenses/agpl-3.0
 * @link    https://www.fusio-project.org/
 */
class SqlTable implements ProviderInterface
{
    private ConnectorInterface $connector;
    private SchemaBuilder $schemaBuilder;

    public function __construct(ConnectorInterface $connector)
    {
        $this->connector = $connector;
        $this->schemaBuilder = new SchemaBuilder();
    }

    public function getName(): string
    {
        return 'SQL-Table';
    }

    public function setup(SetupInterface $setup, string $basePath, ParametersInterface $configuration): void
    {
        $connectionName = $configuration->get('connection');
        $tableName = $configuration->get('table');
        $schemaManager = $this->getConnection($connectionName)->getSchemaManager();

        $this->generateForTable($schemaManager, $connectionName, $tableName, $setup);
    }

    public function configure(BuilderInterface $builder, ElementFactoryInterface $elementFactory): void
    {
        $builder->add($elementFactory->newConnection('connection', 'Connection', 'The SQL connection which should be used'));
        $builder->add($elementFactory->newInput('table', 'Table', 'text', 'Name of the database table'));
    }

    protected function generateForTable(AbstractSchemaManager $schemaManager, string $connectionName, string $tableName, SetupInterface $setup, ?string $name = null)
    {
        if (!$schemaManager->tablesExist([$tableName])) {
            throw new \RuntimeException('Provided table does not exist');
        }

        $table = $schemaManager->listTableDetails($tableName);

        $path = '';
        $prefix = '';
        if (!empty($name)) {
            $path = '/' . $name;
            $prefix = ucfirst($name) . '_';
        }

        $collectionName = $prefix . 'Collection';
        $entityName = $prefix . 'Entity';

        $schemaParameters = $setup->addSchema($prefix . 'Parameters', $this->schemaBuilder->getParameters());
        $schemaResponse = $setup->addSchema($prefix . 'Response', $this->schemaBuilder->getResponse());
        $schemaCollection = $setup->addSchema($collectionName, $this->schemaBuilder->getCollection($collectionName, $entityName));
        $schemaEntity = $setup->addSchema($entityName, $this->schemaBuilder->getEntityByTable($table, $entityName));

        $fetchAllAction = $setup->addAction($prefix . 'Select_All', SqlSelectAll::class, PhpClass::class, [
            'connection' => $connectionName,
            'table' => $tableName,
        ]);

        $fetchRowAction = $setup->addAction($prefix . 'Select_Row', SqlSelectRow::class, PhpClass::class, [
            'connection' => $connectionName,
            'table' => $tableName,
        ]);

        $deleteAction = $setup->addAction($prefix . 'Delete', SqlDelete::class, PhpClass::class, [
            'connection' => $connectionName,
            'table' => $tableName,
        ]);

        $insertAction = $setup->addAction($prefix . 'Insert', SqlInsert::class, PhpClass::class, [
            'connection' => $connectionName,
            'table' => $tableName,
        ]);

        $updateAction = $setup->addAction($prefix . 'Update', SqlUpdate::class, PhpClass::class, [
            'connection' => $connectionName,
            'table' => $tableName,
        ]);

        $setup->addRoute(1, $path . '/', 'Fusio\Impl\Controller\SchemaApiController', [], [
            [
                'version' => 1,
                'methods' => [
                    'GET' => [
                        'active' => true,
                        'public' => true,
                        'description' => 'Returns a collection of entities',
                        'parameters' => $schemaParameters,
                        'responses' => [
                            200 => $schemaCollection,
                        ],
                        'action' => $fetchAllAction,
                    ],
                    'POST' => [
                        'active' => true,
                        'public' => false,
                        'description' => 'Creates a new entity',
                        'request' => $schemaEntity,
                        'responses' => [
                            201 => $schemaResponse,
                        ],
                        'action' => $insertAction,
                    ]
                ],
            ]
        ]);

        $setup->addRoute(1, $path . '/:id', 'Fusio\Impl\Controller\SchemaApiController', [], [
            [
                'version' => 1,
                'methods' => [
                    'GET' => [
                        'active' => true,
                        'public' => true,
                        'description' => 'Returns a single entity',
                        'responses' => [
                            200 => $schemaEntity,
                        ],
                        'action' => $fetchRowAction,
                    ],
                    'PUT' => [
                        'active' => true,
                        'public' => false,
                        'description' => 'Updates an existing entity',
                        'request' => $schemaEntity,
                        'responses' => [
                            200 => $schemaResponse,
                        ],
                        'action' => $updateAction,
                    ],
                    'DELETE' => [
                        'active' => true,
                        'public' => false,
                        'description' => 'Deletes an existing entity',
                        'responses' => [
                            200 => $schemaResponse,
                        ],
                        'action' => $deleteAction,
                    ]
                ],
            ]
        ]);
    }

    protected function getConnection($connectionId): Connection
    {
        $connection = $this->connector->getConnection($connectionId);

        if ($connection instanceof Connection) {
            return $connection;
        } else {
            throw new \RuntimeException('Invalid selected connection');
        }
    }
}
