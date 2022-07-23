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

namespace Fusio\Adapter\Sql\Tests;

use Fusio\Adapter\Sql\Generator\JqlBuilder;
use PHPUnit\Framework\TestCase;
use PSX\Schema\Document\Document;

/**
 * JqlBuilderTest
 *
 * @author  Christoph Kappestein <christoph.kappestein@gmail.com>
 * @license http://www.gnu.org/licenses/agpl-3.0
 * @link    https://www.fusio-project.org/
 */
class JqlBuilderTest extends TestCase
{
    public function testGetCollection()
    {
        $document = $this->getDocument();
        $tableNames = [
            'Human' => 'app_human_0',
            'Location' => 'app_location_0',
            'Category' => 'app_category_0',
        ];

        $actual = (new JqlBuilder())->getCollection($document->getType($document->getRoot()), $tableNames, $document);
        $expect = file_get_contents(__DIR__ . '/resource/collection.json');

        $this->assertJsonStringEqualsJsonString($expect, $actual);
    }

    public function testGetEntity()
    {
        $document = $this->getDocument();
        $tableNames = [
            'Human' => 'app_human_0',
            'Location' => 'app_location_0',
            'Category' => 'app_category_0',
        ];

        $actual = (new JqlBuilder())->getEntity($document->getType($document->getRoot()), $tableNames, $document);
        $expect = file_get_contents(__DIR__ . '/resource/entity.json');

        $this->assertJsonStringEqualsJsonString($expect, $actual);
    }

    private function getDocument(): Document
    {
        $document = json_decode(file_get_contents(__DIR__ . '/resource/document.json'), true);
        return Document::from($document);
    }
}
