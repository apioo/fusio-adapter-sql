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

use Fusio\Adapter\Sql\Action\SqlUpdate;
use Fusio\Adapter\Sql\Tests\SqlTestCase;
use PSX\DateTime\LocalDate;
use PSX\DateTime\LocalDateTime;
use PSX\DateTime\LocalTime;
use PSX\Http\Environment\HttpResponseInterface;
use PSX\Record\Record;

/**
 * SqlUpdateTest
 *
 * @author  Christoph Kappestein <christoph.kappestein@gmail.com>
 * @license http://www.apache.org/licenses/LICENSE-2.0
 * @link    https://www.fusio-project.org/
 */
class SqlUpdateTest extends SqlTestCase
{
    public function testHandle()
    {
        $parameters = $this->getParameters([
            'connection' => 1,
            'table'      => 'app_news',
        ]);

        $body = new Record();
        $body['title'] = 'lorem';
        $body['content'] = 'ipsum';
        $body['date'] = '2015-02-27 19:59:15';

        $action   = $this->getActionFactory()->factory(SqlUpdate::class);
        $response = $action->handle($this->getRequest('PUT', ['id' => 1], [], [], $body), $parameters, $this->getContext());
        $data     = $response->getBody();

        $this->assertInstanceOf(HttpResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals([], $response->getHeaders());

        $this->assertArrayHasKey('success', $data);
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('message', $data);
        $this->assertEquals('Entry successfully updated', $data['message']);
        $this->assertArrayHasKey('id', $data);
        $this->assertNotEmpty($data['id']);

        // check whether the entry was updated
        $row    = $this->connection->fetchAssociative('SELECT id, title, content, date FROM app_news WHERE id = :id', ['id' => $data['id']]);
        $expect = [
            'id'      => $data['id'],
            'title'   => 'lorem',
            'content' => 'ipsum',
            'date'    => '2015-02-27 19:59:15',
        ];

        $this->assertEquals($expect, $row);
    }

    public function testHandleUuid()
    {
        $parameters = $this->getParameters([
            'connection' => 1,
            'table'      => 'app_news_uuid',
        ]);

        $body = new Record();
        $body['title'] = 'lorem';
        $body['content'] = 'ipsum';
        $body['date'] = '2015-02-27 19:59:15';

        $action   = $this->getActionFactory()->factory(SqlUpdate::class);
        $response = $action->handle($this->getRequest('PUT', ['id' => 'b45412cb-8c50-44b8-889f-f0e78e8296ad'], [], [], $body), $parameters, $this->getContext());
        $data     = $response->getBody();

        $this->assertInstanceOf(HttpResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals([], $response->getHeaders());

        $this->assertArrayHasKey('success', $data);
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('message', $data);
        $this->assertEquals('Entry successfully updated', $data['message']);
        $this->assertArrayHasKey('id', $data);
        $this->assertNotEmpty($data['id']);

        // check whether the entry was updated
        $row    = $this->connection->fetchAssociative('SELECT id, title, content, date FROM app_news_uuid WHERE id = :id', ['id' => $data['id']]);
        $expect = [
            'id'      => $data['id'],
            'title'   => 'lorem',
            'content' => 'ipsum',
            'date'    => '2015-02-27 19:59:15',
        ];

        $this->assertEquals($expect, $row);
    }

    public function testHandleColumnTest()
    {
        $parameters = $this->getParameters([
            'connection' => 1,
            'table'      => 'app_column_test',
        ]);

        $blob = \fopen('php://memory', 'a+');
        fwrite($blob, 'foobar');
        fseek($blob, 0);

        $body = new Record();
        $body['col_bigint'] = 68719476735;
        $body['col_binary'] = 'foo';
        $body['col_blob'] = $blob;
        $body['col_boolean'] = 1;
        $body['col_datetime'] = LocalDateTime::parse('2015-01-21 23:59:59');
        $body['col_datetimetz'] = LocalDateTime::parse('2015-01-21 23:59:59');
        $body['col_date'] = LocalDate::parse('2015-01-21');
        $body['col_decimal'] = 10;
        $body['col_float'] = 10.37;
        $body['col_integer'] = 2147483647;
        $body['col_smallint'] = 255;
        $body['col_text'] = 'foobar';
        $body['col_time'] = LocalTime::parse('23:59:59');
        $body['col_string'] = 'foobar';
        $body['col_json'] = (object) ['foo' => 'bar'];
        $body['col_guid'] = 'ebe865da-4982-4353-bc44-dcdf7239e386';

        $action   = $this->getActionFactory()->factory(SqlUpdate::class);
        $response = $action->handle($this->getRequest('PUT', ['id' => 1], [], [], $body), $parameters, $this->getContext());
        $data     = $response->getBody();

        $this->assertInstanceOf(HttpResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals([], $response->getHeaders());

        $this->assertArrayHasKey('success', $data);
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('message', $data);
        $this->assertEquals('Entry successfully updated', $data['message']);
        $this->assertArrayHasKey('id', $data);
        $this->assertNotEmpty($data['id']);

        // check whether the entry was updated
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
}
