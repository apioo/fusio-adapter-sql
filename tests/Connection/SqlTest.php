<?php
/*
 * Fusio
 * A web-application to create dynamically RESTful APIs
 *
 * Copyright (C) 2015-2022 Christoph Kappestein <christoph.kappestein@gmail.com>
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

namespace Fusio\Adapter\Sql\Tests\Connection;

use Doctrine\DBAL\Connection;
use Fusio\Adapter\Sql\Connection\Sql;
use Fusio\Engine\Form\Builder;
use Fusio\Engine\Form\Container;
use Fusio\Engine\Form\Element\Input;
use Fusio\Engine\Form\Element\Select;
use Fusio\Engine\Parameters;
use Fusio\Engine\Test\EngineTestCaseTrait;
use PHPUnit\Framework\TestCase;

/**
 * SqlTest
 *
 * @author  Christoph Kappestein <christoph.kappestein@gmail.com>
 * @license http://www.gnu.org/licenses/agpl-3.0
 * @link    https://www.fusio-project.org/
 */
class SqlTest extends TestCase
{
    use EngineTestCaseTrait;

    public function testGetConnection()
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

    public function testPing()
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
