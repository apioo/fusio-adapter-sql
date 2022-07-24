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

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types;
use PSX\Schema\Document\Type;
use PSX\Schema\Type\TypeAbstract;

/**
 * SchemaBuilder
 *
 * @author  Christoph Kappestein <christoph.kappestein@gmail.com>
 * @license http://www.gnu.org/licenses/agpl-3.0
 * @link    https://www.fusio-project.org/
 */
class SchemaBuilder
{
    public function getParameters()
    {
        return $this->readSchema(__DIR__ . '/schema/sql-table/parameters.json');
    }

    public function getResponse()
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

    public function getEntityByType(Type $type, string $entityName, array $typeSchema, array $typeMapping): array
    {
        $schema = $typeSchema['definitions'][$type->getName()] ?? null;
        if (empty($schema)) {
            throw new \RuntimeException('Could not resolve schema');
        }

        $entityTypeMapping = $typeMapping;
        if (isset($entityTypeMapping[$type->getName()])) {
            // remove the mapping from the type itself
            unset($entityTypeMapping[$type->getName()]);
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
            foreach ($schema['properties'] as $key => $type) {
                if (isset($type['$ref']) && is_string($type['$ref'])) {
                    $properties[$key]['$ref'] = $this->resolveRef($type, $entityTypeMapping);
                } elseif (isset($type['additionalProperties']['$ref']) && is_string($type['additionalProperties']['$ref'])) {
                    $properties[$key]['additionalProperties']['$ref'] = $this->resolveRef($type['additionalProperties'], $entityTypeMapping);
                } elseif (isset($type['items']['$ref']) && is_string($type['items']['$ref'])) {
                    $properties[$key]['items']['$ref'] = $this->resolveRef($type['items'], $entityTypeMapping);
                } elseif (isset($type['oneOf']) && is_array($type['oneOf'])) {
                    $properties[$key]['oneOf'] = $this->resolveRefs($type['oneOf'], $entityTypeMapping);
                } elseif (isset($type['allOf']) && is_array($type['allOf'])) {
                    $properties[$key]['allOf'] = $this->resolveRefs($type['allOf'], $entityTypeMapping);
                } else {
                    $properties[$key] = $type;
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
            $schema['format'] = TypeAbstract::FORMAT_DATETIME;
        } elseif ($type instanceof Types\DateType) {
            $schema['format'] = TypeAbstract::FORMAT_DATE;
        } elseif ($type instanceof Types\TimeType) {
            $schema['format'] = TypeAbstract::FORMAT_TIME;
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

    private function resolveRef(array $type, array $typeMapping): string
    {
        if (isset($typeMapping[$type['$ref']])) {
            return $type['$ref'] . ':' . $typeMapping[$type['$ref']];
        } else {
            return $type['$ref'];
        }
    }

    private function readSchema(string $file)
    {
        return \json_decode(\file_get_contents($file), true);
    }
}
