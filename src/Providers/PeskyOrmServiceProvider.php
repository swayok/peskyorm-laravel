<?php

declare(strict_types=1);

namespace PeskyORMLaravel\Providers;

use Illuminate\Config\Repository as ConfigsRepository;
use Illuminate\Support\Arr;
use Illuminate\Support\ServiceProvider;
use PeskyORM\Adapter\DbAdapterInterface;
use PeskyORM\Config\Connection\DbConnectionConfigAbstract;
use PeskyORM\Config\Connection\DbConnectionConfigInterface;
use PeskyORM\Config\Connection\DbConnectionsFacade;
use PeskyORM\ORM\TableStructure\TableColumnFactory;
use PeskyORM\ORM\TableStructure\TableColumnFactoryInterface;
use PeskyORM\Utils\ArgumentValidators;
use PeskyORM\Utils\ServiceContainer;
use PeskyORMLaravel\Console\Commands\OrmGenerateMigrationCommand;
use PeskyORMLaravel\Console\Commands\OrmMakeDbClassesCommand;
use PeskyORMLaravel\OrmServiceContainerAdapter;
use PeskyORMLaravel\Profiling\PeskyOrmDebugBarPdoTracer;

class PeskyOrmServiceProvider extends ServiceProvider
{
    protected static array $drivers = [
        ServiceContainer::MYSQL,
        ServiceContainer::POSTGRES,
    ];

    public function register(): void
    {
        $this->app->make('auth')->provider('peskyorm', function ($app, $config) {
            return new PeskyOrmUserProvider(
                Arr::get($config, 'model'),
                (array)Arr::get($config, 'relations', [])
            );
        });

        $this->app->register(PeskyValidationServiceProvider::class);

        $this->registerCommands();
    }

    public function boot(): void
    {
        $this->mergeConfigFrom($this->getConfigFilePath(), 'peskyorm');
        $this->configureServiceContainer();
        $this->configureDbConnections();
        $this->configurePublishes();
    }

    protected function getConfigsRepository(): ConfigsRepository
    {
        return $this->app->make('config');
    }

    protected function configureDbConnections(): void
    {
        $configs = $this->getConfigsRepository();
        $connections = $configs->get('database.connections');
        $default = $configs->get('database.default');
        $timezone = $configs->get('app.timezone');
        if (is_array($connections)) {
            foreach ($connections as $name => $configsArray) {
                if (
                    in_array(
                        strtolower(Arr::get($configsArray, 'driver', '')),
                        static::$drivers,
                        true
                    )
                    && !empty($configsArray['password'])
                ) {
                    DbConnectionsFacade::registerConnection(
                        $name,
                        $configsArray['driver'],
                        function () use ($timezone, $configsArray, $name): DbConnectionConfigInterface {
                            $connectionConfig = DbConnectionsFacade::createConnectionConfigFromArray(
                                $name,
                                $configsArray
                            );
                            if ($timezone) {
                                $connectionConfig->setTimezone($timezone);
                            }
                            $this->addPdoWrapperToConnection($connectionConfig);
                            return $connectionConfig;
                        }
                    );

                    if ($name === $default) {
                        DbConnectionsFacade::registerAliasForConnection(
                            $name,
                            'default'
                        );
                    }
                }
            }
        }
    }

    protected function addPdoWrapperToConnection(DbConnectionConfigInterface $connectionConfig): void
    {
        $pdoWrapper = $this->getConfigsRepository()->get('peskyorm.pdo_wrapper');
        if ($pdoWrapper) {
            $wrapperClosure = null;
            if ($pdoWrapper instanceof PeskyOrmDebugBarPdoTracer) {
                if (
                    $this->app->has('debugbar')
                    && $this->app->make('debugbar')->isEnabled()
                ) {
                    $debugBar = $this->app->make('debugbar');
                    $timeCollector = $debugBar->hasCollector('time')
                        ? $debugBar->getCollector('time')
                        : null;
                    /** @noinspection PhpUndefinedClassInspection */
                    /** @noinspection PhpUndefinedNamespaceInspection */
                    $pdoCollector = new DebugBar\DataCollector\PDO\PDOCollector(
                        null,
                        $timeCollector
                    );
                    $pdoCollector->setRenderSqlWithParams(true);
                    $debugBar->addCollector($pdoCollector);
                    $wrapperClosure = $this->getConnectionWrapperClosureForDebugBar(
                        $debugBar
                    );
                }
            } else {
                $wrapperClosure = $this->getConnectionWrapperClosureForPdoWrapperClass(
                    $pdoWrapper
                );
            }
            if ($wrapperClosure) {
                $connectionConfig->addOnConnectCallback(
                    function (DbAdapterInterface $adapter) use ($wrapperClosure) {
                        $adapter->setConnectionWrapper($wrapperClosure);
                    }
                );
            }
        }
    }

    protected function getConnectionWrapperClosureForDebugBar($debugBar): \Closure
    {
        return static function (DbAdapterInterface $adapter, \PDO $pdo) use ($debugBar) {
            /** @noinspection PhpMethodParametersCountMismatchInspection */
            $pdoTracer = new PeskyOrmDebugBarPdoTracer($pdo);
            if ($debugBar->hasCollector('pdo')) {
                $debugBar
                    ->getCollector('pdo')
                    ->addConnection(
                        $pdoTracer,
                        $adapter->getConnectionConfig()->getDbName()
                    );
            }
            return $pdoTracer;
        };
    }

    protected function getConnectionWrapperClosureForPdoWrapperClass(string $pdoWrapperClass): \Closure
    {
        return static function (DbAdapterInterface $adapter, \PDO $pdo) use ($pdoWrapperClass) {
            $connectionConfig = $adapter->getConnectionConfig();
            $name = $connectionConfig->getName() . ' (DB: ' . $connectionConfig->getDbName() . ')';
            return new $pdoWrapperClass($pdo, $name);
        };
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

    protected function configureServiceContainer(): void
    {
        ServiceContainer::replaceContainer(new OrmServiceContainerAdapter($this->app));
        $configs = $this->getConfigsRepository();
        $columnFactoryClass = $configs->get('peskyorm.column_factory', TableColumnFactory::class);
        ArgumentValidators::assertClassImplementsInterface(
            "config('peskyorm.column_factory')",
            $columnFactoryClass,
            TableColumnFactoryInterface::class
        );
        /** @var TableColumnFactoryInterface $columnFactoryClass */
        ServiceContainer::getInstance()->bind(
            TableColumnFactoryInterface::class,
            function () use ($configs, $columnFactoryClass): TableColumnFactoryInterface {
                $factory = new $columnFactoryClass();
                $typeToColumn = $configs->get('peskyorm.type_to_column', []);
                foreach ($typeToColumn as $type => $columnClass) {
                    $factory->mapTypeToColumnClass($type, $columnClass);
                }
                $nameToColumn = $configs->get('peskyorm.name_to_column', []);
                foreach ($nameToColumn as $name => $columnClass) {
                    $factory->mapNameToColumnClass($name, $columnClass);
                }
                return $factory;
            }, true
        );
    }

    protected function registerCommands(): void
    {
        $this->app->singleton('command.orm.make-db-classes', function () {
            return new OrmMakeDbClassesCommand($this->app->make('config'));
        });
        $this->commands('command.orm.make-db-classes');

        $this->app->singleton('command.orm.generate-migration', function () {
            return new OrmGenerateMigrationCommand($this->app->make('composer'));
        });
        $this->commands('command.orm.generate-migration');
    }
}
