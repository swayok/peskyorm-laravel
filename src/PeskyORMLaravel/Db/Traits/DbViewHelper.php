<?php

namespace PeskyORMLaravel\Db\Traits;

use PeskyORM\ORM\Record;

trait DbViewHelper {

    public function saveToDb(array $columnsToSave = []) {
        /** @var Record|DbViewHelper $this */
        throw new \BadMethodCallException('Saving data to a DB View is impossible');
    }

    public function delete($resetAllValuesAfterDelete = true, $deleteFiles = true) {
        /** @var Record|DbViewHelper $this */
        throw new \BadMethodCallException('Deleting data from a DB View is impossible');
    }

}