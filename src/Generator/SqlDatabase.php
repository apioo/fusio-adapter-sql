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

use Fusio\Engine\Form\BuilderInterface;
use Fusio\Engine\Form\ElementFactoryInterface;
use Fusio\Engine\Generator\SetupInterface;
use Fusio\Engine\ParametersInterface;

/**
 * SqlDatabase
 *
 * @author  Christoph Kappestein <christoph.kappestein@gmail.com>
 * @license http://www.gnu.org/licenses/agpl-3.0
 * @link    https://www.fusio-project.org/
 */
class SqlDatabase extends SqlTable
{
    public function getName(): string
    {
        return 'SQL-Database';
    }

    public function setup(SetupInterface $setup, ParametersInterface $configuration): void
    {
        $connectionName = $configuration->get('connection');
        $prefix = $configuration->get('prefix') ?? '';
        $schemaManager = $this->getConnection($connectionName)->createSchemaManager();

        $tableNames = $schemaManager->listTableNames();
        foreach ($tableNames as $tableName) {
            $name = $tableName;
            if (!empty($prefix)) {
                if (str_starts_with($tableName, $prefix)) {
                    $name = substr($tableName, strlen($prefix));
                } else {
                    continue;
                }
            }

            $this->generateForTable($schemaManager, $connectionName, $tableName, $setup, $name);
        }
    }

    public function configure(BuilderInterface $builder, ElementFactoryInterface $elementFactory): void
    {
        $builder->add($elementFactory->newConnection('connection', 'Connection', 'The SQL connection which should be used'));
        $builder->add($elementFactory->newInput('prefix', 'Prefix', 'text', 'Includes only tables which start with this prefix'));
    }
}
