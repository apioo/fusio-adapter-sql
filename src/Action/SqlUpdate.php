<?php
/*
 * Fusio
 * A web-application to create dynamically RESTful APIs
 *
 * Copyright (C) 2015-2023 Christoph Kappestein <christoph.kappestein@gmail.com>
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
use PSX\Http\Exception as StatusCode;
use PSX\Record\Record;

/**
 * Action which allows you to create an API endpoint based on any database
 * table
 *
 * @author  Christoph Kappestein <christoph.kappestein@gmail.com>
 * @license http://www.gnu.org/licenses/agpl-3.0
 * @link    https://www.fusio-project.org/
 */
class SqlUpdate extends SqlManipulationAbstract
{
    public function getName(): string
    {
        return 'SQL-Update';
    }

    public function handle(RequestInterface $request, ParametersInterface $configuration, ContextInterface $context): HttpResponseInterface
    {
        $connection = $this->getConnection($configuration);
        $tableName  = $this->getTableName($configuration);
        $mapping    = $this->getMapping($configuration);

        $id = (int) $request->get('id');
        if (empty($id)) {
            throw new StatusCode\BadRequestException('Id not available');
        }

        $table = $this->getTable($connection, $tableName);
        $key   = $this->getPrimaryKey($table);
        $body  = Record::from($request->getPayload());
        $data  = $this->getData($body, $connection, $table, false, $mapping);

        $existingId = $this->findExistingId($connection, $key, $table, $id);

        $connection->beginTransaction();

        try {
            $affected = $connection->update($table->getName(), $data, [$key => $existingId]);

            $this->insertRelations($connection, $existingId, $body, $mapping);

            $connection->commit();
        } catch (\Exception $e) {
            $connection->rollBack();

            throw $e;
        }

        return $this->response->build(200, [], [
            'success'  => true,
            'message'  => 'Entry successfully updated',
            'id'       => $existingId,
            'affected' => $affected,
        ]);
    }
}
