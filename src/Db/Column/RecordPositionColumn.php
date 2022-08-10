<?php

namespace PeskyORMLaravel\Db\Column;

use PeskyORM\Core\DbExpr;
use PeskyORM\ORM\Column;

class RecordPositionColumn extends Column {

    protected $increment = 100;

    public static function create($type = self::TYPE_INT, $name = null) {
        return parent::create($type, $name);
    }

    public function __construct($name = null, $type = self::TYPE_INT) {
        if (!in_array($type, [static::TYPE_INT, static::TYPE_FLOAT, static::TYPE_UNIX_TIMESTAMP])) {
            throw new \InvalidArgumentException(
                'Column $type must be of a numeric type (integer, float, unix timestamp).'
                . "'$type' type is not allowed"
            );
        }
        parent::__construct($name, $type);
        $this->disallowsNullValues();
        $this->setDefaultValue(function () {
            return DbExpr::create(
                'COALESCE((SELECT `' . $this->getName() . '` FROM `' . $this->getTableStructure()->getTableName()
                    . '` ORDER BY `' . $this->getName() . '` DESC LIMIT 1), 0) + ``' . $this->getIncrement() . '``',
                false
            );
        });
    }

    public function doesNotExistInDb() {
        throw new \BadMethodCallException(
            'This column must exist in database. Column name: ' . ($this->hasName() ? $this->getName() : '__undefined__')
        );
    }

    /**
     * @return int
     */
    public function getIncrement() {
        return $this->increment;
    }

    /**
     * @param int|float $increment - distance between  step
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function setIncrement($increment) {
        if (!is_float($increment) && !is_int($increment)) {
            throw new \InvalidArgumentException('$increment argument must be a number (integer of float)');
        }
        if ($increment === 0) {
            throw new \InvalidArgumentException('$increment argument cannot be 0');
        }
        $this->increment = $increment;
        return $this;
    }

}