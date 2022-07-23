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

use Fusio\Engine\ContextInterface;
use Fusio\Engine\ParametersInterface;
use Fusio\Engine\RequestInterface;
use PSX\Http\Environment\HttpResponseInterface;
use PSX\Record\Record;

/**
 * Action which allows you to create an API endpoint based on any database
 * table
 *
 * @author  Christoph Kappestein <christoph.kappestein@gmail.com>
 * @license http://www.gnu.org/licenses/agpl-3.0
 * @link    https://www.fusio-project.org/
 */
class SqlInsert extends SqlManipulationAbstract
{
    public function getName(): string
    {
        return 'SQL-Insert';
    }

    public function handle(RequestInterface $request, ParametersInterface $configuration, ContextInterface $context): HttpResponseInterface
    {
        $connection = $this->getConnection($configuration);
        $tableName  = $this->getTableName($configuration);
        $mapping    = $this->getMapping($configuration);

        $table = $this->getTable($connection, $tableName);
        $body  = Record::from($request->getPayload());
        $data  = $this->getData($body, $connection, $table, true, $mapping);

        $connection->beginTransaction();

        try {
            $affected = $connection->insert($table->getName(), $data);

            $lastInsertId = (int) $connection->lastInsertId();

            $this->insertRelations($connection, $lastInsertId, $body, $mapping);

            $connection->commit();
        } catch (\Exception $e) {
            $connection->rollBack();

            throw $e;
        }

        return $this->response->build(201, [], [
            'success'  => true,
            'message'  => 'Entry successfully created',
            'id'       => $lastInsertId,
            'affected' => $affected,
        ]);
    }
}
