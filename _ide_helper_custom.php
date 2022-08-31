<?php
/** @noinspection PhpFullyQualifiedNameUsageInspection */

/** @noinspection AutoloadingIssuesInspection */

/** @noinspection PhpUnused */

class IdeHelperRecord extends \PeskyORM\ORM\Record implements \Illuminate\Contracts\Auth\Authenticatable
{
    
    use \PeskyORMLaravel\Db\Traits\Authenticatable;
    use \PeskyORMLaravel\Db\LaravelKeyValueTableHelpers\LaravelKeyValueRecordHelpers;
}

class IdeHelperTableStructure extends \PeskyORM\ORM\TableStructure
{
    
    use \PeskyORMColumns\TableStructureTraits\IdColumn;
    
    public static function getTableName(): string
    {
        return '???';
    }
}

class IdeHelperTableLaravel extends \PeskyORM\ORM\Table implements \PeskyORMLaravel\Db\LaravelKeyValueTableHelpers\LaravelKeyValueTableInterface
{
    
    use \PeskyORMLaravel\Db\LaravelKeyValueTableHelpers\LaravelKeyValueTableHelpers;
    
    public function newRecord(): IdeHelperRecord
    {
        return IdeHelperRecord::newEmptyRecord();
    }
    
    public function getTableStructure(): IdeHelperTableStructure
    {
        return IdeHelperTableStructure::getInstance();
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


