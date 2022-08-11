<?php

declare(strict_types=1);

namespace PeskyORMLaravel\Db\LaravelKeyValueTableHelpers;

use PeskyORM\Exception\InvalidDataException;
use PeskyORM\ORM\KeyValueTableHelpers\KeyValueTableHelpers;
use PeskyORM\ORM\Record;
use PeskyORM\ORM\RecordInterface;

/**
 * @psalm-require-implements \PeskyORMLaravel\Db\KeyValueTableUtils\LaravelKeyValueTableInterface
 */
trait LaravelKeyValueTableHelpers
{
    
    use KeyValueTableHelpers;
    
    /**
     * @param mixed $foreignKeyValue
     * @return null|string
     */
    public static function getCacheKeyToStoreAllValuesForAForeignKey($foreignKeyValue = null): ?string
    {
        return null;
    }
    
    /**
     * @return int - minutes
     */
    public static function getCacheDurationForAllValues(): int
    {
        return 10;
    }
    
    /**
     * Update existing value or create new one
     * @param array $data - must contain: key, foreign_key, value
     * @return Record
     * @throws InvalidDataException
     */
    public static function updateOrCreateRecord(array $data): RecordInterface
    {
        $success = KeyValueTableHelpers::updateOrCreateRecord($data);
        /** @var LaravelKeyValueTableInterface $table */
        $table = static::getInstance();
        $fkName = $table->getMainForeignKeyColumnName();
        static::cleanCachedValues(empty($fkName) ? null : $data[$fkName]);
        return $success;
    }
    
    /**
     * @param string $key
     * @param string|null $format - get formatted version of value
     * @param mixed $foreignKeyValue - use null if there is no main foreign key column and
     *      getMainForeignKeyColumnName() method returns null
     * @param mixed $default
     * @param bool $ignoreEmptyValue
     *      - true: if value recorded to DB is empty - returns $default
     *      - false: returns any value from DB if it exists
     * @return mixed
     * @throws \InvalidArgumentException
     */
    public static function getFormattedValue(
        string $key,
        ?string $format,
        $foreignKeyValue = null,
        $default = null,
        bool $ignoreEmptyValue = false
    ) {
        $cacheKey = static::getCacheKeyToStoreAllValuesForAForeignKey($foreignKeyValue);
        if (!empty($cacheKey)) {
            $cachedValues = self::getValuesForForeignKey($foreignKeyValue);
            if (array_key_exists($key, $cachedValues)) {
                $recordData = [
                    static::getKeysColumnName() => $key,
                    static::getValuesColumnName() => $cachedValues[$key],
                ];
                if ($foreignKeyValue) {
                    /** @var LaravelKeyValueTableInterface $table */
                    $table = static::getInstance();
                    $recordData[$table->getMainForeignKeyColumnName()] = $foreignKeyValue;
                }
            }
        }
        if (empty($recordData)) {
            $recordData = static::findRecordForKey($key, $foreignKeyValue);
        }
        $defaultClosure = ($default instanceof \Closure)
            ? $default
            : function () use ($default) {
                return $default;
            };
        return static::getFormattedValueFromRecordData($recordData, $key, $format, $defaultClosure, $ignoreEmptyValue);
    }
    
    /**
     * @param mixed $foreignKeyValue
     * @param bool $ignoreCache
     * @param bool $ignoreEmptyValues
     *      - true: return only not empty values stored in DB
     *      - false: return all values strored in db
     * @return array
     * @throws \InvalidArgumentException
     */
    public static function getValuesForForeignKey($foreignKeyValue = null, bool $ignoreCache = false, bool $ignoreEmptyValues = false): array
    {
        if (!$ignoreCache) {
            $cacheKey = static::getCacheKeyToStoreAllValuesForAForeignKey($foreignKeyValue);
            if (!empty($cacheKey)) {
                $data = app('cache')->get($cacheKey, null);
                if (is_array($data)) {
                    return $data;
                }
            }
        }
        $data = KeyValueTableHelpers::getValuesForForeignKey($foreignKeyValue, $ignoreEmptyValues);
        if (!empty($cacheKey)) {
            app('cache')->put($cacheKey, $data, static::getCacheDurationForAllValues());
        }
        return $data;
    }
    
    public static function cleanCachedValues($foreignKeyValue = null): void
    {
        $cacheKey = static::getCacheKeyToStoreAllValuesForAForeignKey($foreignKeyValue);
        if ($cacheKey) {
            app('cache')->forget($cacheKey);
        }
    }
    
}
