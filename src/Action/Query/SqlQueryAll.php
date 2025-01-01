<?php
/*
 * Fusio is an open source API management platform which helps to create innovative API solutions.
 * For the current version and information visit <https://www.fusio-project.org/>
 *
 * Copyright 2015-2023 Christoph Kappestein <christoph.kappestein@gmail.com>
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

namespace Fusio\Adapter\Sql\Action\Query;

use Fusio\Engine\ContextInterface;
use Fusio\Engine\Exception\ConfigurationException;
use Fusio\Engine\Form\BuilderInterface;
use Fusio\Engine\Form\ElementFactoryInterface;
use Fusio\Engine\ParametersInterface;
use Fusio\Engine\RequestInterface;
use PSX\Http\Environment\HttpResponseInterface;

/**
 * SqlSelect
 *
 * @author  Christoph Kappestein <christoph.kappestein@gmail.com>
 * @license http://www.apache.org/licenses/LICENSE-2.0
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

        $sql   = $configuration->get('sql') ?? throw new ConfigurationException('No sql configured');
        $limit = (int) $configuration->get('limit');

        [$query, $params] = $this->parseSql($sql, $request, $context);

        $startIndex = (int) $request->get('startIndex');
        $count      = (int) $request->get('count');

        $startIndex = $startIndex < 0 ? 0 : $startIndex;
        $limit      = $limit <= 0 ? 16 : $limit;
        $count      = $count >= 1 && $count <= $limit ? $count : $limit;

        $totalResults = (int) $connection->fetchOne('SELECT COUNT(*) AS cnt FROM (' . $query . ') res', $params);

        $query = $connection->getDatabasePlatform()->modifyLimitQuery($query, $count, $startIndex);
        $data  = $connection->fetchAllAssociative($query, $params);

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
