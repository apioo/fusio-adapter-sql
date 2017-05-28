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
use Fusio\Engine\ActionAbstract;
use PSX\Sql\Builder;
use PSX\Sql\Field;
use PSX\Sql\Provider;

/**
 * SqlBuilderAbstract
 *
 * @author  Christoph Kappestein <christoph.kappestein@gmail.com>
 * @license http://www.gnu.org/licenses/agpl-3.0
 * @link    http://fusio-project.org
 */
abstract class SqlBuilderAbstract extends ActionAbstract
{
    /**
     * @var \PSX\Sql\Builder
     */
    protected $builder;

    public function __construct()
    {
        $this->builder = new Builder();
    }

    /**
     * @param array $definition
     * @return mixed
     */
    protected function build($definition)
    {
        return $this->builder->build($definition);
    }

    /**
     * @param \Doctrine\DBAL\Connection $connection
     * @param string $sql
     * @param array $arguments
     * @param array $definition
     * @param string|null $key
     * @param \Closure|null $filter
     * @return \PSX\Sql\Provider\ProviderCollectionInterface
     */
    protected function doCollection(Connection $connection, $sql, array $arguments, array $definition, $key = null, \Closure $filter = null)
    {
        return new Provider\DBAL\Collection($connection, $sql, $arguments, $definition, $key, $filter);
    }

    /**
     * @param \Doctrine\DBAL\Connection $connection
     * @param string $sql
     * @param array $arguments
     * @param array $definition
     * @return \PSX\Sql\Provider\ProviderEntityInterface
     */
    protected function doEntity(Connection $connection, $sql, array $arguments, array $definition)
    {
        return new Provider\DBAL\Entity($connection, $sql, $arguments, $definition);
    }

    /**
     * @param \Doctrine\DBAL\Connection $connection
     * @param string $sql
     * @param array $arguments
     * @param mixed $definition
     * @return \PSX\Sql\Provider\ProviderColumnInterface
     */
    protected function doColumn(Connection $connection, $sql, array $arguments, $definition)
    {
        return new Provider\DBAL\Column($connection, $sql, $arguments, $definition);
    }

    /**
     * @param \Doctrine\DBAL\Connection $connection
     * @param string $sql
     * @param array $arguments
     * @param mixed $definition
     * @return \PSX\Sql\Provider\ProviderValueInterface
     */
    protected function doValue(Connection $connection, $sql, array $arguments = [], $definition = null)
    {
        return new Provider\DBAL\Value($connection, $sql, $arguments, $definition);
    }

    /**
     * @param string $value
     * @return \PSX\Sql\Field\Boolean
     */
    protected function fieldBoolean($value)
    {
        return new Field\Boolean($value);
    }

    /**
     * @param string $key
     * @param \Closure $callback
     * @return \PSX\Sql\Field\Callback
     */
    protected function fieldCallback($key, \Closure $callback)
    {
        return new Field\Callback($key, $callback);
    }

    /**
     * @param string $key
     * @param string $delimiter
     * @return \PSX\Sql\Field\Csv
     */
    protected function fieldCsv($key, $delimiter = ',')
    {
        return new Field\Csv($key, $delimiter);
    }

    /**
     * @param string $value
     * @return \PSX\Sql\Field\DateTime
     */
    protected function fieldDateTime($value)
    {
        return new Field\DateTime($value);
    }

    /**
     * @param string $value
     * @return \PSX\Sql\Field\Integer
     */
    protected function fieldInteger($value)
    {
        return new Field\Integer($value);
    }

    /**
     * @param string $value
     * @return \PSX\Sql\Field\Json
     */
    protected function fieldJson($value)
    {
        return new Field\Json($value);
    }

    /**
     * @param string $value
     * @return \PSX\Sql\Field\Number
     */
    protected function fieldNumber($value)
    {
        return new Field\Number($value);
    }

    /**
     * @param string $value
     * @return \PSX\Sql\Field\Replace
     */
    protected function fieldReplace($value)
    {
        return new Field\Replace($value);
    }

    /**
     * @param string $value
     * @param Connection $connection
     * @param integer $type
     * @return \PSX\Sql\Field\Type
     */
    protected function fieldType($value, Connection $connection, $type)
    {
        return new Field\Type($value, $connection, $type);
    }

    /**
     * @param string $value
     * @return \PSX\Sql\Field\Value
     */
    protected function fieldValue($value)
    {
        return new Field\Value($value);
    }
}
