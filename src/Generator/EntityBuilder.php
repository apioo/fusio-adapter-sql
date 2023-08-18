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

use TypeAPI\Editor\Model\Type;

/**
 * EntityBuilder
 *
 * @author  Christoph Kappestein <christoph.kappestein@gmail.com>
 * @license http://www.apache.org/licenses/LICENSE-2.0
 * @link    https://www.fusio-project.org/
 */
class EntityBuilder
{
    public function getCollection(string $collectionName, string $entityName): object
    {
        return (object) [
            '$import' => [
                'Entity' => 'schema://' . $entityName
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
                                '$ref' => 'Entity:' . $entityName
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

        $selfMapping = [];
        if (isset($typeMapping[$typeName])) {
            $selfMapping[$typeName] = $typeMapping[$typeName];
        }

        $import = [];
        foreach ($typeMapping as $importTypeName => $realName) {
            if (isset($selfMapping[$importTypeName])) {
                continue;
            }
            $import[$importTypeName] = 'schema://' . $realName;
        }

        $usedRefs = [];
        if (isset($schema['properties']) && is_array($schema['properties'])) {
            $properties = [];
            $properties['id'] = [
                'type' => 'integer'
            ];
            foreach ($schema['properties'] as $key => $property) {
                if (isset($property['$ref']) && is_string($property['$ref'])) {
                    $properties[$key]['$ref'] = $this->resolveRef($property, $typeMapping, $selfMapping, $usedRefs);
                } elseif (isset($property['additionalProperties']['$ref']) && is_string($property['additionalProperties']['$ref'])) {
                    $properties[$key]['additionalProperties']['$ref'] = $this->resolveRef($property['additionalProperties'], $typeMapping, $selfMapping, $usedRefs);
                } elseif (isset($property['items']['$ref']) && is_string($property['items']['$ref'])) {
                    $properties[$key]['items']['$ref'] = $this->resolveRef($property['items'], $typeMapping, $selfMapping, $usedRefs);
                } elseif (isset($property['oneOf']) && is_array($property['oneOf'])) {
                    $properties[$key]['oneOf'] = $this->resolveRefs($property['oneOf'], $typeMapping, $selfMapping, $usedRefs);
                } elseif (isset($property['allOf']) && is_array($property['allOf'])) {
                    $properties[$key]['allOf'] = $this->resolveRefs($property['allOf'], $typeMapping, $selfMapping, $usedRefs);
                } else {
                    $properties[$key] = $property;
                }
            }
            $schema['properties'] = $properties;
        }

        $result = new \stdClass();
        foreach ($import as $name => $uri) {
            if (!in_array($name, $usedRefs)) {
                unset($import[$name]);
            }
        }

        if (!empty($import)) {
            $result->{'$import'} = (object) $import;
        }

        $result->definitions = new \stdClass();
        $result->definitions->{$entityName} = $schema;
        $result->{'$ref'} = $entityName;

        return $result;
    }

    private function resolveRefs(array $types, array $typeMapping, array $selfMapping, array &$usedRefs): array
    {
        $return = [];
        foreach ($types as $type) {
            if (isset($type['$ref'])) {
                $type['$ref'] = $this->resolveRef($type, $typeMapping, $selfMapping, $usedRefs);
                $return[] = $type;
            } else {
                $return[] = $type;
            }
        }
        return $return;
    }

    private function resolveRef(array|\ArrayAccess $type, array $typeMapping, array $selfMapping, array &$usedRefs): string
    {
        if (isset($selfMapping[$type['$ref']])) {
            return $selfMapping[$type['$ref']];
        } elseif (isset($typeMapping[$type['$ref']])) {
            $usedRefs[] = $type['$ref'];
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
