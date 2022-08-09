<?php

namespace PeskyORMLaravel\Db\TableStructureTraits;

use PeskyORM\Core\DbExpr;
use PeskyORM\ORM\Column;

trait TimestampColumns {

    use CreatedAtColumn;

    private function updated_at() {
        return Column::create(Column::TYPE_TIMESTAMP)
            ->disallowsNullValues()
            ->valueCannotBeSetOrChanged()
            ->autoUpdateValueOnEachSaveWith(function () {
                return DbExpr::create('NOW()');
            });
    }
}