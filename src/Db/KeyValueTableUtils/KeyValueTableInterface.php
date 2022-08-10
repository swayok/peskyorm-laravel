<?php

declare(strict_types=1);

namespace PeskyORMLaravel\Db\KeyValueTableUtils;

use PeskyORM\ORM\Record;
use PeskyORM\ORM\RecordInterface;
use PeskyORM\ORM\TableInterface;

interface KeyValueTableInterface extends TableInterface
{
    
    /**
     * @return string|null
     */
    public function getMainForeignKeyColumnName(): ?string;
    
    /**
     * Make array that represents DB record and can be saved to DB
     * @param string $key
     * @param mixed $value
     * @param mixed $foreignKeyValue
     * @return array
     */
    public static function makeDataForRecord(string $key, $value, $foreignKeyValue = null): array;
    
    /**
     * Convert associative array to arrays that represent DB record and are ready for saving to DB
     * @param array $settingsAssoc - associative array of settings
     * @param mixed $foreignKeyValue
     * @param array $additionalConstantValues - contains constant values for all records (for example: admin id)
     * @return array
     */
    public static function convertToDataForRecords(array $settingsAssoc, $foreignKeyValue = null, array $additionalConstantValues = []): array;
    
    /**
     * Update existing value or create new one
     * @param array $data - must contain: key, foreign_key, value
     * @return Record
     */
    public static function updateOrCreateRecord(array $data): RecordInterface;
    
    /**
     * Update existing values and create new
     * @param array $records
     */
    public static function updateOrCreateRecords(array $records): void;
    
    /**
     * @param string $key
     * @param mixed $foreignKeyValue - use null if there is no main foreign key column and
     *      getMainForeignKeyColumnName() method returns null
     * @param mixed $default
     * @return mixed
     */
    public static function getValue(string $key, $foreignKeyValue = null, $default = null);
    
    /**
     * @param mixed $foreignKeyValue
     * @param bool $ignoreCache
     * @return array
     */
    public static function getValuesForForeignKey($foreignKeyValue = null, bool $ignoreCache = false): array;
    
    /**
     * @param mixed $foreignKeyValue
     * @return void
     */
    public static function cleanCachedValues($foreignKeyValue = null): void;
    
}