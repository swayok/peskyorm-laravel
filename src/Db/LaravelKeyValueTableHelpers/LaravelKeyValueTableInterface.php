<?php

declare(strict_types=1);

namespace PeskyORMLaravel\Db\LaravelKeyValueTableHelpers;

use PeskyORM\ORM\KeyValueTableHelpers\KeyValueTableInterface;

interface LaravelKeyValueTableInterface extends KeyValueTableInterface
{
    
    /**
     * @param mixed $foreignKeyValue
     * @return void
     */
    public static function cleanCachedValues($foreignKeyValue = null): void;
    
}