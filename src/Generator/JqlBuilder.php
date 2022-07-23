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
use PSX\Schema\Document\Document;
use PSX\Schema\Document\Type;
use PSX\Schema\Type\TypeAbstract;

/**
 * JqlBuilder
 *
 * @author  Christoph Kappestein <christoph.kappestein@gmail.com>
 * @license http://www.gnu.org/licenses/agpl-3.0
 * @link    https://www.fusio-project.org/
 */
class JqlBuilder
{
    public function getCollection(Type $type, array $tableNames, Document $document): string
    {
        $tableName = $tableNames[$type->getName()];
        $columns = [];
        $definition = $this->getDefinition($type, $tableNames, $document, $columns);

        $jql = [
            'totalEntries' => [
                '$value' => 'SELECT COUNT(*) AS cnt FROM ' . $tableName,
                '$definition' => [
                    '$key' => 'cnt',
                    '$field' => 'integer',
                ],
            ],
            'entries' => [
                '$collection' => 'SELECT ' . implode(', ', $columns) . ' FROM ' . $tableName . ' ORDER BY id ASC LIMIT :startIndex, 16',
                '$params' => [
                    'startIndex' => 'startIndex'
                ],
                '$definition' => $definition
            ]
        ];

        return \json_encode($jql, JSON_PRETTY_PRINT);
    }

    public function getEntity(Type $type, array $tableNames, Document $document): string
    {
        $tableName = $tableNames[$type->getName()];
        $columns = [];
        $definition = $this->getDefinition($type, $tableNames, $document, $columns);

        $jql = [
            '$entity' => 'SELECT ' . implode(', ', $columns) . ' FROM ' . $tableName . ' WHERE id = :id',
            '$params' => [
                'id' => 'id'
            ],
            '$definition' => $definition
        ];

        return \json_encode($jql, JSON_PRETTY_PRINT);
    }

    private function getDefinition(Type $type, array $tableNames, Document $document, array &$columns, int $depth = 0): array
    {
        $definition = [];

        $columns[] = 'id';
        $definition['id'] = [
            '$field' => 'integer',
        ];

        foreach ($type->getProperties() as $property) {
            $value = null;
            if (SqlEntity::isScalar($property->getType())) {
                $columnName = SqlEntity::getColumnName($property);
                $columns[] = $columnName;

                $value = [
                    '$key' => $columnName,
                    '$field' => $property->getType(),
                ];
            } elseif ($property->getType() === 'object') {
                if ($depth > 0) {
                    continue;
                }

                $index = $document->indexOf($property->getFirstRef());
                if ($index === null) {
                    continue;
                }

                $columnName = SqlEntity::getColumnName($property);
                $columns[] = $columnName;

                $foreignType = $document->getType($index);
                $foreignTable = $tableNames[$property->getFirstRef()];

                $foreignColumns = [];
                $entityDefinition = $this->getDefinition($foreignType, $tableNames, $document, $foreignColumns, $depth + 1);

                foreach ($foreignColumns as $index => $column) {
                    $foreignColumns[$index] = 'entity.' . $column;
                }

                $value = [
                    '$entity' => 'SELECT ' . implode(', ', $foreignColumns) . ' FROM ' . $foreignTable . ' entity WHERE entity.id = :id',
                    '$params' => [
                        'id' => [
                            '$ref' => SqlEntity::getColumnName($property),
                        ],
                    ],
                    '$definition' => $entityDefinition,
                ];
            } elseif ($property->getType() === 'map') {
                if (SqlEntity::isScalar($property->getFirstRef())) {
                    $columnName = SqlEntity::getColumnName($property);
                    $columns[] = $columnName;

                    $value = [
                        '$key' => $columnName,
                        '$field' => 'json',
                    ];
                } else {
                    if ($depth > 0) {
                        continue;
                    }

                    $index = $document->indexOf($property->getFirstRef());
                    if ($index === null) {
                        continue;
                    }

                    $foreignType = $document->getType($index);
                    $table = $tableNames[$type->getName()];
                    $foreignTable = $tableNames[$property->getFirstRef()];
                    $relationTable = $table . '_' . SqlEntity::underscore($property->getFirstRef());

                    $foreignColumns = [];
                    $mapDefinition = $this->getDefinition($foreignType, $tableNames, $document, $foreignColumns, $depth + 1);

                    foreach ($foreignColumns as $index => $column) {
                        $foreignColumns[$index] = 'entity.' . $column;
                    }

                    array_unshift($foreignColumns, 'rel.name AS hash_key');

                    $query = 'SELECT ' . implode(', ', $foreignColumns) . ' ';
                    $query.= 'FROM ' . $relationTable . ' rel ';
                    $query.= 'INNER JOIN ' . $foreignTable . ' entity ';
                    $query.= 'ON entity.id = rel.' . SqlEntity::underscore($property->getFirstRef()) . '_id ';
                    $query.= 'WHERE rel.' . SqlEntity::underscore($type->getName()) . '_id = :id ';
                    $query.= 'ORDER BY entity.id DESC ';
                    $query.= 'LIMIT 16';

                    $value = [
                        '$collection' => $query,
                        '$params' => [
                            'id' => [
                                '$ref' => 'id',
                            ],
                        ],
                        '$definition' => $mapDefinition,
                        '$key' => 'hash_key'
                    ];
                }
            } elseif ($property->getType() === 'array') {
                if (SqlEntity::isScalar($property->getFirstRef())) {
                    $columns[] = SqlEntity::getColumnName($property);

                    $value = [
                        '$field' => 'json',
                    ];
                } else {
                    if ($depth > 0) {
                        continue;
                    }

                    $index = $document->indexOf($property->getFirstRef());
                    if ($index === null) {
                        continue;
                    }

                    $foreignType = $document->getType($index);
                    $table = $tableNames[$type->getName()];
                    $foreignTable = $tableNames[$property->getFirstRef()];
                    $relationTable = $table . '_' . SqlEntity::underscore($property->getFirstRef());

                    $foreignColumns = [];
                    $arrayDefinition = $this->getDefinition($foreignType, $tableNames, $document, $foreignColumns, $depth + 1);

                    foreach ($foreignColumns as $index => $column) {
                        $foreignColumns[$index] = 'entity.' . $column;
                    }

                    $query = 'SELECT ' . implode(', ', $foreignColumns) . ' ';
                    $query.= 'FROM ' . $relationTable . ' rel ';
                    $query.= 'INNER JOIN ' . $foreignTable . ' entity ';
                    $query.= 'ON entity.id = rel.' . SqlEntity::underscore($property->getFirstRef()) . '_id ';
                    $query.= 'WHERE rel.' . SqlEntity::underscore($type->getName()) . '_id = :id ';
                    $query.= 'ORDER BY entity.id DESC ';
                    $query.= 'LIMIT 16';

                    $value = [
                        '$collection' => $query,
                        '$params' => [
                            'id' => [
                                '$ref' => 'id',
                            ],
                        ],
                        '$definition' => $arrayDefinition,
                    ];
                }
            }

            if ($value !== null) {
                $definition[$property->getName()] = $value;
            }
        }

        return $definition;
    }

}
