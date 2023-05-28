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
use Doctrine\DBAL\Schema\Schema;
use PSX\Schema\Document\Document;
use PSX\Schema\Document\Property;
use PSX\Schema\Document\Type;

/**
 * EntityExecutor
 *
 * @author  Christoph Kappestein <christoph.kappestein@gmail.com>
 * @license http://www.gnu.org/licenses/agpl-3.0
 * @link    https://www.fusio-project.org/
 */
class EntityExecutor
{
    public function execute(Connection $connection, Document $document)
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

        $queries = $schema->toSql($connection->getDatabasePlatform());
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
        $i = 0;
        $format = strtolower('app_' . $typeName . '_%s');

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
                if (self::isScalar($property->getFirstRef() ?? '')) {
                    $mapping[$this->getColumnName($property)] = $property->getName();
                } else {
                    $config = $this->getRelationConfig($type, $property, $tableNames);
                    $mapping[$this->getColumnName($property)] = implode(':', $config);
                }
            } elseif ($property->getType() === 'map') {
                if (self::isScalar($property->getFirstRef() ?? '')) {
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
            $entityName = $prefix . '_SQL_Entity';

            $typeMapping[$type->getName() ?? ''] = $entityName;
        }

        return $typeMapping;
    }

    public function getRouteName(Type $type): string
    {
        return self::underscore($type->getName() ?? '');
    }

    private function getRelationConfig(Type $type, Property $property, array $tableNames): array
    {
        $tableName = $tableNames[$type->getName() ?? ''] ?? '';
        $foreignTableName = $tableName . '_' . self::underscore($property->getFirstRef() ?? '');
        $typeColumn = self::underscore($type->getName() ?? '') . '_id';
        $foreignColumn = self::underscore($property->getFirstRef() ?? '') . '_id';

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

                if ($property->getType() === 'object' && isset($tableNames[$property->getFirstRef()])) {
                    $relations[] = [$tableName, $tableNames[$property->getFirstRef() ?? ''], [$columnName], ['id']];
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

                if (isset($tableNames[$property->getFirstRef()])) {
                    $relations[] = [$relationTableName, $tableName, [$typeColumn], ['id']];
                    $relations[] = [$relationTableName, $tableNames[$property->getFirstRef() ?? ''], [$foreignColumn], ['id']];
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
                return $property->getMaxLength() > 500 ? 'text' : 'string';
            }
        } elseif ($property->getType() === 'object') {
            // reference to a different entity
            return 'integer';
        } elseif (in_array($property->getType(), ['map', 'array'])) {
            if (self::isScalar($property->getFirstRef() ?? '')) {
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

    public static function getColumnName(Property $property): string
    {
        if ($property->getType() === 'object') {
            // reference to a different entity
            $ref = $property->getFirstRef();
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
        return strtolower(preg_replace(array('/([A-Z]+)([A-Z][a-z])/', '/([a-z\d])([A-Z])/'), array('\\1_\\2', '\\1_\\2'), strtr($id, '_', '.')));
    }
}