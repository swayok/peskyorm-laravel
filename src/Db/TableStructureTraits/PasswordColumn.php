<?php

declare(strict_types=1);

namespace PeskyORMLaravel\Db\TableStructureTraits;

use Illuminate\Support\Facades\Hash;
use PeskyORM\ORM\Column;
use PeskyORM\ORM\DefaultColumnClosures;
use PeskyORM\ORM\RecordValue;

/**
 * @psalm-require-implements \PeskyORM\ORM\TableStructureInterface
 */
trait PasswordColumn
{
    
    private function password(): Column
    {
        return static::createPasswordColumn()
            ->disallowsNullValues();
    }
    
    public static function createPasswordColumn(): Column
    {
        $column = Column::create(Column::TYPE_PASSWORD)
            ->convertsEmptyStringToNull()
            ->setValuePreprocessor(function ($value, $isDbValue, $isForValidation, Column $column) {
                $value = DefaultColumnClosures::valuePreprocessor($value, $isDbValue, $isForValidation, $column);
                if ($isDbValue) {
                    return $value;
                } elseif (!empty($value)) {
                    return static::hashPassword($value);
                } else {
                    return $value;
                }
            })
            ->setValueSetter(function ($newValue, $isFromDb, RecordValue $valueContainer, $trustDataReceivedFromDb) {
                if (!$isFromDb && ($newValue === null || (is_string($newValue) && trim($newValue) === ''))) {
                    return;
                }
                DefaultColumnClosures::valueSetter($newValue, $isFromDb, $valueContainer, $trustDataReceivedFromDb);
            })
            ->privateValue();
        static::modifyPasswordColumn($column);
        return $column;
    }
    
    protected static function modifyPasswordColumn(Column $column): void
    {
    }
    
    public static function hashPassword(string $plainPassword): string
    {
        return Hash::make($plainPassword);
    }
    
}