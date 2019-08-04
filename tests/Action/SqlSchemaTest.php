<?php
/*
 * Fusio
 * A web-application to create dynamically RESTful APIs
 *
 * Copyright (C) 2015-2019 Christoph Kappestein <christoph.kappestein@gmail.com>
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

use Doctrine\DBAL\Schema\Schema;
use Fusio\Adapter\Sql\Action\SqlSchema;
use Fusio\Adapter\Sql\Tests\DbTestCase;
use Fusio\Engine\Form\Builder;
use Fusio\Engine\Form\Container;
use PSX\Http\Environment\HttpResponseInterface;
use PSX\Record\Record;

/**
 * SqlSchemaTest
 *
 * @author  Christoph Kappestein <christoph.kappestein@gmail.com>
 * @license http://www.gnu.org/licenses/agpl-3.0
 * @link    http://fusio-project.org
 */
class SqlSchemaTest extends DbTestCase
{
    public function setUp()
    {
        parent::setUp();

        $schema = $this->connection->getSchemaManager()->createSchema();
        if ($schema->hasTable('foo')) {
            $schema->dropTable('foo');

            $this->migrateTo($schema);
        }
    }

    public function testHandleGetCollection()
    {
        $parameters = $this->getParameters([
            'connection' => 1,
        ]);

        $action   = $this->getActionFactory()->factory(SqlSchema::class);
        $response = $action->handle($this->getRequest(), $parameters, $this->getContext());

        $actual = json_encode($response->getBody(), JSON_PRETTY_PRINT);
        $expect = <<<JSON
{
    "tables": [
        "app_invalid",
        "app_news"
    ]
}
JSON;

        $this->assertInstanceOf(HttpResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals([], $response->getHeaders());
        $this->assertJsonStringEqualsJsonString($expect, $actual, $actual);
    }

    public function testHandleGetEntity()
    {
        $parameters = $this->getParameters([
            'connection' => 1,
        ]);

        $action   = $this->getActionFactory()->factory(SqlSchema::class);
        $response = $action->handle($this->getRequest('GET', ['table' => 'app_news']), $parameters, $this->getContext());

        $actual = json_encode($response->getBody(), JSON_PRETTY_PRINT);
        $expect = <<<JSON
{
    "name": "app_news",
    "columns": {
        "id": {
            "type": "integer",
            "default": null,
            "notnull": true,
            "length": null,
            "precision": 10,
            "scale": 0,
            "fixed": false,
            "unsigned": false,
            "autoincrement": true,
            "comment": null
        },
        "title": {
            "type": "string",
            "default": null,
            "notnull": true,
            "length": 255,
            "precision": 10,
            "scale": 0,
            "fixed": false,
            "unsigned": false,
            "autoincrement": false,
            "comment": null
        },
        "price": {
            "type": "float",
            "default": null,
            "notnull": false,
            "length": null,
            "precision": 10,
            "scale": 0,
            "fixed": false,
            "unsigned": false,
            "autoincrement": false,
            "comment": null
        },
        "content": {
            "type": "text",
            "default": null,
            "notnull": false,
            "length": null,
            "precision": 10,
            "scale": 0,
            "fixed": false,
            "unsigned": false,
            "autoincrement": false,
            "comment": null
        },
        "image": {
            "type": "blob",
            "default": null,
            "notnull": false,
            "length": null,
            "precision": 10,
            "scale": 0,
            "fixed": false,
            "unsigned": false,
            "autoincrement": false,
            "comment": null
        },
        "posted": {
            "type": "time",
            "default": null,
            "notnull": false,
            "length": null,
            "precision": 10,
            "scale": 0,
            "fixed": false,
            "unsigned": false,
            "autoincrement": false,
            "comment": null
        },
        "date": {
            "type": "datetime",
            "default": null,
            "notnull": false,
            "length": null,
            "precision": 10,
            "scale": 0,
            "fixed": false,
            "unsigned": false,
            "autoincrement": false,
            "comment": null
        }
    },
    "indexes": {
        "primary": {
            "columns": [
                "id"
            ],
            "unique": true,
            "primary": true
        }
    }
}
JSON;

        $this->assertInstanceOf(HttpResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals([], $response->getHeaders());
        $this->assertJsonStringEqualsJsonString($expect, $actual, $actual);
    }

    /**
     * @expectedException \PSX\Http\Exception\NotFoundException
     */
    public function testHandleGetEntityInvalidTable()
    {
        $parameters = $this->getParameters([
            'connection' => 1,
        ]);

        $action = $this->getActionFactory()->factory(SqlSchema::class);
        $action->handle($this->getRequest('GET', ['table' => 'baz']), $parameters, $this->getContext());
    }

    public function testHandlePost()
    {
        $parameters = $this->getParameters([
            'connection' => 1,
        ]);

        $body = new Record();
        $body['name'] = 'foo';
        $body['columns'] = (object) [
            'id' => (object) [
                'type' => 'integer'
            ],
            'title' => (object) [
                'type' => 'string',
                'length' => 64
            ],
            'date' => (object) [
                'type' => 'date'
            ],
        ];
        $body['indexes'] = [
            'primary' => (object) [
                'columns' => ['id'],
                'primary' => true,
            ]
        ];

        $action   = $this->getActionFactory()->factory(SqlSchema::class);
        $response = $action->handle($this->getRequest('POST', [], [], [], $body), $parameters, $this->getContext());
        $body     = $response->getBody();

        unset($body['queries']);

        $result = [
            'executed' => true,
            'table' => 'foo',
            'columns' => [
                'id' => [
                    'type' => 'integer',
                    'default' => null,
                    'notnull' => true,
                    'length' => null,
                    'precision' => 10,
                    'scale' => 0,
                    'fixed' => false,
                    'unsigned' => false,
                    'autoincrement' => false,
                    'comment' => null,
                ],
                'title' => [
                    'type' => 'string',
                    'default' => null,
                    'notnull' => true,
                    'length' => 64,
                    'precision' => 10,
                    'scale' => 0,
                    'fixed' => false,
                    'unsigned' => false,
                    'autoincrement' => false,
                    'comment' => null,
                ],
                'date' => [
                    'type' => 'date',
                    'default' => null,
                    'notnull' => true,
                    'length' => null,
                    'precision' => 10,
                    'scale' => 0,
                    'fixed' => false,
                    'unsigned' => false,
                    'autoincrement' => false,
                    'comment' => null,
                ],
            ],
        ];

        $this->assertInstanceOf(HttpResponseInterface::class, $response);
        $this->assertEquals(201, $response->getStatusCode());
        $this->assertEquals([], $response->getHeaders());
        $this->assertEquals($result, $body);

        // check whether the table was created
        $schema  = $this->connection->getSchemaManager()->createSchema();
        $table   = $schema->getTable('foo');
        $columns = $table->getColumns();

        $this->assertEquals(3, count($columns));
        $this->assertArrayHasKey('id', $columns);
        $this->assertArrayHasKey('title', $columns);
        $this->assertArrayHasKey('date', $columns);
        $this->assertEquals('id', $columns['id']->getName());
        $this->assertEquals('integer', $columns['id']->getType()->getName());
        $this->assertEquals('title', $columns['title']->getName());
        $this->assertEquals('string', $columns['title']->getType()->getName());
        $this->assertEquals(64, $columns['title']->getLength());
        $this->assertEquals('date', $columns['date']->getName());
        $this->assertEquals('date', $columns['date']->getType()->getName());
    }

    public function testHandlePostPreview()
    {
        $parameters = $this->getParameters([
            'connection' => 1,
        ]);

        $body = new Record();
        $body['name'] = 'foo';
        $body['columns'] = (object) [
            'id' => (object) [
                'type' => 'integer'
            ],
            'title' => (object) [
                'type' => 'string',
                'length' => 64
            ],
            'date' => (object) [
                'type' => 'date'
            ],
        ];
        $body['indexes'] = [
            'primary' => (object) [
                'columns' => ['id'],
                'primary' => true,
            ]
        ];

        $action   = $this->getActionFactory()->factory(SqlSchema::class);
        $response = $action->handle($this->getRequest('POST', [], ['preview' => 1], [], $body), $parameters, $this->getContext());
        $body     = $response->getBody();

        unset($body['queries']);

        $result = [
            'executed' => false,
            'table' => 'foo',
            'columns' => [
                'id' => [
                    'type' => 'integer',
                    'default' => null,
                    'notnull' => true,
                    'length' => null,
                    'precision' => 10,
                    'scale' => 0,
                    'fixed' => false,
                    'unsigned' => false,
                    'autoincrement' => false,
                    'comment' => null,
                ],
                'title' => [
                    'type' => 'string',
                    'default' => null,
                    'notnull' => true,
                    'length' => 64,
                    'precision' => 10,
                    'scale' => 0,
                    'fixed' => false,
                    'unsigned' => false,
                    'autoincrement' => false,
                    'comment' => null,
                ],
                'date' => [
                    'type' => 'date',
                    'default' => null,
                    'notnull' => true,
                    'length' => null,
                    'precision' => 10,
                    'scale' => 0,
                    'fixed' => false,
                    'unsigned' => false,
                    'autoincrement' => false,
                    'comment' => null,
                ],
            ],
        ];

        $this->assertInstanceOf(HttpResponseInterface::class, $response);
        $this->assertEquals(201, $response->getStatusCode());
        $this->assertEquals([], $response->getHeaders());
        $this->assertEquals($result, $body);

        // check whether the table was created
        $schema = $this->connection->getSchemaManager()->createSchema();

        $this->assertEquals(false, $schema->hasTable('foo'));
    }

    public function testHandlePut()
    {
        $schema = $this->connection->getSchemaManager()->createSchema();
        $table = $schema->createTable('foo');
        $table->addColumn('id', 'integer');

        $this->migrateTo($schema);

        $parameters = $this->getParameters([
            'connection' => 1,
        ]);

        $body = new Record();
        $body['name'] = 'foo';
        $body['columns'] = (object) [
            'id' => (object) [
                'type' => 'integer',
                'autoincrement' => true,
            ],
            'title' => (object) [
                'type' => 'string',
                'length' => 64
            ],
            'date' => (object) [
                'type' => 'date'
            ],
        ];
        $body['indexes'] = [
            'primary' => (object) [
                'columns' => ['id'],
                'primary' => true,
            ]
        ];

        $action   = $this->getActionFactory()->factory(SqlSchema::class);
        $response = $action->handle($this->getRequest('PUT', ['table' => 'foo'], [], [], $body), $parameters, $this->getContext());
        $body     = $response->getBody();

        unset($body['queries']);

        $result = [
            'executed' => true,
            'table' => 'foo',
            'columns' => [
                'id' => [
                    'type' => 'integer',
                    'default' => null,
                    'notnull' => true,
                    'length' => null,
                    'precision' => 10,
                    'scale' => 0,
                    'fixed' => false,
                    'unsigned' => false,
                    'autoincrement' => true,
                    'comment' => null,
                ],
                'title' => [
                    'type' => 'string',
                    'default' => null,
                    'notnull' => true,
                    'length' => 64,
                    'precision' => 10,
                    'scale' => 0,
                    'fixed' => false,
                    'unsigned' => false,
                    'autoincrement' => false,
                    'comment' => null,
                ],
                'date' => [
                    'type' => 'date',
                    'default' => null,
                    'notnull' => true,
                    'length' => null,
                    'precision' => 10,
                    'scale' => 0,
                    'fixed' => false,
                    'unsigned' => false,
                    'autoincrement' => false,
                    'comment' => null,
                ],
            ],
        ];

        $this->assertInstanceOf(HttpResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals([], $response->getHeaders());
        $this->assertEquals($result, $body);

        // check whether the table was updated
        $schema  = $this->connection->getSchemaManager()->createSchema();
        $table   = $schema->getTable('foo');
        $columns = $table->getColumns();

        $this->assertEquals(3, count($columns));
        $this->assertArrayHasKey('id', $columns);
        $this->assertArrayHasKey('title', $columns);
        $this->assertArrayHasKey('date', $columns);
        $this->assertEquals('id', $columns['id']->getName());
        $this->assertEquals('integer', $columns['id']->getType()->getName());
        $this->assertEquals('title', $columns['title']->getName());
        $this->assertEquals('string', $columns['title']->getType()->getName());
        $this->assertEquals(64, $columns['title']->getLength());
        $this->assertEquals('date', $columns['date']->getName());
        $this->assertEquals('date', $columns['date']->getType()->getName());
    }

    public function testHandlePutPreview()
    {
        $schema = $this->connection->getSchemaManager()->createSchema();
        $table = $schema->createTable('foo');
        $table->addColumn('id', 'integer');

        $this->migrateTo($schema);

        $parameters = $this->getParameters([
            'connection' => 1,
        ]);

        $body = new Record();
        $body['name'] = 'foo';
        $body['columns'] = (object) [
            'id' => (object) [
                'type' => 'integer',
                'autoincrement' => true,
            ],
            'title' => (object) [
                'type' => 'string',
                'length' => 64
            ],
            'date' => (object) [
                'type' => 'date'
            ],
        ];
        $body['indexes'] = [
            'primary' => (object) [
                'columns' => ['id'],
                'primary' => true,
            ]
        ];

        $action   = $this->getActionFactory()->factory(SqlSchema::class);
        $response = $action->handle($this->getRequest('PUT', ['table' => 'foo'], ['preview' => 1], [], $body), $parameters, $this->getContext());
        $body     = $response->getBody();

        unset($body['queries']);

        $result = [
            'executed' => false,
            'table' => 'foo',
            'columns' => [
                'id' => [
                    'type' => 'integer',
                    'default' => null,
                    'notnull' => true,
                    'length' => null,
                    'precision' => 10,
                    'scale' => 0,
                    'fixed' => false,
                    'unsigned' => false,
                    'autoincrement' => true,
                    'comment' => null,
                ],
                'title' => [
                    'type' => 'string',
                    'default' => null,
                    'notnull' => true,
                    'length' => 64,
                    'precision' => 10,
                    'scale' => 0,
                    'fixed' => false,
                    'unsigned' => false,
                    'autoincrement' => false,
                    'comment' => null,
                ],
                'date' => [
                    'type' => 'date',
                    'default' => null,
                    'notnull' => true,
                    'length' => null,
                    'precision' => 10,
                    'scale' => 0,
                    'fixed' => false,
                    'unsigned' => false,
                    'autoincrement' => false,
                    'comment' => null,
                ],
            ],
        ];

        $this->assertInstanceOf(HttpResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals([], $response->getHeaders());
        $this->assertEquals($result, $body);

        // check whether the table was updated
        $schema  = $this->connection->getSchemaManager()->createSchema();
        $table   = $schema->getTable('foo');
        $columns = $table->getColumns();

        $this->assertEquals(1, count($columns));
        $this->assertArrayHasKey('id', $columns);
        $this->assertEquals('id', $columns['id']->getName());
        $this->assertEquals('integer', $columns['id']->getType()->getName());
    }

    public function testHandleDelete()
    {
        $schema = $this->connection->getSchemaManager()->createSchema();
        $table = $schema->createTable('foo');
        $table->addColumn('id', 'integer');

        $this->migrateTo($schema);

        $parameters = $this->getParameters([
            'connection' => 1,
        ]);

        $action   = $this->getActionFactory()->factory(SqlSchema::class);
        $response = $action->handle($this->getRequest('DELETE', ['table' => 'foo']), $parameters, $this->getContext());
        $body     = $response->getBody();

        unset($body['queries']);

        $result = [
            'executed' => true,
        ];

        $this->assertInstanceOf(HttpResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals([], $response->getHeaders());
        $this->assertEquals($result, $body);

        // check whether the table was deleted
        $schema = $this->connection->getSchemaManager()->createSchema();

        $this->assertEquals(false, $schema->hasTable('foo'));
    }

    public function testHandleDeletePreview()
    {
        $schema = $this->connection->getSchemaManager()->createSchema();
        $table = $schema->createTable('foo');
        $table->addColumn('id', 'integer');

        $this->migrateTo($schema);

        $parameters = $this->getParameters([
            'connection' => 1,
        ]);

        $action   = $this->getActionFactory()->factory(SqlSchema::class);
        $response = $action->handle($this->getRequest('DELETE', ['table' => 'foo'], ['preview' => 1]), $parameters, $this->getContext());
        $body     = $response->getBody();

        unset($body['queries']);

        $result = [
            'executed' => false,
        ];

        $this->assertInstanceOf(HttpResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals([], $response->getHeaders());
        $this->assertEquals($result, $body);

        // check whether the table was deleted
        $schema = $this->connection->getSchemaManager()->createSchema();

        $this->assertEquals(true, $schema->hasTable('foo'));
    }

    private function migrateTo(Schema $toSchema)
    {
        $fromSchema = $this->connection->getSchemaManager()->createSchema();
        $queries    = $fromSchema->getMigrateToSql($toSchema, $this->connection->getDatabasePlatform());

        foreach ($queries as $query) {
            $this->connection->query($query);
        }
    }
}
