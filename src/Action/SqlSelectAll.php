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

namespace Fusio\Adapter\Sql\Action;

use Doctrine\DBAL\Query\QueryBuilder;
use Fusio\Engine\ContextInterface;
use Fusio\Engine\Form\BuilderInterface;
use Fusio\Engine\Form\ElementFactoryInterface;
use Fusio\Engine\ParametersInterface;
use Fusio\Engine\RequestInterface;
use PSX\Http\Environment\HttpResponseInterface;

/**
 * Action which allows you to create an API endpoint based on any database
 * table
 *
 * @author  Christoph Kappestein <christoph.kappestein@gmail.com>
 * @license http://www.gnu.org/licenses/agpl-3.0
 * @link    https://www.fusio-project.org/
 */
class SqlSelectAll extends SqlActionAbstract
{
    public function getName(): string
    {
        return 'SQL-Select-All';
    }

    public function handle(RequestInterface $request, ParametersInterface $configuration, ContextInterface $context): HttpResponseInterface
    {
        $connection = $this->getConnection($configuration);
        $tableName  = $this->getTableName($configuration);
        $mapping    = $this->getMapping($configuration);

        $table   = $this->getTable($connection, $tableName);
        $columns = $configuration->get('columns');
        $orderBy = $configuration->get('orderBy');
        $limit   = (int) $configuration->get('limit');

        $allColumns = $this->getColumns($table, $columns);
        $primaryKey = $this->getPrimaryKey($table);

        $qb = $connection->createQueryBuilder();
        $qb->select($allColumns);
        $qb->from($table->getName());

        $this->addFilter($request, $qb, $allColumns);
        $this->addOrderBy($request, $qb, $primaryKey, $allColumns, $orderBy);
        $this->addLimit($request, $qb, $limit);

        $totalCount = (int) $connection->fetchOne('SELECT COUNT(*) FROM ' . $table->getName());
        $result     = $connection->fetchAllAssociative($qb->getSQL(), $qb->getParameters());

        $data = [];
        foreach ($result as $row) {
            $data[] = $this->convertRow($row, $connection, $table, $mapping);
        }

        return $this->response->build(200, [], [
            'totalResults' => $totalCount,
            'itemsPerPage' => $qb->getMaxResults(),
            'startIndex'   => $qb->getFirstResult(),
            'entry'        => $data,
        ]);
    }

    public function configure(BuilderInterface $builder, ElementFactoryInterface $elementFactory): void
    {
        parent::configure($builder, $elementFactory);

        $builder->add($elementFactory->newCollection('columns', 'Columns', 'text', 'Columns which are selected on the table (default is *)'));
        $builder->add($elementFactory->newInput('orderBy', 'Order by', 'text', 'The default order by column (default is primary key)'));
        $builder->add($elementFactory->newInput('limit', 'Limit', 'number', 'The default limit of the result (default is 16)'));
    }
    
    private function addFilter(RequestInterface $request, QueryBuilder $qb, array $allColumns): void
    {
        $filterBy    = $request->get('filterBy');
        $filterOp    = $request->get('filterOp');
        $filterValue = $request->get('filterValue');

        if (!empty($filterBy) && !empty($filterOp) && !empty($filterValue) && in_array($filterBy, $allColumns)) {
            switch ($filterOp) {
                case 'contains':
                    $qb->where($filterBy . ' LIKE :filter');
                    $qb->setParameter('filter', '%' . $filterValue . '%');
                    break;

                case 'equals':
                    $qb->where($filterBy . ' = :filter');
                    $qb->setParameter('filter', $filterValue);
                    break;

                case 'startsWith':
                    $qb->where($filterBy . ' LIKE :filter');
                    $qb->setParameter('filter', $filterValue . '%');
                    break;

                case 'present':
                    $qb->where($filterBy . ' IS NOT NULL');
                    break;
            }
        }
    }

    private function addOrderBy(RequestInterface $request, QueryBuilder $qb, ?string $primaryKey, array $allColumns, ?string $orderBy): void
    {
        $sortBy    = $request->get('sortBy');
        $sortOrder = $request->get('sortOrder');

        if (!empty($sortBy) && !empty($sortOrder) && in_array($sortBy, $allColumns)) {
            $sortOrder = strtoupper($sortOrder);
            $sortOrder = in_array($sortOrder, ['ASC', 'DESC']) ? $sortOrder : 'DESC';

            $qb->orderBy($sortBy, $sortOrder);
        } elseif (!empty($orderBy) && in_array($orderBy, $allColumns)) {
            $qb->orderBy($orderBy, 'DESC');
        } else {
            $qb->orderBy($primaryKey, 'DESC');
        }
    }

    private function addLimit(RequestInterface $request, QueryBuilder $qb, ?int $limit): void
    {
        $startIndex = (int) $request->get('startIndex');
        $count      = (int) $request->get('count');

        $startIndex = $startIndex < 0 ? 0 : $startIndex;
        $limit = $limit <= 0 ? 16 : $limit;
        $count = $count >= 1 && $count <= $limit ? $count : $limit;

        $qb->setFirstResult($startIndex);
        $qb->setMaxResults($count);
    }
}
