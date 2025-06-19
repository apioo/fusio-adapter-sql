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

namespace Fusio\Adapter\Sql\Tests\Action;

use Fusio\Adapter\Sql\Action\SqlBuilder;
use Fusio\Adapter\Sql\Tests\SqlTestCase;
use PSX\Http\Environment\HttpResponseInterface;

/**
 * SqlBuilderTest
 *
 * @author  Christoph Kappestein <christoph.kappestein@gmail.com>
 * @license http://www.apache.org/licenses/LICENSE-2.0
 * @link    https://www.fusio-project.org/
 */
class SqlBuilderTest extends SqlTestCase
{
    public function testHandleGet()
    {
        $jql = <<<'JSON'
{
    "count": {
        "$value": "SELECT COUNT(*) AS cnt FROM app_news",
        "$definition": {
            "$key": "cnt",
            "$field": "integer"
        }
    },
    "result": {
        "$collection": "SELECT * FROM app_news",
        "$definition": {
            "id": {
                "$key": "id",
                "$field": "integer"
            },
            "title": "title"
        }
    }
}
JSON;

        $parameters = $this->getParameters([
            'connection' => 1,
            'jql'        => $jql
        ]);

        $action   = $this->getActionFactory()->factory(SqlBuilder::class);
        $response = $action->handle($this->getRequest(), $parameters, $this->getContext());

        $actual = json_encode($response->getBody(), JSON_PRETTY_PRINT);
        $expect = <<<JSON
{
    "count": 3,
    "result": [
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
}
