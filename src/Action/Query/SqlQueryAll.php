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

namespace Fusio\Adapter\Sql\Action\Query;

use Fusio\Engine\ContextInterface;
use Fusio\Engine\Form\BuilderInterface;
use Fusio\Engine\Form\ElementFactoryInterface;
use Fusio\Engine\ParametersInterface;
use Fusio\Engine\RequestInterface;
use PSX\Http\Environment\HttpResponseInterface;

/**
 * SqlSelect
 *
 * @author  Christoph Kappestein <christoph.kappestein@gmail.com>
 * @license http://www.gnu.org/licenses/agpl-3.0
 * @link    https://www.fusio-project.org/
 */
class SqlQueryAll extends SqlQueryAbstract
{
    public function getName(): string
    {
        return 'SQL-Query-All';
    }

    public function handle(RequestInterface $request, ParametersInterface $configuration, ContextInterface $context): HttpResponseInterface
    {
        $connection = $this->getConnection($configuration);

        $sql   = $configuration->get('sql');
        $limit = (int) $configuration->get('limit');

        [$query, $params] = $this->parseSql($sql, $request);

        $startIndex = (int) $request->get('startIndex');
        $count      = (int) $request->get('count');

        $startIndex = $startIndex < 0 ? 0 : $startIndex;
        $limit      = $limit <= 0 ? 16 : $limit;
        $count      = $count >= 1 && $count <= $limit ? $count : $limit;

        $totalResults = (int) $connection->fetchColumn('SELECT COUNT(*) AS cnt FROM (' . $query . ') res', $params);

        $query = $connection->getDatabasePlatform()->modifyLimitQuery($query, $count, $startIndex);
        $data  = $connection->fetchAll($query, $params);

        $result = [
            'totalResults' => $totalResults,
            'itemsPerPage' => $count,
            'startIndex'   => $startIndex,
            'entry'        => $data,
        ];

        return $this->response->build(200, [], $result);
    }

    public function configure(BuilderInterface $builder, ElementFactoryInterface $elementFactory): void
    {
        parent::configure($builder, $elementFactory);

        $builder->add($elementFactory->newInput('limit', 'Limit', 'number', 'The default limit of the result (default is 16)'));
    }
}
