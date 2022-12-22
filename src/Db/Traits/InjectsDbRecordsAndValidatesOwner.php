<?php

declare(strict_types=1);

namespace PeskyORMLaravel\Db\Traits;

use Illuminate\Routing\Route;
use PeskyORM\ORM\Record\RecordInterface;

/**
 * @psalm-require-implements \Illuminate\Routing\Controller
 */
trait InjectsDbRecordsAndValidatesOwner
{
    use InjectsDbRecords;

    protected function addConditionsForDbObjectInjection(
        Route $route,
        RecordInterface $object,
        array &$conditions
    ): void {
        /** @noinspection NullPointerExceptionInspection */
        $conditions[$this->getOwnerIdFieldName($object)] = app('auth')
            ->guard()
            ->user()
            ->getAuthIdentifier();
    }

    /**
     * Get owner ID field name. Autodetects 'user_id' and 'admin_id'. In other cases - owerwrite this method
     * @param RecordInterface $object
     * @return string
     * @throws \Exception
     */
    protected function getOwnerIdFieldName(RecordInterface $object): string
    {
        $tableStructure = $object->getTable()->getTableStructure();
        if ($tableStructure->hasColumn('user_id')) {
            return 'user_id';
        }
        if ($tableStructure->hasColumn('admin_id')) {
            return 'admin_id';
        }
        throw new \UnexpectedValueException(
            __METHOD__ . '() cannot find owner id field name'
        );
    }

}