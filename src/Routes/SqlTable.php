<?php
/*
 * Fusio
 * A web-application to create dynamically RESTful APIs
 *
 * Copyright (C) 2015-2018 Christoph Kappestein <christoph.kappestein@gmail.com>
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

use Fusio\Engine\Factory\Resolver\PhpClass;
use Fusio\Engine\Form\BuilderInterface;
use Fusio\Engine\Form\ElementFactoryInterface;
use Fusio\Engine\ParametersInterface;
use Fusio\Engine\Routes\SetupInterface;

/**
 * SqlTable
 *
 * @author  Christoph Kappestein <christoph.kappestein@gmail.com>
 * @license http://www.gnu.org/licenses/agpl-3.0
 * @link    http://fusio-project.org
 */
class SqlTable
{
    public function getName()
    {
        return 'SQL-Table';
    }

    public function setup(SetupInterface $setup, ParametersInterface $configuration)
    {
        $schemaParameters = $setup->addSchema('SQL-Table-Parameters', $this->readSchema(__DIR__ . '/schema/sql-table/parameters.json'));
        $schemaCollection = $setup->addSchema('SQL-Table-Collection', $this->readSchema(__DIR__ . '/schema/sql-table/collection.json'));
        $schemaEntity = $setup->addSchema('SQL-Table-Entity', $this->readSchema(__DIR__ . '/schema/sql-table/entity.json'));
        $schemaResponse = $setup->addSchema('SQL-Table-Response', $this->readSchema(__DIR__ . '/schema/sql-table/response.json'));

        $tableName = $configuration->get('table');

        $action = $setup->addAction(ucfirst($tableName) . '-Sql-Table', SqlTable::class, PhpClass::class, [
            'table' => $tableName,
        ]);

        $setup->addRoute(1, '/', 'Fusio\Impl\Controller\SchemaApiController', [], [
            [
                'version' => 1,
                'status' => 1,
                'methods' => [
                    'GET' => [
                        'status' => 1,
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
                        'status' => 1,
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
                'status' => 1,
                'methods' => [
                    'GET' => [
                        'status' => 1,
                        'active' => true,
                        'public' => true,
                        'description' => 'Returns a single entity',
                        'responses' => [
                            200 => $schemaEntity,
                        ],
                        'action' => $action,
                    ],
                    'PUT' => [
                        'status' => 1,
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
                        'status' => 1,
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
        $builder->add($elementFactory->newInput('table', 'Table'));
    }

    private function readSchema(string $file)
    {
        return \json_decode(\file_get_contents($file));
    }
}
