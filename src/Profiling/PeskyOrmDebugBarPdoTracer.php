<?php
/** @noinspection PhpUndefinedNamespaceInspection */
/** @noinspection PhpUndefinedFieldInspection */
/** @noinspection PhpUndefinedClassInspection */

declare(strict_types=1);

namespace PeskyORMLaravel\Profiling;

use DebugBar\DataCollector\PDO\TraceablePDO;

class PeskyOrmDebugBarPdoTracer extends TraceablePDO
{
    
    /** @noinspection PhpUnused */
    protected function profileCall($method, $sql, array $args)
    {
        if (!preg_match('%^(COMMIT|BEGIN)%i', $sql)) {
            return parent::profileCall($method, $sql, $args);
        } else {
            return call_user_func_array([$this->pdo, $method], $args);
        }
    }
}