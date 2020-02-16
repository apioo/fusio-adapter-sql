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

namespace Fusio\Adapter\Sql\Routes;

use Doctrine\DBAL\Connection;
use Fusio\Engine\Factory\ContainerAwareInterface;
use Fusio\Engine\Factory\Resolver\PhpClass;
use Fusio\Engine\Form\BuilderInterface;
use Fusio\Engine\Form\ElementFactoryInterface;
use Fusio\Engine\ParametersInterface;
use Fusio\Engine\Routes\ProviderInterface;
use Fusio\Engine\Routes\SetupInterface;
use Psr\Container\ContainerInterface;

/**
 * SqlTable
 *
 * @author  Christoph Kappestein <christoph.kappestein@gmail.com>
 * @license http://www.gnu.org/licenses/agpl-3.0
 * @link    http://fusio-project.org
 */
class SqlTable implements ProviderInterface, ContainerAwareInterface
{
    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * @var SchemaBuilder
     */
    private $schemaBuilder;

    public function __construct()
    {
        $this->schemaBuilder = new SchemaBuilder();
    }

    public function getName()
    {
        return 'SQL-Table';
    }

    public function setup(SetupInterface $setup, string $basePath, ParametersInterface $configuration)
    {
        $connection = $this->getConnection($configuration->get('connection'));
        $schemaManager = $connection->getSchemaManager();
        $tableName = $configuration->get('table');

        if (!$schemaManager->tablesExist([$tableName])) {
            throw new \RuntimeException('Provided table does not exist');
        }

        $table = $schemaManager->listTableDetails($tableName);
        $prefix = $this->getPrefix($basePath);

        $schemaParameters = $setup->addSchema('SQL-Table-Parameters', $this->schemaBuilder->getParameters());
        $schemaResponse = $setup->addSchema('SQL-Table-Response', $this->schemaBuilder->getResponse());
        $schemaCollection = $setup->addSchema($prefix . '-Collection', $this->schemaBuilder->getCollection($table));
        $schemaEntity = $setup->addSchema($prefix . '-Entity', $this->schemaBuilder->getEntity($table));

        $action = $setup->addAction($prefix . '-Action', \Fusio\Adapter\Sql\Action\SqlTable::class, PhpClass::class, [
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
                        'action' => $action,
                    ],
                    'POST' => [
                        'active' => true,
                        'public' => false,
                        'description' => 'Creates a new entity',
                        'request' => $schemaEntity,
                        'responses' => [
                            201 => $schemaResponse,
                        ],
                        'action' => $action,
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
                        'action' => $action,
                    ],
                    'PUT' => [
                        'active' => true,
                        'public' => false,
                        'description' => 'Updates an existing entity',
                        'request' => $schemaEntity,
                        'responses' => [
                            200 => $schemaResponse,
                        ],
                        'action' => $action,
                    ],
                    'DELETE' => [
                        'active' => true,
                        'public' => false,
                        'description' => 'Deletes an existing entity',
                        'responses' => [
                            200 => $schemaResponse,
                        ],
                        'action' => $action,
                    ]
                ],
            ]
        ]);
    }

    public function configure(BuilderInterface $builder, ElementFactoryInterface $elementFactory)
    {
        $builder->add($elementFactory->newConnection('connection', 'Connection', 'The SQL connection which should be used'));
        $builder->add($elementFactory->newInput('table', 'Table', 'text', 'Name of the database table'));
    }

    public function setContainer(ContainerInterface $container)
    {
        $this->container = $container;
    }

    private function getConnection($connectionId): Connection
    {
        /** @var \Fusio\Engine\ConnectorInterface $connector */
        $connector = $this->container->get('connector');
        $connection = $connector->getConnection($connectionId);

        if ($connection instanceof Connection) {
            return $connection;
        } else {
            throw new \RuntimeException('Invalid selected connection');
        }
    }

    private function getPrefix(string $path)
    {
        return implode('-', array_map('ucfirst', array_filter(explode('/', $path))));
    }
}
