<?php

namespace PeskyORMLaravel\Tests\Unit;

use Illuminate\Validation\Factory;
use PeskyORM\Core\DbConnectionsManager;
use PeskyORM\ORM\FakeRecord;
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
        $supportedDrivers = ReflectionUtils::getObjectPropertyValue(new PeskyOrmServiceProvider($this->app), 'drivers');
        $supportedConnectionConfigs = array_filter($connectionsConfigs, function ($config) use ($supportedDrivers) {
            return in_array($config['driver'], $supportedDrivers, true);
        });
        $connections = DbConnectionsManager::getAll();
        static::assertArrayHasKey('default', $connections);
        static::assertCount(count($supportedConnectionConfigs) + 1, $connections);
        static::assertEquals(array_merge(array_keys($supportedConnectionConfigs), ['default']), array_keys($connections));
        
        $defaultConnectionName = $this->app['config']->get('database.default');
        static::assertArrayHasKey($defaultConnectionName, $supportedConnectionConfigs);
        static::assertArrayHasKey($defaultConnectionName, $connections);
        static::assertSame($connections[$defaultConnectionName], $connections['default']);
        static::assertSame($connections[$defaultConnectionName], DbConnectionsManager::getConnection('default'));
    }
    
    public function testServiceProviderRegisters(): void
    {
        // validation service provider
        static::assertTrue($this->app->providerIsLoaded(PeskyValidationServiceProvider::class));
        // user provider for auth
        $providers = ReflectionUtils::getObjectPropertyValue($this->app['auth'], 'customProviderCreators');
        static::assertArrayHasKey('peskyorm', $providers);
        static::assertInstanceOf(PeskyOrmUserProvider::class, $providers['peskyorm']($this->app, ['model' => FakeRecord::class]));
        // default connection
        static::assertTrue($this->app->has('peskyorm.connection'));
        static::assertSame($this->app->get('peskyorm.connection'), DbConnectionsManager::getConnection('default'));
        // commands
        static::assertTrue($this->app->has('command.orm.make-db-classes'));
        static::assertTrue($this->app->has('command.orm.generate-migration'));
    }
    
    public function testValidationServiceProviderValidators(): void
    {
        static::assertTrue($this->app->has('validation.presence'));
        static::assertInstanceOf(PeskyOrmDatabasePresenceVerifier::class, $this->app->get('validation.presence'));
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