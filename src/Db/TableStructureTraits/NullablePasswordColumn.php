<?php

declare(strict_types=1);

namespace PeskyORMLaravel\Db\TableStructureTraits;

use PeskyORM\ORM\Column;

trait NullablePasswordColumn
{
    
    private function password(): Column
    {
        return PasswordColumn::createPasswordColumn()
            ->allowsNullValues();
    }
    
}