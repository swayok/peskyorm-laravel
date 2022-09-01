<?php

declare(strict_types=1);

namespace PeskyORMLaravel\Db\Traits;

/**
 * @psalm-require-implements \PeskyORM\ORM\RecordInterface
 * @psalm-require-implements \Illuminate\Contracts\Auth\Authenticatable
 */
trait Authenticatable
{
    
    /**
     * Get the unique identifier for the user.
     */
    public function getAuthIdentifier(): int|string|float|null
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
     */
    public function getKey(): int|string|float|null
    {
        return $this->getPrimaryKeyValue();
    }
    
    /**
     * Needed to fit eloquent ORM
     */
    public function getKeyName(): string
    {
        return $this::getTable()->getPkColumnName();
    }
    
    /**
     * Get the password for the user.
     */
    public function getAuthPassword(): string
    {
        return $this->getValue('password');
    }
    
    /**
     * Get the token value for the "remember me" session.
     */
    public function getRememberToken(): string
    {
        return $this->getValue($this->getRememberTokenName());
    }
    
    /**
     * Set the token value for the "remember me" session.
     */
    public function setRememberToken($value): void
    {
        $this->updateValue($this->getRememberTokenName(), $value, false);
    }
    
    /**
     * Get the column name for the "remember me" token.
     */
    public function getRememberTokenName(): string
    {
        return 'remember_token';
    }
}
