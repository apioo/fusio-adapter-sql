<?php
/*
 * Fusio
 * A web-application to create dynamically RESTful APIs
 *
 * Copyright (C) 2015-2020 Christoph Kappestein <christoph.kappestein@gmail.com>
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

namespace Fusio\Adapter\Sql\Tests\Action;

use Fusio\Adapter\Sql\Action\SqlSelectRow;
use Fusio\Adapter\Sql\Tests\DbTestCase;
use PSX\Http\Environment\HttpResponseInterface;
use PSX\Http\Exception\NotFoundException;

/**
 * SqlTableTest
 *
 * @author  Christoph Kappestein <christoph.kappestein@gmail.com>
 * @license http://www.gnu.org/licenses/agpl-3.0
 * @link    http://fusio-project.org
 */
class SqlTableTest extends DbTestCase
{
    public function testHandle()
    {
        $parameters = $this->getParameters([
            'connection' => 1,
            'table'      => 'app_news',
        ]);

        $action   = $this->getActionFactory()->factory(SqlSelectRow::class);
        $response = $action->handle($this->getRequest('GET', ['id' => 1]), $parameters, $this->getContext());

        $actual = json_encode($response->getBody(), JSON_PRETTY_PRINT);
        $expect = <<<JSON
{
    "id": 1,
    "title": "foo",
    "price": 39.99,
    "content": "bar",
    "image": "AAAAAAAAAAAAAAAAAAAAAA==",
    "posted": "13:37:00",
    "date": "2015-02-27T19:59:15+00:00"
}
JSON;

        $this->assertInstanceOf(HttpResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals([], $response->getHeaders());
        $this->assertJsonStringEqualsJsonString($expect, $actual, $actual);
    }

    public function testHandleColumns()
    {
        $parameters = $this->getParameters([
            'connection' => 1,
            'table'      => 'app_news',
            'columns'    => ['id', 'title'],
        ]);

        $action   = $this->getActionFactory()->factory(SqlSelectRow::class);
        $response = $action->handle($this->getRequest('GET', ['id' => 1]), $parameters, $this->getContext());

        $actual = json_encode($response->getBody(), JSON_PRETTY_PRINT);
        $expect = <<<JSON
{
    "id": 1,
    "title": "foo"
}
JSON;

        $this->assertInstanceOf(HttpResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals([], $response->getHeaders());
        $this->assertJsonStringEqualsJsonString($expect, $actual, $actual);
    }

    public function testHandleInvalid()
    {
        $this->expectException(NotFoundException::class);

        $parameters = $this->getParameters([
            'connection' => 1,
            'table'      => 'app_news',
            'columns'    => ['id', 'title'],
        ]);

        $action = $this->getActionFactory()->factory(SqlSelectRow::class);
        $action->handle($this->getRequest('GET', ['id' => 5]), $parameters, $this->getContext());
    }
}
