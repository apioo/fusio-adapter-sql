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

namespace Fusio\Adapter\Sql\Tests\Connection;

use Doctrine\DBAL\Connection;
use Fusio\Adapter\Sql\Connection\SqlAdvanced;
use Fusio\Adapter\Sql\Tests\SqlTestCase;
use Fusio\Engine\Parameters;

/**
 * SqlAdvancedTest
 *
 * @author  Christoph Kappestein <christoph.kappestein@gmail.com>
 * @license http://www.apache.org/licenses/LICENSE-2.0
 * @link    https://www.fusio-project.org/
 */
class SqlAdvancedTest extends SqlTestCase
{
    public function testGetConnection()
    {
        /** @var SqlAdvanced $connectionFactory */
        $connectionFactory = $this->getConnectionFactory()->factory(SqlAdvanced::class);

        $config = new Parameters([
            'url' => 'sqlite:///:memory:',
        ]);

        $connection = $connectionFactory->getConnection($config);

        $this->assertInstanceOf(Connection::class, $connection);
    }

    public function testPing()
    {
        /** @var SqlAdvanced $connectionFactory */
        $connectionFactory = $this->getConnectionFactory()->factory(SqlAdvanced::class);

        $config = new Parameters([
            'url' => 'sqlite:///:memory:',
        ]);

        $connection = $connectionFactory->getConnection($config);

        $this->assertTrue($connectionFactory->ping($connection));
    }
}
