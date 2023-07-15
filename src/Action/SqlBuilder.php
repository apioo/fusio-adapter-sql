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

namespace Fusio\Adapter\Sql\Action;

use Doctrine\DBAL\Connection;
use Fusio\Engine\ActionAbstract;
use Fusio\Engine\ContextInterface;
use Fusio\Engine\Exception\ConfigurationException;
use Fusio\Engine\Form\BuilderInterface;
use Fusio\Engine\Form\ElementFactoryInterface;
use Fusio\Engine\ParametersInterface;
use Fusio\Engine\Request\HttpRequest;
use Fusio\Engine\Request\HttpRequestContext;
use Fusio\Engine\Request\RpcRequest;
use Fusio\Engine\Request\RpcRequestContext;
use Fusio\Engine\RequestInterface;
use PSX\Http\Environment\HttpResponseInterface;
use PSX\Http\Exception as StatusCode;
use PSX\Nested\Builder;
use PSX\Nested\JsonProvider;

/**
 * SqlBuilder
 *
 * @author  Christoph Kappestein <christoph.kappestein@gmail.com>
 * @license http://www.apache.org/licenses/LICENSE-2.0
 * @link    https://www.fusio-project.org/
 */
class SqlBuilder extends ActionAbstract
{
    public function getName(): string
    {
        return 'SQL-Builder';
    }

    public function handle(RequestInterface $request, ParametersInterface $configuration, ContextInterface $context): HttpResponseInterface
    {
        $connection = $this->getConnection($configuration);

        $definition = (new JsonProvider($connection))->create(json_decode($configuration->get('jql')), $this->getContext($request));
        $data = (new Builder($connection))->build($definition);

        if (empty($data)) {
            throw new StatusCode\NotFoundException('Entry not available');
        }

        return $this->response->build(200, [], $data);
    }

    public function configure(BuilderInterface $builder, ElementFactoryInterface $elementFactory): void
    {
        $builder->add($elementFactory->newConnection('connection', 'Connection', 'The SQL connection which should be used'));
        $builder->add($elementFactory->newTextArea('jql', 'JQL', 'json', 'The JQL to query the database'));
    }

    private function getContext(RequestInterface $request): array
    {
        $requestContext = $request->getContext();
        if ($requestContext instanceof HttpRequestContext) {
            return array_merge($requestContext->getParameters(), $requestContext->getRequest()->getUri()->getParameters());
        } elseif ($requestContext instanceof RpcRequestContext) {
            return [];
        } else {
            return [];
        }
    }

    private function getConnection(ParametersInterface $configuration): Connection
    {
        $connection = $this->connector->getConnection($configuration->get('connection'));
        if (!$connection instanceof Connection) {
            throw new ConfigurationException('Given connection must be a DBAL connection');
        }

        return $connection;
    }
}
