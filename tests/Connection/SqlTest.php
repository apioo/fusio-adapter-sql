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

namespace Fusio\Adapter\Sql\Tests\Connection;

use Doctrine\DBAL\Connection;
use Fusio\Adapter\Sql\Connection\Sql;
use Fusio\Adapter\Sql\Tests\SqlTestCase;
use Fusio\Engine\Parameters;

/**
 * SqlTest
 *
 * @author  Christoph Kappestein <christoph.kappestein@gmail.com>
 * @license http://www.apache.org/licenses/LICENSE-2.0
 * @link    https://www.fusio-project.org/
 */
class SqlTest extends SqlTestCase
{
    public function testGetConnection(): void
    {
        /** @var Sql $connectionFactory */
        $connectionFactory = $this->getConnectionFactory()->factory(Sql::class);

        $config = new Parameters([
            'type'     => 'pdo_mysql',
            'host'     => 'localhost',
            'username' => 'root',
            'password' => 'test1234',
            'database' => 'app',
        ]);

        $connection = $connectionFactory->getConnection($config);

        $this->assertInstanceOf(Connection::class, $connection);
    }

    public function testPing(): void
    {
        /** @var Sql $connectionFactory */
        $connectionFactory = $this->getConnectionFactory()->factory(Sql::class);

        $config = new Parameters([
            'type'     => 'pdo_mysql',
            'host'     => 'localhost',
            'username' => 'root',
            'password' => 'test1234',
            'database' => 'app',
        ]);

        $connection = $connectionFactory->getConnection($config);

        $this->assertTrue($connectionFactory->ping($connection));
    }
}
