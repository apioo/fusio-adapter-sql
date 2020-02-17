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

use Fusio\Adapter\Sql\Tests\DbTestCase;
use PSX\Http\Environment\HttpResponseInterface;

/**
 * SqlBuilderTest
 *
 * @author  Christoph Kappestein <christoph.kappestein@gmail.com>
 * @license http://www.gnu.org/licenses/agpl-3.0
 * @link    http://fusio-project.org
 */
class SqlBuilderTest extends DbTestCase
{
    public function testHandleGet()
    {
        $action   = $this->getActionFactory()->factory(BuilderTest::class);
        $response = $action->handle($this->getRequest(), $this->getParameters([]), $this->getContext());

        $actual = json_encode($response->getBody(), JSON_PRETTY_PRINT);
        $expect = <<<JSON
{
    "totalEntries": 3,
    "startIndex": 0,
    "entries": [
        {
            "id": 3,
            "price": 29.99,
            "articleNumber": "bar",
            "description": "foo",
            "postedAt": "13:37:00",
            "insertDate": "2015-02-27T19:59:15Z",
            "links": {
                "self": "\/news\/3"
            }
        },
        {
            "id": 2,
            "articleNumber": "baz",
            "links": {
                "self": "\/news\/2"
            }
        },
        {
            "id": 1,
            "price": 39.99,
            "articleNumber": "foo",
            "description": "bar",
            "postedAt": "13:37:00",
            "insertDate": "2015-02-27T19:59:15Z",
            "links": {
                "self": "\/news\/1"
            }
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
