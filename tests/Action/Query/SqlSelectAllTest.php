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

namespace Fusio\Adapter\Sql\Tests\Action\Query;

use Fusio\Adapter\Sql\Action\Query\SqlQueryAll;
use Fusio\Adapter\Sql\Tests\DbTestCase;
use PSX\Http\Environment\HttpResponseInterface;

/**
 * SqlSelectAllTest
 *
 * @author  Christoph Kappestein <christoph.kappestein@gmail.com>
 * @license http://www.gnu.org/licenses/agpl-3.0
 * @link    http://fusio-project.org
 */
class SqlSelectAllTest extends DbTestCase
{
    public function testHandle()
    {
        $parameters = $this->getParameters([
            'connection' => 1,
            'sql'        => 'SELECT id, title, price, content, date FROM app_news ORDER BY id ASC',
        ]);

        $action   = $this->getActionFactory()->factory(SqlQueryAll::class);
        $response = $action->handle($this->getRequest(), $parameters, $this->getContext());

        $actual = json_encode($response->getBody(), JSON_PRETTY_PRINT);
        $expect = <<<JSON
{
    "totalResults": 3,
    "itemsPerPage": 16,
    "startIndex": 0,
    "entry": [
        {
            "id": "1",
            "title": "foo",
            "price": "39.99",
            "content": "bar",
            "date": "2015-02-27 19:59:15"
        },
        {
            "id": "2",
            "title": "baz",
            "price": null,
            "content": null,
            "date": null
        },
        {
            "id": "3",
            "title": "bar",
            "price": "29.99",
            "content": "foo",
            "date": "2015-02-27 19:59:15"
        }
    ]
}
JSON;

        $this->assertInstanceOf(HttpResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals([], $response->getHeaders());
        $this->assertJsonStringEqualsJsonString($expect, $actual, $actual);
    }

    public function testHandleCondition()
    {
        $parameters = $this->getParameters([
            'connection' => 1,
            'sql'        => 'SELECT id, title, price, content, date FROM app_news WHERE title LIKE {title%}',
        ]);

        $action   = $this->getActionFactory()->factory(SqlQueryAll::class);
        $response = $action->handle($this->getRequest('GET', [], ['title' => 'fo']), $parameters, $this->getContext());

        $actual = json_encode($response->getBody(), JSON_PRETTY_PRINT);
        $expect = <<<JSON
{
    "totalResults": 1,
    "itemsPerPage": 16,
    "startIndex": 0,
    "entry": [
        {
            "id": "1",
            "title": "foo",
            "price": "39.99",
            "content": "bar",
            "date": "2015-02-27 19:59:15"
        }
    ]
}
JSON;

        $this->assertInstanceOf(HttpResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals([], $response->getHeaders());
        $this->assertJsonStringEqualsJsonString($expect, $actual, $actual);
    }

    public function testHandlePagination()
    {
        $parameters = $this->getParameters([
            'connection' => 1,
            'sql'        => 'SELECT id, title, price, content, date FROM app_news ORDER BY id ASC',
        ]);

        $action   = $this->getActionFactory()->factory(SqlQueryAll::class);
        $response = $action->handle($this->getRequest('GET', [], ['startIndex' => 1, 'count' => 1]), $parameters, $this->getContext());

        $actual = json_encode($response->getBody(), JSON_PRETTY_PRINT);
        $expect = <<<JSON
{
    "totalResults": 3,
    "itemsPerPage": 1,
    "startIndex": 1,
    "entry": [
        {
            "id": "2",
            "title": "baz",
            "price": null,
            "content": null,
            "date": null
        }
    ]
}
JSON;

        $this->assertInstanceOf(HttpResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals([], $response->getHeaders());
        $this->assertJsonStringEqualsJsonString($expect, $actual, $actual);
    }

    public function testHandleLimit()
    {
        $parameters = $this->getParameters([
            'connection' => 1,
            'sql'        => 'SELECT id, title, price, content, date FROM app_news ORDER BY id ASC',
            'limit'      => 2,
        ]);

        $action   = $this->getActionFactory()->factory(SqlQueryAll::class);
        $response = $action->handle($this->getRequest(), $parameters, $this->getContext());

        $actual = json_encode($response->getBody(), JSON_PRETTY_PRINT);
        $expect = <<<JSON
{
    "totalResults": 3,
    "itemsPerPage": 2,
    "startIndex": 0,
    "entry": [
        {
            "id": "1",
            "title": "foo",
            "price": "39.99",
            "content": "bar",
            "date": "2015-02-27 19:59:15"
        },
        {
            "id": "2",
            "title": "baz",
            "price": null,
            "content": null,
            "date": null
        }
    ]
}
JSON;

        $this->assertInstanceOf(HttpResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals([], $response->getHeaders());
        $this->assertJsonStringEqualsJsonString($expect, $actual, $actual);
    }
}
