<?php

declare(strict_types=1);

namespace PeskyORMLaravel\Db\Traits;

use PeskyORM\ORM\Record;

/**
 * @psalm-require-implements \PeskyORM\ORM\RecordInterface
 */
trait DbViewHelper
{
    
    public function saveToDb(array $columnsToSave = [])
    {
        /** @var Record|DbViewHelper $this */
        throw new \BadMethodCallException('Saving data to a DB View is impossible');
    }
    
    public function delete(bool $resetAllValuesAfterDelete = true, bool $deleteFiles = true)
    {
        /** @var Record|DbViewHelper $this */
        throw new \BadMethodCallException('Deleting data from a DB View is impossible');
    }
    
}