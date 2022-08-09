<?php

namespace PeskyORMLaravel\Db\TableStructureTraits;

use PeskyORM\Core\DbExpr;
use PeskyORM\ORM\Column;

trait CreatedAtColumn {

    private function created_at() {
        return Column::create(Column::TYPE_TIMESTAMP)
            ->disallowsNullValues()
            ->valueCannotBeSetOrChanged()
            ->setDefaultValue(DbExpr::create('NOW()'));
    }

}