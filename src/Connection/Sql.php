<?php
/*
 * Fusio
 * A web-application to create dynamically RESTful APIs
 *
 * Copyright (C) 2015-2020 Christoph Kappestein <christoph.kappestein@gmail.com>
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
 * @link    http://fusio-project.org
 */
class Sql implements ConnectionInterface, PingableInterface
{
    public function getName()
    {
        return 'SQL';
    }

    /**
     * @param \Fusio\Engine\ParametersInterface $config
     * @return \Doctrine\DBAL\Connection
     */
    public function getConnection(ParametersInterface $config)
    {
        $params = array(
            'dbname'   => $config->get('database'),
            'user'     => $config->get('username'),
            'password' => $config->get('password'),
            'host'     => $config->get('host'),
            'driver'   => $config->get('type'),
        );

        if ($config->get('type') == 'other' && !empty($config->get('driverClass'))) {
            unset($params['driver']);
            $params['driverClass'] = $config->get('driverClass');
        }
        
        if ($config->get('port') != '') {
        	$params['port'] = $config->get('port');
        }

        if ($config->get('type') == 'pdo_mysql') {
            $params['charset'] = 'utf8';
            $params['driverOptions'] = [
                \PDO::ATTR_EMULATE_PREPARES => false,
            ];
        }

        parse_str($config->get('customOptions'), $customOptions);
        $params = array_merge($params, $customOptions);

        parse_str($config->get('driverOptions'), $driverOptions);
        if (!empty($driverOptions)) {
            if (!empty($params['driverOptions'])) {
                $params['driverOptions'] = array_merge($params['driverOptions'], $driverOptions);
            } else {
                $params['driverOptions'] = $driverOptions;
            }
        }

        return DriverManager::getConnection($params);
    }

    public function configure(BuilderInterface $builder, ElementFactoryInterface $elementFactory)
    {
        $types = array(
            'pdo_mysql'   => 'MySQL',
            'pdo_pgsql'   => 'PostgreSQL',
            'sqlsrv'      => 'Microsoft SQL Server',
            'oci8'        => 'Oracle Database',
            'sqlanywhere' => 'SAP Sybase SQL Anywhere',
            'other'       => 'Custom driver class',
        );

        $builder->add($elementFactory->newSelect('type', 'Type', $types, 'The driver which is used to connect to the database'));
        $builder->add($elementFactory->newInput('driverClass', 'Driver class', 'text', 'Custom driver class name'));
        $builder->add($elementFactory->newInput('host', 'Host', 'text', 'The IP or hostname of the database server'));
        $builder->add($elementFactory->newInput('port', 'Port', 'number', 'The port used to connect to the database server'));
        $builder->add($elementFactory->newInput('username', 'Username', 'text', 'The name of the database user'));
        $builder->add($elementFactory->newInput('password', 'Password', 'password', 'The password of the database user'));
        $builder->add($elementFactory->newInput('database', 'Database', 'text', 'The name of the database which is used upon connection'));
        $builder->add($elementFactory->newInput('customOptions', 'Custom options', 'text', 'Custom options for this connection, like charset etc. Formatted as URL arguments'));
        $builder->add($elementFactory->newInput('driverOptions', 'Driver options', 'text', 'Additional options of this connection, specific to the driver. Formatted as URL arguments'));
    }

    public function ping($connection)
    {
        if ($connection instanceof Connection) {
            return $connection->ping();
        } else {
            return false;
        }
    }
}
