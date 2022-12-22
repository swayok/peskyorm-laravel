<?php
/** @noinspection PhpFullyQualifiedNameUsageInspection */

/** @noinspection AutoloadingIssuesInspection */

/** @noinspection PhpUnused */

class IdeHelperRecord extends \PeskyORM\ORM\Record\Record implements \Illuminate\Contracts\Auth\Authenticatable
{
    use \PeskyORMLaravel\Db\Traits\Authenticatable;
}

class IdeHelperTableStructure extends \PeskyORM\ORM\TableStructure\TableStructure
{
    public function getTableName(): string
    {
        return '???';
    }

    protected function registerColumns(): void
    {
    }

    protected function registerRelations(): void
    {
    }
}

class IdeHelperTableLaravel extends \PeskyORM\ORM\Table\Table {
    public function __construct()
    {
        parent::__construct(new IdeHelperTableStructure(), IdeHelperRecord::class);
    }
}

class IdeHelperController1 extends \Illuminate\Routing\Controller
{
    use \PeskyORMLaravel\Db\Traits\InjectsDbRecords;
}

class IdeHelperController2 extends \Illuminate\Routing\Controller
{
    use \PeskyORMLaravel\Db\Traits\InjectsDbRecordsAndValidatesOwner;
}


