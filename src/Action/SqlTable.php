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
class SqlTable extends ActionAbstract
{
    public function getName()
    {
        return 'SQL-Table';
    }

    public function handle(RequestInterface $request, ParametersInterface $configuration, ContextInterface $context)
    {
        $connection = $this->connector->getConnection($configuration->get('connection'));

        if ($connection instanceof Connection) {
            $tableName = $configuration->get('table');

            if (empty($tableName)) {
                throw new ConfigurationException('No table name provided');
            }

            $id      = (int) $request->getUriFragment('id');
            $table   = $this->getTable($connection, $tableName);
            $columns = $configuration->get('columns');
            $orderBy = $configuration->get('orderBy');
            $limit   = (int) $configuration->get('limit');

            switch ($request->getMethod()) {
                case 'HEAD':
                case 'GET':
                    if (empty($id)) {
                        return $this->doGetCollection($request, $connection, $table, $columns, $orderBy, $limit);
                    } else {
                        return $this->doGetEntity($id, $connection, $table, $columns);
                    }
                    break;

                case 'POST':
                    if (!empty($id)) {
                        throw new StatusCode\MethodNotAllowedException('Method not allowed', ['GET', 'POST']);
                    }

                    return $this->doPost($request, $connection, $table);
                    break;

                case 'PUT':
                    if (empty($id)) {
                        throw new StatusCode\MethodNotAllowedException('Method not allowed', ['GET', 'PUT', 'DELETE']);
                    }

                    return $this->doPut($request, $connection, $table, $id);
                    break;

                case 'DELETE':
                    if (empty($id)) {
                        throw new StatusCode\MethodNotAllowedException('Method not allowed', ['GET', 'PUT', 'DELETE']);
                    }

                    return $this->doDelete($connection, $table, $id);
                    break;
            }

            if (empty($id)) {
                throw new StatusCode\MethodNotAllowedException('Method not allowed', ['GET', 'POST']);
            } else {
                throw new StatusCode\MethodNotAllowedException('Method not allowed', ['GET', 'PUT', 'DELETE']);
            }
        } else {
            throw new ConfigurationException('Given connection must be a DBAL connection');
        }
    }

    public function configure(BuilderInterface $builder, ElementFactoryInterface $elementFactory)
    {
        $builder->add($elementFactory->newConnection('connection', 'Connection', 'The SQL connection which should be used'));
        $builder->add($elementFactory->newInput('table', 'Table', 'text', 'Name of the database table'));
        $builder->add($elementFactory->newTag('columns', 'Columns', 'Columns which are selected on the table (default is *)'));
        $builder->add($elementFactory->newInput('orderBy', 'Order by', 'text', 'The default order by column (default is primary key)'));
        $builder->add($elementFactory->newInput('limit', 'Limit', 'number', 'The default limit of the result (default is 16)'));
    }

    protected function doGetCollection(RequestInterface $request, Connection $connection, Table $table, $columns, $orderBy, $limit)
    {
        $startIndex  = (int) $request->getParameter('startIndex');
        $count       = (int) $request->getParameter('count');
        $sortBy      = $request->getParameter('sortBy');
        $sortOrder   = $request->getParameter('sortOrder');
        $filterBy    = $request->getParameter('filterBy');
        $filterOp    = $request->getParameter('filterOp');
        $filterValue = $request->getParameter('filterValue');

        $allColumns  = $this->getAvailableColumns($table);
        $primaryKey  = $this->getPrimaryKey($table);

        if (!empty($columns)) {
            $allColumns = array_intersect($allColumns, $columns);
        }

        $startIndex  = $startIndex < 0 ? 0 : $startIndex;
        $limit       = $limit <= 0 ? 16 : $limit;
        $count       = $count >= 1 && $count <= $limit ? $count : $limit;

        $qb = $connection->createQueryBuilder();
        $qb->select($allColumns);
        $qb->from($table->getName());

        if (!empty($sortBy) && !empty($sortOrder) && in_array($sortBy, $allColumns)) {
            $sortOrder = strtoupper($sortOrder);
            $sortOrder = in_array($sortOrder, ['ASC', 'DESC']) ? $sortOrder : 'DESC';

            $qb->orderBy($sortBy, $sortOrder);
        } elseif (!empty($orderBy) && in_array($orderBy, $allColumns)) {
            $qb->orderBy($orderBy, 'DESC');
        } else {
            $qb->orderBy($primaryKey, 'DESC');
        }

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

        $qb->setFirstResult($startIndex);
        $qb->setMaxResults($count);

        $totalCount = (int) $connection->fetchColumn('SELECT COUNT(*) FROM ' . $table->getName());
        $result     = $connection->fetchAll($qb->getSQL(), $qb->getParameters());

        $data = [];
        foreach ($result as $row) {
            $data[] = $this->convertRow($row, $connection, $table);
        }

        return $this->response->build(200, [], [
            'totalResults' => $totalCount,
            'itemsPerPage' => $count,
            'startIndex'   => $startIndex,
            'entry'        => $data,
        ]);
    }

