<?php

namespace PeskyORMLaravel\Providers;

use Illuminate\Support\ServiceProvider;
use PeskyORM\Core\DbAdapter;
use PeskyORM\Core\DbAdapterInterface;
use PeskyORM\Core\DbConnectionsManager;
use PeskyORMLaravel\Console\Commands\OrmGenerateMigrationCommand;
use PeskyORMLaravel\Console\Commands\OrmMakeDbClassesCommand;

class PeskyOrmServiceProvider extends ServiceProvider {

    static protected $drivers = [
        'mysql',
        'pgsql'
    ];

    public function boot() {
        $this->mergeConfigFrom($this->getConfigFilePath(), 'peskyorm');
        $connections = config('database.connections');
        $default = config('database.default');
        $timezone = config('app.timezone');
        if (is_array($connections)) {
            try {
                foreach ($connections as $name => $connectionConfig) {
                    if (
                        in_array(strtolower(array_get($connectionConfig, 'driver', '')), static::$drivers)
                        && !empty($connectionConfig['password'])
                    ) {
                        $connection = DbConnectionsManager::createConnectionFromArray($name, $connectionConfig, $this->app->runningInConsole());
                        if ($name === $default) {
                            DbConnectionsManager::addAlternativeNameForConnection($name, 'default', $this->app->runningInConsole());
                        }
                        
                        if ($timezone) {
                            $connection->onConnect(function (DbAdapterInterface $adapter) use ($timezone) {
                                $adapter->setTimezone($timezone);
                            }, 'timezone');
                        }
                    }
                }
            } catch (\InvalidArgumentException $exception) {

            }
        }
        $this->configurePublishes();
        $this->addPdoCollectorForDebugbar();
    }

    protected function addPdoCollectorForDebugbar() {
        $pdoWrapper = config('peskyorm.pdo_wrapper');
        if ($pdoWrapper) {
            if ($pdoWrapper instanceof \PeskyORMLaravel\Profiling\PeskyOrmDebugBarPdoTracer) {
                if (app()->offsetExists('debugbar') && debugbar()->isEnabled()) {
                    $timeCollector = debugbar()->hasCollector('time') ? debugbar()->getCollector('time') : null;
                    $pdoCollector = new DebugBar\DataCollector\PDO\PDOCollector(null, $timeCollector);
                    $pdoCollector->setRenderSqlWithParams(true);
                    debugbar()->addCollector($pdoCollector);
                    DbAdapter::setConnectionWrapper(function (DbAdapterInterface $adapter, \PDO $pdo) {
                        $pdoTracer = new \PeskyORMLaravel\Profiling\PeskyOrmDebugBarPdoTracer($pdo);
                        if (debugbar()->hasCollector('pdo')) {
                            debugbar()->getCollector('pdo')->addConnection(
                                $pdoTracer,
                                $adapter->getConnectionConfig()->getDbName()
                            );
                        }

                        return $pdoTracer;
                    });
                }
            } else {
                DbAdapter::setConnectionWrapper(function (DbAdapterInterface $adapter, \PDO $pdo) use ($pdoWrapper) {
                    $name = $adapter->getConnectionConfig()->getName() . ' (DB: ' . $adapter->getConnectionConfig()->getDbName() . ')';
                    return new $pdoWrapper($pdo, $name);
                });
            }
        }
    }

    public function register() {
        \Auth::provider('peskyorm', function($app, $config) {
            return new PeskyOrmUserProvider(array_get($config, 'model'), (array)array_get($config, 'relations', []));
        });

        \App::singleton('peskyorm.connection', function () {
            DbConnectionsManager::getConnection('default');
        });

        $this->app->register(PeskyValidationServiceProvider::class);

        $this->registerCommands();
    }

    public function provides() {
        return [
            'peskyorm.connection'
        ];
    }

    protected function getConfigFilePath() {
        return __DIR__ . '/../Config/peskyorm.config.php';
    }

    protected function configurePublishes() {
        $this->publishes([
            $this->getConfigFilePath() => config_path('peskyorm.php'),
        ], 'config');
    }

    protected function registerCommands() {
        $this->app->singleton('command.orm.make-db-classes', function() {
            return new OrmMakeDbClassesCommand();
        });
        $this->commands('command.orm.make-db-classes');

        $this->app->singleton('command.orm.generate-migration', function() {
            return new OrmGenerateMigrationCommand($this->app['composer']);
        });
        $this->commands('command.orm.generate-migration');
    }
}
