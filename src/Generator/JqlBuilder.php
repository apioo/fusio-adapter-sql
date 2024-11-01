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

use TypeAPI\Editor\Model\Document;
use TypeAPI\Editor\Model\Type;

/**
 * JqlBuilder
 *
 * @author  Christoph Kappestein <christoph.kappestein@gmail.com>
 * @license http://www.apache.org/licenses/LICENSE-2.0
 * @link    https://www.fusio-project.org/
 */
class JqlBuilder
{
    public function getCollection(Type $type, array $tableNames, Document $document): string
    {
        $tableName = $tableNames[$type->getName() ?? ''] ?? '';
        $columns = [];
        $definition = $this->getDefinition($type, $tableNames, $document, $columns);

        $jql = [
            'totalResults' => [
                '$value' => 'SELECT COUNT(*) AS cnt FROM ' . $tableName,
                '$definition' => [
                    '$key' => 'cnt',
                    '$field' => 'integer',
                ],
            ],
            'startIndex' => [
                '$context' => 'startIndex',
                '$default' => 0
            ],
            'itemsPerPage' => 16,
            'entry' => [
                '$collection' => 'SELECT ' . implode(', ', $columns) . ' FROM ' . $tableName . ' ORDER BY id DESC',
                '$offset' => [
                    '$context' => 'startIndex',
                    '$default' => 0
                ],
                '$limit' => 16,
                '$definition' => $definition
            ]
        ];

        return \json_encode($jql, JSON_PRETTY_PRINT);
    }

    public function getEntity(Type $type, array $tableNames, Document $document): string
    {
        $tableName = $tableNames[$type->getName() ?? ''] ?? '';
        $columns = [];
        $definition = $this->getDefinition($type, $tableNames, $document, $columns);

        $jql = [
            '$entity' => 'SELECT ' . implode(', ', $columns) . ' FROM ' . $tableName . ' WHERE id = :id',
            '$params' => [
                'id' => [
                    '$context' => 'id'
                ]
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
            if (EntityExecutor::isScalar($property->getType() ?? '')) {
                $columnName = EntityExecutor::getColumnName($property);
                $columns[] = $columnName;

                $value = [
                    '$key' => $columnName,
                    '$field' => $property->getType(),
                ];
            } elseif ($property->getType() === 'object') {
                if ($depth > 0) {
                    continue;
                }

                $index = $document->indexOfType($property->getReference() ?? '');
                if ($index === null) {
                    continue;
                }

                $columnName = EntityExecutor::getColumnName($property);
                $columns[] = $columnName;

                $foreignType = $document->getType($index);
                if ($foreignType === null) {
                    continue;
                }

                $foreignTable = $tableNames[$property->getReference() ?? ''];

                $foreignColumns = [];
                $entityDefinition = $this->getDefinition($foreignType, $tableNames, $document, $foreignColumns, $depth + 1);

                foreach ($foreignColumns as $index => $column) {
                    $foreignColumns[$index] = 'entity.' . $column;
                }

                $value = [
                    '$entity' => 'SELECT ' . implode(', ', $foreignColumns) . ' FROM ' . $foreignTable . ' entity WHERE entity.id = :id',
                    '$params' => [
                        'id' => [
                            '$ref' => EntityExecutor::getColumnName($property),
                        ],
                    ],
                    '$definition' => $entityDefinition,
                ];
            } elseif ($property->getType() === 'map') {
                if (EntityExecutor::isScalar($property->getReference() ?? '')) {
                    $columnName = EntityExecutor::getColumnName($property);
                    $columns[] = $columnName;

                    $value = [
                        '$key' => $columnName,
                        '$field' => 'json',
                    ];
                } else {
                    if ($depth > 0) {
                        continue;
                    }

                    $index = $document->indexOfType($property->getReference() ?? '');
                    if ($index === null) {
                        continue;
                    }

                    $foreignType = $document->getType($index);
                    if ($foreignType === null) {
                        continue;
                    }

                    $table = $tableNames[$type->getName() ?? ''];
                    $foreignTable = $tableNames[$property->getReference() ?? ''];
                    $relationTable = $table . '_' . EntityExecutor::underscore($property->getReference() ?? '');

                    $foreignColumns = [];
                    $mapDefinition = $this->getDefinition($foreignType, $tableNames, $document, $foreignColumns, $depth + 1);

                    foreach ($foreignColumns as $index => $column) {
                        $foreignColumns[$index] = 'entity.' . $column;
                    }

                    array_unshift($foreignColumns, 'rel.name AS hash_key');

                    $query = 'SELECT ' . implode(', ', $foreignColumns) . ' ';
                    $query.= 'FROM ' . $relationTable . ' rel ';
                    $query.= 'INNER JOIN ' . $foreignTable . ' entity ';
                    $query.= 'ON entity.id = rel.' . EntityExecutor::underscore($property->getReference() ?? '') . '_id ';
                    $query.= 'WHERE rel.' . EntityExecutor::underscore($type->getName() ?? '') . '_id = :id ';
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
                if (EntityExecutor::isScalar($property->getReference() ?? '')) {
                    $columns[] = EntityExecutor::getColumnName($property);

                    $value = [
                        '$field' => 'json',
                    ];
                } else {
                    if ($depth > 0) {
                        continue;
                    }

                    $index = $document->indexOfType($property->getReference() ?? '');
                    if ($index === null) {
                        continue;
                    }

                    $foreignType = $document->getType($index);
                    if ($foreignType === null) {
                        continue;
                    }

                    $table = $tableNames[$type->getName() ?? ''];
                    $foreignTable = $tableNames[$property->getReference() ?? ''];
                    $relationTable = $table . '_' . EntityExecutor::underscore($property->getReference() ?? '');

                    $foreignColumns = [];
                    $arrayDefinition = $this->getDefinition($foreignType, $tableNames, $document, $foreignColumns, $depth + 1);

                    foreach ($foreignColumns as $index => $column) {
                        $foreignColumns[$index] = 'entity.' . $column;
                    }

                    $query = 'SELECT ' . implode(', ', $foreignColumns) . ' ';
                    $query.= 'FROM ' . $relationTable . ' rel ';
                    $query.= 'INNER JOIN ' . $foreignTable . ' entity ';
                    $query.= 'ON entity.id = rel.' . EntityExecutor::underscore($property->getReference() ?? '') . '_id ';
                    $query.= 'WHERE rel.' . EntityExecutor::underscore($type->getName() ?? '') . '_id = :id ';
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
                $definition[$property->getName() ?? ''] = $value;
            }
        }

        return $definition;
    }
}
