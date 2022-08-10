<?php

declare(strict_types=1);

namespace PeskyORMLaravel\Db\TableStructureTraits;

use PeskyORM\ORM\Column;

/**
 * @psalm-require-implements \PeskyORM\ORM\TableStructureInterface
 */
trait NullablePasswordColumn
{
    
    private function password(): Column
    {
        return PasswordColumn::createPasswordColumn()
            ->allowsNullValues();
    }
    
}