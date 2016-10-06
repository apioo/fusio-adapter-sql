<?php
/*
 * Fusio
 * A web-application to create dynamically RESTful APIs
 *
 * Copyright (C) 2015-2016 Christoph Kappestein <christoph.kappestein@gmail.com>
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
use PSX\Sql\Builder;
use PSX\Sql\Field;
use PSX\Sql\Provider;
use PSX\Sql\Reference;

/**
 * SqlBuilder
 *
 * @author  Christoph Kappestein <christoph.kappestein@gmail.com>
 * @license http://www.gnu.org/licenses/agpl-3.0
 * @link    http://fusio-project.org
 */
class SqlBuilder extends ActionAbstract
{
    public function getName()
    {
        return 'SQL-Builder';
    }

    public function handle(RequestInterface $request, ParametersInterface $configuration, ContextInterface $context)
    {
        $connection = $this->connector->getConnection($configuration->get('connection'));

        if ($connection instanceof Connection) {
            $parser     = $this->templateFactory->newTextParser();
            $definition = $parser->parse($request, $context, $configuration->get('definition'));

            $builder = new Builder();
            $result  = $builder->build(
                $this->parseDefinition($connection, json_decode($definition, true))
            );

            if (empty($result)) {
                throw new StatusCode\NotFoundException('Entry not available');
            }

            return $this->response->build(200, [], $result);
        } else {
            throw new ConfigurationException('Given connection must be a DBAL connection');
        }
    }

    public function configure(BuilderInterface $builder, ElementFactoryInterface $elementFactory)
    {
        $builder->add($elementFactory->newConnection('connection', 'Connection', 'The SQL connection which should be used'));
        $builder->add($elementFactory->newTextArea('definition', 'Definition', 'json', 'The JSON definition to build a nested response. Click <a ng-click="help.showDialog(\'help/action/sql-builder.md\')">here</a> for more informations about the JSON format.'));
    }

    protected function parseDefinition($connection, array $definition)
    {
        $result = [];

        // parameters
        $parameters = [];
        if (isset($definition['!parameters']) && is_array($definition['!parameters'])) {
            foreach ($definition['!parameters'] as $key => $value) {
                if (isset($value[0]) && $value[0] == '$') {
                    $parameters[$key] = new Reference(substr($value, 1));
                } else {
                    $parameters[$key] = $value;
                }
            }
        }

        if (isset($definition['!collection']) && is_string($definition['!collection'])) {
            if (is_array($definition['definition'])) {
                $def = $this->parseDefinition($connection, $definition['definition']);
            } else {
                $def = [];
            }

            return new Provider\DBAL\Collection($connection, $definition['!collection'], $parameters, $def);
        } elseif (isset($definition['!entity']) && is_string($definition['!entity'])) {
            if (is_array($definition['definition'])) {
                $def = $this->parseDefinition($connection, $definition['definition']);
            } else {
                $def = [];
            }

            return new Provider\DBAL\Entity($connection, $definition['!entity'], $parameters, $def);
        } elseif (isset($definition['!value']) && is_string($definition['!value'])) {
            return new Provider\DBAL\Value($connection, $definition['!value'], $parameters);
        } else {
            foreach ($definition as $key => $value) {
                if (is_array($value)) {
                    $result[$key] = $this->parseDefinition($connection, $value);
                } else {
                    if (is_string($value)) {
                        $pos = strpos($value, '|');
                        if ($pos !== false) {
                            $type  = substr($value, $pos + 1);
                            $value = substr($value, 0, $pos);
                            $value = $this->parseType($value, $type);
                        }
                    }

                    $result[$key] = $value;
                }
            }
        }

        return $result;
    }

    protected function parseType($value, $type)
    {
        switch ($type) {
            case 'boolean':
                return new Field\Boolean($value);
                break;

            case 'datetime':
                return new Field\DateTime($value);
                break;

            case 'integer':
                return new Field\Integer($value);
                break;

            case 'number':
                return new Field\Number($value);
                break;

            case 'replace':
                return new Field\Replace($value);
                break;

            default:
                return $value;
                break;
        }
    }
}
