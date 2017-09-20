<?php

namespace PeskyORMLaravel\TableStructureTraits;

use PeskyORM\ORM\Column;

trait UserAuthColumns {

    use PasswordColumn;

    private function remember_token() {
        return Column::create(Column::TYPE_STRING)
            ->allowsNullValues()
            ->convertsEmptyStringToNull()
            ->privateValue();
    }

}