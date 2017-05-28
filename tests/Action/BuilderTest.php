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

namespace Fusio\Adapter\Sql\Tests\Action;

use Fusio\Adapter\Sql\Action\SqlBuilderAbstract;
use Fusio\Engine\ContextInterface;
use Fusio\Engine\ParametersInterface;
use Fusio\Engine\RequestInterface;

/**
 * BuilderTest
 *
 * @author  Christoph Kappestein <christoph.kappestein@gmail.com>
 * @license http://www.gnu.org/licenses/agpl-3.0
 * @link    http://fusio-project.org
 */
class BuilderTest extends SqlBuilderAbstract
{
    public function handle(RequestInterface $request, ParametersInterface $configuration, ContextInterface $context)
    {
        /** @var \Doctrine\DBAL\Connection $connection */
        $connection = $this->connector->getConnection('foo');

        $sql = 'SELECT news.id,
                       news.title,
                       news.content,
                       news.tags,
                       news.date
                  FROM app_news news
              ORDER BY news.id DESC';

        $startIndex = 0;
        $parameters = ['startIndex' => $startIndex];
        $definition = [
            'totalEntries' => $this->doValue($connection, 'SELECT COUNT(*) AS cnt FROM app_news', [], $this->fieldInteger('cnt')),
            'startIndex' => $startIndex,
            'entries' => $this->doCollection($connection, $sql, $parameters, [
                'id' => $this->fieldInteger('id'),
                'articleNumber' => 'title',
                'description' => 'content',
                'articleCount' => $this->fieldJson('tags'),
                'insertDate' => $this->fieldDateTime('date'),
                'links' => [
                    'self' => $this->fieldReplace('/news/{id}'),
                ]
            ])
        ];

        return $this->response->build(200, [], $this->builder->build($definition));
    }
}
