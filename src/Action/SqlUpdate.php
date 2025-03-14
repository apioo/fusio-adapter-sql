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
 * @license http://www.apache.org/licenses/LICENSE-2.0
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

        $table = $this->getTable($connection, $tableName);
        $key   = $this->getPrimaryKey($table);
        $body  = Record::from($request->getPayload());
        $data  = $this->getData($body, $connection, $table, false, $mapping);

        $existingId = $this->findExistingId($connection, $key, $table, $request);

        $connection->beginTransaction();

        try {
            $connection->update($table->getName(), $data, [$key => $existingId]);

            $this->insertRelations($connection, $existingId, $body, $mapping);

            $connection->commit();
        } catch (\Exception $e) {
            $connection->rollBack();

            throw $e;
        }

        return $this->response->build(200, [], [
            'success' => true,
            'message' => 'Entry successfully updated',
            'id'      => (string) $existingId,
        ]);
    }
}
