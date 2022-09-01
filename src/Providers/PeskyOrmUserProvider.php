<?php

declare(strict_types=1);

namespace PeskyORMLaravel\Providers;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Support\Facades\Hash;
use PeskyORM\ORM\Column;
use PeskyORM\ORM\RecordInterface;

class PeskyOrmUserProvider implements UserProvider
{
    
    protected string $dbRecordClass;
    protected array $relationsToFetch;
    
    /**
     * @param string $dbRecordClass
     * @param array $relationsToFetch
     * @throws \InvalidArgumentException
     */
    public function __construct(string $dbRecordClass, array $relationsToFetch = [])
    {
        if (empty($dbRecordClass) || !class_exists($dbRecordClass)) {
            throw new \InvalidArgumentException(
                'Argument $dbRecordClass must contin a class name that implements PeskyORM\ORM\RecordInterface'
            );
        }
        $this->dbRecordClass = $dbRecordClass;
        $this->relationsToFetch = $relationsToFetch;
    }
    
    public function retrieveById($identifier)
    {
        if (!$this->isValidIdentifierValue($identifier)) {
            return null;
        }
        $user = $this->createEmptyUserRecord()
            ->fetchByPrimaryKey($identifier, [], $this->getRelationsToFetch());
        
        return $this->validateUser($user, null);
    }
    
    public function retrieveByToken($identifier, $token)
    {
        /** @var RecordInterface|Authenticatable $userRecord */
        if (!$this->isValidIdentifierValue($identifier)) {
            return null;
        }
        $userRecord = $this->createEmptyUserRecord();
        $conditions = array_merge(
            [
                $userRecord->getAuthIdentifierName() => $identifier,
                $userRecord->getRememberTokenName() => $token,
            ],
            $this->getAdditionalConditionsForFetchOne()
        );
        /** @var RecordInterface $user */
        $user = $userRecord->fetch($conditions, [], $this->getRelationsToFetch());
        
        return $this->validateUser($user, null);
    }
    
    protected function isValidIdentifierValue(mixed $identifier): bool
    {
        if (is_string($identifier) && preg_match('%^s:\d+:%', $identifier)) {
            // it seems that after one of Laravel's minor updates they does not
            // serialize data anymore and in result it crashes during DB query
            return false;
        }
        
        /** @var RecordInterface|Authenticatable $userRecord */
        $userRecord = $this->createEmptyUserRecord();
        // do not attempt to use empty, non-numeric or negative value in DB query
        return (
        !(empty($identifier)
            || (
                in_array(
                    $userRecord::getColumn($userRecord->getAuthIdentifierName())
                        ->getType(),
                    [Column::TYPE_INT, Column::TYPE_FLOAT],
                    true
                )
                && (
                    !is_numeric($identifier)
                    || (int)$identifier <= 0
                )
            ))
        );
    }
    
    /**
     * @return mixed|RecordInterface
     */
    protected function validateUser(RecordInterface $user, mixed $onFailReturn = null): mixed
    {
        if (
            $user->existsInDb()
            && (!$user::hasColumn('is_active') || $user->getValue('is_active'))
            && (!$user::hasColumn('is_banned') || !$user->getValue('is_banned'))
            && (!$user::hasColumn('is_deleted') || !$user->getValue('is_deleted'))
        ) {
            return $user;
        }
        
        return $onFailReturn;
    }
    
    /**
     * Update the "remember me" token for the given user in storage.
     *
     * @param Authenticatable $user
     * @param string $token
     * @return void
     */
    public function updateRememberToken(Authenticatable $user, $token): void
    {
        /** @var RecordInterface|Authenticatable $user */
        $user->begin();
        $user->setRememberToken($token);
        $user->commit();
    }
    
    /**
     * Retrieve a user by the given credentials.
     */
    public function retrieveByCredentials(array $credentials): RecordInterface|Authenticatable|null
    {
        $conditions = [];
        
        foreach ($this->normalizeCredentials($credentials) as $key => $value) {
            if (!str_contains($key, 'password')) {
                $conditions[$key] = $value;
            }
        }
        $user = $this->createEmptyUserRecord();
        
        $user->fetch(
            array_merge($conditions, $this->getAdditionalConditionsForFetchOne()),
            [],
            $this->getRelationsToFetch()
        );
        
        return $this->validateUser($user, null);
    }
    
    protected function normalizeCredentials(array $credentials): array
    {
        if (isset($credentials['email']) && is_string($credentials['email'])) {
            $credentials['email'] = mb_strtolower($credentials['email']);
        }
        return $credentials;
    }
    
    /**
     * Get specific conditions for user fetching by credentials or by remember token.
     */
    public function getAdditionalConditionsForFetchOne(): array
    {
        $conditions = [];
        /** @var RecordInterface $userClass */
        $userClass = $this->dbRecordClass;
        if ($userClass::hasColumn('is_active')) {
            $conditions['is_active'] = true;
        }
        if ($userClass::hasColumn('is_deleted')) {
            $conditions['is_deleted'] = false;
        }
        if ($userClass::hasColumn('is_banned')) {
            $conditions['is_banned'] = false;
        }
        return $conditions;
    }
    
    public function validateCredentials(Authenticatable $user, array $credentials): bool
    {
        foreach ($this->normalizeCredentials($credentials) as $columnName => $value) {
            if (is_string($columnName) && !is_numeric($columnName)) {
                if ($columnName === 'password') {
                    if (!Hash::check($value, $user->getAuthPassword())) {
                        return false;
                    }
                } elseif ($user->$columnName !== $value) {
                    return false;
                }
            }
        }
        
        return true;
    }
    
    /**
     * @return RecordInterface|Authenticatable
     * @noinspection PhpDocSignatureInspection
     */
    public function createEmptyUserRecord(): RecordInterface
    {
        /** @var RecordInterface $class */
        $class = $this->dbRecordClass;
        
        return new $class();
    }
    
    /**
     * Needed for JWTGuard
     */
    public function getModel(): string
    {
        return $this->dbRecordClass;
    }
    
    public function getRelationsToFetch(): array
    {
        return $this->relationsToFetch;
    }
}
