<?php

declare(strict_types=1);

namespace PeskyORMLaravel\Tests\Unit;

use Illuminate\Validation\Factory;
use PeskyORM\Adapter\Mysql;
use PeskyORM\Adapter\Postgres;
use PeskyORM\Config\Connection\DbConnectionsFacade;
use PeskyORM\ORM\Record\Record;
use PeskyORMLaravel\PeskyOrmDatabasePresenceVerifier;
use PeskyORMLaravel\Providers\PeskyOrmServiceProvider;
use PeskyORMLaravel\Providers\PeskyOrmUserProvider;
use PeskyORMLaravel\Providers\PeskyValidationServiceProvider;
use PeskyORMLaravel\Tests\TestCase;
use Swayok\Utils\ReflectionUtils;

class ProvidersTest extends TestCase
{
    public function testServiceProviderDbConnections(): void
    {
        $connectionsConfigs = $this->app['config']->get('database.connections');
        $supportedDrivers = ReflectionUtils::getObjectPropertyValue(
            new PeskyOrmServiceProvider($this->app),
            'drivers'
        );
        $supportedConnectionConfigs = array_filter(
            $connectionsConfigs,
            static function ($config) use ($supportedDrivers) {
                return in_array($config['driver'], $supportedDrivers, true);
            }
        );
        foreach ($supportedConnectionConfigs as $name => $config) {
            $connection = DbConnectionsFacade::getConnection($name);
            switch ($config['driver']) {
                case 'pgsql':
                    static::assertInstanceOf(Postgres::class, $connection);
                    break;
                case 'mysql':
                    static::assertInstanceOf(Mysql::class, $connection);
                    break;
                default:
                    static::fail('Unknown driver');
            }
        }
        // default connection
        $defaultConnectionName = $this->app['config']->get('database.default');
        $defaultConnection = DbConnectionsFacade::getConnection('default');
        $connection = DbConnectionsFacade::getConnection($defaultConnectionName);
        self::assertSame($connection, $defaultConnection);
    }
    
    public function testServiceProviderRegisters(): void
    {
        // validation service provider
        static::assertTrue(
            $this->app->providerIsLoaded(PeskyValidationServiceProvider::class)
        );
        // user provider for auth
        $providers = ReflectionUtils::getObjectPropertyValue(
            $this->app['auth'],
            'customProviderCreators'
        );
        static::assertArrayHasKey('peskyorm', $providers);
        static::assertInstanceOf(
            PeskyOrmUserProvider::class,
            $providers['peskyorm']($this->app, ['model' => Record::class])
        );
        // commands
        static::assertTrue($this->app->has('command.orm.make-db-classes'));
        static::assertTrue($this->app->has('command.orm.generate-migration'));
    }
    
    public function testValidationServiceProviderValidators(): void
    {
        static::assertTrue($this->app->has('validation.presence'));
        static::assertInstanceOf(
            PeskyOrmDatabasePresenceVerifier::class,
            $this->app->get('validation.presence')
        );
        /** @var Factory $validator */
        $validator = $this->app['validator'];
        $extensions = ReflectionUtils::getObjectPropertyValue($validator, 'extensions');
        $replacers = ReflectionUtils::getObjectPropertyValue($validator, 'replacers');
        static::assertArrayHasKey('exists-eloquent', $extensions);
        static::assertArrayHasKey('exists-eloquent', $replacers);
        static::assertArrayHasKey('unique_ceseinsensitive', $extensions);
        static::assertArrayHasKey('unique_ceseinsensitive', $replacers);
    }
}