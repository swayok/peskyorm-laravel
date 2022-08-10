<?php

declare(strict_types=1);

namespace PeskyORMLaravel\Db\Traits;

use Illuminate\Support\Arr;
use PeskyORM\Core\DbExpr;
use PeskyORM\ORM\RecordInterface;
use PeskyORMLaravel\Db\Column\RecordPositionColumn;
use Swayok\Utils\NormalizeValue;

/**
 * @psalm-require-implements \PeskyORM\ORM\RecordInterface
 */
trait HandlesPositioningCollisions
{
    
    private $transactionWasCreatedForPositioningCollision = false;
    
    protected function beforeSave(array $columnsToSave, array $data, bool $isUpdate): array
    {
        /** @var RecordInterface $this */
        $this->transactionWasCreatedForPositioningCollision = false;
        if ($isUpdate) {
            $table = $this::getTable();
            if (!$table::inTransaction()) {
                $this->transactionWasCreatedForPositioningCollision = true;
                $table::beginTransaction();
            }
            $this->handlePositioningCollision($columnsToSave, $data);
        }
        return [];
    }
    
    protected function afterSave(bool $isCreated, array $updatedColumns = [])
    {
        parent::afterSave($isCreated, $updatedColumns);
        $this->finishPositioningCollision();
    }
    
    protected function finishPositioningCollision(): void
    {
        /** @var RecordInterface $this */
        $table = $this::getTable();
        if ($table::inTransaction() && $this->transactionWasCreatedForPositioningCollision) {
            $table::commitTransaction();
        }
        $this->transactionWasCreatedForPositioningCollision = false;
    }
    
    protected function handlePositioningCollision(array $columnsToSave, array $data): void
    {
        /** @var RecordInterface $this */
        $table = $this::getTable();
        $repositioningColumns = $this->getListOfRepositioningColumns();
        $affectedColumns = array_intersect($columnsToSave, $repositioningColumns);
        if (!empty($affectedColumns)) {
            foreach ($affectedColumns as $columnName) {
                $value = Arr::get($data, $columnName, null);
                /** @var RecordPositionColumn $column */
                $column = $table::getStructure()
                    ->getColumn($columnName);
                if (!empty($value) && !is_object($value) && empty($this::validateValue($column, $value))) {
                    $normalizedValue = $column->getType() === $column::TYPE_FLOAT
                        ? NormalizeValue::normalizeFloat($value)
                        : NormalizeValue::normalizeInteger($value);
                    $isConflict = (bool)$table::selectValue(DbExpr::create('1'), [
                        $columnName => $normalizedValue,
                        $table::getPkColumnName() . ' !=' => $this->getPrimaryKeyValue(),
                    ]);
                    if ($isConflict) {
                        $step = $column instanceof RecordPositionColumn ? $column->getIncrement() : 100;
                        $table::update(
                            [$columnName => DbExpr::create("`{$columnName}` + ``{$step}``")],
                            [
                                $columnName . '>=' => $normalizedValue,
                            ]
                        );
                    }
                }
            }
        }
    }
    
    protected function getListOfRepositioningColumns(): array
    {
        $ret = [];
        /** @var RecordInterface $this */
        $table = $this::getTable();
        foreach (
            $table::getStructure()
                ->getColumns() as $name => $column
        ) {
            if ($column instanceof RecordPositionColumn) {
                $ret[] = $name;
            }
        }
        return $ret;
    }
}