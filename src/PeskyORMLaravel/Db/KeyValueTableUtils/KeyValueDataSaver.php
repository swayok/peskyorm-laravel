<?php

namespace PeskyORMLaravel\Db\KeyValueTableUtils;

use PeskyORM\ORM\Column;
use PeskyORM\ORM\FakeTable;
use PeskyORM\ORM\Record;

class KeyValueDataSaver extends Record {

    /**
     * @var FakeTable $table
     */
    static protected $table;
    /**
     * @var KeyValueTableInterface $table
     */
    static protected $originalTable;
    protected $_fkValue;
    protected $_constantAdditionalData = [];

    /**
     * @return FakeTable
     */
    static public function getTable() {
        return static::$table;
    }

    /**
     * @param KeyValueTableInterface $table
     * @param array $originalData
     * @param array $newData
     * @param mixed $fkValue
     * @param array $constantAdditionalData
     * @throws \PeskyORM\Exception\DbException
     * @throws \PDOException
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     * @throws \PeskyORM\Exception\InvalidDataException
     * @throws \PeskyORM\Exception\InvalidTableColumnConfigException
     * @throws \PeskyORM\Exception\OrmException
     */
    static public function saveKeyValuePairs(
        KeyValueTableInterface $table,
        array $originalData,
        array $newData,
        $fkValue = null,
        array $constantAdditionalData = []
    ) {
        /** @var array|Column[] $columns */
        $columns = [
            'fakeid' => Column::TYPE_INT
        ];
        $tableStructure = $table->getTableStructure();
        foreach ($newData as $key => $value) {
            if ($tableStructure::hasColumn($key)) {
                $columns[$key] = $tableStructure::getColumn($key);
            } else {
                $columns[$key] = is_array($value) ? Column::TYPE_JSON : Column::TYPE_TEXT;
            }
        }
        static::$originalTable = $table;
        $fkName = $table->getMainForeignKeyColumnName();
        $fkName = empty($fkName) ? 'null' : "'{$fkName}'";
        static::$table = FakeTable::makeNewFakeTable(
            $table::getName(),
            $columns,
            null,
            [KeyValueTableInterface::class],
            [KeyValueTableHelpers::class],
            "public function getMainForeignKeyColumnName() {return {$fkName};}"
        );
        static::$table->getTableStructure()->markColumnAsPrimaryKey('fakeid');
        static::fromArray($originalData, true, false)
            ->updateValue(static::getPrimaryKeyColumnName(), 0, true)
            ->updateValues($newData, false)
            ->saveToDb(array_keys($newData), $fkValue, $constantAdditionalData);
    }

    /**
     * @param array $columnsToSave
     * @param null $fkValue
     * @param array $constantAdditionalData
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     * @throws \PDOException
     * @throws \PeskyORM\Exception\DbException
     * @throws \PeskyORM\Exception\InvalidDataException
     * @throws \PeskyORM\Exception\InvalidTableColumnConfigException
     * @throws \PeskyORM\Exception\OrmException
     * @throws \UnexpectedValueException
     */
    protected function saveToDb(array $columnsToSave = [], $fkValue = null, array $constantAdditionalData = []) {
        $this->_fkValue = $fkValue;
        $this->_constantAdditionalData = $constantAdditionalData;
        parent::saveToDb($columnsToSave);
    }

    protected function collectValuesForSave(array &$columnsToSave, $isUpdate) {
        $data = [];
        foreach ($columnsToSave as $columnName) {
            $column = static::getColumn($columnName);
            if ($column->isAutoUpdatingValue()) {
                $data[$columnName] = static::getColumn($columnName)->getAutoUpdateForAValue();
            } else if (!$column->isItPrimaryKey()) {
                $data[$columnName] = $this->getValue($column);
            }
        }
        return $data;
    }

    protected function performDataSave($isUpdate, array $data) {
        $table = static::$originalTable;
        $alreadyInTransaction = $table::inTransaction();
        if (!$alreadyInTransaction) {
            $table::beginTransaction();
        }
        try {
            $success = $table->updateOrCreateRecords(
                $table::convertToDataForRecords($data, $this->_fkValue, $this->_constantAdditionalData)
            );
            if (!$alreadyInTransaction) {
                if ($success) {
                    $table::commitTransaction();
                } else {
                    $table::rollBackTransaction();
                }
            }
            return $success;
        } catch (\PDOException $exc) {
            if ($table::inTransaction()) {
                $table::rollBackTransaction();
            }
            throw $exc;
        }
    }

    protected function getAllColumnsWithUpdatableValues() {
        return array_keys(static::getColumns());
    }

    public function existsInDb($useDbQuery = false) {
        return true;
    }

    protected function _existsInDb() {
        return true;
    }

}