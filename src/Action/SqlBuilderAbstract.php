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
 * @link    https://www.fusio-project.org/
 */
abstract class SqlBuilderAbstract extends ActionAbstract
{
    protected Builder $builder;

    public function __construct()
    {
        $this->builder = new Builder();
    }

    protected function build(array $definition): mixed
    {
        return $this->builder->build($definition);
    }

    protected function doCollection(Connection $connection, string $sql, array $arguments, array $definition, ?string $key = null, ?\Closure $filter = null): Provider\ProviderCollectionInterface
    {
        return new Provider\DBAL\Collection($connection, $sql, $arguments, $definition, $key, $filter);
    }

    protected function doEntity(Connection $connection, string $sql, array $arguments, array $definition): Provider\ProviderEntityInterface
    {
        return new Provider\DBAL\Entity($connection, $sql, $arguments, $definition);
    }

    protected function doColumn(Connection $connection, string $sql, array $arguments, mixed $definition): Provider\ProviderColumnInterface
    {
        return new Provider\DBAL\Column($connection, $sql, $arguments, $definition);
    }

    protected function doValue(Connection $connection, string $sql, array $arguments = [], mixed $definition = null): Provider\ProviderValueInterface
    {
        return new Provider\DBAL\Value($connection, $sql, $arguments, $definition);
    }

    protected function fieldBoolean(string $value): Field\Boolean
    {
        return new Field\Boolean($value);
    }

    protected function fieldCallback(string $key, \Closure $callback): Field\Callback
    {
        return new Field\Callback($key, $callback);
    }

    protected function fieldCsv(string $key, string $delimiter = ','): Field\Csv
    {
        return new Field\Csv($key, $delimiter);
    }

    protected function fieldDateTime(string $value): Field\DateTime
    {
        return new Field\DateTime($value);
    }

    protected function fieldInteger(string $value): Field\Integer
    {
        return new Field\Integer($value);
    }

    protected function fieldJson(string $value): Field\Json
    {
        return new Field\Json($value);
    }

    protected function fieldNumber(string $value): Field\Number
    {
        return new Field\Number($value);
    }

    protected function fieldReplace(string $value): Field\Replace
    {
        return new Field\Replace($value);
    }

    protected function fieldType(string $value, Connection $connection, int $type): Field\Type
    {
        return new Field\Type($value, $connection, $type);
    }

    protected function fieldValue(string $value): Field\Value
    {
        return new Field\Value($value);
    }
}
