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

namespace Fusio\Adapter\Sql\Action;

use Doctrine\DBAL\Connection;
use Fusio\Engine\ActionAbstract;
use Fusio\Engine\ContextInterface;
use Fusio\Engine\Exception\ConfigurationException;
use Fusio\Engine\Form\BuilderInterface;
use Fusio\Engine\Form\ElementFactoryInterface;
use Fusio\Engine\ParametersInterface;
use Fusio\Engine\RequestInterface;

/**
 * SqlExecute
 *
 * @author  Christoph Kappestein <christoph.kappestein@gmail.com>
 * @license http://www.gnu.org/licenses/agpl-3.0
 * @link    http://fusio-project.org
 */
class SqlExecute extends ActionAbstract
{
    public function getName()
    {
        return 'SQL-Execute';
    }

    public function handle(RequestInterface $request, ParametersInterface $configuration, ContextInterface $context)
    {
        $connection = $this->connector->getConnection($configuration->get('connection'));

        if ($connection instanceof Connection) {
            // parse sql
            $parser = $this->templateFactory->newSqlParser();
            $sql    = $parser->parse($request, $context->withConnection($connection), $configuration->get('sql'));

            $connection->executeUpdate($sql, $parser->getSqlParameters());

            return $this->response->build(200, [], array(
                'success' => true,
                'message' => 'Execution was successful'
            ));
        } else {
            throw new ConfigurationException('Given connection must be an DBAL connection');
        }
    }

    public function configure(BuilderInterface $builder, ElementFactoryInterface $elementFactory)
    {
        $builder->add($elementFactory->newConnection('connection', 'Connection', 'The SQL connection which should be used'));
        $builder->add($elementFactory->newTextArea('sql', 'SQL', 'sql', 'The INSERT, UPDATE or DELETE query which gets executed. It is possible to access values from the environment with i.e. <code ng-non-bindable>{{ body.get("title")|prepare }}</code>. <b>Note you must use the prepare filter for each parameter in order to generate a safe SQL query which uses prepared statments.</b> Click <a ng-click="help.showDialog(\'help/template.md\')">here</a> for more informations about the template syntax.'));
    }
}
