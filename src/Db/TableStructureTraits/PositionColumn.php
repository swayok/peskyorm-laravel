<?php

declare(strict_types=1);

namespace PeskyORMLaravel\Db\TableStructureTraits;

use PeskyORMLaravel\Db\Column\RecordPositionColumn;

/**
 * @psalm-require-implements \PeskyORM\ORM\TableStructureInterface
 */
trait PositionColumn
{
    
    private function position(): RecordPositionColumn
    {
        return RecordPositionColumn::create()
            ->disallowsNullValues()
            ->uniqueValues();
    }
}