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

use PSX\Record\Record;
use TypeAPI\Editor\Model\Type;
use TypeAPI\Model\CollectionPropertyType;
use TypeAPI\Model\IntegerPropertyType;
use TypeAPI\Model\ReferencePropertyType;
use TypeAPI\Model\StructDefinitionType;
use TypeAPI\Model\TypeSchema;

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
            'import' => [
                'Entity' => 'schema://' . $entityName
            ],
            'definitions' => [
                $collectionName => [
                    'type' => 'struct',
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
                            'schema' => [
                                'type' => 'reference',
                                'target' => 'Entity:' . $entityName
                            ],
                        ],
                    ],
                ]
            ],
            'root' => $collectionName
        ];
    }

    public function getEntity(Type $property, string $entityName, TypeSchema $specification, array $typeMapping): object
    {
        $type = $specification->getDefinitions()->get($property->getName() ?? '');
        if (!$type instanceof StructDefinitionType) {
            throw new \RuntimeException('Could not resolve type');
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
        $typeProperties = $type->getProperties();
        if (!empty($typeProperties)) {
            $properties = new Record();

            $idType = new IntegerPropertyType();
            $idType->setType('integer');
            $properties->put('id', $idType);

            foreach ($typeProperties as $key => $property) {
                if ($property instanceof ReferencePropertyType) {
                    $property->setTarget($this->resolveRef($property, $typeMapping, $selfMapping, $usedRefs));
                } elseif ($property instanceof CollectionPropertyType) {
                    $schema = $property->getSchema();
                    if ($schema instanceof ReferencePropertyType) {
                        $schema->setTarget($this->resolveRef($schema, $typeMapping, $selfMapping, $usedRefs));
                    }
                }

                $properties->put($key, $property);
            }

            $type->setProperties($properties);
        }

        $result = new \stdClass();
        foreach ($import as $name => $uri) {
            if (!in_array($name, $usedRefs)) {
                unset($import[$name]);
            }
        }

        if (!empty($import)) {
            $result->{'import'} = (object) $import;
        }

        $result->definitions = new \stdClass();
        $result->definitions->{$entityName} = $type;
        $result->{'root'} = $entityName;

        return $result;
    }

    private function resolveRef(ReferencePropertyType $type, array $typeMapping, array $selfMapping, array &$usedRefs): string
    {
        if (isset($selfMapping[$type->getTarget()])) {
            return $selfMapping[$type->getTarget()];
        } elseif (isset($typeMapping[$type->getTarget()])) {
            $usedRefs[] = $type->getTarget();
            return $type->getTarget() . ':' . $typeMapping[$type->getTarget()];
        } else {
            return $type->getTarget();
        }
    }

    public static function underscore(string $id): string
    {
        return strtolower(preg_replace(array('/([A-Z]+)([A-Z][a-z])/', '/([a-z\d])([A-Z])/'), array('\\1_\\2', '\\1_\\2'), strtr($id, '_', '.')));
    }
}
