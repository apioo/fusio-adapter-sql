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

namespace Fusio\Adapter\Sql\Action;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types;
use Fusio\Engine\ActionAbstract;
use Fusio\Engine\Exception\ConfigurationException;
use Fusio\Engine\Form\BuilderInterface;
use Fusio\Engine\Form\ElementFactoryInterface;
use Fusio\Engine\ParametersInterface;
use PSX\Http\Exception as StatusCode;
use PSX\Record\RecordInterface;

/**
 * SqlManipulationAbstract
 *
 * @author  Christoph Kappestein <christoph.kappestein@gmail.com>
 * @license http://www.gnu.org/licenses/agpl-3.0
 * @link    https://www.fusio-project.org/
 */
abstract class SqlManipulationAbstract extends SqlActionAbstract
{
    protected function insertRelations(Connection $connection, int $entityId, RecordInterface $body, ?array $mapping = null)
    {
        $configs = $this->getRelationMappingConfig($mapping);
        foreach ($configs as $config) {
            [$propertyName, $type, $relationTable, $entityIdColumn, $foreignIdColumn] = $config;

            $data = $body->getProperty($propertyName);
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

    protected function deleteRelations(Connection $connection, int $entityId, ?array $mapping = null)
    {
        $configs = $this->getRelationMappingConfig($mapping);
        foreach ($configs as $config) {
            [$propertyName, $type, $relationTable, $entityIdColumn, $foreignIdColumn] = $config;

            $connection->delete($relationTable, [
                $entityIdColumn  => $entityId,
            ]);
        }
    }

    protected function findExistingId(Connection $connection, string $key, Table $table, int $id): int
    {
        $qb = $connection->createQueryBuilder();
        $qb->select([$key]);
        $qb->from($table->getName());
        $qb->where($key . ' = :id');
        $existingId = (int) $connection->fetchOne($qb->getSQL(), ['id' => $id]);
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
            return $data->getProperty($propertyName);
        } elseif ($data instanceof \stdClass) {
            return $data->{$propertyName} ?? null;
        } elseif (is_array($data)) {
            return $data[$propertyName] ?? null;
        }

        return null;
    }
}
