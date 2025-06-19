<?php
/*
 * Fusio - Self-Hosted API Management for Builders.
 * For the current version and information visit <https://www.fusio-project.org/>
 *
 * Copyright (c) Christoph Kappestein <christoph.kappestein@gmail.com>
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

use Fusio\Engine\Form\BuilderInterface;
use Fusio\Engine\Form\ElementFactoryInterface;
use Fusio\Engine\Generator\SetupInterface;
use Fusio\Engine\ParametersInterface;

/**
 * SqlDatabase
 *
 * @author  Christoph Kappestein <christoph.kappestein@gmail.com>
 * @license http://www.apache.org/licenses/LICENSE-2.0
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
