<?php

namespace PeskyORMLaravel\Db\TableStructureTraits;

use PeskyORM\ORM\Column;
use PeskyORM\ORM\DefaultColumnClosures;
use PeskyORM\ORM\RecordValue;

trait PasswordColumn {

    private function password() {
        return static::createPasswordColumn()
            ->disallowsNullValues();
    }
    
    static public function createPasswordColumn() {
        $column = Column::create(Column::TYPE_PASSWORD)
            ->convertsEmptyStringToNull()
            ->setValuePreprocessor(function ($value, $isDbValue, $isForValidation, Column $column) {
                $value = DefaultColumnClosures::valuePreprocessor($value, $isDbValue, $isForValidation, $column);
                if ($isDbValue) {
                    return $value;
                } else if (!empty($value)) {
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
    
    static protected function modifyPasswordColumn(Column $column) {
    
    }

    static public function hashPassword(string $plainPassword): string {
        return \Hash::make($plainPassword);
    }

}