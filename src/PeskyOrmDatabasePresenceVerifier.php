<?php

declare(strict_types=1);

namespace PeskyORMLaravel;

use Illuminate\Validation\DatabasePresenceVerifierInterface;
use PeskyORM\Core\DbConnectionsManager;
use PeskyORM\ORM\FakeTable;
use PeskyORM\ORM\TableInterface;

class PeskyOrmDatabasePresenceVerifier implements DatabasePresenceVerifierInterface
{
    
    protected array $tables = [];
    /**
     * Defines if column values should be compared in case sensitive mode or in case insensitive mode
     * @var bool
     */
    protected bool $caseSensitiveModeEnabled = true;
    /**
     * @var string
     */
    protected string $connectionName = 'default';
    
    public function setConnection($connection): void
    {
        $this->connectionName = $connection;
    }
    
    /** @noinspection PhpParameterNameChangedDuringInheritanceInspection */
    public function getCount($tableName, $column, $value, $excludeId = null, $idColumn = null, array $extra = []): int
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
    
    /** @noinspection PhpParameterNameChangedDuringInheritanceInspection */
    public function getMultiCount($tableName, $column, array $values, array $extra = []): int
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