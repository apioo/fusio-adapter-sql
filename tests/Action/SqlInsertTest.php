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

use Fusio\Adapter\Sql\Action\SqlInsert;
use Fusio\Adapter\Sql\Tests\SqlTestCase;
use PSX\Http\Environment\HttpResponseInterface;
use PSX\Http\Exception\BadRequestException;
use PSX\Record\Record;

/**
 * SqlInsertTest
 *
 * @author  Christoph Kappestein <christoph.kappestein@gmail.com>
 * @license http://www.apache.org/licenses/LICENSE-2.0
 * @link    https://www.fusio-project.org/
 */
class SqlInsertTest extends SqlTestCase
{
    public function testHandle()
    {
        $parameters = $this->getParameters([
            'connection' => 1,
            'table'      => 'app_news',
        ]);

        $body = new Record();
        $body['title'] = 'lorem';
        $body['price'] = 59.99;
        $body['content'] = 'ipsum';
        $body['image'] = str_repeat("\0", 16);
        $body['posted'] = '19:59:15';
        $body['date'] = '2015-02-27 19:59:15';

        $action   = $this->getActionFactory()->factory(SqlInsert::class);
        $response = $action->handle($this->getRequest('POST', [], [], [], $body), $parameters, $this->getContext());
        $data     = $response->getBody();

        $this->assertInstanceOf(HttpResponseInterface::class, $response);
        $this->assertEquals(201, $response->getStatusCode());
        $this->assertEquals([], $response->getHeaders());

        $this->assertArrayHasKey('success', $data);
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('message', $data);
        $this->assertEquals('Entry successfully created', $data['message']);
        $this->assertArrayHasKey('id', $data);
        $this->assertNotEmpty($data['id']);

        // check whether the entry was inserted
        $actual = $this->connection->fetchAssociative('SELECT id, title, price, content, image, posted, date FROM app_news WHERE id = :id', ['id' => $data['id']]);
        $expect = [
            'id'      => $data['id'],
            'title'   => 'lorem',
            'price'   => '59.99',
            'content' => 'ipsum',
            'image'   => str_repeat("\0", 16),
            'posted'  => '19:59:15',
            'date'    => '2015-02-27 19:59:15',
        ];

        $this->assertEquals($expect, $actual);
    }

    public function testHandleUuid()
    {
        $parameters = $this->getParameters([
            'connection' => 1,
            'table'      => 'app_news_uuid',
        ]);

        $body = new Record();
        $body['title'] = 'lorem';
        $body['price'] = 59.99;
        $body['content'] = 'ipsum';
        $body['image'] = str_repeat("\0", 16);
        $body['posted'] = '19:59:15';
        $body['date'] = '2015-02-27 19:59:15';

        $action   = $this->getActionFactory()->factory(SqlInsert::class);
        $response = $action->handle($this->getRequest('POST', [], [], [], $body), $parameters, $this->getContext());
        $data     = $response->getBody();

        $this->assertInstanceOf(HttpResponseInterface::class, $response);
        $this->assertEquals(201, $response->getStatusCode());
        $this->assertEquals([], $response->getHeaders());

        $this->assertArrayHasKey('success', $data);
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('message', $data);
        $this->assertEquals('Entry successfully created', $data['message']);
        $this->assertArrayHasKey('id', $data);
        $this->assertNotEmpty($data['id']);

        // check whether the entry was inserted
        $actual = $this->connection->fetchAssociative('SELECT id, title, price, content, image, posted, date FROM app_news_uuid WHERE id = :id', ['id' => $data['id']]);
        $expect = [
            'id'      => $data['id'],
            'title'   => 'lorem',
            'price'   => '59.99',
            'content' => 'ipsum',
            'image'   => str_repeat("\0", 16),
            'posted'  => '19:59:15',
            'date'    => '2015-02-27 19:59:15',
        ];

        $this->assertEquals($expect, $actual);
    }
    public function testHandleColumnTypes()
    {
        $parameters = $this->getParameters([
            'connection' => 1,
            'table'      => 'app_column_test',
        ]);

        $body = new Record();
        $body['col_bigint'] = 68719476735;
        $body['col_binary'] = 'foo';
        $body['col_blob'] = 'foobar';
        $body['col_boolean'] = 1;
        $body['col_datetime'] = '2015-01-21 23:59:59';
        $body['col_datetimetz'] = '2015-01-21 23:59:59';
        $body['col_date'] = '2015-01-21';
        $body['col_decimal'] = 10;
        $body['col_float'] = 10.37;
        $body['col_integer'] = 2147483647;
        $body['col_smallint'] = 255;
        $body['col_text'] = 'foobar';
        $body['col_time'] = '23:59:59';
        $body['col_string'] = 'foobar';
        $body['col_json'] = '{"foo":"bar"}';
        $body['col_guid'] = 'ebe865da-4982-4353-bc44-dcdf7239e386';

        $action   = $this->getActionFactory()->factory(SqlInsert::class);
        $response = $action->handle($this->getRequest('POST', [], [], [], $body), $parameters, $this->getContext());
        $data     = $response->getBody();

        $this->assertInstanceOf(HttpResponseInterface::class, $response);
        $this->assertEquals(201, $response->getStatusCode());
        $this->assertEquals([], $response->getHeaders());

        $this->assertArrayHasKey('success', $data);
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('message', $data);
        $this->assertEquals('Entry successfully created', $data['message']);
        $this->assertArrayHasKey('id', $data);
        $this->assertNotEmpty($data['id']);

        // check whether the entry was inserted
        $actual = $this->connection->fetchAssociative('SELECT * FROM app_column_test WHERE id = :id', ['id' => $data['id']]);
        $expect = [
            'id' => $data['id'],
            'col_bigint' => 68719476735,
            'col_binary' => 'foo',
            'col_blob' => 'foobar',
            'col_boolean' => 1,
            'col_datetime' => '2015-01-21 23:59:59',
            'col_datetimetz' => '2015-01-21 23:59:59',
            'col_date' => '2015-01-21',
            'col_decimal' => 10,
            'col_float' => 10.37,
            'col_integer' => 2147483647,
            'col_smallint' => 255,
            'col_text' => 'foobar',
            'col_time' => '23:59:59',
            'col_string' => 'foobar',
            'col_json' => '{"foo":"bar"}',
            'col_guid' => 'ebe865da-4982-4353-bc44-dcdf7239e386'
        ];

        $this->assertEquals($expect, $actual);
    }

    public function testHandleNoData()
    {
        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('Property title must not be null');

        $parameters = $this->getParameters([
            'connection' => 1,
            'table'      => 'app_news',
        ]);

        $action = $this->getActionFactory()->factory(SqlInsert::class);
        $action->handle($this->getRequest('POST'), $parameters, $this->getContext());
    }

    public function testHandleDefaultsOnInsert()
    {
        $parameters = $this->getParameters([
            'connection' => 1,
            'table'      => 'app_insert',
        ]);

        $body = new Record();
        $body['title'] = 'lorem';

        $action = $this->getActionFactory()->factory(SqlInsert::class);
        $action->handle($this->getRequest('POST', [], [], [], $body), $parameters, $this->getContext());

        // check whether the entry was inserted
        $row = $this->connection->fetchAssociative('SELECT * FROM app_insert WHERE id = :id', ['id' => 1]);
        
        $this->assertEquals(1, $row['id']);
        $this->assertEquals('Test content', $row['content']);
        $this->assertEquals(999, $row['counter']);
        $this->assertNotNull($row['created_at']);
    }
}
