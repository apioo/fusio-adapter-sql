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

namespace Fusio\Adapter\Sql\Action;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\IntegerType;
use Fusio\Engine\RequestInterface;
use PSX\Http\Exception as StatusCode;
use PSX\Record\RecordInterface;

/**
 * SqlManipulationAbstract
 *
 * @author  Christoph Kappestein <christoph.kappestein@gmail.com>
 * @license http://www.apache.org/licenses/LICENSE-2.0
 * @link    https://www.fusio-project.org/
 */
abstract class SqlManipulationAbstract extends SqlActionAbstract
{
    protected function insertRelations(Connection $connection, int|string $entityId, RecordInterface $body, ?array $mapping = null): void
    {
        $configs = $this->getRelationMappingConfig($mapping);
        foreach ($configs as $config) {
            [$propertyName, $type, $relationTable, $entityIdColumn, $foreignIdColumn] = $config;

            $data = $body->get($propertyName);
            if (empty($data)) {
                continue;
            }

            if ($type === 'array') {
                if (!is_array($data)) {
                    throw new StatusCode\BadRequestException('Provided data for property "' . $propertyName . '" must be an array');
                }

                // delete all existing entries
                $connection->delete($relationTable, [$entityIdColumn => $entityId]);

                foreach ($data as $row) {
                    $foreignId = $this->getValue($row, 'id');
                    if (empty($foreignId) || !is_int($foreignId)) {
                        continue;
                    }

                    $connection->insert($relationTable, [
                        $entityIdColumn  => $entityId,
                        $foreignIdColumn => $foreignId,
                    ]);
                }
            } elseif ($type === 'map') {
                if (!$data instanceof RecordInterface) {
                    throw new StatusCode\BadRequestException('Provided data for property "' . $propertyName . '" must be an object');
                }

                // delete all existing entries
                $connection->delete($relationTable, [$entityIdColumn => $entityId]);

                foreach ($data as $key => $row) {
                    $foreignId = $this->getValue($row, 'id');
                    if (empty($foreignId) || !is_int($foreignId)) {
                        continue;
                    }

                    $connection->insert($relationTable, [
                        $entityIdColumn  => $entityId,
                        'name'           => $key,
                        $foreignIdColumn => $foreignId,
                    ]);
                }
            }
        }
    }

    protected function deleteRelations(Connection $connection, int|string $entityId, ?array $mapping = null): void
    {
        $configs = $this->getRelationMappingConfig($mapping);
        foreach ($configs as $config) {
            [$propertyName, $type, $relationTable, $entityIdColumn, $foreignIdColumn] = $config;

            $connection->delete($relationTable, [
                $entityIdColumn => $entityId,
            ]);
        }
    }

    protected function findExistingId(Connection $connection, string $key, Table $table, RequestInterface $request): int|string
    {
        $rawId = $request->get('id');
        if (empty($rawId) || !is_scalar($rawId)) {
            throw new StatusCode\BadRequestException('Id not available');
        }

        $qb = $connection->createQueryBuilder();
        $qb->select([$key]);
        $qb->from($table->getName());
        $qb->where($key . ' = :id');

        $existingId = $connection->fetchOne($qb->getSQL(), ['id' => $rawId]);
        if (empty($existingId)) {
            throw new StatusCode\NotFoundException('Entry not available');
        }

        return $existingId;
    }

    private function getRelationMappingConfig(?array $mapping = null): array
    {
        if (empty($mapping)) {
            return [];
        }

        $configs = [];
        foreach ($mapping as $config) {
            if (!is_string($config)) {
                continue;
            }

            $parts = explode(':', $config);
            if (count($parts) !== 5) {
                continue;
            }

            $type = $parts[1];
            if ($type === 'array' || $type === 'map') {
                $configs[] = $parts;
            } else {
                throw new StatusCode\InternalServerErrorException('Configured mapping has an invalid type "' . $type . '", must bei either array or map');
            }
        }

        return $configs;
    }

    private function getValue(mixed $data, string $propertyName): mixed
    {
        if ($data instanceof RecordInterface) {
            return $data->get($propertyName);
        } elseif ($data instanceof \stdClass) {
            return $data->{$propertyName} ?? null;
        } elseif (is_array($data)) {
            return $data[$propertyName] ?? null;
        }

        return null;
    }
}
