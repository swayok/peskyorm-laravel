<?php

namespace PeskyORMLaravel\Db\TableStructureTraits;

use PeskyORMLaravel\Db\Column\RecordPositionColumn;

trait PositionColumn {

    private function position() {
        return RecordPositionColumn::create()
            ->disallowsNullValues()
            ->uniqueValues();
    }
}