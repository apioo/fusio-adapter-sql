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

namespace Fusio\Adapter\Sql\Action\Query;

use Doctrine\DBAL\Connection;
use Fusio\Engine\ActionAbstract;
use Fusio\Engine\ContextInterface;
use Fusio\Engine\Exception\ConfigurationException;
use Fusio\Engine\Form\BuilderInterface;
use Fusio\Engine\Form\ElementFactoryInterface;
use Fusio\Engine\ParametersInterface;
use Fusio\Engine\RequestInterface;

/**
 * SqlQueryAbstract
 *
 * @author  Christoph Kappestein <christoph.kappestein@gmail.com>
 * @license http://www.apache.org/licenses/LICENSE-2.0
 * @link    https://www.fusio-project.org/
 */
abstract class SqlQueryAbstract extends ActionAbstract
{
    public function configure(BuilderInterface $builder, ElementFactoryInterface $elementFactory): void
    {
        $builder->add($elementFactory->newConnection('connection', 'Connection', 'The SQL connection which should be used'));
        $builder->add($elementFactory->newTextArea('sql', 'SQL', 'sql', 'The SQL to query the database'));
    }

    protected function getConnection(ParametersInterface $configuration): Connection
    {
        $connection = $this->connector->getConnection($configuration->get('connection'));
        if (!$connection instanceof Connection) {
            throw new ConfigurationException('Given connection must be a DBAL connection');
        }

        return $connection;
    }

    protected function parseSql(string $query, RequestInterface $request, ContextInterface $context): array
    {
        $params = [];
        $query = preg_replace_callback('/\{(\%?)([A-Za-z0-9_]+)(\%?)\}/', static function($matches) use ($request, $context, &$params){
            $left  = $matches[1];
            $name  = $matches[2];
            $right = $matches[3];

            if (str_starts_with($name, 'user_')) {
                $value = match (substr($name, 5)) {
                    'id' => $context->getUser()->getId(),
                    'name' => $context->getUser()->getName(),
                    default => throw new ConfigurationException('Provided an invalid user key'),
                };
            } else {
                $value = $request->get($name);
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
