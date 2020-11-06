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

namespace Fusio\Adapter\Sql\Action;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types;
use Fusio\Engine\ActionAbstract;
use Fusio\Engine\ContextInterface;
use Fusio\Engine\Exception\ConfigurationException;
use Fusio\Engine\Form\BuilderInterface;
use Fusio\Engine\Form\ElementFactoryInterface;
use Fusio\Engine\ParametersInterface;
use Fusio\Engine\Request\HttpRequest;
use Fusio\Engine\Request\RpcRequest;
use Fusio\Engine\RequestInterface;
use PSX\Http\Exception as StatusCode;

/**
 * Action which allows you to create an API endpoint based on any database
 * table
 *
 * @author  Christoph Kappestein <christoph.kappestein@gmail.com>
 * @license http://www.gnu.org/licenses/agpl-3.0
 * @link    http://fusio-project.org
 */
class SqlSelectRow extends SqlActionAbstract
{
    public function getName()
    {
        return 'SQL-Select-Row';
    }

    public function handle(RequestInterface $request, ParametersInterface $configuration, ContextInterface $context)
    {
        $connection = $this->getConnection($configuration);
        $tableName  = $this->getTableName($configuration);

        $id      = (int) $request->get('id');
        $table   = $this->getTable($connection, $tableName);
        $columns = $configuration->get('columns');

        $allColumns = $this->getColumns($table, $columns);
        $primaryKey = $this->getPrimaryKey($table);

        $qb = $connection->createQueryBuilder();
        $qb->select($allColumns);
        $qb->from($table->getName());
        $qb->where($primaryKey . ' = :id');
        $qb->setParameter('id', $id);

        $row = $connection->fetchAssoc($qb->getSQL(), $qb->getParameters());

        if (empty($row)) {
            throw new StatusCode\NotFoundException('Entry not available');
        }

        $data = $this->convertRow($row, $connection, $table);

        return $this->response->build(200, [], $data);
    }

    public function configure(BuilderInterface $builder, ElementFactoryInterface $elementFactory)
    {
        parent::configure($builder, $elementFactory);

        $builder->add($elementFactory->newTag('columns', 'Columns', 'Columns which are selected on the table (default is *)'));
    }
}
