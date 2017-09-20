<?php

namespace PeskyORMLaravel\TableStructureTraits;

use PeskyORM\ORM\Column;

trait IsActiveColumn {

    private function is_active() {
        return Column::create(Column::TYPE_BOOL)
            ->disallowsNullValues()
            ->setDefaultValue(true);
    }
}