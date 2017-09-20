<?php

namespace PeskyORMLaravel\TableStructureTraits;

use PeskyORM\ORM\Column;

trait IsPublishedColumn {

    private function is_published() {
        return Column::create(Column::TYPE_BOOL)
            ->disallowsNullValues()
            ->setDefaultValue(true);
    }
}