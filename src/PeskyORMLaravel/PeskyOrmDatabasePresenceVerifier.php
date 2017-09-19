<?php

namespace PeskyORMLaravel;

use Illuminate\Validation\PresenceVerifierInterface;
use PeskyORM\Core\DbConnectionsManager;
use PeskyORM\ORM\FakeTable;
use PeskyORM\ORM\TableInterface;

class PeskyOrmDatabasePresenceVerifier implements PresenceVerifierInterface {

    protected $tables = [];
    /**
     * Defines if column values should be compared in case sensitive mode or in case insensitive mode
     * @var bool
     */
    protected $caseSensitiveModeEnabled = true;

    /**
     * Count the number of objects in a collection having the given value.
     *
     * @param  string $tableName
     * @param  string $column
     * @param  string $value
     * @param  int $excludeId
     * @param  string $idColumn
     * @param  array $extra
     * @return int
     * @throws \UnexpectedValueException
     * @throws \PeskyORM\Exception\OrmException
     * @throws \PDOException
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     */
    public function getCount($tableName, $column, $value, $excludeId = null, $idColumn = null, array $extra = []) {
        if ($this->caseSensitiveModeEnabled || is_numeric($value)) {
            $conditions = [$column => $value];
        } else {
            $conditions = [$column . ' ~*' => preg_quote($value, null)];
        }
        if ($excludeId !== null && $excludeId !== 'NULL') {
            $conditions[($idColumn ?: 'id') . ' !='] = $excludeId;
        }
        foreach ($extra as $key => $extraValue) {
            $this->addWhere($conditions, $key, $extraValue);
        }
        return $this->getTable($tableName)->count($conditions);
    }

    /**
     * Count the number of objects in a collection with the given values.
     *
     * @param  string $tableName
     * @param  string $column
     * @param  array $values
     * @param  array $extra
     * @return int
     * @throws \UnexpectedValueException
     * @throws \PeskyORM\Exception\OrmException
     * @throws \PDOException
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     */
    public function getMultiCount($tableName, $column, array $values, array $extra = []) {
        $conditions = [$column => $values];

        foreach ($extra as $key => $extraValue) {
            $this->addWhere($conditions, $key, $extraValue);
        }

        return $this->getTable($tableName)->count($conditions);
    }

    /**
     * @param string $tableName
     * @return TableInterface
     * @throws \UnexpectedValueException
     * @throws \PeskyORM\Exception\OrmException
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    private function getTable($tableName) {
        if (!array_key_exists($tableName, $this->tables)) {
            $this->tables[$tableName] = FakeTable::makeNewFakeTable(
                $tableName,
                null,
                DbConnectionsManager::getConnection('default')
            );
        }
        return $this->tables[$tableName];
    }

    /**
     * Add a "where" clause to the given query.
     *
     * @param  array $conditions
     * @param  string $key
     * @param  string $extraValue
     * @return void
     */
    protected function addWhere(&$conditions, $key, $extraValue) {
        if ($extraValue === 'NULL') {
            $conditions[$key] = null;
        } elseif ($extraValue === 'NOT_NULL') {
            $conditions[$key . '!='] = null;
        } else {
            $conditions[$key] = $extraValue;
        }
    }

    public function setConnection($connection) {
        // don't need this but may come
    }

    public function enableCaseInsensitiveMode() {
        $this->caseSensitiveModeEnabled = false;
        return $this;
    }

    public function enableCaseSensitiveMode() {
        $this->caseSensitiveModeEnabled = true;
        return $this;
    }

}