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
        $connection = $this->getConnection($configuration->get('connection'));
        $schemaManager = $connection->getSchemaManager();
        $tableName = $configuration->get('table');

        if (!$schemaManager->tablesExist([$tableName])) {
            throw new \RuntimeException('Provided table does not exist');
        }

        $table = $schemaManager->listTableDetails($tableName);
        $prefix = $this->getPrefix($basePath);

        $collectionName = $prefix . '_Collection';
        $entityName = $prefix . '_Entity';

        $schemaParameters = $setup->addSchema('SQL_Table_Parameters', $this->schemaBuilder->getParameters());
        $schemaResponse = $setup->addSchema('SQL_Table_Response', $this->schemaBuilder->getResponse());
        $schemaCollection = $setup->addSchema($collectionName, $this->schemaBuilder->getCollection($collectionName, $entityName));
        $schemaEntity = $setup->addSchema($entityName, $this->schemaBuilder->getEntityByTable($table, $entityName));

        $fetchAllAction = $setup->addAction($prefix . '_Select_All', SqlSelectAll::class, PhpClass::class, [
            'connection' => $configuration->get('connection'),
            'table' => $configuration->get('table'),
        ]);

        $fetchRowAction = $setup->addAction($prefix . '_Select_Row', SqlSelectRow::class, PhpClass::class, [
            'connection' => $configuration->get('connection'),
            'table' => $configuration->get('table'),
        ]);

        $deleteAction = $setup->addAction($prefix . '_Delete', SqlDelete::class, PhpClass::class, [
            'connection' => $configuration->get('connection'),
            'table' => $configuration->get('table'),
        ]);

        $insertAction = $setup->addAction($prefix . '_Insert', SqlInsert::class, PhpClass::class, [
            'connection' => $configuration->get('connection'),
            'table' => $configuration->get('table'),
        ]);

        $updateAction = $setup->addAction($prefix . '_Update', SqlUpdate::class, PhpClass::class, [
            'connection' => $configuration->get('connection'),
            'table' => $configuration->get('table'),
        ]);

        $setup->addRoute(1, '/', 'Fusio\Impl\Controller\SchemaApiController', [], [
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

        $setup->addRoute(1, '/:id', 'Fusio\Impl\Controller\SchemaApiController', [], [
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

    public function configure(BuilderInterface $builder, ElementFactoryInterface $elementFactory): void
    {
        $builder->add($elementFactory->newConnection('connection', 'Connection', 'The SQL connection which should be used'));
        $builder->add($elementFactory->newInput('table', 'Table', 'text', 'Name of the database table'));
    }

    private function getConnection($connectionId): Connection
    {
        $connection = $this->connector->getConnection($connectionId);

        if ($connection instanceof Connection) {
            return $connection;
        } else {
            throw new \RuntimeException('Invalid selected connection');
        }
    }

    private function getPrefix(string $path): string
    {
        return implode('_', array_map('ucfirst', array_filter(explode('/', $path))));
    }
}
