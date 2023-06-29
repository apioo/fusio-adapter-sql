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
use PSX\Schema\Format;

/**
 * TableBuilder
 *
 * @author  Christoph Kappestein <christoph.kappestein@gmail.com>
 * @license http://www.gnu.org/licenses/agpl-3.0
 * @link    https://www.fusio-project.org/
 */
class TableBuilder
{
    public function getCollection(string $collectionName, string $entityName): object
    {
        return (object) [
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
