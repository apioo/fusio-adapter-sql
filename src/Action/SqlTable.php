<?php
/*
 * Fusio
 * A web-application to create dynamically RESTful APIs
 *
 * Copyright (C) 2015-2017 Christoph Kappestein <christoph.kappestein@gmail.com>
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

            $id    = (int) $request->getUriFragment('id');
            $table = $this->getTable($connection, $tableName);

            switch ($request->getMethod()) {
                case 'GET':
                    return $this->doGet($request, $connection, $table, $id);
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

                    return $this->doDelete($request, $connection, $table, $id);
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
    }

    protected function doGet(RequestInterface $request, Connection $connection, Table $table, $id)
    {
        if (empty($id)) {
            return $this->doGetCollection(
                $request,
                $connection,
                $table
            );
        } else {
            return $this->doGetEntity(
                $id,
                $connection,
                $table
            );
        }
    }

    protected function doGetCollection(RequestInterface $request, Connection $connection, Table $table)
    {
        $startIndex  = (int) $request->getParameter('startIndex');
        $count       = (int) $request->getParameter('count');
        $sortBy      = $request->getParameter('sortBy');
        $sortOrder   = $request->getParameter('sortOrder');
        $filterBy    = $request->getParameter('filterBy');
        $filterOp    = $request->getParameter('filterOp');
        $filterValue = $request->getParameter('filterValue');

        $columns     = $this->getAvailableColumns($table);
        $primaryKey  = $this->getPrimaryKey($table);
        $startIndex  = $startIndex < 0 ? 0 : $startIndex;
        $count       = $count >= 1 && $count <= 32 ? $count : 16;

        $qb = $connection->createQueryBuilder();
        $qb->select($columns);
        $qb->from($table->getName());

        if (!empty($sortBy) && !empty($sortOrder) && in_array($sortBy, $columns)) {
            $sortOrder = strtoupper($sortOrder);
            $sortOrder = in_array($sortOrder, ['ASC', 'DESC']) ? $sortOrder : 'DESC';

            $qb->orderBy($sortBy, $sortOrder);
        } else {
            $qb->orderBy($primaryKey, 'DESC');
        }

        if (!empty($filterBy) && !empty($filterOp) && !empty($filterValue) && in_array($filterBy, $columns)) {
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

        return $this->response->build(200, [], [
            'totalResults' => $totalCount,
            'itemsPerPage' => $count,
            'startIndex'   => $startIndex,
            'entry'        => $result,
        ]);
    }

    protected function doGetEntity($id, Connection $connection, Table $table)
    {
        $columns    = $this->getAvailableColumns($table);
        $primaryKey = $this->getPrimaryKey($table);

        $qb = $connection->createQueryBuilder();
        $qb->select($columns);
        $qb->from($table->getName());
        $qb->where($primaryKey . ' = :id');
        $qb->setParameter('id', $id);

        $row = $connection->fetchAssoc($qb->getSQL(), $qb->getParameters());

        if (!empty($row)) {
            return $this->response->build(200, [], $row);
        } else {
            throw new StatusCode\NotFoundException('Entry not available');
        }
    }

    protected function doPost(RequestInterface $request, Connection $connection, Table $table)
    {
        $connection->insert($table->getName(), $this->getData($request, $table));

        return $this->response->build(201, [], [
            'success' => true,
            'message' => 'Entry successful created',
            'id'      => $connection->lastInsertId()
        ]);
    }

    protected function doPut(RequestInterface $request, Connection $connection, Table $table, $id)
    {
        $primaryKey = $this->getPrimaryKey($table);

        $connection->update($table->getName(), $this->getData($request, $table), [$primaryKey => $id]);

        return $this->response->build(200, [], [
            'success' => true,
            'message' => 'Entry successful updated'
        ]);
    }

    protected function doDelete(RequestInterface $request, Connection $connection, Table $table, $id)
    {
        $primaryKey = $this->getPrimaryKey($table);

        $connection->delete($table->getName(), [$primaryKey => $id]);

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

    protected function getData(RequestInterface $request, Table $table)
    {
        $columns = $this->getAvailableColumns($table);
        $body    = $request->getBody();

        $data = [];
        foreach ($body as $key => $value) {
            if (in_array($key, $columns)) {
                $data[$key] = $value;
            }
        }

        if (empty($data)) {
            throw new StatusCode\BadRequestException('No valid data provided');
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
}
