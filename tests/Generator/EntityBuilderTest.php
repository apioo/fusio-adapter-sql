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

namespace Fusio\Adapter\Sql\Tests;

use Fusio\Adapter\Sql\Generator\EntityBuilder;
use Fusio\Adapter\Sql\Generator\EntityExecutor;
use PHPUnit\Framework\TestCase;
use TypeAPI\Editor\Generator;
use TypeAPI\Editor\Model\Document;

/**
 * EntityBuilderTest
 *
 * @author  Christoph Kappestein <christoph.kappestein@gmail.com>
 * @license http://www.apache.org/licenses/LICENSE-2.0
 * @link    https://www.fusio-project.org/
 */
class EntityBuilderTest extends SqlTestCase
{
    public function testGetCollection()
    {
        $actual = (new EntityBuilder())->getCollection('Human_SQL_GetAll', 'Human_SQL_Get');
        $expect = file_get_contents(__DIR__ . '/resource/entity/collection.json');

        $this->assertJsonStringEqualsJsonString($expect, \json_encode($actual));
    }

    public function testGetEntity()
    {
        $document = $this->getDocument();
        $specification = (new Generator())->toModel($document);
        $type = $document->getType(0);
        $tableNames = (new EntityExecutor())->getTableNames($document, $this->connection->createSchemaManager());
        $typeMapping = (new EntityExecutor())->getTypeMapping($document, $tableNames);

        $actual = (new EntityBuilder())->getEntity($type, 'Human_SQL_Get', $specification, $typeMapping);
        $expect = file_get_contents(__DIR__ . '/resource/entity/entity.json');

        $this->assertJsonStringEqualsJsonString($expect, \json_encode($actual));
    }

    private function getDocument(): Document
    {
        $document = json_decode(file_get_contents(__DIR__ . '/resource/document.json'), true);
        return Document::from($document);
    }
}
