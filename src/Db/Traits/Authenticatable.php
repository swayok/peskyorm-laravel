<?php

declare(strict_types=1);

namespace PeskyORMLaravel\Db\Traits;

use PeskyORM\ORM\RecordInterface;

/**
 * @psalm-require-implements \PeskyORM\ORM\RecordInterface
 * @psalm-require-implements \Illuminate\Contracts\Auth\Authenticatable
 */
trait Authenticatable
{
    
    /**
     * Get the unique identifier for the user.
     * @return int|string
     */
    public function getAuthIdentifier()
    {
        return $this->getKey();
    }
    
    /**
     * Get the name of the unique identifier for the user.
     */
    public function getAuthIdentifierName(): string
    {
        return $this->getKeyName();
    }
    
    /**
     * Needed to fit eloquent ORM
     * @return int|string
     */
    public function getKey()
    {
        /** @var RecordInterface|Authenticatable $this */
        return $this->getPrimaryKeyValue();
    }
    
    /**
     * Needed to fit eloquent ORM
     */
    public function getKeyName(): string
    {
        /** @var RecordInterface|Authenticatable $this */
        return $this::getTable()
            ->getPkColumnName();
    }
    
    /**
     * Get the password for the user.
     */
    public function getAuthPassword(): string
    {
        /** @var RecordInterface|Authenticatable $this */
        return $this->getValue('password');
    }
    
    /**
     * Get the token value for the "remember me" session.
     */
    public function getRememberToken(): string
    {
        /** @var RecordInterface|Authenticatable $this */
        return $this->getValue($this->getRememberTokenName());
    }
    
    /**
     * Set the token value for the "remember me" session.
     * @return static
     */
    public function setRememberToken($value)
    {
        /** @var RecordInterface|Authenticatable $this */
        return $this->updateValue($this->getRememberTokenName(), $value, false);
    }
    
    /**
     * Get the column name for the "remember me" token.
     *
     * @return string
     */
    public function getRememberTokenName(): string
    {
        return 'remember_token';
    }
}
