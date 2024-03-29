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

use Fusio\Adapter\Sql\Action\SqlUpdate;
use Fusio\Adapter\Sql\Tests\SqlTestCase;
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

        $result = [
            'success'  => true,
            'message'  => 'Entry successfully updated',
            'id'       => 1,
            'affected' => 1,
        ];

        $this->assertInstanceOf(HttpResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals([], $response->getHeaders());
        $this->assertEquals($result, $response->getBody());

        // check whether the entry was inserted
        $row    = $this->connection->fetchAssociative('SELECT id, title, content, date FROM app_news WHERE id = 1');
        $expect = [
            'id'      => 1,
            'title'   => 'lorem',
            'content' => 'ipsum',
            'date'    => '2015-02-27 19:59:15',
        ];

        $this->assertEquals($expect, $row);
    }
}
