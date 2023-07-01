<?php

declare(strict_types=1);

namespace PeskyORMLaravel\Db\TableColumn;

use Illuminate\Support\Facades\Hash;
use PeskyORM\ORM\TableStructure\TableColumn\Column\PasswordColumn;
use PeskyORM\ORM\TableStructure\TableColumn\RealTableColumnAbstract;

class LaravelPasswordColumn extends PasswordColumn
{
    public function __construct(string $name = 'password')
    {
        RealTableColumnAbstract::__construct($name);
        $this->setPasswordHasher(
            function (string $value): string {
                return Hash::make($value);
            },
            function (string $plainValue, string $hashedValue): bool {
                return Hash::check($plainValue, $hashedValue);
            }
        );
        parent::__construct($name);
    }
}
