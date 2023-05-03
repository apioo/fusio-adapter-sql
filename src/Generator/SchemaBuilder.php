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

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types;
use PSX\Schema\Document\Type;
use PSX\Schema\Format;

/**
 * SchemaBuilder
 *
 * @author  Christoph Kappestein <christoph.kappestein@gmail.com>
 * @license http://www.gnu.org/licenses/agpl-3.0
 * @link    https://www.fusio-project.org/
 */
class SchemaBuilder
{
    public function getParameters(): array
    {
        return $this->readSchema(__DIR__ . '/schema/sql-table/parameters.json');
    }

    public function getResponse(): array
    {
        return $this->readSchema(__DIR__ . '/schema/sql-table/response.json');
    }

    public function getEntityByTable(Table $table, string $entityName): array
    {
        $properties = [];
        $columns = $table->getColumns();
        foreach ($columns as $name => $column) {
            $properties[$name] = $this->getSchemaByColumn($column);
        }

        return [
            'definitions' => [
                $entityName => [
                    'type' => 'object',
                    'properties' => $properties,
                ]
            ],
            '$ref' => $entityName
        ];
    }

    public function getEntityByType(Type $property, string $entityName, array $typeSchema, array $typeMapping): array
    {
        $schema = $typeSchema['definitions'][$property->getName() ?? ''] ?? null;
        if (empty($schema)) {
            throw new \RuntimeException('Could not resolve schema');
        }

        $typeName = $property->getName();
        if ($typeName === null) {
            throw new \RuntimeException('Could not resolve type name');
        }

        $entityTypeMapping = $typeMapping;
        if (isset($entityTypeMapping[$typeName])) {
            // remove the mapping from the type itself
            unset($entityTypeMapping[$typeName]);
        }

        $import = [];
        foreach ($entityTypeMapping as $typeName => $realName) {
            $import[$typeName] = 'schema:///' . $realName;
        }

        if (isset($schema['properties']) && is_array($schema['properties'])) {
            $properties = [];
            $properties['id'] = [
                'type' => 'integer'
            ];
            foreach ($schema['properties'] as $key => $property) {
                if (isset($property['$ref']) && is_string($property['$ref'])) {
                    $properties[$key]['$ref'] = $this->resolveRef($property, $entityTypeMapping);
                } elseif (isset($property['additionalProperties']['$ref']) && is_string($property['additionalProperties']['$ref'])) {
                    $properties[$key]['additionalProperties']['$ref'] = $this->resolveRef($property['additionalProperties'], $entityTypeMapping);
                } elseif (isset($property['items']['$ref']) && is_string($property['items']['$ref'])) {
                    $properties[$key]['items']['$ref'] = $this->resolveRef($property['items'], $entityTypeMapping);
                } elseif (isset($property['oneOf']) && is_array($property['oneOf'])) {
                    $properties[$key]['oneOf'] = $this->resolveRefs($property['oneOf'], $entityTypeMapping);
                } elseif (isset($property['allOf']) && is_array($property['allOf'])) {
                    $properties[$key]['allOf'] = $this->resolveRefs($property['allOf'], $entityTypeMapping);
                } else {
                    $properties[$key] = $property;
                }
            }
            $schema['properties'] = $properties;
        }

        return [
            '$import' => $import,
            'definitions' => [
                $entityName => $schema
            ],
            '$ref' => $entityName
        ];
    }

    public function getCollection(string $collectionName, string $entityName): array
    {
        return [
            '$import' => [
                'entity' => 'schema:///' . $entityName
            ],
            'definitions' => [
                $collectionName => [
                    'type' => 'object',
                    'properties' => [
                        'totalResults' => [
                            'type' => 'integer'
                        ],
                        'itemsPerPage' => [
                            'type' => 'integer'
                        ],
                        'startIndex' => [
                            'type' => 'integer'
                        ],
                        'entry' => [
                            'type' => 'array',
                            'items' => [
                                '$ref' => 'entity:' . $entityName
                            ],
                        ],
                    ],
                ]
            ],
            '$ref' => $collectionName
        ];
    }

    private function getSchemaByColumn(Column $column): array
    {
        $type = $column->getType();

        $schema = [];
        $schema['type'] = $this->getSchemaType($type);

        if ($type instanceof Types\DateTimeType) {
            $schema['format'] = Format::DATETIME;
        } elseif ($type instanceof Types\DateType) {
            $schema['format'] = Format::DATE;
        } elseif ($type instanceof Types\TimeType) {
            $schema['format'] = Format::TIME;
        }

        $length = $column->getLength();
        if (!empty($length)) {
            if ($type instanceof Types\IntegerType) {
                $schema['maximum'] = $length;
            } elseif ($type instanceof Types\SmallIntType) {
                $schema['maximum'] = $length;
            } elseif ($type instanceof Types\BigIntType) {
                $schema['maximum'] = $length;
            } elseif ($type instanceof Types\StringType) {
                $schema['maxLength'] = $length;
            }
        }

        $comment = $column->getComment();
        if (!empty($comment)) {
            $schema['description'] = $comment;
        }

        return $schema;
    }

    private function getSchemaType(Types\Type $type): string
    {
        if ($type instanceof Types\IntegerType) {
            return 'integer';
        } elseif ($type instanceof Types\SmallIntType) {
            return 'integer';
        } elseif ($type instanceof Types\BigIntType) {
            return 'integer';
        } elseif ($type instanceof Types\FloatType) {
            return 'number';
        } elseif ($type instanceof Types\BooleanType) {
            return 'boolean';
        }

        return 'string';
    }

    private function resolveRefs(array $types, array $typeMapping): array
    {
        $return = [];
        foreach ($types as $type) {
            if (isset($type['$ref'])) {
                $type['$ref'] = $this->resolveRef($type, $typeMapping);
                $return[] = $type;
            } else {
                $return[] = $type;
            }
        }
        return $return;
    }

    private function resolveRef(array|\ArrayAccess $type, array $typeMapping): string
    {
        if (isset($typeMapping[$type['$ref']])) {
            return $type['$ref'] . ':' . $typeMapping[$type['$ref']];
        } else {
            return $type['$ref'];
        }
    }

    private function readSchema(string $file): array
    {
        return \json_decode(\file_get_contents($file), true);
    }
}
