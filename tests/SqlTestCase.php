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

use Fusio\Adapter\Soap\Connection\Soap;
use Fusio\Adapter\Sql\Action\SqlBuilder;
use Fusio\Adapter\Sql\Action\SqlDelete;
use Fusio\Adapter\Sql\Action\SqlInsert;
use Fusio\Adapter\Sql\Action\SqlSelectAll;
use Fusio\Adapter\Sql\Action\SqlSelectRow;
use Fusio\Adapter\Sql\Action\SqlUpdate;
use Fusio\Adapter\Sql\Connection\Sql;
use Fusio\Adapter\Sql\Connection\SqlAdvanced;
use Fusio\Adapter\Sql\Generator\SqlDatabase;
use Fusio\Adapter\Sql\Generator\SqlEntity;
use Fusio\Adapter\Sql\Generator\SqlTable;
use Fusio\Engine\Action\Runtime;
use Fusio\Engine\ConnectorInterface;
use Fusio\Engine\Model\Connection;
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

    protected function configure(Runtime $runtime, Container $container): void
    {
        $container->set(Sql::class, new Sql());
        $container->set(SqlAdvanced::class, new SqlAdvanced());
        $container->set(SqlBuilder::class, new SqlBuilder($runtime));
        $container->set(SqlDelete::class, new SqlDelete($runtime));
        $container->set(SqlInsert::class, new SqlInsert($runtime));
        $container->set(SqlSelectAll::class, new SqlSelectAll($runtime));
        $container->set(SqlSelectRow::class, new SqlSelectRow($runtime));
        $container->set(SqlUpdate::class, new SqlUpdate($runtime));
        $container->set(SqlDatabase::class, new SqlDatabase($container->get(ConnectorInterface::class)));
        $container->set(SqlEntity::class, new SqlEntity($container->get(ConnectorInterface::class)));
        $container->set(SqlTable::class, new SqlTable($container->get(ConnectorInterface::class)));
    }

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

    protected function getConnection(): \Doctrine\DBAL\Connection
    {
        return getConnection();
    }

    protected function getDataSet()
    {
        return include __DIR__ . '/fixture.php';
    }
}
