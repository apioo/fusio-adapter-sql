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

namespace Fusio\Adapter\Sql\Tests\Action\Query;

use Fusio\Adapter\Sql\Action\Query\SqlQueryAll;
use Fusio\Adapter\Sql\Tests\SqlTestCase;
use PSX\Http\Environment\HttpResponseInterface;

/**
 * SqlSelectAllTest
 *
 * @author  Christoph Kappestein <christoph.kappestein@gmail.com>
 * @license http://www.apache.org/licenses/LICENSE-2.0
 * @link    https://www.fusio-project.org/
 */
class SqlSelectAllTest extends SqlTestCase
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

        if (PHP_VERSION_ID < 80100) {
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
        } else {
            $expect = <<<JSON
{
    "totalResults": 3,
    "itemsPerPage": 16,
    "startIndex": 0,
    "entry": [
        {
            "id": 1,
            "title": "foo",
            "price": 39.99,
            "content": "bar",
            "date": "2015-02-27 19:59:15"
        },
        {
            "id": 2,
            "title": "baz",
            "price": null,
            "content": null,
            "date": null
        },
        {
            "id": 3,
            "title": "bar",
            "price": 29.99,
            "content": "foo",
            "date": "2015-02-27 19:59:15"
        }
    ]
}
JSON;
        }

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

        if (PHP_VERSION_ID < 80100) {
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
        } else {
            $expect = <<<JSON
{
    "totalResults": 1,
    "itemsPerPage": 16,
    "startIndex": 0,
    "entry": [
        {
            "id": 1,
            "title": "foo",
            "price": 39.99,
            "content": "bar",
            "date": "2015-02-27 19:59:15"
        }
    ]
}
JSON;
        }

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

        if (PHP_VERSION_ID < 80100) {
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
        } else {
            $expect = <<<JSON
{
    "totalResults": 3,
    "itemsPerPage": 1,
    "startIndex": 1,
    "entry": [
        {
            "id": 2,
            "title": "baz",
            "price": null,
            "content": null,
            "date": null
        }
    ]
}
JSON;
        }

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

        if (PHP_VERSION_ID < 80100) {
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
        } else {
            $expect = <<<JSON
{
    "totalResults": 3,
    "itemsPerPage": 2,
    "startIndex": 0,
    "entry": [
        {
            "id": 1,
            "title": "foo",
            "price": 39.99,
            "content": "bar",
            "date": "2015-02-27 19:59:15"
        },
        {
            "id": 2,
            "title": "baz",
            "price": null,
            "content": null,
            "date": null
        }
    ]
}
JSON;
        }

        $this->assertInstanceOf(HttpResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals([], $response->getHeaders());
        $this->assertJsonStringEqualsJsonString($expect, $actual, $actual);
    }


    public function testHandleUser()
    {
        $parameters = $this->getParameters([
            'connection' => 1,
            'sql'        => 'SELECT id, title, price, content, date FROM app_news WHERE id = {user_id}',
        ]);

        $action   = $this->getActionFactory()->factory(SqlQueryAll::class);
        $response = $action->handle($this->getRequest(), $parameters, $this->getContext());

        $actual = json_encode($response->getBody(), JSON_PRETTY_PRINT);
        $expect = <<<JSON
{
    "totalResults": 1,
    "itemsPerPage": 16,
    "startIndex": 0,
    "entry": [
        {
            "id": 2,
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
