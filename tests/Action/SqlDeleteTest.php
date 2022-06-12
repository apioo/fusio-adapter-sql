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

namespace Fusio\Adapter\Sql\Tests\Action;

use Fusio\Adapter\Sql\Action\SqlDelete;
use Fusio\Adapter\Sql\Tests\DbTestCase;
use PSX\Http\Environment\HttpResponseInterface;

/**
 * SqlDeleteTest
 *
 * @author  Christoph Kappestein <christoph.kappestein@gmail.com>
 * @license http://www.gnu.org/licenses/agpl-3.0
 * @link    https://www.fusio-project.org/
 */
class SqlDeleteTest extends DbTestCase
{
    public function testHandleDelete()
    {
        $parameters = $this->getParameters([
            'connection' => 1,
            'table'      => 'app_news',
        ]);

        $action   = $this->getActionFactory()->factory(SqlDelete::class);
        $response = $action->handle($this->getRequest('DELETE', ['id' => 1]), $parameters, $this->getContext());

        $result = [
            'success'  => true,
            'message'  => 'Entry successfully deleted',
            'id'       => 1,
            'affected' => 1,
        ];

        $this->assertInstanceOf(HttpResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals([], $response->getHeaders());
        $this->assertEquals($result, $response->getBody());

        $row = $this->connection->fetchAssoc('SELECT id, title, content, date FROM app_news WHERE id = 1');

        $this->assertEmpty($row);
    }
}
