<?php

use Fusio\Adapter\Sql\Action\Query\SqlQueryAll;
use Fusio\Adapter\Sql\Action\Query\SqlQueryRow;
use Fusio\Adapter\Sql\Action\SqlBuilder;
use Fusio\Adapter\Sql\Action\SqlDelete;
use Fusio\Adapter\Sql\Action\SqlInsert;
use Fusio\Adapter\Sql\Action\SqlSelectAll;
use Fusio\Adapter\Sql\Action\SqlSelectRow;
use Fusio\Adapter\Sql\Action\SqlUpdate;
use Fusio\Adapter\Sql\Connection\Sql;
use Fusio\Adapter\Sql\Connection\SqlAdvanced;
use Fusio\Adapter\Sql\Generator\SqlDatabase;
use Fusio\Adapter\Sql\Generator\SqlEntity;
use Fusio\Adapter\Sql\Generator\SqlTable;
use Fusio\Engine\Adapter\ServiceBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container) {
    $services = ServiceBuilder::build($container);
    $services->set(Sql::class);
    $services->set(SqlAdvanced::class);
    $services->set(SqlQueryAll::class);
    $services->set(SqlQueryRow::class);
    $services->set(SqlBuilder::class);
    $services->set(SqlDelete::class);
    $services->set(SqlInsert::class);
    $services->set(SqlSelectAll::class);
    $services->set(SqlSelectRow::class);
    $services->set(SqlUpdate::class);
    $services->set(SqlDatabase::class);
    $services->set(SqlEntity::class);
    $services->set(SqlTable::class);
};
