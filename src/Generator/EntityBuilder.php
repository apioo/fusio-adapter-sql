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

use PSX\Schema\Document\Type;

/**
 * EntityBuilder
 *
 * @author  Christoph Kappestein <christoph.kappestein@gmail.com>
 * @license http://www.gnu.org/licenses/agpl-3.0
 * @link    https://www.fusio-project.org/
 */
class EntityBuilder
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

    public function getEntity(Type $property, string $entityName, array $typeSchema, array $typeMapping): object
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

        return (object) [
            '$import' => $import,
            'definitions' => [
                $entityName => $schema
            ],
            '$ref' => $entityName
        ];
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

    public static function underscore(string $id): string
    {
        return strtolower(preg_replace(array('/([A-Z]+)([A-Z][a-z])/', '/([a-z\d])([A-Z])/'), array('\\1_\\2', '\\1_\\2'), strtr($id, '_', '.')));
    }
}
