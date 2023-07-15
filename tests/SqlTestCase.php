<?php
/*
 * Fusio
 * A web-application to create dynamically RESTful APIs
 *
 * Copyright (C) 2015-2023 Christoph Kappestein <christoph.kappestein@gmail.com>
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

use Doctrine\DBAL\Connection as DoctrineConnection;
use Fusio\Adapter\Sql\Action\Query\SqlQueryAll;
use Fusio\Adapter\Sql\Action\Query\SqlQueryRow;
use Fusio\Adapter\Sql\Action\SqlBuilder;
use Fusio\Adapter\Sql\Action\SqlDelete;
use Fusio\Adapter\Sql\Action\SqlInsert;
use Fusio\Adapter\Sql\Action\SqlSelectAll;
use Fusio\Adapter\Sql\Action\SqlSelectRow;
use Fusio\Adapter\Sql\Action\SqlUpdate;
use Fusio\Adapter\Sql\Adapter;
use Fusio\Adapter\Sql\Connection\Sql;
use Fusio\Adapter\Sql\Connection\SqlAdvanced;
use Fusio\Adapter\Sql\Generator\SqlDatabase;
use Fusio\Adapter\Sql\Generator\SqlEntity;
use Fusio\Adapter\Sql\Generator\SqlTable;
use Fusio\Engine\Action\Runtime;
use Fusio\Engine\ConnectorInterface;
use Fusio\Engine\Model\Connection;
use Fusio\Engine\Parameters;
use Fusio\Engine\Test\CallbackConnection;
use Fusio\Engine\Test\EngineTestCaseTrait;
use PHPUnit\Framework\TestCase;
use PSX\Sql\Test\DatabaseTestCaseTrait;
use Symfony\Component\DependencyInjection\Container;

/**
 * SqlTestCase
 *
 * @author  Christoph Kappestein <christoph.kappestein@gmail.com>
 * @license http://www.gnu.org/licenses/agpl-3.0
 * @link    https://www.fusio-project.org/
 */
class SqlTestCase extends TestCase
{
    use EngineTestCaseTrait;
    use DatabaseTestCaseTrait;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpFixture();

        $connection = new Connection(1, 'foo', CallbackConnection::class, [
            'callback' => function(){
                return $this->connection;
            },
        ]);

        $this->getConnectionRepository()->add($connection);
    }

    protected function getConnection(): DoctrineConnection
    {
        if (!isset($this->connection)) {
            $this->connection = $this->newConnection();
        }

        return $this->connection;
    }

    protected function getDataSet(): array
    {
        return include __DIR__ . '/fixture.php';
    }

    protected function newConnection(): DoctrineConnection
    {
        $connector = new SqlAdvanced();

        try {
            $connection = $connector->getConnection(new Parameters([
                'url' => 'pdo-sqlite:///:memory:',
            ]));

            $schema = $connection->createSchemaManager()->introspectSchema();

            $table = $schema->createTable('app_news');
            $table->addColumn('id', 'integer', ['autoincrement' => true]);
            $table->addColumn('title', 'string');
            $table->addColumn('price', 'float', ['notnull' => false]);
            $table->addColumn('content', 'text', ['notnull' => false]);
            $table->addColumn('image', 'binary', ['notnull' => false]);
            $table->addColumn('posted', 'time', ['notnull' => false]);
            $table->addColumn('date', 'datetime', ['notnull' => false]);
            $table->setPrimaryKey(['id']);

            $table = $schema->createTable('app_invalid');
            $table->addColumn('id', 'integer');
            $table->addColumn('title', 'string');

            $table = $schema->createTable('app_insert');
            $table->addColumn('id', 'integer', ['autoincrement' => true]);
            $table->addColumn('title', 'string');
            $table->addColumn('content', 'string', ['default' => 'Test content']);
            $table->addColumn('counter', 'integer', ['default' => 999]);
            $table->addColumn('created_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP']);

            $queries = $schema->toSql($connection->getDatabasePlatform());
            foreach ($queries as $query) {
                $connection->executeQuery($query);
            }

            return $connection;
        } catch (\Exception $e) {
            $this->markTestSkipped('SQL connection not available');
        }
    }

    protected function getAdapterClass(): string
    {
        return Adapter::class;
    }
}
