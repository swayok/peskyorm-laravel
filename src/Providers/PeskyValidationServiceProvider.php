<?php

declare(strict_types=1);

namespace PeskyORMLaravel\Providers;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\DatabasePresenceVerifier;
use Illuminate\Validation\ValidationServiceProvider;
use PeskyORMLaravel\PeskyOrmDatabasePresenceVerifier;

class PeskyValidationServiceProvider extends ValidationServiceProvider
{
    
    public function boot(): void
    {
        $this->addAlternativeExistsValidator();
        $this->addCaseInsensitiveUniquenessValidator();
    }
    
    protected function registerPresenceVerifier()
    {
        $this->app->singleton('validation.presence', function ($app) {
            return new PeskyOrmDatabasePresenceVerifier();
        });
    }
    
    /**
     * Alternative 'exists' validator that uses default laravel's DatabasePresenceVerifier
     * Error message is: trans('validation.exists', ['attribute' => $attribute])
     */
    protected function addAlternativeExistsValidator(): void
    {
        Validator::extend('exists-eloquent', function ($attribute, $value, $parameters) {
            $validator = Validator::make([$attribute => $value], [$attribute => 'exists:' . implode(',', $parameters)]);
            $validator->setPresenceVerifier(new DatabasePresenceVerifier(app('db')));
            return $validator->passes();
        });
        Validator::replacer('exists-eloquent', function ($message, $attribute, $rule, $parameters) {
            return trans('validation.exists', ['attribute' => $attribute]);
        });
    }
    
    protected function addCaseInsensitiveUniquenessValidator(): void
    {
        Validator::extend('unique_ceseinsensitive', function ($attribute, $value, $parameters) {
            $validator = Validator::make([$attribute => $value], [$attribute => 'unique:' . implode(',', $parameters)]);
            $verifier = new PeskyOrmDatabasePresenceVerifier();
            $validator->setPresenceVerifier($verifier->enableCaseInsensitiveMode());
            return $validator->passes();
        });
        Validator::replacer('unique_ceseinsensitive', function ($message, $attribute, $rule, $parameters) {
            return trans('validation.unique', ['attribute' => $attribute]);
        });
    }
    
}