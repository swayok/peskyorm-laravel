<?php

declare(strict_types=1);

namespace PeskyORMLaravel\Db\Traits;

/**
 * @deprecated Use InjectsDbRecordsAndValidatesOwner;
 * @psalm-require-implements \Illuminate\Routing\Controller
 */
trait InjectsDbObjectsAndValidatesOwner
{
    
    use InjectsDbRecordsAndValidatesOwner;
}