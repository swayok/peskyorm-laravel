<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Support\Arr;
use PeskyORMLaravel\Providers\PeskyOrmServiceProvider;

class TestCase extends \Orchestra\Testbench\TestCase
{
    
    public function setUp(): void
    {
        parent::setUp();
        // additional setup
    }
    
    protected function getPackageProviders($app)
    {
        return [
            PeskyOrmServiceProvider::class,
        ];
    }
    
    protected function defineEnvironment($app)
    {
        $dbConfigs = include $app->basePath('/vendor/swayok/peskyorm/tests/configs/global.php');
        $app['config']->set('database.default', 'pgsql');
        $app['config']->set('database.connections.pgsql', [
            'driver' => 'pgsql',
            'host' => Arr::get($dbConfigs, 'pgsql.host', 'localhost'),
            'port' => Arr::get($dbConfigs, 'pgsql.port') ?: 5432,
            'database' => Arr::get($dbConfigs, 'pgsql.database', 'unknown'),
            'username' => Arr::get($dbConfigs, 'pgsql.username', 'noname'),
            'password' => Arr::get($dbConfigs, 'pgsql.password', 'popassword'),
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
            'schema' => 'public',
            'sslmode' => 'prefer',
        ]);
        $app['config']->set('database.connections.mysql', [
            'driver' => 'mysql',
            'host' => Arr::get($dbConfigs, 'mysql.host', 'localhost'),
            'port' => Arr::get($dbConfigs, 'mysql.port') ?: 5432,
            'database' => Arr::get($dbConfigs, 'mysql.database', 'unknown'),
            'username' => Arr::get($dbConfigs, 'mysql.username', 'noname'),
            'password' => Arr::get($dbConfigs, 'mysql.password', 'popassword'),
            'charset' => 'utf8',
            'collation' => 'utf8_unicode_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => false,
        ]);
    }
}