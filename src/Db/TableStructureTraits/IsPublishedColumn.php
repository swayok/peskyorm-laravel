<?php

declare(strict_types=1);

namespace PeskyORMLaravel\Db\TableStructureTraits;

use PeskyORM\ORM\Column;

/**
 * @psalm-require-implements \PeskyORM\ORM\TableStructureInterface
 */
trait IsPublishedColumn
{
    
    private function is_published(): Column
    {
        return Column::create(Column::TYPE_BOOL)
            ->disallowsNullValues()
            ->setDefaultValue(true);
    }
}