    protected function doGetEntity($id, Connection $connection, Table $table, $columns)
    {
        $allColumns = $this->getAvailableColumns($table);
        $primaryKey = $this->getPrimaryKey($table);

        if (!empty($columns)) {
            $allColumns = array_intersect($allColumns, $columns);
        }

        $qb = $connection->createQueryBuilder();
        $qb->select($allColumns);
        $qb->from($table->getName());
        $qb->where($primaryKey . ' = :id');
        $qb->setParameter('id', $id);

        $row = $connection->fetchAssoc($qb->getSQL(), $qb->getParameters());

        if (!empty($row)) {
            $data = $this->convertRow($row, $connection, $table);

            return $this->response->build(200, [], $data);
        } else {
            throw new StatusCode\NotFoundException('Entry not available');
        }
    }

    protected function doPost(RequestInterface $request, Connection $connection, Table $table)
    {
        $data = $this->getData($request, $connection, $table, true);

        $connection->insert($table->getName(), $data);

        return $this->response->build(201, [], [
            'success' => true,
            'message' => 'Entry successful created',
            'id'      => $connection->lastInsertId()
        ]);
    }

    protected function doPut(RequestInterface $request, Connection $connection, Table $table, $id)
    {
        $key  = $this->getPrimaryKey($table);
        $data = $this->getData($request, $connection, $table);

        $connection->update($table->getName(), $data, [$key => $id]);

        return $this->response->build(200, [], [
            'success' => true,
            'message' => 'Entry successful updated'
        ]);
    }

    protected function doDelete(Connection $connection, Table $table, $id)
    {
        $key = $this->getPrimaryKey($table);

        $connection->delete($table->getName(), [$key => $id]);

        return $this->response->build(200, [], [
            'success' => true,
            'message' => 'Entry successful deleted'
        ]);
    }

    protected function getTable(Connection $connection, $tableName)
    {
        $key   = __CLASS__ . $tableName;
        $table = $this->cache->get($key);

        if ($table === null) {
            $sm = $connection->getSchemaManager();

            if ($sm->tablesExist([$tableName])) {
                $table = $sm->listTableDetails($tableName);
                $this->cache->set($key, $table);
            } else {
                throw new StatusCode\InternalServerErrorException('Table ' . $tableName . ' does not exist on connection');
            }
        }

        return $table;
    }

    protected function getData(RequestInterface $request, Connection $connection, Table $table, $validateNull = false)
    {
        $body = $request->getBody();
        $data = [];

        $columns = $table->getColumns();
        foreach ($columns as $column) {
            if ($column->getAutoincrement()) {
                // in case the column is autoincrement we dont need it
                continue;
            }

            $value = null;
            if ($body->hasProperty($column->getName())) {
                $value = $body->getProperty($column->getName());
            } elseif (!$validateNull) {
                continue;
            }

            if ($value === null && $column->getNotnull() && $validateNull) {
                throw new StatusCode\BadRequestException('Column ' . $column->getName() . ' must not be null');
            }

            if ($value instanceof \DateTime) {
                $platform = $connection->getDatabasePlatform();
                if ($column->getType() instanceof Types\DateType) {
                    $value = $value->format($platform->getDateFormatString());
                } elseif ($column->getType() instanceof Types\DateTimeType) {
                    $value = $value->format($platform->getDateTimeFormatString());
                } elseif ($column->getType() instanceof Types\DateTimeTzType) {
                    $value = $value->format($platform->getDateTimeTzFormatString());
                } elseif ($column->getType() instanceof Types\TimeType) {
                    $value = $value->format($platform->getTimeFormatString());
                }
            }

            $data[$column->getName()] = $value;
        }

        return $data;
    }

    protected function getAvailableColumns(Table $table)
    {
        return array_keys($table->getColumns());
    }

    protected function getPrimaryKey(Table $table)
    {
        $primaryKey = $table->getPrimaryKey();
        if ($primaryKey instanceof Index) {
            $columns = $primaryKey->getColumns();
            return reset($columns);
        } else {
            throw new StatusCode\InternalServerErrorException('Primary column not available');
        }
    }

    /**
     * Converts a raw database row to the correct PHP types
     *
     * @param array $row
     * @param \Doctrine\DBAL\Connection $connection
     * @param \Doctrine\DBAL\Schema\Table $table
     * @return array
     * @throws \Doctrine\DBAL\Schema\SchemaException
     */
    protected function convertRow(array $row, Connection $connection, Table $table)
    {
        $result = [];
        foreach ($row as $key => $value) {
            $type = $table->getColumn($key)->getType();
            $val  = $type->convertToPHPValue($value, $connection->getDatabasePlatform());

            if ($val === null) {
            } elseif ($type instanceof Types\DateType) {
                $val = $val->format('Y-m-d');
            } elseif ($type instanceof Types\DateTimeType) {
                $val = $val->format(\DateTime::RFC3339);
            } elseif ($type instanceof Types\DateTimeTzType) {
                $val = $val->format(\DateTime::RFC3339_EXTENDED);
            } elseif ($type instanceof Types\TimeType) {
                $val = $val->format('H:i:s');
            } elseif ($type instanceof Types\BinaryType || $type instanceof Types\BlobType) {
                $val = base64_encode(stream_get_contents($val));
            }

            $result[$key] = $val;
        }

        return $result;
    }
}
