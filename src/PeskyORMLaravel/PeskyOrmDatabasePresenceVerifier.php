<?php

declare(strict_types=1);

namespace PeskyORMLaravel;

use Illuminate\Validation\PresenceVerifierInterface;
use PeskyORM\Core\DbConnectionsManager;
use PeskyORM\ORM\FakeTable;
use PeskyORM\ORM\TableInterface;

class PeskyOrmDatabasePresenceVerifier implements PresenceVerifierInterface
{
    
    protected $tables = [];
    /**
     * Defines if column values should be compared in case sensitive mode or in case insensitive mode
     * @var bool
     */
    protected $caseSensitiveModeEnabled = true;
    /**
     * @var string
     */
    protected $connectionName = 'default';
    
    public function getCount($tableName, $column, $value, $excludeId = null, $idColumn = null, array $extra = [])
    {
        if ($this->caseSensitiveModeEnabled || is_numeric($value)) {
            $conditions = [$column => $value];
        } else {
            $conditions = [$column . ' ~*' => '^' . preg_quote($value, null) . '$'];
        }
        if ($excludeId !== null && $excludeId !== 'NULL') {
            $conditions[($idColumn ?: 'id') . ' !='] = $excludeId;
        }
        foreach ($extra as $key => $extraValue) {
            $this->addWhere($conditions, $key, $extraValue);
        }
        return $this->getTable($tableName)
            ->count($conditions);
    }
    
    public function getMultiCount($tableName, $column, array $values, array $extra = [])
    {
        $conditions = [$column => $values];
        
        foreach ($extra as $key => $extraValue) {
            $this->addWhere($conditions, $key, $extraValue);
        }
        
        return $this->getTable($tableName)
            ->count($conditions);
    }
    
    private function getTable(string $tableName): TableInterface
    {
        if (!array_key_exists($tableName, $this->tables)) {
            $this->tables[$tableName] = FakeTable::makeNewFakeTable(
                $tableName,
                null,
                DbConnectionsManager::getConnection($this->connectionName)
            );
        }
        return $this->tables[$tableName];
    }
    
    /**
     * Add a "where" clause to the given query.
     */
    protected function addWhere(array &$conditions, string $key, string $extraValue): void
    {
        if ($extraValue === 'NULL') {
            $conditions[$key] = null;
        } elseif ($extraValue === 'NOT_NULL') {
            $conditions[$key . '!='] = null;
        } else {
            $conditions[$key] = $extraValue;
        }
    }
    
    public function setConnection(string $connectionName): void
    {
        $this->connectionName = $connectionName;
    }
    
    /**
     * @return static
     */
    public function enableCaseInsensitiveMode()
    {
        $this->caseSensitiveModeEnabled = false;
        return $this;
    }
    
    /**
     * @return static
     */
    public function enableCaseSensitiveMode()
    {
        $this->caseSensitiveModeEnabled = true;
        return $this;
    }
    
}