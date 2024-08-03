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

namespace Fusio\Adapter\Sql\Tests\Action;

use Fusio\Adapter\Sql\Action\SqlSelectRow;
use Fusio\Adapter\Sql\Tests\SqlTestCase;
use PSX\Http\Environment\HttpResponseInterface;
use PSX\Http\Exception\NotFoundException;

/**
 * SqlSelectRowTest
 *
 * @author  Christoph Kappestein <christoph.kappestein@gmail.com>
 * @license http://www.apache.org/licenses/LICENSE-2.0
 * @link    https://www.fusio-project.org/
 */
class SqlSelectRowTest extends SqlTestCase
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

    public function testHandleUuid()
    {
        $parameters = $this->getParameters([
            'connection' => 1,
            'table'      => 'app_news_uuid',
        ]);

        $action   = $this->getActionFactory()->factory(SqlSelectRow::class);
        $response = $action->handle($this->getRequest('GET', ['id' => 'b45412cb-8c50-44b8-889f-f0e78e8296ad']), $parameters, $this->getContext());

        $actual = json_encode($response->getBody(), JSON_PRETTY_PRINT);

        $expect = <<<JSON
{
    "id": "b45412cb-8c50-44b8-889f-f0e78e8296ad",
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
    public function testHandleColumnTypes()
    {
        $parameters = $this->getParameters([
            'connection' => 1,
            'table'      => 'app_column_test',
        ]);

        $action   = $this->getActionFactory()->factory(SqlSelectRow::class);
        $response = $action->handle($this->getRequest('GET', ['id' => 1]), $parameters, $this->getContext());

        $actual = json_encode($response->getBody(), JSON_PRETTY_PRINT);

        $expect = <<<JSON
{
    "id": 1,
    "col_bigint": "68719476735",
    "col_binary": "Zm9v",
    "col_blob": "Zm9vYmFy",
    "col_boolean": true,
    "col_datetime": "2015-01-21T23:59:59+00:00",
    "col_datetimetz": "2015-01-21T23:59:59+00:00",
    "col_date": "2015-01-21",
    "col_decimal": "10",
    "col_float": 10.37,
    "col_integer": 2147483647,
    "col_smallint": 255,
    "col_text": "foobar",
    "col_time": "23:59:59",
    "col_string": "foobar",
    "col_json": {
        "foo": "bar"
    },
    "col_guid": "ebe865da-4982-4353-bc44-dcdf7239e386"
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
