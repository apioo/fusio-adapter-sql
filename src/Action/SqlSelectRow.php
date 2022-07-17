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

namespace Fusio\Adapter\Sql\Action;

use Fusio\Engine\ContextInterface;
use Fusio\Engine\Form\BuilderInterface;
use Fusio\Engine\Form\ElementFactoryInterface;
use Fusio\Engine\ParametersInterface;
use Fusio\Engine\RequestInterface;
use PSX\Http\Environment\HttpResponseInterface;
use PSX\Http\Exception as StatusCode;

/**
 * Action which allows you to create an API endpoint based on any database
 * table
 *
 * @author  Christoph Kappestein <christoph.kappestein@gmail.com>
 * @license http://www.gnu.org/licenses/agpl-3.0
 * @link    https://www.fusio-project.org/
 */
class SqlSelectRow extends SqlActionAbstract
{
    public function getName(): string
    {
        return 'SQL-Select-Row';
    }

    public function handle(RequestInterface $request, ParametersInterface $configuration, ContextInterface $context): HttpResponseInterface
    {
        $connection = $this->getConnection($configuration);
        $tableName  = $this->getTableName($configuration);
        $mapping    = $this->getMapping($configuration);

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

        $data = $this->convertRow($row, $connection, $table, $mapping);

        return $this->response->build(200, [], $data);
    }

    public function configure(BuilderInterface $builder, ElementFactoryInterface $elementFactory): void
    {
        parent::configure($builder, $elementFactory);

        $builder->add($elementFactory->newCollection('columns', 'Columns', 'text', 'Columns which are selected on the table (default is *)'));
        $builder->add($elementFactory->newMap('mapping', 'Mapping', 'text', 'Optional a property to column mapping'));
    }
}
