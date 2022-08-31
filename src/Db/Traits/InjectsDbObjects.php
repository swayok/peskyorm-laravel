<?php

declare(strict_types=1);

namespace PeskyORMLaravel\Db\Traits;

/**
 * @deprecated use InjectsDbRecords
 * @psalm-require-implements \Illuminate\Routing\Controller
 */
trait InjectsDbObjects
{
    
    use InjectsDbRecords;
}
