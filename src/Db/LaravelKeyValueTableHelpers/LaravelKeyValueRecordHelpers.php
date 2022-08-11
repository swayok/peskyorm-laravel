<?php

declare(strict_types=1);

namespace PeskyORMLaravel\Db\LaravelKeyValueTableHelpers;

use PeskyORM\ORM\KeyValueTableHelpers\KeyValueRecordHelpers;

/**
 * @method static LaravelKeyValueTableInterface getTable()
 * @psalm-require-implements \PeskyORM\ORM\RecordInterface
 */
trait LaravelKeyValueRecordHelpers
{
    
    use KeyValueRecordHelpers;
    
    /**
     * Clean cache related to this record after saving it's data to DB
     * @param bool $isCreated
     */
    protected function cleanCacheAfterSave(bool $isCreated)
    {
        parent::cleanCacheAfterSave($isCreated);
        $this->cleanCacheOnChange();
    }
    
    protected function cleanCacheAfterDelete()
    {
        parent::cleanCacheAfterDelete();
        $this->cleanCacheOnChange();
    }
    
    protected function cleanCacheOnChange(): void
    {
        $fkName = static::getTable()
            ->getMainForeignKeyColumnName();
        if ($fkName === null) {
            static::getTable()
                ->cleanCachedValues();
        } elseif ($this->hasValue($fkName)) {
            static::getTable()
                ->cleanCachedValues($this->getValue($fkName));
        }
    }
    
}
