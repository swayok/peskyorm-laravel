<?php

declare(strict_types=1);

namespace PeskyORMLaravel\Db\KeyValueTableUtils;

/**
 * @method static KeyValueTableInterface getTable()
 * @psalm-require-implements \PeskyORM\ORM\RecordInterface
 */
trait KeyValueRecordHelpers
{
    
    /**
     * @param string $key
     * @param mixed $foreignKeyValue
     * @param mixed $default
     * @return mixed
     */
    public static function get(string $key, $foreignKeyValue = null, $default = null)
    {
        return static::getTable()
            ->getValue($key, $foreignKeyValue, $default);
    }
    
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
    
    /**
     * @param string $key
     * @param array $arguments
     * @return mixed
     * @throws \InvalidArgumentException
     */
    public static function __callStatic(string $key, array $arguments)
    {
        $fkValue = $arguments[0] ?? null;
        $default = $arguments[1] ?? null;
        return static::get($key, $fkValue, $default);
    }
    
    
}
