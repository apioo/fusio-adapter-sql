<?php
/*
 * Fusio
 * A web-application to create dynamically RESTful APIs
 *
 * Copyright (C) 2015-2019 Christoph Kappestein <christoph.kappestein@gmail.com>
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

namespace Fusio\Adapter\Sql\Tests;

use Fusio\Engine\Model\Connection;
use Fusio\Engine\Test\CallbackConnection;
use Fusio\Engine\Test\EngineTestCaseTrait;
use PHPUnit\Framework\TestCase;
use PSX\Sql\Test\DatabaseTestCaseTrait;

/**
 * DbTestCase
 *
 * @author  Christoph Kappestein <christoph.kappestein@gmail.com>
 * @license http://www.gnu.org/licenses/agpl-3.0
 * @link    http://fusio-project.org
 */
class DbTestCase extends TestCase
{
    use EngineTestCaseTrait;
    use DatabaseTestCaseTrait;

    protected function setUp()
    {
        parent::setUp();

        $this->setUpFixture();

        $connection = new Connection();
        $connection->setId(1);
        $connection->setName('foo');
        $connection->setClass(CallbackConnection::class);
        $connection->setConfig([
            'callback' => function(){
                return $this->connection;
            },
        ]);

        $this->getConnectionRepository()->add($connection);
    }

    protected function getConnection()
    {
        return getConnection();
    }

    protected function getDataSet()
    {
        return include __DIR__ . '/fixture.php';
    }
}
