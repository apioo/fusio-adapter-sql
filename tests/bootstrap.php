<?php

require_once __DIR__ . '/../vendor/autoload.php';

function getConnection()
{
    static $connection;

    if ($connection) {
        return $connection;
    }

    $params = [
        'memory' => true,
        'driver' => 'pdo_sqlite',
    ];

    $config     = new \Doctrine\DBAL\Configuration();
    $connection = \Doctrine\DBAL\DriverManager::getConnection($params, $config);

    $fromSchema = $connection->getSchemaManager()->createSchema();
    $toSchema   = new \Doctrine\DBAL\Schema\Schema();

    $table = $toSchema->createTable('app_news');
    $table->addColumn('id', 'integer', ['autoincrement' => true]);
    $table->addColumn('title', 'string');
    $table->addColumn('content', 'text');
    $table->addColumn('tags', 'json_array', ['notnull' => false]);
    $table->addColumn('date', 'datetime');
    $table->setPrimaryKey(['id']);

    $table = $toSchema->createTable('app_invalid');
    $table->addColumn('id', 'integer');
    $table->addColumn('title', 'string');

    $queries = $fromSchema->getMigrateToSql($toSchema, $connection->getDatabasePlatform());
    foreach ($queries as $query) {
        $connection->query($query);
    }

    return $connection;
}
