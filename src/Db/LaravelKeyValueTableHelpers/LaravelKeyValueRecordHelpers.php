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
     * @noinspection PhpUnused
     */
    protected function cleanCacheAfterSave(bool $isCreated): void
    {
        parent::cleanCacheAfterSave($isCreated);
        $this->cleanCacheOnChange();
    }
    
    /** @noinspection PhpUnused */
    protected function cleanCacheAfterDelete(): void
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
