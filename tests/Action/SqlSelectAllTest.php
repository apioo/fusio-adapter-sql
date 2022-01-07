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

namespace Fusio\Adapter\Sql\Tests\Action;

use Fusio\Adapter\Sql\Action\SqlSelectAll;
use Fusio\Adapter\Sql\Tests\DbTestCase;
use PSX\Http\Environment\HttpResponseInterface;

/**
 * SqlSelectAllTest
 *
 * @author  Christoph Kappestein <christoph.kappestein@gmail.com>
 * @license http://www.gnu.org/licenses/agpl-3.0
 * @link    https://www.fusio-project.org/
 */
class SqlSelectAllTest extends DbTestCase
{
    public function testHandle()
    {
        $parameters = $this->getParameters([
            'connection' => 1,
            'table'      => 'app_news',
        ]);

        $action   = $this->getActionFactory()->factory(SqlSelectAll::class);
        $response = $action->handle($this->getRequest(), $parameters, $this->getContext());

        $actual = json_encode($response->getBody(), JSON_PRETTY_PRINT);
        $expect = <<<JSON
{
    "totalResults": 3,
    "itemsPerPage": 16,
    "startIndex": 0,
    "entry": [
        {
            "id": 3,
            "title": "bar",
            "price": 29.99,
            "content": "foo",
            "image": "AAAAAAAAAAAAAAAAAAAAAA==",
            "posted": "13:37:00",
            "date": "2015-02-27T19:59:15+00:00"
        },
        {
            "id": 2,
            "title": "baz",
            "price": null,
            "content": null,
            "image": null,
            "posted": null,
            "date": null
        },
        {
            "id": 1,
            "title": "foo",
            "price": 39.99,
            "content": "bar",
            "image": "AAAAAAAAAAAAAAAAAAAAAA==",
            "posted": "13:37:00",
            "date": "2015-02-27T19:59:15+00:00"
        }
    ]
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

        $action   = $this->getActionFactory()->factory(SqlSelectAll::class);
        $response = $action->handle($this->getRequest(), $parameters, $this->getContext());

        $actual = json_encode($response->getBody(), JSON_PRETTY_PRINT);
        $expect = <<<JSON
{
    "totalResults": 3,
    "itemsPerPage": 16,
    "startIndex": 0,
    "entry": [
        {
            "id": 3,
            "title": "bar"
        },
        {
            "id": 2,
            "title": "baz"
        },
        {
            "id": 1,
            "title": "foo"
        }
    ]
}
JSON;

        $this->assertInstanceOf(HttpResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals([], $response->getHeaders());
        $this->assertJsonStringEqualsJsonString($expect, $actual, $actual);
    }

    public function testHandleOrderBy()
    {
        $parameters = $this->getParameters([
            'connection' => 1,
            'table'      => 'app_news',
            'columns'    => ['id', 'title'],
            'orderBy'    => 'title',
        ]);

        $action   = $this->getActionFactory()->factory(SqlSelectAll::class);
        $response = $action->handle($this->getRequest(), $parameters, $this->getContext());

        $actual = json_encode($response->getBody(), JSON_PRETTY_PRINT);
        $expect = <<<JSON
{
    "totalResults": 3,
    "itemsPerPage": 16,
    "startIndex": 0,
    "entry": [
        {
            "id": 1,
            "title": "foo"
        },
        {
            "id": 2,
            "title": "baz"
        },
        {
            "id": 3,
            "title": "bar"
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
            'table'      => 'app_news',
            'limit'      => 1,
        ]);

        $action   = $this->getActionFactory()->factory(SqlSelectAll::class);
        $response = $action->handle($this->getRequest(), $parameters, $this->getContext());

        $actual = json_encode($response->getBody(), JSON_PRETTY_PRINT);
        $expect = <<<JSON
{
    "totalResults": 3,
    "itemsPerPage": 1,
    "startIndex": 0,
    "entry": [
        {
            "id": 3,
            "title": "bar",
            "price": 29.99,
            "content": "foo",
            "image": "AAAAAAAAAAAAAAAAAAAAAA==",
            "posted": "13:37:00",
            "date": "2015-02-27T19:59:15+00:00"
        }
    ]
}
JSON;

        $this->assertInstanceOf(HttpResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals([], $response->getHeaders());
        $this->assertJsonStringEqualsJsonString($expect, $actual, $actual);
    }

    public function testHandleSortByAsc()
    {
        $parameters = $this->getParameters([
            'connection' => 1,
            'table'      => 'app_news',
            'columns'    => ['id', 'title'],
        ]);

        $action   = $this->getActionFactory()->factory(SqlSelectAll::class);
        $response = $action->handle($this->getRequest(null, [], ['sortBy' => 'title', 'sortOrder' => 'asc']), $parameters, $this->getContext());

        $actual = json_encode($response->getBody(), JSON_PRETTY_PRINT);
        $expect = <<<JSON
{
    "totalResults": 3,
    "itemsPerPage": 16,
    "startIndex": 0,
    "entry": [
        {
            "id": 3,
            "title": "bar"
        },
        {
            "id": 2,
            "title": "baz"
        },
        {
            "id": 1,
            "title": "foo"
        }
    ]
}
JSON;

        $this->assertInstanceOf(HttpResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals([], $response->getHeaders());
        $this->assertJsonStringEqualsJsonString($expect, $actual, $actual);
    }

    public function testHandleSortByDesc()
    {
        $parameters = $this->getParameters([
            'connection' => 1,
            'table'      => 'app_news',
            'columns'    => ['id', 'title'],
        ]);

        $action   = $this->getActionFactory()->factory(SqlSelectAll::class);
        $response = $action->handle($this->getRequest(null, [], ['sortBy' => 'title', 'sortOrder' => 'desc']), $parameters, $this->getContext());

        $actual = json_encode($response->getBody(), JSON_PRETTY_PRINT);
        $expect = <<<JSON
{
    "totalResults": 3,
    "itemsPerPage": 16,
    "startIndex": 0,
    "entry": [
        {
            "id": 1,
            "title": "foo"
        },
        {
            "id": 2,
            "title": "baz"
        },
        {
            "id": 3,
            "title": "bar"
        }
    ]
}
JSON;

        $this->assertInstanceOf(HttpResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals([], $response->getHeaders());
        $this->assertJsonStringEqualsJsonString($expect, $actual, $actual);
    }

    public function testHandleFilterContains()
    {
        $parameters = $this->getParameters([
            'connection' => 1,
            'table'      => 'app_news',
            'columns'    => ['id', 'title'],
        ]);

        $action   = $this->getActionFactory()->factory(SqlSelectAll::class);
        $response = $action->handle($this->getRequest(null, [], ['filterBy' => 'title', 'filterOp' => 'contains', 'filterValue' => 'fo']), $parameters, $this->getContext());

        $actual = json_encode($response->getBody(), JSON_PRETTY_PRINT);
        $expect = <<<JSON
{
    "totalResults": 3,
    "itemsPerPage": 16,
    "startIndex": 0,
    "entry": [
        {
            "id": 1,
            "title": "foo"
        }
    ]
}
JSON;

        $this->assertInstanceOf(HttpResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals([], $response->getHeaders());
        $this->assertJsonStringEqualsJsonString($expect, $actual, $actual);
    }

    public function testHandleFilterEquals()
    {
        $parameters = $this->getParameters([
            'connection' => 1,
            'table'      => 'app_news',
            'columns'    => ['id', 'title'],
        ]);

        $action   = $this->getActionFactory()->factory(SqlSelectAll::class);
        $response = $action->handle($this->getRequest(null, [], ['filterBy' => 'title', 'filterOp' => 'equals', 'filterValue' => 'bar']), $parameters, $this->getContext());

        $actual = json_encode($response->getBody(), JSON_PRETTY_PRINT);
        $expect = <<<JSON
{
    "totalResults": 3,
    "itemsPerPage": 16,
    "startIndex": 0,
    "entry": [
        {
            "id": 3,
            "title": "bar"
        }
    ]
}
JSON;

        $this->assertInstanceOf(HttpResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals([], $response->getHeaders());
        $this->assertJsonStringEqualsJsonString($expect, $actual, $actual);
    }

    public function testHandleFilterStartsWith()
    {
        $parameters = $this->getParameters([
            'connection' => 1,
            'table'      => 'app_news',
            'columns'    => ['id', 'title'],
        ]);

        $action   = $this->getActionFactory()->factory(SqlSelectAll::class);
        $response = $action->handle($this->getRequest(null, [], ['filterBy' => 'title', 'filterOp' => 'startsWith', 'filterValue' => 'ba']), $parameters, $this->getContext());

        $actual = json_encode($response->getBody(), JSON_PRETTY_PRINT);
        $expect = <<<JSON
{
    "totalResults": 3,
    "itemsPerPage": 16,
    "startIndex": 0,
    "entry": [
        {
            "id": 3,
            "title": "bar"
        },
        {
            "id": 2,
            "title": "baz"
        }
    ]
}
JSON;

        $this->assertInstanceOf(HttpResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals([], $response->getHeaders());
        $this->assertJsonStringEqualsJsonString($expect, $actual, $actual);
    }

    public function testHandleFilterPresent()
    {
        $parameters = $this->getParameters([
            'connection' => 1,
            'table'      => 'app_news',
            'columns'    => ['id', 'title'],
        ]);

        $action   = $this->getActionFactory()->factory(SqlSelectAll::class);
        $response = $action->handle($this->getRequest(null, [], ['filterBy' => 'title', 'filterOp' => 'present', 'filterValue' => 'null']), $parameters, $this->getContext());

        $actual = json_encode($response->getBody(), JSON_PRETTY_PRINT);
        $expect = <<<JSON
{
    "totalResults": 3,
    "itemsPerPage": 16,
    "startIndex": 0,
    "entry": [
        {
            "id": 3,
            "title": "bar"
        },
        {
            "id": 2,
            "title": "baz"
        },
        {
            "id": 1,
            "title": "foo"
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
