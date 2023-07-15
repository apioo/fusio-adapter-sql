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
    public function testHandlePost()
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
        $body     = $response->getBody();

        $result = [
            'success'  => true,
            'message'  => 'Entry successfully created',
            'id'       => 4,
            'affected' => 1,
        ];

        $this->assertInstanceOf(HttpResponseInterface::class, $response);
        $this->assertEquals(201, $response->getStatusCode());
        $this->assertEquals([], $response->getHeaders());
        $this->assertEquals($result, $body);

        // check whether the entry was inserted
        $row    = $this->connection->fetchAssociative('SELECT id, title, price, content, image, posted, date FROM app_news WHERE id = :id', ['id' => $body['id']]);
        $expect = [
            'id'      => 4,
            'title'   => 'lorem',
            'price'   => '59.99',
            'content' => 'ipsum',
            'image'   => str_repeat("\0", 16),
            'posted'  => '19:59:15',
            'date'    => '2015-02-27 19:59:15',
        ];

        $this->assertEquals($expect, $row);
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
        $row    = $this->connection->fetchAssociative('SELECT * FROM app_insert WHERE id = :id', ['id' => 1]);
        
        $this->assertEquals(1, $row['id']);
        $this->assertEquals('Test content', $row['content']);
        $this->assertEquals(999, $row['counter']);
        $this->assertNotNull($row['created_at']);
    }

}
