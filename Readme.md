# What is this?
This is a package with classes used to replace Laravel's Eloquent ORM by PeskyORM

## Installation

**Add service provider**

Add `\PeskyORMLaravel\Providers\PeskyOrmServiceProvider::class` to `providers` array in `config/app.php`

This will also register: 
- `\PeskyORMLaravel\Providers\PeskyValidationServiceProvider` - several situational validators;
- `\PeskyORMLaravel\Providers\PeskyOrmUserProvider` - `Auth` will use PeskyORM and its Record object to manage authorisation. Which Record class to use is configured in `config/auth.php` in `providers` array:

        'providers' => [
            'frontend' => [
                'driver' => 'peskyorm',
                'model' => \App\Db\User\User::class,
            ]
        ]

- `\PeskyORMLaravel\Console\Commands\OrmMakeDbClasses` Command (`php artisan orm:make-db-classes`) - generates DB classes by table name
- If you have DebugBar package enabled - it will be configured to display queries executed by PeskyOrm adapters (only if this functionality is enabled in DebugBar)

**Publish config using artisan**

`php artisan vendor:publish --tag=config --force`

This will add `config/peskyorm.php` file 

## Notes

1. You may remove `'Eloquent' => Illuminate\Database\Eloquent\Model::class` form `helpers` array in `config/app.php` if you're not going to use it along with PeskyORM
2. You may also remove `Illuminate\Pagination\PaginationServiceProvider::class` and `Illuminate\Auth\Passwords\PasswordResetServiceProvider::class` form `providers` (don't forget to remove `'Password' => Illuminate\Support\Facades\Password::class` helper) because PeskyORM does not support these currently.
3. Do not remove Laravel's `DatabaseServiceProvider` - some parts of Laravel use it to do service things like migrations, db seeding, etc.
4. Do not remove `DB` helper - it may be useful and it won't harm your app's perfomance or stability

## Todo
1. Update tests and cover more functionality
2. Think about a helper facade