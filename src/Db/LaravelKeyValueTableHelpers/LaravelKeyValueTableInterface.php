<?php

declare(strict_types=1);

namespace PeskyORMLaravel\Db\LaravelKeyValueTableHelpers;

use PeskyORM\ORM\KeyValueTableHelpers\KeyValueTableInterface;

interface LaravelKeyValueTableInterface extends KeyValueTableInterface
{
    
    public static function cleanCachedValues(int|string|float|null $foreignKeyValue = null): void;
    
}