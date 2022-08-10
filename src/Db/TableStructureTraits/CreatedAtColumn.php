<?php

declare(strict_types=1);

namespace PeskyORMLaravel\Db\TableStructureTraits;

use PeskyORM\Core\DbExpr;
use PeskyORM\ORM\Column;

trait CreatedAtColumn
{
    
    private function created_at(): Column
    {
        return Column::create(Column::TYPE_TIMESTAMP)
            ->disallowsNullValues()
            ->valueCannotBeSetOrChanged()
            ->setDefaultValue(DbExpr::create('NOW()'));
    }
    
}