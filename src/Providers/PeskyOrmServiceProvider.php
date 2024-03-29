<?php

declare(strict_types=1);

namespace PeskyORMLaravel\Providers;

use Illuminate\Support\Arr;
use Illuminate\Support\ServiceProvider;
use PeskyORM\Core\DbAdapter;
use PeskyORM\Core\DbAdapterInterface;
use PeskyORM\Core\DbConnectionsManager;
use PeskyORMLaravel\Console\Commands\OrmGenerateMigrationCommand;
use PeskyORMLaravel\Console\Commands\OrmMakeDbClassesCommand;
use PeskyORMLaravel\Profiling\PeskyOrmDebugBarPdoTracer;

class PeskyOrmServiceProvider extends ServiceProvider
{
    
    protected static array $drivers = [
        'mysql',
        'pgsql',
    ];
    
    public function boot(): void
    {
        $this->mergeConfigFrom($this->getConfigFilePath(), 'peskyorm');
        $connections = $this->app['config']->get('database.connections');
        $default = $this->app['config']->get('database.default');
        $timezone = $this->app['config']->get('app.timezone');
        if (is_array($connections)) {
            try {
                foreach ($connections as $name => $connectionConfig) {
                    if (
                        in_array(strtolower(Arr::get($connectionConfig, 'driver', '')), static::$drivers, true)
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
    
    protected function addPdoCollectorForDebugbar(): void
    {
        $pdoWrapper = $this->app['config']->get('peskyorm.pdo_wrapper');
        if ($pdoWrapper) {
            if ($pdoWrapper instanceof PeskyOrmDebugBarPdoTracer) {
                if (app()->offsetExists('debugbar') && app('debugbar')->isEnabled()) {
                    $debugBar = app('debugbar');
                    $timeCollector = $debugBar->hasCollector('time') ? $debugBar->getCollector('time') : null;
                    $pdoCollector = new DebugBar\DataCollector\PDO\PDOCollector(null, $timeCollector);
                    $pdoCollector->setRenderSqlWithParams(true);
                    $debugBar->addCollector($pdoCollector);
                    DbAdapter::setConnectionWrapper(function (DbAdapterInterface $adapter, \PDO $pdo) use ($debugBar) {
                        /** @noinspection PhpMethodParametersCountMismatchInspection */
                        $pdoTracer = new PeskyOrmDebugBarPdoTracer($pdo);
                        if ($debugBar->hasCollector('pdo')) {
                            $debugBar
                                ->getCollector('pdo')
                                ->addConnection(
                                    $pdoTracer,
                                    $adapter->getConnectionConfig()
                                        ->getDbName()
                                );
                        }
                        
                        return $pdoTracer;
                    });
                }
            } else {
                DbAdapter::setConnectionWrapper(function (DbAdapterInterface $adapter, \PDO $pdo) use ($pdoWrapper) {
                    $name = $adapter->getConnectionConfig()
                            ->getName() . ' (DB: ' . $adapter->getConnectionConfig()
                            ->getDbName() . ')';
                    return new $pdoWrapper($pdo, $name);
                });
            }
        }
    }
    
    public function register(): void
    {
        $this->app['auth']->provider('peskyorm', function ($app, $config) {
            return new PeskyOrmUserProvider(Arr::get($config, 'model'), (array)Arr::get($config, 'relations', []));
        });
        
        $this->app->singleton('peskyorm.connection', function () {
            return DbConnectionsManager::getConnection('default');
        });
        
        $this->app->register(PeskyValidationServiceProvider::class);
        
        $this->registerCommands();
    }
    
    public function provides(): array
    {
        return [
            'peskyorm.connection',
        ];
    }
    
    protected function getConfigFilePath(): string
    {
        return __DIR__ . '/../Config/peskyorm.config.php';
    }
    
    protected function configurePublishes(): void
    {
        $this->publishes([
            $this->getConfigFilePath() => config_path('peskyorm.php'),
        ], 'config');
    }
    
    protected function registerCommands(): void
    {
        $this->app->singleton('command.orm.make-db-classes', function () {
            return new OrmMakeDbClassesCommand();
        });
        $this->commands('command.orm.make-db-classes');
        
        $this->app->singleton('command.orm.generate-migration', function () {
            return new OrmGenerateMigrationCommand($this->app['composer']);
        });
        $this->commands('command.orm.generate-migration');
    }
}
