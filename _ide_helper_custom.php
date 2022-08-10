<?php
/** @noinspection PhpFullyQualifiedNameUsageInspection */

/** @noinspection AutoloadingIssuesInspection */

/** @noinspection PhpUnused */

class IdeHelperRecord extends \PeskyORM\ORM\Record implements \Illuminate\Contracts\Auth\Authenticatable
{
    
    use \PeskyORMLaravel\Db\Traits\Authenticatable;
    use \PeskyORMLaravel\Db\Traits\HandlesPositioningCollisions;
    use \PeskyORMLaravel\Db\Traits\DbViewHelper;
    use \PeskyORMLaravel\Db\KeyValueTableUtils\KeyValueRecordHelpers;
}

class IdeHelperTableStructure extends \PeskyORM\ORM\TableStructure
{
    
    use \PeskyORMLaravel\Db\TableStructureTraits\IdColumn;
    
    public static function getTableName(): string
    {
        return '???';
    }
}

class IdeHelperTable extends \PeskyORM\ORM\Table implements \PeskyORMLaravel\Db\KeyValueTableUtils\KeyValueTableInterface
{
    
    use \PeskyORMLaravel\Db\KeyValueTableUtils\KeyValueTableHelpers;
    
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


