<?php
/** @noinspection PhpUnused */

declare(strict_types=1);

namespace PeskyORMLaravel\Db\Traits;

use PeskyORM\ORM\Traits\DbViewRecordProtection;

/**
 * @psalm-require-implements \PeskyORM\ORM\RecordInterface
 * @deprecated use PeskyORM\ORM\Traits\DbViewRecordProtection
 */
trait DbViewHelper
{
    
    use DbViewRecordProtection;
}