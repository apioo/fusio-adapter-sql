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
use Doctrine\DBAL\Schema\Schema;
use Fusio\Adapter\Sql\Action\SqlBuilder;
use Fusio\Adapter\Sql\Action\SqlDelete;
use Fusio\Adapter\Sql\Action\SqlInsert;
use Fusio\Adapter\Sql\Action\SqlUpdate;
use Fusio\Engine\ConnectorInterface;
use Fusio\Engine\Factory\Resolver\PhpClass;
use Fusio\Engine\Form\BuilderInterface;
use Fusio\Engine\Form\ElementFactoryInterface;
use Fusio\Engine\Generator\ExecutableInterface;
use Fusio\Engine\Generator\ProviderInterface;
use Fusio\Engine\Generator\SetupInterface;
use Fusio\Engine\ParametersInterface;
use PSX\Schema\Document\Document;
use PSX\Schema\Document\Generator;
use PSX\Schema\Document\Property;
use PSX\Schema\Document\Type;

/**
 * SqlEntity
 *
 * @author  Christoph Kappestein <christoph.kappestein@gmail.com>
 * @license http://www.gnu.org/licenses/agpl-3.0
 * @link    https://www.fusio-project.org/
 */
class SqlEntity implements ProviderInterface, ExecutableInterface
{
    private ConnectorInterface $connector;
    private SchemaBuilder $schemaBuilder;
    private JqlBuilder $jqlBuilder;

    public function __construct(ConnectorInterface $connector)
    {
        $this->connector = $connector;
        $this->schemaBuilder = new SchemaBuilder();
        $this->jqlBuilder = new JqlBuilder();
    }

    public function getName(): string
    {
        return 'SQL-Entity';
    }

    public function setup(SetupInterface $setup, string $basePath, ParametersInterface $configuration): void
    {
        $connection = $this->getConnection($configuration->get('connection'));
        $document = Document::from($configuration->get('schema'));

        $schemaManager = $connection->getSchemaManager();

        $schemaParameters = $setup->addSchema('SQL_Table_Parameters', $this->schemaBuilder->getParameters());
        $schemaResponse = $setup->addSchema('SQL_Table_Response', $this->schemaBuilder->getResponse());

        $typeSchema = \json_decode((new Generator())->generate($document), true);

        $types = $document->getTypes();
        $tableNames = $this->getTableNames($document, $schemaManager);
        $typeMapping = $this->getTypeMapping($document, $tableNames);

        foreach ($types as $type) {
            $tableName = $tableNames[$type->getName()];
            $routeName = $this->getRouteName($type);
            $mapping = $this->getMapping($type, $tableNames);

            $prefix = ucfirst(substr($tableName, 4));
            $collectionName = $prefix . '_Collection';
            $entityName = $prefix . '_Entity';

            $schemaCollection = $setup->addSchema($collectionName, $this->schemaBuilder->getCollection($collectionName, $entityName));
            $schemaEntity = $setup->addSchema($entityName, $this->schemaBuilder->getEntityByType($type, $entityName, $typeSchema, $typeMapping));

            $fetchAllAction = $setup->addAction($prefix . '_Select_All', SqlBuilder::class, PhpClass::class, [
                'connection' => $configuration->get('connection'),
                'jql' => $this->jqlBuilder->getCollection($type, $tableNames, $document),
            ]);

            $fetchRowAction = $setup->addAction($prefix . '_Select_Row', SqlBuilder::class, PhpClass::class, [
                'connection' => $configuration->get('connection'),
                'jql' => $this->jqlBuilder->getEntity($type, $tableNames, $document),
            ]);

            $deleteAction = $setup->addAction($prefix . '_Delete', SqlDelete::class, PhpClass::class, [
                'connection' => $configuration->get('connection'),
                'table' => $tableName,
                'mapping' => $mapping,
            ]);

            $insertAction = $setup->addAction($prefix . '_Insert', SqlInsert::class, PhpClass::class, [
                'connection' => $configuration->get('connection'),
                'table' => $tableName,
                'mapping' => $mapping,
            ]);

            $updateAction = $setup->addAction($prefix . '_Update', SqlUpdate::class, PhpClass::class, [
                'connection' => $configuration->get('connection'),
                'table' => $tableName,
                'mapping' => $mapping,
            ]);

            $setup->addRoute(1, '/' . $routeName, 'Fusio\Impl\Controller\SchemaApiController', [], [
                [
                    'version' => 1,
                    'methods' => [
                        'GET' => [
                            'active' => true,
                            'public' => true,
                            'description' => 'Returns a collection of ' . $type->getName(),
                            'parameters' => $schemaParameters,
                            'responses' => [
                                200 => $schemaCollection,
                            ],
                            'action' => $fetchAllAction,
                        ],
                        'POST' => [
                            'active' => true,
                            'public' => false,
                            'description' => 'Creates a new ' . $type->getName(),
                            'request' => $schemaEntity,
                            'responses' => [
                                201 => $schemaResponse,
                            ],
                            'action' => $insertAction,
                        ]
                    ],
                ]
            ]);

            $setup->addRoute(1, '/' . $routeName . '/:id', 'Fusio\Impl\Controller\SchemaApiController', [], [
                [
                    'version' => 1,
                    'methods' => [
                        'GET' => [
                            'active' => true,
                            'public' => true,
                            'description' => 'Returns a single ' . $type->getName(),
                            'responses' => [
                                200 => $schemaEntity,
                            ],
                            'action' => $fetchRowAction,
                        ],
                        'PUT' => [
                            'active' => true,
                            'public' => false,
                            'description' => 'Updates an existing ' . $type->getName(),
                            'request' => $schemaEntity,
                            'responses' => [
                                200 => $schemaResponse,
                            ],
                            'action' => $updateAction,
                        ],
                        'DELETE' => [
                            'active' => true,
                            'public' => false,
                            'description' => 'Deletes an existing ' . $type->getName(),
                            'responses' => [
                                200 => $schemaResponse,
                            ],
                            'action' => $deleteAction,
                        ]
                    ],
                ]
            ]);
        }
    }

