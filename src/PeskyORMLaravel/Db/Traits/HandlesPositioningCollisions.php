<?php

namespace PeskyORMLaravel\Db\Traits;

use PeskyORM\Adapter\Postgres;
use PeskyORM\Core\DbExpr;
use PeskyORM\ORM\RecordInterface;
use PeskyORM\ORM\TableInterface;
use PeskyORMLaravel\Db\Column\RecordPositionColumn;
use Swayok\Utils\NormalizeValue;

trait HandlesPositioningCollisions {

    private $transactionWasCreatedForPositioningCollision = false;

    protected function beforeSave(array $columnsToSave, array $data, $isUpdate) {
        $this->transactionWasCreatedForPositioningCollision = false;
        if ($isUpdate) {
            /** @var TableInterface $table */
            $table = static::getTable();
            if (!$table::inTransaction()) {
                $this->transactionWasCreatedForPositioningCollision = true;
                $table::beginTransaction();
            }
            $this->handlePositioningCollision($columnsToSave, $data);
        }
        return [];
    }

    protected function afterSave($isCreated, array $updatedColumns = []) {
        $this->finishPositioningCollision();
    }

    protected function finishPositioningCollision() {
        /** @var TableInterface $table */
        $table = static::getTable();
        if ($table::inTransaction() && $this->transactionWasCreatedForPositioningCollision) {
            $table::commitTransaction();
        }
        $this->transactionWasCreatedForPositioningCollision = false;
    }

    protected function handlePositioningCollision(array $columnsToSave, array $data) {
        /** @var TableInterface $table */
        /** @var RecordInterface $this */
        $table = static::getTable();
        $repositioningColumns = $this->getListOfRepositioningColumns();
        $affectedColumns = array_intersect($columnsToSave, $repositioningColumns);
        if (!empty($affectedColumns)) {
            foreach ($affectedColumns as $columnName) {
                $value = array_get($data, $columnName, null);
                /** @var RecordPositionColumn $column */
                $column = $table::getStructure()->getColumn($columnName);
                if (!empty($value) && !is_object($value) && empty($this::validateValue($column, $value))) {
                    $normalizedValue = $column->getType() === $column::TYPE_FLOAT
                        ? NormalizeValue::normalizeFloat($value)
                        : NormalizeValue::normalizeInteger($value);
                    $isConflict = (bool)$table::selectValue(DbExpr::create('1'), [
                        $columnName => $normalizedValue,
                        $table::getPkColumnName() . ' !=' => $this->getPrimaryKeyValue()
                    ]);
                    if ($isConflict) {
                        $step = $column instanceof RecordPositionColumn ? $column->getIncrement() : 100;
                        $table::update(
                            [$columnName => DbExpr::create("`{$columnName}` + ``{$step}``")],
                            [
                                $columnName . '>=' => $normalizedValue
                            ]
                        );
                    }
                }
            }
        }
    }

    protected function getListOfRepositioningColumns() {
        $ret = [];
        /** @var TableInterface $table */
        $table = static::getTable();
        foreach ($table::getStructure()->getColumns() as $name => $column) {
            if ($column instanceof RecordPositionColumn) {
                $ret[] = $name;
            }
        }
        return $ret;
    }
}