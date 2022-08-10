<?php

declare(strict_types=1);

namespace PeskyORMLaravel\Db\TableStructureTraits;

use PeskyORM\ORM\Column;

/**
 * @psalm-require-implements \PeskyORM\ORM\TableStructureInterface
 */
trait IsActiveColumn
{
    
    private function is_active(): Column
    {
        return Column::create(Column::TYPE_BOOL)
            ->disallowsNullValues()
            ->setDefaultValue(true);
    }
}