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

use Fusio\Adapter\Sql\Action\SqlDelete;
use Fusio\Adapter\Sql\Tests\SqlTestCase;
use PSX\Http\Environment\HttpResponseInterface;

/**
 * SqlDeleteTest
 *
 * @author  Christoph Kappestein <christoph.kappestein@gmail.com>
 * @license http://www.apache.org/licenses/LICENSE-2.0
 * @link    https://www.fusio-project.org/
 */
class SqlDeleteTest extends SqlTestCase
{
    public function testHandle(): void
    {
        $parameters = $this->getParameters([
            'connection' => 1,
            'table'      => 'app_news',
        ]);

        $action   = $this->getActionFactory()->factory(SqlDelete::class);
        $response = $action->handle($this->getRequest('DELETE', ['id' => 1]), $parameters, $this->getContext());
        $data     = $response->getBody();

        $this->assertInstanceOf(HttpResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals([], $response->getHeaders());

        $this->assertArrayHasKey('success', $data);
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('message', $data);
        $this->assertEquals('Entry successfully deleted', $data['message']);
        $this->assertArrayHasKey('id', $data);
        $this->assertNotEmpty($data['id']);

        $row = $this->connection->fetchAssociative('SELECT id, title, content, date FROM app_news WHERE id = :id', ['id' => $data['id']]);

        $this->assertEmpty($row);
    }

    public function testHandleUuid(): void
    {
        $parameters = $this->getParameters([
            'connection' => 1,
            'table'      => 'app_news_uuid',
        ]);

        $action   = $this->getActionFactory()->factory(SqlDelete::class);
        $response = $action->handle($this->getRequest('DELETE', ['id' => 'b45412cb-8c50-44b8-889f-f0e78e8296ad']), $parameters, $this->getContext());
        $data     = $response->getBody();

        $this->assertInstanceOf(HttpResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals([], $response->getHeaders());

        $this->assertArrayHasKey('success', $data);
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('message', $data);
        $this->assertEquals('Entry successfully deleted', $data['message']);
        $this->assertArrayHasKey('id', $data);
        $this->assertNotEmpty($data['id']);

        $row = $this->connection->fetchAssociative('SELECT id, title, content, date FROM app_news_uuid WHERE id = :id', ['id' => $data['id']]);

        $this->assertEmpty($row);
    }
}
