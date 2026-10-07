<?php
/*
 * Fusio - Self-Hosted API Management for Builders.
 * For the current version and information visit <https://www.fusio-project.org/>
 *
 * Copyright (c) Christoph Kappestein <christoph.kappestein@gmail.com>
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace Fusio\Adapter\Sql\Action;

use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\StringType;
use Fusio\Engine\ContextInterface;
use Fusio\Engine\Exception\ConfigurationException;
use Fusio\Engine\Form\BuilderInterface;
use Fusio\Engine\Form\ElementFactoryInterface;
use Fusio\Engine\ParametersInterface;
use Fusio\Engine\RequestInterface;
use PSX\Http\Environment\HttpResponseInterface;
use PSX\Sql\Condition;
use PSX\Sql\Filter\DoctrineBuilder;

/**
 * Action which allows you to create an API endpoint based on any database
 * table
 *
 * @author  Christoph Kappestein <christoph.kappestein@gmail.com>
 * @license http://www.apache.org/licenses/LICENSE-2.0
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
        $tableName = $this->getTableName($configuration);
        $mapping = $this->getMapping($configuration);

        $table = $this->getTable($connection, $tableName);
        $columns = $configuration->get('columns');
        $orderBy = $configuration->get('orderBy');
        $orderDirection = $configuration->get('orderDirection');
        $limit = (int) $configuration->get('limit');
        $searchColumn = $configuration->get('searchColumn');

        $allColumns = $this->getColumns($table, $columns);
        $primaryKey = $this->getPrimaryKey($table);

        $queryBuilder = $connection->createQueryBuilder();
        $queryBuilder->select($allColumns);
        $queryBuilder->from($table->getName());

        $condition = $this->buildCondition($request, $allColumns, $table, $searchColumn);
        if ($condition->hasCondition()) {
            $queryBuilder->where($condition->getExpression($connection->getDatabasePlatform()));
            $queryBuilder->setParameters($condition->getValues());
        }

        $countQueryBuilder = clone $queryBuilder;
        $countQueryBuilder->select('COUNT(*) AS cnt');

        $this->addOrderBy($request, $queryBuilder, $primaryKey, $allColumns, $orderBy, $orderDirection);
        $this->addLimit($request, $queryBuilder, $limit);

        $totalCount = (int) $connection->fetchOne($countQueryBuilder->getSQL(), $countQueryBuilder->getParameters());
        $result = $connection->fetchAllAssociative($queryBuilder->getSQL(), $queryBuilder->getParameters());

        $data = [];
        foreach ($result as $row) {
            $data[] = $this->convertRow($row, $connection, $table, $mapping);
        }

        return $this->response->build(200, [], [
            'totalResults' => $totalCount,
            'itemsPerPage' => $queryBuilder->getMaxResults(),
            'startIndex'   => $queryBuilder->getFirstResult(),
            'entry'        => $data,
        ]);
    }

    public function configure(BuilderInterface $builder, ElementFactoryInterface $elementFactory): void
    {
        parent::configure($builder, $elementFactory);

        $options = [
            'ASC' => 'Ascending',
            'DESC' => 'Descending',
        ];

        $builder->add($elementFactory->newCollection('columns', 'Columns', 'text', 'Columns which are selected on the table (default is *)'));
        $builder->add($elementFactory->newInput('orderBy', 'Order by', 'text', 'The default order by column (default is primary key)'));
        $builder->add($elementFactory->newSelect('orderDirection', 'Order direction', $options, 'The order direction (default is descending)'));
        $builder->add($elementFactory->newInput('limit', 'Limit', 'number', 'The default limit of the result (default is 16)'));
        $builder->add($elementFactory->newInput('searchColumn', 'Search Column', 'text', 'The default search column which is used if no column was explicit specified'));
    }

    /**
     * @param list<string> $allColumns
     */
    private function buildCondition(RequestInterface $request, array $allColumns, Table $table, ?string $searchColumn): Condition
    {
        $search = $request->get('search');
        if (!empty($search)) {
            if (empty($searchColumn)) {
                $searchColumn = $this->getSearchColumn($table);
            }

            return (new DoctrineBuilder())->build($table, $searchColumn, $search);
        }

        $filterBy = $request->get('filterBy');
        $filterOp = $request->get('filterOp');
        $filterValue = $request->get('filterValue');

        $condition = Condition::withAnd();
        if (!empty($filterBy) && !empty($filterOp) && !empty($filterValue) && in_array($filterBy, $allColumns)) {
            switch ($filterOp) {
                case 'contains':
                    $condition->like($filterBy, '%' . $filterValue . '%');
                    break;

                case 'equals':
                    $condition->equals($filterBy, $filterValue);
                    break;

                case 'startsWith':
                    $condition->like($filterBy, $filterValue . '%');
                    break;

                case 'present':
                    $condition->notNil($filterBy);
                    break;
            }
        }

        return $condition;
    }

    private function getSearchColumn(Table $table): string
    {
        foreach ($table->getColumns() as $columnName => $column) {
            $type = $column->getType();
            if ($type instanceof StringType) {
                return $columnName;
            }
        }

        foreach ($table->getColumns() as $columnName => $column) {
            return $columnName;
        }

        throw new ConfigurationException('Could not find default search column');
    }

    /**
     * @param list<string> $allColumns
     */
    private function addOrderBy(RequestInterface $request, QueryBuilder $qb, ?string $primaryKey, array $allColumns, ?string $orderBy, ?string $orderDirection): void
    {
        $sortBy = $request->get('sortBy');
        $sortOrder = $request->get('sortOrder');

        $orderDirection = !empty($orderDirection) && in_array($orderDirection, ['ASC', 'DESC']) ? $orderDirection : 'DESC';

        if (!empty($sortBy) && !empty($sortOrder) && in_array($sortBy, $allColumns)) {
            $sortOrder = strtoupper($sortOrder);
            $sortOrder = in_array($sortOrder, ['ASC', 'DESC']) ? $sortOrder : 'DESC';

            $qb->orderBy($sortBy, $sortOrder);
        } elseif (!empty($orderBy) && in_array($orderBy, $allColumns)) {
            $qb->orderBy($orderBy, $orderDirection);
        } elseif (!empty($primaryKey)) {
            $qb->orderBy($primaryKey, $orderDirection);
        }
    }

    private function addLimit(RequestInterface $request, QueryBuilder $qb, ?int $limit): void
    {
        $startIndex = (int) $request->get('startIndex');
        $count = (int) $request->get('count');

        $startIndex = $startIndex < 0 ? 0 : $startIndex;
        $limit = $limit <= 0 ? 16 : $limit;
        $count = $count >= 1 && $count <= $limit ? $count : $limit;

        $qb->setFirstResult($startIndex);
        $qb->setMaxResults($count);
    }
}
