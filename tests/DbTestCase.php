<?php
/*
 * Fusio
 * A web-application to create dynamically RESTful APIs
 *
 * Copyright (C) 2015-2016 Christoph Kappestein <christoph.kappestein@gmail.com>
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

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\DriverManager;
use Fusio\Engine\Model\Connection;
use Fusio\Engine\Test\CallbackConnection;
use Fusio\Engine\Test\EngineTestCaseTrait;

/**
 * DbTestCase
 *
 * @author  Christoph Kappestein <christoph.kappestein@gmail.com>
 * @license http://www.gnu.org/licenses/agpl-3.0
 * @link    http://fusio-project.org
 */
class DbTestCase extends \PHPUnit_Extensions_Database_TestCase
{
    use EngineTestCaseTrait;

    protected static $con;

    /**
     * @var \Doctrine\DBAL\Connection
     */
    protected $connection;

    protected function setUp()
    {
        parent::setUp();

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
        if (self::$con === null) {
            self::$con = $this->newConnection();
        }

        if ($this->connection === null) {
            $this->connection = self::$con;
        }

        return $this->createDefaultDBConnection($this->connection->getWrappedConnection(), 'database');
    }

    protected function getDataSet()
    {
        return $this->createFlatXMLDataSet(__DIR__ . '/fixture.xml');
    }

    protected function newConnection()
    {
        $params = [
            'memory' => true,
            'driver' => 'pdo_sqlite',
        ];

        $config     = new Configuration();
        $connection = DriverManager::getConnection($params, $config);

        $fromSchema = $connection->getSchemaManager()->createSchema();
        $toSchema   = new \Doctrine\DBAL\Schema\Schema();

        $table = $toSchema->createTable('app_news');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->addColumn('title', 'string');
        $table->addColumn('content', 'text');
        $table->addColumn('tags', 'text', ['notnull' => false]);
        $table->addColumn('date', 'datetime');
        $table->setPrimaryKey(['id']);

        $queries = $fromSchema->getMigrateToSql($toSchema, $connection->getDatabasePlatform());
        foreach ($queries as $query) {
            $connection->query($query);
        }

        return $connection;
    }
}
