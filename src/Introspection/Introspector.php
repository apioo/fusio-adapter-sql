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
 * @license http://www.gnu.org/licenses/agpl-3.0
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
