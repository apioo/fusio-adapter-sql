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

namespace Fusio\Adapter\Sql\Tests\Action\Query;

use Fusio\Adapter\Sql\Action\Query\SqlQueryRow;
use Fusio\Adapter\Sql\Tests\SqlTestCase;
use PSX\Http\Environment\HttpResponseInterface;

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
            'sql'        => 'SELECT id, title, price, content, date FROM app_news WHERE id = {id}',
        ]);

        $action   = $this->getActionFactory()->factory(SqlQueryRow::class);
        $response = $action->handle($this->getRequest('GET', ['id' => 1]), $parameters, $this->getContext());

        $actual = json_encode($response->getBody(), JSON_PRETTY_PRINT);

        if (PHP_VERSION_ID < 80100) {
            $expect = <<<JSON
{
    "id": "1",
    "title": "foo",
    "price": "39.99",
    "content": "bar",
    "date": "2015-02-27 19:59:15"
}
JSON;
        } else {
            $expect = <<<JSON
{
    "id": 1,
    "title": "foo",
    "price": 39.99,
    "content": "bar",
    "date": "2015-02-27 19:59:15"
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

        $action   = $this->getActionFactory()->factory(SqlQueryRow::class);
        $response = $action->handle($this->getRequest('GET', ['id' => 1]), $parameters, $this->getContext());

        $actual = json_encode($response->getBody(), JSON_PRETTY_PRINT);
        $expect = <<<JSON
{
    "id": 2,
    "title": "baz",
    "price": null,
    "content": null,
    "date": null
}
JSON;

        $this->assertInstanceOf(HttpResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals([], $response->getHeaders());
        $this->assertJsonStringEqualsJsonString($expect, $actual, $actual);
    }
}
