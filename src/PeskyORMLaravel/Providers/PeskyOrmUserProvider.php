<?php

namespace PeskyORMLaravel\Providers;

use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as UserContract;
use Illuminate\Contracts\Auth\UserProvider;
use PeskyORM\ORM\Column;
use PeskyORM\ORM\RecordInterface;

class PeskyOrmUserProvider implements UserProvider {

    /**
     * The PeskyORM user object (DbObject).
     *
     * @var string
     */
    protected $dbRecordClass;
    /**
     * @var array
     */
    protected $relationsToFetch = [];

    /**
     * Create a new database user provider.
     *
     * @param string $dbRecordClass
     * @param array $relationsToFetch
     * @throws \InvalidArgumentException
     */
    public function __construct($dbRecordClass, array $relationsToFetch = []) {
        if (empty($dbRecordClass) || !class_exists($dbRecordClass)) {
            throw new \InvalidArgumentException(
                'Argument $dbRecordClass must contin a class name that implements PeskyORM\ORM\RecordInterface'
            );
        }
        $this->dbRecordClass = $dbRecordClass;
        $this->relationsToFetch = $relationsToFetch;
    }

    /**
     * Retrieve a user by their unique identifier.
     *
     * @param mixed $identifier
     * @return \Illuminate\Contracts\Auth\Authenticatable|null
     */
    public function retrieveById($identifier) {
        if (!$this->isValidIdentifierValue($identifier)) {
            return null;
        }
        /** @var RecordInterface $user */
        $user = $this->createEmptyUserRecord()->fromPrimaryKey($identifier, [], $this->getRelationsToFetch());

        return $this->validateUser($user, null);
    }

    /**
     * Retrieve a user by their unique identifier and "remember me" token.
     *
     * @param  mixed $identifier
     * @param  string $token
     * @return \Illuminate\Contracts\Auth\Authenticatable|null
     */
    public function retrieveByToken($identifier, $token) {
        /** @var RecordInterface|Authenticatable $userRecord */
        if (!$this->isValidIdentifierValue($identifier)) {
            return null;
        }
        $userRecord = $this->createEmptyUserRecord();
        /** @var RecordInterface $user */
        $user = $userRecord->fromDb([
            $userRecord->getAuthIdentifierName() => $identifier,
            $userRecord->getRememberTokenName() => $token,
        ], [], $this->getRelationsToFetch());

        return $this->validateUser($user, null);
    }

    /**
     * @param mixed $identifier
     * @return bool
     */
    protected function isValidIdentifierValue($identifier) {
        if (is_string($identifier) && preg_match('%^s:\d+:%', $identifier)) {
            // it seems that after one of Laravel's minor updates they does not
            // serialize data anymore and in result it crashes during DB query
            return false;
        }

        /** @var RecordInterface|Authenticatable $userRecord */
        $userRecord = $this->createEmptyUserRecord();
        // do not attempt to use empty, non-numeric or negative value in DB query
        if (
            empty($identifier)
            || (
                in_array(
                    $userRecord::getColumn($userRecord->getAuthIdentifierName())->getType(),
                    [Column::TYPE_INT, Column::TYPE_FLOAT],
                    true
                )
                && (
                    !is_numeric($identifier)
                    || (int)$identifier <= 0
                )
            )
        ) {
            return false;
        }
        return true;
    }

    /**
     * @param RecordInterface $user
     * @param mixed $onFailReturn
     * @return mixed|RecordInterface
     */
    protected function validateUser(RecordInterface $user, $onFailReturn = null) {
        if (
            $user->existsInDb()
            && (!$user::hasColumn('is_active') || $user->getValue('is_active'))
            && (!$user::hasColumn('is_banned') || !$user->getValue('is_banned'))
        ) {
            return $user;
        }

        return $onFailReturn;
    }

    /**
     * Update the "remember me" token for the given user in storage.
     *
     * @param  \Illuminate\Contracts\Auth\Authenticatable $user
     * @param  string $token
     * @return void
     * @throws \BadMethodCallException
     */
    public function updateRememberToken(UserContract $user, $token) {
        $user->setRememberToken($token);
        /** @var RecordInterface $user */
        $user->save();
    }

    /**
     * Retrieve a user by the given credentials.
     *
     * @param  array $credentials
     * @return \Illuminate\Contracts\Auth\Authenticatable|null
     */
    public function retrieveByCredentials(array $credentials) {

        $conditions = [];

        foreach ($credentials as $key => $value) {
            if (!str_contains($key, 'password')) {
                $conditions[$key] = $value;
            }
        }
        $user = $this->createEmptyUserRecord()->fromDb($conditions, [], $this->getRelationsToFetch());

        return $this->validateUser($user, null);
    }

    /**
     * Validate a user against the given credentials.
     *
     * @param  \Illuminate\Contracts\Auth\Authenticatable $user
     * @param  array $credentials
     * @return bool
     */
    public function validateCredentials(UserContract $user, array $credentials) {
        foreach ($credentials as $columnName => $value) {
            if (is_string($columnName) && !is_numeric($columnName)) {
                if ($columnName === 'password') {
                    if (!\Hash::check($value, $user->getAuthPassword())) {
                        return false;
                    }
                } else if ($user->$columnName !== $value) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Create a new instance of the db record.
     *
     * @return RecordInterface
     */
    public function createEmptyUserRecord() {
        /** @var RecordInterface $class */
        $class = $this->dbRecordClass;

        return new $class();
    }

    /**
     * Needed for JWTGuard
     *
     * @return string
     */
    public function getModel() {
        return $this->dbRecordClass;
    }

    /**
     * Related records to read together with main record
     * @return array
     */
    public function getRelationsToFetch() {
        return $this->relationsToFetch;
    }
}
