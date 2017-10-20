<?php

namespace PeskyORMLaravel\Db\Traits;

use PeskyORM\Core\DbExpr;
use PeskyORM\ORM\RecordInterface;
use PeskyORM\ORM\TableInterface;
use PeskyORMLaravel\Db\Column\RecordPositionColumn;
use Swayok\Utils\NormalizeValue;

trait HandlesPositioningCollisions {

    protected function beforeSave(array $columnsToSave, array $data, $isUpdate) {
        if ($isUpdate) {
            $this->handlePositioningCollision($columnsToSave, $data);
        }
    }

    protected function afterSave($isCreated) {
        /** @var TableInterface $table */
        $table = static::getTable();
        if (!$isCreated && $table::inTransaction()) {
            $table::commitTransaction();
        }
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
                if (!empty($value) && !is_object($value) && $this::validateValue($column, $value)) {
                    $normalizedValue = $column->getType() === $column::TYPE_FLOAT
                        ? NormalizeValue::normalizeFloat($value)
                        : NormalizeValue::normalizeInteger($value);
                    $isConflict = (bool)$table::selectValue(DbExpr::create('1'), [
                        $columnName => $normalizedValue
                    ]);
                    if ($isConflict) {
                        $step = $column instanceof RecordPositionColumn ? $column->getIncrement() : 100;
                        $table::update(
                            [$columnName => DbExpr::create("`{$columnName}` + ``{$step}``")],
                            [$value . '>=' => $normalizedValue]
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