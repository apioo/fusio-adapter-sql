<?php
/*
 * Fusio
 * A web-application to create dynamically RESTful APIs
 *
 * Copyright (C) 2015-2019 Christoph Kappestein <christoph.kappestein@gmail.com>
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
use PSX\Http\Exception as StatusCode;

/**
 * SqlSelect
 *
 * @author  Christoph Kappestein <christoph.kappestein@gmail.com>
 * @license http://www.gnu.org/licenses/agpl-3.0
 * @link    http://fusio-project.org
 */
class SqlSelect extends ActionAbstract
{
    const FETCH_ALL = 0;
    const FETCH_ROW = 1;

    public function getName()
    {
        return 'SQL-Select';
    }

    public function handle(RequestInterface $request, ParametersInterface $configuration, ContextInterface $context)
    {
        $connection = $this->connector->getConnection($configuration->get('connection'));

        if ($connection instanceof Connection) {
            $mode  = $configuration->get('mode');
            $sql   = $configuration->get('sql');
            $limit = (int) $configuration->get('limit');

            [$query, $params] = $this->parseSql($sql, $request);

            if ($mode == self::FETCH_ALL) {
                $startIndex  = (int) $request->getParameter('startIndex');
                $count       = (int) $request->getParameter('count');

                $startIndex  = $startIndex < 0 ? 0 : $startIndex;
                $limit       = $limit <= 0 ? 16 : $limit;
                $count       = $count >= 1 && $count <= $limit ? $count : $limit;

                $totalResults = (int) $connection->fetchColumn('SELECT COUNT(*) AS cnt FROM (' . $query . ')', $params);

                $query = $connection->getDatabasePlatform()->modifyLimitQuery($query, $count, $startIndex);
                $data  = $connection->fetchAll($query, $params);

                $result = [
                    'totalResults' => $totalResults,
                    'itemsPerPage' => $count,
                    'startIndex'   => $startIndex,
                    'entry'        => $data,
                ];
            } elseif ($mode == self::FETCH_ROW) {
                $result = $connection->fetchAssoc($query, $params);

                if (empty($result)) {
                    throw new StatusCode\NotFoundException('Entry not found');
                }
            } else {
                throw new \RuntimeException('Invalid mode');
            }

            return $this->response->build(200, [], $result);
        } else {
            throw new ConfigurationException('Given connection must be a DBAL connection');
        }
    }

    public function configure(BuilderInterface $builder, ElementFactoryInterface $elementFactory)
    {
        $modes = [
            self::FETCH_ALL => 'All Rows',
            self::FETCH_ROW => 'First Row',
        ];

        $builder->add($elementFactory->newConnection('connection', 'Connection', 'The SQL connection which should be used'));
        $builder->add($elementFactory->newSelect('mode', 'Mode', $modes, 'Whether to return only the first row or the complete result set'));
        $builder->add($elementFactory->newTextArea('sql', 'SQL', 'sql', 'The SQL to query the database. Click <a ng-click="help.showDialog(\'help/action/sql-select.md\')">here</a> for more information.'));
        $builder->add($elementFactory->newInput('limit', 'Limit', 'number', 'The default limit of the result (default is 16)'));
    }

    private function parseSql(string $query, RequestInterface $request)
    {
        $params = [];
        $query = preg_replace_callback('/\{(\%?)(\w+)(\%?)\}/', static function($matches) use ($request, &$params){
            $left = $matches[1];
            $name = $matches[2];
            $right = $matches[3];

            $value = $request->getUriFragment($name);
            if (empty($value)) {
                $value = $request->getParameter($name);
            }

            if (!empty($left)) {
                $value = '%' . $value;
            }
            if (!empty($right)) {
                $value = $value . '%';
            }

            $params[$name] = $value;

            return ':' . $name;
        }, $query);

        return [
            $query,
            $params,
        ];
    }
}
