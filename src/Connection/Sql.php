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

namespace Fusio\Adapter\Sql\Connection;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Fusio\Engine\ConnectionInterface;
use Fusio\Engine\Connection\PingableInterface;
use Fusio\Engine\Form\BuilderInterface;
use Fusio\Engine\Form\ElementFactoryInterface;
use Fusio\Engine\ParametersInterface;

/**
 * Sql
 *
 * @author  Christoph Kappestein <christoph.kappestein@gmail.com>
 * @license http://www.gnu.org/licenses/agpl-3.0
 * @link    https://www.fusio-project.org/
 */
class Sql implements ConnectionInterface, PingableInterface
{
    public function getName(): string
    {
        return 'SQL';
    }

    public function getConnection(ParametersInterface $config): Connection
    {
        $params = array(
            'dbname'   => $config->get('database'),
            'user'     => $config->get('username'),
            'password' => $config->get('password'),
            'host'     => $config->get('host'),
            'driver'   => $config->get('type'),
        );

        if ($config->get('type') == 'pdo_mysql') {
            $params['charset'] = 'utf8';
            $params['driverOptions'] = [
                \PDO::ATTR_EMULATE_PREPARES => false,
            ];
        }

        return DriverManager::getConnection($params);
    }

    public function configure(BuilderInterface $builder, ElementFactoryInterface $elementFactory): void
    {
        $types = array(
            'pdo_mysql'   => 'MySQL',
            'pdo_pgsql'   => 'PostgreSQL',
            'sqlsrv'      => 'Microsoft SQL Server',
            'oci8'        => 'Oracle Database',
            'sqlanywhere' => 'SAP Sybase SQL Anywhere',
        );

        $builder->add($elementFactory->newSelect('type', 'Type', $types, 'The driver which is used to connect to the database'));
        $builder->add($elementFactory->newInput('host', 'Host', 'text', 'The IP or hostname of the database server'));
        $builder->add($elementFactory->newInput('username', 'Username', 'text', 'The name of the database user'));
        $builder->add($elementFactory->newInput('password', 'Password', 'password', 'The password of the database user'));
        $builder->add($elementFactory->newInput('database', 'Database', 'text', 'The name of the database which is used upon connection'));
    }

    public function ping(mixed $connection): bool
    {
        if ($connection instanceof Connection) {
            return $connection->ping();
        } else {
            return false;
        }
    }
}
