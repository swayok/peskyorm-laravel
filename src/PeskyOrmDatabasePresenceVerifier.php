<?php

declare(strict_types=1);

namespace PeskyORMLaravel;

use Illuminate\Validation\DatabasePresenceVerifierInterface;
use PeskyORM\Adapter\DbAdapterInterface;
use PeskyORM\Config\Connection\DbConnectionsFacade;
use PeskyORM\DbExpr;

class PeskyOrmDatabasePresenceVerifier implements DatabasePresenceVerifierInterface
{
    protected array $tables = [];
    /**
     * Defines if column values should be compared
     * in case-sensitive mode or in case-insensitive mode
     * @var bool
     */
    protected bool $caseSensitiveModeEnabled = true;

    public function __construct(
        protected string $connectionName = 'default'
    ) {
    }

    public function setConnection($connection): void
    {
        $this->connectionName = $connection;
    }

    protected function getConnection(): DbAdapterInterface
    {
        return DbConnectionsFacade::getConnection($this->connectionName);
    }

    /** @noinspection PhpParameterNameChangedDuringInheritanceInspection */
    public function getCount(
        $tableName,
        $column,
        $value,
        $excludeId = null,
        $idColumn = null,
        array $extra = []
    ): int {
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
        return (int)$this->getConnection()->selectValue(
            $tableName,
            new DbExpr('COUNT(*)'),
            $conditions
        );
    }

    /** @noinspection PhpParameterNameChangedDuringInheritanceInspection */
    public function getMultiCount(
        $tableName,
        $column,
        array $values,
        array $extra = []
    ): int {
        $conditions = [$column => $values];

        foreach ($extra as $key => $extraValue) {
            $this->addWhere($conditions, $key, $extraValue);
        }

        return (int)$this->getConnection()->selectValue(
            $tableName,
            new DbExpr('COUNT(*)'),
            $conditions
        );
    }

    /**
     * Add a "where" clause to the given query.
     */
    protected function addWhere(
        array &$conditions,
        string $key,
        string $extraValue
    ): void {
        if ($extraValue === 'NULL') {
            $conditions[$key] = null;
        } elseif ($extraValue === 'NOT_NULL') {
            $conditions[$key . '!='] = null;
        } else {
            $conditions[$key] = $extraValue;
        }
    }

    public function enableCaseInsensitiveMode(): static
    {
        $this->caseSensitiveModeEnabled = false;
        return $this;
    }
}