    public function execute(ParametersInterface $configuration): void
    {
        $connection = $this->getConnection($configuration->get('connection'));
        $document = Document::from($configuration->get('schema'));

        $schemaManager = $connection->getSchemaManager();
        $schema = $schemaManager->createSchema();
        $tableNames = $this->getTableNames($document, $schemaManager);

        $types = $document->getTypes();
        foreach ($types as $type) {
            $this->createTableFromType($schema, $type, $tableNames);
        }

        $from = $schemaManager->createSchema();
        $queries = $from->getMigrateToSql($schema, $connection->getDatabasePlatform());

        foreach ($queries as $query) {
            $connection->executeQuery($query);
        }
    }

    public function configure(BuilderInterface $builder, ElementFactoryInterface $elementFactory): void
    {
        $builder->add($elementFactory->newConnection('connection', 'Connection', 'The SQL connection which should be used'));
        $builder->add($elementFactory->newTypeSchema('schema', 'Schema', 'TypeSchema specification'));
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

    private function getTableName(AbstractSchemaManager $schemaManager, string $typeName): string
    {
        $i = 0;
        $format = strtolower('app_' . $typeName . '_%s');

        do {
            $tableName = sprintf($format, $i);
            $i++;
        } while ($schemaManager->tablesExist($tableName));

        return $tableName;
    }

    private function getTableNames(Document $document, AbstractSchemaManager $schemaManager): array
    {
        $types = $document->getTypes();
        $tableNames = [];
        foreach ($types as $type) {
            $tableName = $this->getTableName($schemaManager, $type->getName());
            $tableNames[$type->getName()] = $tableName;
        }

        return $tableNames;
    }

    private function getTypeMapping(Document $document, array $tableNames): array
    {
        $types = $document->getTypes();
        $typeMapping = [];
        foreach ($types as $type) {
            $tableName = $tableNames[$type->getName()];

            $prefix = ucfirst(substr($tableName, 4));
            $entityName = $prefix . '_Entity';

            $typeMapping[$type->getName()] = $entityName;
        }

        return $typeMapping;
    }

    private function createTableFromType(Schema $schema, Type $type, array $tableNames): void
    {
        $tableName = $tableNames[$type->getName()];
        $table = $schema->createTable($tableName);
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->setPrimaryKey(['id']);

        foreach ($type->getProperties() as $property) {
            $columnType = $this->getColumnType($property);
            if ($columnType !== null) {
                $table->addColumn(
                    $this->getColumnName($property),
                    $columnType,
                    $this->getColumnOptions($property)
                );
            } elseif (in_array($property->getType(), ['map', 'array'])) {
                $config = $this->getRelationConfig($type, $property, $tableNames);
                [$propertyName, $typeName, $foreignTableName, $typeColumn, $foreignColumn] = $config;

                $foreignTable = $schema->createTable($foreignTableName);
                $foreignTable->addColumn('id', 'integer', ['autoincrement' => true]);
                $foreignTable->addColumn($typeColumn, 'integer');
                if ($typeName === 'map') {
                    $foreignTable->addColumn('name', 'string');
                }
                $foreignTable->addColumn($foreignColumn, 'integer');
                $foreignTable->setPrimaryKey(['id']);
            }
        }
    }

    private function getColumnOptions(Property $property): array
    {
        $options = ['notnull' => false];

        if ($property->getType() === 'integer' || $property->getType() === 'number') {
            $maximum = (int) $property->getMaximum();
            if ($maximum > 0) {
                $options['length'] = $maximum;
            }
        } elseif ($property->getType() === 'string') {
            $maxLength = (int) $property->getMaxLength();
            if ($maxLength > 0) {
                $options['length'] = $maxLength;
            }
        }

        return $options;
    }

    private function getMapping(Type $type, array $tableNames): array
    {
        $mapping = [];
        foreach ($type->getProperties() as $property) {
            if (self::isScalar($property->getType())) {
                $mapping[$this->getColumnName($property)] = $property->getName();
            } elseif ($property->getType() === 'array') {
                if (self::isScalar($property->getFirstRef())) {
                    $mapping[$this->getColumnName($property)] = $property->getName();
                } else {
                    $config = $this->getRelationConfig($type, $property, $tableNames);
                    $mapping[$this->getColumnName($property)] = implode(':', $config);
                }
            } elseif ($property->getType() === 'map') {
                if (self::isScalar($property->getFirstRef())) {
                    $mapping[$this->getColumnName($property)] = $property->getName();
                } else {
                    $config = $this->getRelationConfig($type, $property, $tableNames);
                    $mapping[$this->getColumnName($property)] = implode(':', $config);
                }
            }
        }

        return $mapping;
    }

    private function getRouteName(Type $type): string
    {
        return self::underscore($type->getName());
    }

    private function getRelationConfig(Type $type, Property $property, array $tableNames): array
    {
        $tableName = $tableNames[$type->getName()];
        $foreignTableName = $tableName . '_' . self::underscore($property->getFirstRef());
        $typeColumn = self::underscore($type->getName()) . '_id';
        $foreignColumn = self::underscore($property->getFirstRef()) . '_id';

        return [
            $property->getName(),
            $property->getType(),
            $foreignTableName,
            $typeColumn,
            $foreignColumn,
        ];
    }

    public static function getColumnName(Property $property): string
    {
        if ($property->getType() === 'object') {
            // reference to a different entity
            $ref = $property->getFirstRef();
            if (!empty($ref)) {
                return self::underscore($ref) . '_id';
            }
        }

        return self::underscore($property->getName());
    }

    public static function getColumnType(Property $property): ?string
    {
        if ($property->getType() === 'boolean') {
            return 'boolean';
        } elseif ($property->getType() === 'integer') {
            return 'integer';
        } elseif ($property->getType() === 'number') {
            return 'float';
        } elseif ($property->getType() === 'string') {
            if ($property->getFormat() === 'date') {
                return 'date';
            } elseif ($property->getFormat() === 'date-time') {
                return 'datetime';
            } elseif ($property->getFormat() === 'time') {
                return 'time';
            } else {
                return $property->getMaxLength() > 500 ? 'text' : 'string';
            }
        } elseif ($property->getType() === 'object') {
            // reference to a different entity
            return 'integer';
        } elseif (in_array($property->getType(), ['map', 'array'])) {
            if (self::isScalar($property->getFirstRef())) {
                // if we have a scalar array we use a json property
                return 'json';
            } else {
                // if we have a reference to an object
                return null;
            }
        } elseif ($property->getType() === 'union') {
            return null;
        } elseif ($property->getType() === 'intersection') {
            return null;
        }

        return null;
    }

    public static function isScalar(string $ref): bool
    {
        return in_array($ref, ['boolean', 'integer', 'number', 'string']);
    }

    public static function underscore(string $id): string
    {
        return strtolower(preg_replace(array('/([A-Z]+)([A-Z][a-z])/', '/([a-z\d])([A-Z])/'), array('\\1_\\2', '\\1_\\2'), strtr($id, '_', '.')));
    }
}