<?php
/** @noinspection PhpFullyQualifiedNameUsageInspection */

/** @noinspection AutoloadingIssuesInspection */

/** @noinspection PhpUnused */

class IdeHelperRecord extends \PeskyORM\ORM\Record
{
    
    use \PeskyORMLaravel\Db\Traits\Authenticatable;
    use \PeskyORMLaravel\Db\Traits\HandlesPositioningCollisions;
    use \PeskyORMLaravel\Db\Traits\DbViewHelper;
}

class IdeHelperController1 extends \Illuminate\Routing\Controller
{
    
    use \PeskyORMLaravel\Db\Traits\InjectsDbRecords;
}

class IdeHelperController2 extends \Illuminate\Routing\Controller
{
    
    use \PeskyORMLaravel\Db\Traits\InjectsDbRecordsAndValidatesOwner;
}
