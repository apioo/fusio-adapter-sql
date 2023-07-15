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

namespace Fusio\Adapter\Sql\Introspection;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Type;
use Fusio\Engine\Connection\Introspection\Entity;
use Fusio\Engine\Connection\Introspection\IntrospectorInterface;
use Fusio\Engine\Connection\Introspection\Row;

/**
 * Introspector
 *
 * @author  Christoph Kappestein <christoph.kappestein@gmail.com>
 * @license http://www.apache.org/licenses/LICENSE-2.0
 * @link    https://www.fusio-project.org/
 */
class Introspector implements IntrospectorInterface
{
    private Connection $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function getEntities(): array
    {
        return $this->connection->createSchemaManager()->listTableNames();
    }

    public function getEntity(string $entityName): Entity
    {
        $table = $this->connection->createSchemaManager()->introspectTable($entityName);
        $entity = new Entity($table->getName(), [
            'Name',
            'Type',
            'Comment',
        ]);

        foreach ($table->getColumns() as $column) {
            $entity->addRow(new Row([
                $column->getName(),
                Type::getTypeRegistry()->lookupName($column->getType()),
                $column->getComment(),
            ]));
        }

        return $entity;
    }
}
