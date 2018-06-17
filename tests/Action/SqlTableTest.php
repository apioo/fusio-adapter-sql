<?php
/*
 * Fusio
 * A web-application to create dynamically RESTful APIs
 *
 * Copyright (C) 2015-2018 Christoph Kappestein <christoph.kappestein@gmail.com>
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

use Fusio\Adapter\Sql\Action\SqlTable;
use Fusio\Adapter\Sql\Tests\DbTestCase;
use Fusio\Engine\Form\Builder;
use Fusio\Engine\Form\Container;
use PSX\Http\Environment\HttpResponseInterface;
use PSX\Record\Record;

/**
 * SqlTableTest
 *
 * @author  Christoph Kappestein <christoph.kappestein@gmail.com>
 * @license http://www.gnu.org/licenses/agpl-3.0
 * @link    http://fusio-project.org
 */
class SqlTableTest extends DbTestCase
{
    public function testHandleGetCollection()
    {
        $parameters = $this->getParameters([
            'connection' => 1,
            'table'      => 'app_news',
        ]);

        $action   = $this->getActionFactory()->factory(SqlTable::class);
        $response = $action->handle($this->getRequest(), $parameters, $this->getContext());

        $actual = json_encode($response->getBody(), JSON_PRETTY_PRINT);
        $expect = <<<JSON
{
    "totalResults": 2,
    "itemsPerPage": 16,
    "startIndex": 0,
    "entry": [
        {
            "id": "2",
            "title": "bar",
            "content": "foo",
            "tags": "[\"foo\"]",
            "date": "2015-02-27 19:59:15"
        },
        {
            "id": "1",
            "title": "foo",
            "content": "bar",
            "tags": "[\"foo\",\"bar\"]",
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

    public function testHandleGetCollectionLimit()
    {
        $parameters = $this->getParameters([
            'connection' => 1,
            'table'      => 'app_news',
            'limit'      => 1,
        ]);

        $action   = $this->getActionFactory()->factory(SqlTable::class);
        $response = $action->handle($this->getRequest(), $parameters, $this->getContext());

        $actual = json_encode($response->getBody(), JSON_PRETTY_PRINT);
        $expect = <<<JSON
{
    "totalResults": 2,
    "itemsPerPage": 1,
    "startIndex": 0,
    "entry": [
        {
            "id": "2",
            "title": "bar",
            "content": "foo",
            "tags": "[\"foo\"]",
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

    public function testHandleGetEntity()
    {
        $parameters = $this->getParameters([
            'connection' => 1,
            'table'      => 'app_news',
        ]);

        $action   = $this->getActionFactory()->factory(SqlTable::class);
        $response = $action->handle($this->getRequest('GET', ['id' => 1]), $parameters, $this->getContext());

        $actual = json_encode($response->getBody(), JSON_PRETTY_PRINT);
        $expect = <<<JSON
{
    "id": "1",
    "title": "foo",
    "content": "bar",
    "tags": "[\"foo\",\"bar\"]",
    "date": "2015-02-27 19:59:15"
}
JSON;

        $this->assertInstanceOf(HttpResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals([], $response->getHeaders());
        $this->assertJsonStringEqualsJsonString($expect, $actual, $actual);
    }

    public function testHandlePost()
    {
        $parameters = $this->getParameters([
            'connection' => 1,
            'table'      => 'app_news',
        ]);

        $body = new Record();
        $body['title'] = 'lorem';
        $body['content'] = 'ipsum';
        $body['date'] = '2015-02-27 19:59:15';

        $action   = $this->getActionFactory()->factory(SqlTable::class);
        $response = $action->handle($this->getRequest('POST', [], [], [], $body), $parameters, $this->getContext());
        $body     = $response->getBody();

        $result = [
            'success' => true,
            'message' => 'Entry successful created',
            'id'      => 3,
        ];

        $this->assertInstanceOf(HttpResponseInterface::class, $response);
        $this->assertEquals(201, $response->getStatusCode());
        $this->assertEquals([], $response->getHeaders());
        $this->assertEquals($result, $body);

        // check whether the entry was inserted
        $row    = $this->connection->fetchAssoc('SELECT id, title, content, date FROM app_news WHERE id = :id', ['id' => $body['id']]);
        $expect = [
            'id'      => 3,
            'title'   => 'lorem',
            'content' => 'ipsum',
            'date'    => '2015-02-27 19:59:15',
        ];

        $this->assertEquals($expect, $row);
    }

    public function testHandlePut()
    {
        $parameters = $this->getParameters([
            'connection' => 1,
            'table'      => 'app_news',
        ]);

        $body = new Record();
        $body['title'] = 'lorem';
        $body['content'] = 'ipsum';
        $body['date'] = '2015-02-27 19:59:15';

        $action   = $this->getActionFactory()->factory(SqlTable::class);
        $response = $action->handle($this->getRequest('PUT', ['id' => 1], [], [], $body), $parameters, $this->getContext());

        $result = [
            'success' => true,
            'message' => 'Entry successful updated',
        ];

        $this->assertInstanceOf(HttpResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals([], $response->getHeaders());
        $this->assertEquals($result, $response->getBody());

        // check whether the entry was inserted
        $row    = $this->connection->fetchAssoc('SELECT id, title, content, date FROM app_news WHERE id = 1');
        $expect = [
            'id'      => 1,
            'title'   => 'lorem',
            'content' => 'ipsum',
            'date'    => '2015-02-27 19:59:15',
        ];

        $this->assertEquals($expect, $row);
    }

    public function testHandleDelete()
    {
        $parameters = $this->getParameters([
            'connection' => 1,
            'table'      => 'app_news',
        ]);

        $action   = $this->getActionFactory()->factory(SqlTable::class);
        $response = $action->handle($this->getRequest('DELETE', ['id' => 1]), $parameters, $this->getContext());

        $result = [
            'success' => true,
            'message' => 'Entry successful deleted',
        ];

        $this->assertInstanceOf(HttpResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals([], $response->getHeaders());
        $this->assertEquals($result, $response->getBody());

        $row = $this->connection->fetchAssoc('SELECT id, title, content, date FROM app_news WHERE id = 1');

        $this->assertEmpty($row);
    }

    /**
     * @expectedException \PSX\Http\Exception\InternalServerErrorException
     * @expectedExceptionMessage Table foo does not exist on connection
     */
    public function testHandleInvalidTable()
    {
        $parameters = $this->getParameters([
            'connection' => 1,
            'table'      => 'foo',
        ]);

        $action = $this->getActionFactory()->factory(SqlTable::class);
        $action->handle($this->getRequest('GET'), $parameters, $this->getContext());
    }

    /**
     * @expectedException \PSX\Http\Exception\InternalServerErrorException
     * @expectedExceptionMessage Primary column not available
     */
    public function testHandleNoPkTable()
    {
        $parameters = $this->getParameters([
            'connection' => 1,
            'table'      => 'app_invalid',
        ]);

        $action = $this->getActionFactory()->factory(SqlTable::class);
        $action->handle($this->getRequest('GET'), $parameters, $this->getContext());
    }

    /**
     * @expectedException \PSX\Http\Exception\BadRequestException
     * @expectedExceptionMessage No valid data provided
     */
    public function testHandlePostNoData()
    {
        $parameters = $this->getParameters([
            'connection' => 1,
            'table'      => 'app_news',
        ]);

        $action = $this->getActionFactory()->factory(SqlTable::class);
        $action->handle($this->getRequest('POST'), $parameters, $this->getContext());
    }

    /**
     * @expectedException \PSX\Http\Exception\MethodNotAllowedException
     * @expectedExceptionMessage Method not allowed
     */
    public function testHandlePostWithId()
    {
        $parameters = $this->getParameters([
            'connection' => 1,
            'table'      => 'app_news',
        ]);

        $action = $this->getActionFactory()->factory(SqlTable::class);
        $action->handle($this->getRequest('POST', ['id' => 1]), $parameters, $this->getContext());
    }

    /**
     * @expectedException \PSX\Http\Exception\MethodNotAllowedException
     * @expectedExceptionMessage Method not allowed
     */
    public function testHandlePutWithoutId()
    {
        $parameters = $this->getParameters([
            'connection' => 1,
            'table'      => 'app_news',
        ]);

        $action = $this->getActionFactory()->factory(SqlTable::class);
        $action->handle($this->getRequest('PUT'), $parameters, $this->getContext());
    }

    /**
     * @expectedException \PSX\Http\Exception\MethodNotAllowedException
     * @expectedExceptionMessage Method not allowed
     */
    public function testHandleDeleteWithoutId()
    {
        $parameters = $this->getParameters([
            'connection' => 1,
            'table'      => 'app_news',
        ]);

        $action = $this->getActionFactory()->factory(SqlTable::class);
        $action->handle($this->getRequest('DELETE'), $parameters, $this->getContext());
    }

    public function testGetForm()
    {
        $action  = $this->getActionFactory()->factory(SqlTable::class);
        $builder = new Builder();
        $factory = $this->getFormElementFactory();

        $action->configure($builder, $factory);

        $this->assertInstanceOf(Container::class, $builder->getForm());
    }
}
