<?php


namespace PeskyORMLaravel\Db\KeyValueTableUtils;

/**
 * @method static KeyValueTableInterface getTable()
 */
trait KeyValueRecordHelpers {

    /**
     * @param string $key
     * @param mixed $foreignKeyValue
     * @param mixed $default
     * @return mixed
     */
    static public function get($key, $foreignKeyValue = null, $default = null) {
        return static::getTable()->getValue($key, $foreignKeyValue, $default);
    }

    /**
     * Clean cache related to this record after saving it's data to DB
     * @param bool $isCreated
     */
    protected function cleanCacheAfterSave(bool $isCreated) {
        parent::cleanCacheAfterSave($isCreated);
        $this->cleanCacheOnChange();
    }

    protected function cleanCacheAfterDelete() {
        parent::cleanCacheAfterDelete();
        $this->cleanCacheOnChange();
    }
    
    protected function cleanCacheOnChange() {
        $fkName = static::getTable()->getMainForeignKeyColumnName();
        if ($fkName === null) {
            static::getTable()->cleanCachedValues();
        } else if ($this->hasValue($fkName)) {
            static::getTable()->cleanCachedValues($this->getValue($fkName));
        }
    }

    /**
     * @param string $key
     * @param array $arguments
     * @return mixed
     * @throws \InvalidArgumentException
     */
    public static function __callStatic($key, array $arguments) {
        $fkValue = isset($arguments[0]) ? $arguments[0] : null;
        $default = isset($arguments[1]) ? $arguments[1] : null;
        return static::get($key, $fkValue, $default);
    }


}
