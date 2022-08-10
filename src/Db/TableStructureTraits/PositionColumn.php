<?php

declare(strict_types=1);

namespace PeskyORMLaravel\Db\TableStructureTraits;

use PeskyORMLaravel\Db\Column\RecordPositionColumn;

trait PositionColumn
{
    
    private function position(): RecordPositionColumn
    {
        return RecordPositionColumn::create()
            ->disallowsNullValues()
            ->uniqueValues();
    }
}