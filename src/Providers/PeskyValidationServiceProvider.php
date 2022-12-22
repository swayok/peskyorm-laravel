<?php

declare(strict_types=1);

namespace PeskyORMLaravel\Providers;

use Illuminate\Validation\DatabasePresenceVerifier;
use Illuminate\Validation\Factory;
use Illuminate\Validation\ValidationServiceProvider;
use PeskyORMLaravel\PeskyOrmDatabasePresenceVerifier;

class PeskyValidationServiceProvider extends ValidationServiceProvider
{
    public function boot(): void
    {
        $this->addAlternativeExistsValidator();
        $this->addCaseInsensitiveUniquenessValidator();
    }

    protected function registerPresenceVerifier(): void
    {
        $this->app->singleton('validation.presence', function () {
            return new PeskyOrmDatabasePresenceVerifier();
        });
    }

    protected function getValidator(): Factory
    {
        return $this->app['validator'];
    }

    /**
     * Alternative 'exists' validator that uses default laravel's DatabasePresenceVerifier
     * Error message is: trans('validation.exists', ['attribute' => $attribute])
     */
    protected function addAlternativeExistsValidator(): void
    {
        $this->getValidator()->extend(
            'exists-eloquent',
            function ($attribute, $value, $parameters) {
                $validator = $this->getValidator()->make(
                    [$attribute => $value],
                    [$attribute => 'exists:' . implode(',', $parameters)]
                );
                $verifier = new DatabasePresenceVerifier($this->app->make('db'));
                $validator->setPresenceVerifier($verifier);
                return $validator->passes();
            }
        );
        $this->getValidator()->replacer(
            'exists-eloquent',
            function ($message, $attribute) {
                return trans('validation.exists', ['attribute' => $attribute]);
            }
        );
    }

    protected function addCaseInsensitiveUniquenessValidator(): void
    {
        $this->getValidator()->extend(
            'unique_ceseinsensitive',
            function ($attribute, $value, $parameters) {
                $validator = $this->getValidator()->make(
                    [$attribute => $value],
                    [$attribute => 'unique:' . implode(',', $parameters)]
                );
                $verifier = new PeskyOrmDatabasePresenceVerifier();
                $verifier->enableCaseInsensitiveMode();
                $validator->setPresenceVerifier($verifier);
                return $validator->passes();
            }
        );
        $this->getValidator()->replacer(
            'unique_ceseinsensitive',
            function ($message, $attribute) {
                return trans('validation.unique', ['attribute' => $attribute]);
            }
        );
    }

}