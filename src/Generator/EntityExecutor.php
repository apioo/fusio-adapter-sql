<?php
/*
 * Fusio - Self-Hosted API Management for Builders.
 * For the current version and information visit <https://www.fusio-project.org/>
 *
 * Copyright (c) Christoph Kappestein <christoph.kappestein@gmail.com>
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
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Schema;
use TypeAPI\Editor\Model\Document;
use TypeAPI\Editor\Model\Property;
use TypeAPI\Editor\Model\Type;

/**
 * EntityExecutor
 *
 * @author  Christoph Kappestein <christoph.kappestein@gmail.com>
 * @license http://www.apache.org/licenses/LICENSE-2.0
 * @link    https://www.fusio-project.org/
 */
class EntityExecutor
{
    public function execute(Connection $connection, Document $document): void
    {
        $schemaManager = $connection->createSchemaManager();
        $schema = $schemaManager->introspectSchema();
        $tableNames = $this->getTableNames($document, $schemaManager);

        $types = $document->getTypes();
        $relations = [];
        foreach ($types as $type) {
            $this->createTableFromType($schema, $type, $tableNames, $relations);
        }

        foreach ($relations as $relation) {
            [$tableName, $foreignTable, $localColumns, $foreignColumns] = $relation;

            $table = $schema->getTable($tableName);
            $table->addForeignKeyConstraint($schema->getTable($foreignTable), $localColumns, $foreignColumns);
        }

        $diff = $schemaManager->createComparator()->compareSchemas($schemaManager->introspectSchema(), $schema);
        $queries = $connection->getDatabasePlatform()->getAlterSchemaSQL($diff);
        foreach ($queries as $query) {
            $connection->executeQuery($query);
        }
    }

    public function getTableNames(Document $document, AbstractSchemaManager $schemaManager): array
    {
        $types = $document->getTypes();
        $tableNames = [];
        foreach ($types as $type) {
            $tableName = $this->getTableName($schemaManager, $type->getName() ?? '');
            $tableNames[$type->getName() ?? ''] = $tableName;
        }

        return $tableNames;
    }

    private function getTableName(AbstractSchemaManager $schemaManager, string $typeName): string
    {
        $tableName = 'app_' . strtolower($typeName);
        if (!$schemaManager->tablesExist($tableName)) {
            return $tableName;
        }

        $i = 1;
        $format = $tableName . '_%s';

        do {
            $tableName = sprintf($format, $i);
            $i++;
        } while ($schemaManager->tablesExist($tableName));

        return $tableName;
    }

    public function getMapping(Type $type, array $tableNames): array
    {
        $mapping = [];
        foreach ($type->getProperties() as $property) {
            if (self::isScalar($property->getType() ?? '')) {
                $mapping[$this->getColumnName($property)] = $property->getName();
            } elseif ($property->getType() === 'object') {
                $mapping[$this->getColumnName($property)] = implode(':', [$property->getName(), $property->getType()]);
            } elseif ($property->getType() === 'array') {
                if (self::isScalar($property->getReference() ?? '')) {
                    $mapping[$this->getColumnName($property)] = $property->getName();
                } else {
                    $config = $this->getRelationConfig($type, $property, $tableNames);
                    $mapping[$this->getColumnName($property)] = implode(':', $config);
                }
            } elseif ($property->getType() === 'map') {
                if (self::isScalar($property->getReference() ?? '')) {
                    $mapping[$this->getColumnName($property)] = $property->getName();
                } else {
                    $config = $this->getRelationConfig($type, $property, $tableNames);
                    $mapping[$this->getColumnName($property)] = implode(':', $config);
                }
            }
        }

        return $mapping;
    }

    public function getTypeMapping(Document $document, array $tableNames): array
    {
        $types = $document->getTypes();
        $typeMapping = [];
        foreach ($types as $type) {
            $tableName = $tableNames[$type->getName() ?? ''];

            $prefix = ucfirst(substr($tableName, 4));
            $entityName = $prefix . '_SQL_Get';

            $typeMapping[$type->getName() ?? ''] = $entityName;
        }

        return $typeMapping;
    }

    public function getRouteName(Type $type): string
    {
        return '/' . self::underscore($type->getName() ?? '');
    }

    private function getRelationConfig(Type $type, Property $property, array $tableNames): array
    {
        $tableName = $tableNames[$type->getName() ?? ''] ?? '';
        $foreignTableName = $tableName . '_' . self::underscore($property->getReference() ?? '');
        $typeColumn = self::underscore($type->getName() ?? '') . '_id';
        $foreignColumn = self::underscore($property->getReference() ?? '') . '_id';

        return [
            $property->getName(),
            $property->getType(),
            $foreignTableName,
            $typeColumn,
            $foreignColumn,
        ];
    }

    private function createTableFromType(Schema $schema, Type $type, array $tableNames, array &$relations): void
    {
        $tableName = $tableNames[$type->getName() ?? ''] ?? '';
        $table = $schema->createTable($tableName);
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->setPrimaryKey(['id']);

        foreach ($type->getProperties() as $property) {
            $columnType = $this->getColumnType($property);
            if ($columnType !== null) {
                $columnName = $this->getColumnName($property);
                $columnOptions = $this->getColumnOptions($property);

                $table->addColumn($columnName, $columnType, $columnOptions);

                if ($property->getType() === 'object' && isset($tableNames[$property->getReference()])) {
                    $relations[] = [$tableName, $tableNames[$property->getReference() ?? ''], [$columnName], ['id']];
                }
            } elseif (in_array($property->getType(), ['map', 'array'])) {
                $config = $this->getRelationConfig($type, $property, $tableNames);
                [$propertyName, $typeName, $relationTableName, $typeColumn, $foreignColumn] = $config;

                $relationTable = $schema->createTable($relationTableName);
                $relationTable->addColumn('id', 'integer', ['autoincrement' => true]);
                $relationTable->addColumn($typeColumn, 'integer');
                if ($typeName === 'map') {
                    $relationTable->addColumn('name', 'string');
                }
                $relationTable->addColumn($foreignColumn, 'integer');
                $relationTable->setPrimaryKey(['id']);

                if (isset($tableNames[$property->getReference()])) {
                    $relations[] = [$relationTableName, $tableName, [$typeColumn], ['id']];
                    $relations[] = [$relationTableName, $tableNames[$property->getReference() ?? ''], [$foreignColumn], ['id']];
                }
            }
        }
    }

    public function getColumnType(Property $property): ?string
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
                return 'string';
            }
        } elseif ($property->getType() === 'object') {
            // reference to a different entity
            return 'integer';
        } elseif (in_array($property->getType(), ['map', 'array'])) {
            if (self::isScalar($property->getReference() ?? '')) {
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

    private function getColumnOptions(Property $property): array
    {
        $options = ['notnull' => false];

        return $options;
    }

    public static function getColumnName(Property $property): string
    {
        if ($property->getType() === 'object') {
            // reference to a different entity
            $ref = $property->getReference();
            if (!empty($ref)) {
                return self::underscore($ref) . '_id';
            }
        }

        return self::underscore($property->getName() ?? '');
    }

    public static function isScalar(string $ref): bool
    {
        return in_array($ref, ['boolean', 'integer', 'number', 'string']);
    }

    public static function underscore(string $id): string
    {
        return strtolower((string) preg_replace(array('/([A-Z]+)([A-Z][a-z])/', '/([a-z\d])([A-Z])/'), array('\\1_\\2', '\\1_\\2'), strtr($id, '_', '.')));
    }
}