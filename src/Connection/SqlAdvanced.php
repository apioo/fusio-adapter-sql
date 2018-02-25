<?php
/*
 * Fusio
 * A web-application to create dynamically RESTful APIs
 *
 * Copyright (C) 2015-2018 Christoph Kappestein <christoph.kappestein@gmail.com>
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
use Fusio\Engine\Connection\PingableInterface;
use Fusio\Engine\ConnectionInterface;
use Fusio\Engine\Form\BuilderInterface;
use Fusio\Engine\Form\ElementFactoryInterface;
use Fusio\Engine\ParametersInterface;

/**
 * SqlAdvanced
 *
 * @author  Christoph Kappestein <christoph.kappestein@gmail.com>
 * @license http://www.gnu.org/licenses/agpl-3.0
 * @link    http://fusio-project.org
 */
class SqlAdvanced implements ConnectionInterface, PingableInterface
{
    public function getName()
    {
        return 'SQL (advanced)';
    }

    /**
     * @param \Fusio\Engine\ParametersInterface $config
     * @return \Doctrine\DBAL\Connection
     */
    public function getConnection(ParametersInterface $config)
    {
        return DriverManager::getConnection(array(
            'url' => $config->get('url')
        ));
    }

    public function configure(BuilderInterface $builder, ElementFactoryInterface $elementFactory)
    {
        $builder->add($elementFactory->newInput('url', 'URL', 'text', 'Uses an specific URL which contains all database connection information. Click <a ng-click="help.showDialog(\'help/connection/dbal_advanced.md\')">here</a> for more information.'));
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
