<?php

namespace PeskyORMLaravel\Db\TableStructureTraits;

use PeskyORM\ORM\Column;
use PeskyORM\ORM\DefaultColumnClosures;
use PeskyORM\ORM\RecordValue;

trait NullablePasswordColumn {

    private function password() {
        return PasswordColumn::createPasswordColumn()
            ->allowsNullValues();
    }

}