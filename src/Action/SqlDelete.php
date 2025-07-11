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

namespace Fusio\Adapter\Sql\Action;

use Fusio\Engine\ContextInterface;
use Fusio\Engine\ParametersInterface;
use Fusio\Engine\RequestInterface;
use PSX\Http\Environment\HttpResponseInterface;
use PSX\Http\Exception as StatusCode;

/**
 * Action which allows you to create an API endpoint based on any database
 * table
 *
 * @author  Christoph Kappestein <christoph.kappestein@gmail.com>
 * @license http://www.apache.org/licenses/LICENSE-2.0
 * @link    https://www.fusio-project.org/
 */
class SqlDelete extends SqlManipulationAbstract
{
    public function getName(): string
    {
        return 'SQL-Delete';
    }

    public function handle(RequestInterface $request, ParametersInterface $configuration, ContextInterface $context): HttpResponseInterface
    {
        $connection = $this->getConnection($configuration);
        $tableName  = $this->getTableName($configuration);
        $mapping    = $this->getMapping($configuration);

        $table = $this->getTable($connection, $tableName);
        $key   = $this->getPrimaryKey($table);

        $existingId = $this->findExistingId($connection, $key, $table, $request);

        $connection->beginTransaction();

        try {
            $this->deleteRelations($connection, $existingId, $mapping);

            $connection->delete($table->getName(), [$key => $existingId]);

            $connection->commit();
        } catch (\Exception $e) {
            $connection->rollBack();

            throw $e;
        }


        return $this->response->build(200, [], [
            'success' => true,
            'message' => 'Entry successfully deleted',
            'id'      => (string) $existingId,
        ]);
    }
}
