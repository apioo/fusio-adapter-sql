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

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types;

/**
 * TableBuilder
 *
 * @author  Christoph Kappestein <christoph.kappestein@gmail.com>
 * @license http://www.apache.org/licenses/LICENSE-2.0
 * @link    https://www.fusio-project.org/
 */
class TableBuilder
{
    public function getCollection(string $collectionName, string $schemaName, string $entityName): object
    {
        return (object) [
            '$import' => [
                'entity' => 'schema://' . $schemaName
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

    public function getEntity(Table $table, string $entityName): object
    {
        $properties = [];
        $columns = $table->getColumns();
        foreach ($columns as $name => $column) {
            $properties[$name] = $this->getSchemaByColumn($column);
        }

        return (object) [
            'definitions' => [
                $entityName => [
                    'type' => 'object',
                    'properties' => $properties,
                ]
            ],
            '$ref' => $entityName
        ];
    }

    private function getSchemaByColumn(Column $column): array
    {
        $type = $column->getType();

        $schema = [];
        $schema['type'] = $this->getSchemaType($type);

        if ($type instanceof Types\DateTimeType) {
            $schema['format'] = 'date-time';
        } elseif ($type instanceof Types\DateType) {
            $schema['format'] = 'date';
        } elseif ($type instanceof Types\TimeType) {
            $schema['format'] = 'time';
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
}
