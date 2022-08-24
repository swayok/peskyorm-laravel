<?php

declare(strict_types=1);

namespace PeskyORMLaravel\Db\LaravelKeyValueTableHelpers;

use PeskyORM\ORM\KeyValueTableHelpers\KeyValueRecordHelpers;

/**
 * @psalm-require-implements \PeskyORM\ORM\RecordInterface
 */
trait LaravelKeyValueRecordHelpers
{
    
    use KeyValueRecordHelpers;
    
    /**
     * Clean cache related to this record after saving it's data to DB
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
        /** @var LaravelKeyValueTableInterface $table */
        $table = static::getTable();
        $fkName = $table->getMainForeignKeyColumnName();
        if ($fkName === null) {
            $table->cleanCachedValues();
        } elseif ($this->hasValue($fkName)) {
            $table->cleanCachedValues($this->getValue($fkName));
        }
    }
    
}
