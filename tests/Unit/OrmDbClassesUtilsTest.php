<?php

declare(strict_types=1);

namespace PeskyORMLaravel\Tests\Unit;

use PeskyORMLaravel\Db\OrmDbClassesUtils;
use PeskyORMLaravel\Tests\TestCase;

class OrmDbClassesUtilsTest extends TestCase
{
    
    public function testDbClassesUtils()
    {
        $tableName = 'admins';
        
        $namespace = OrmDbClassesUtils::getNamespaceForOrmDbClassesByTableName($tableName);
        static::assertEquals('App\\Db\\Admins', $namespace);
        
        $path = OrmDbClassesUtils::getFolderPathForOrmDbClassesByTableName($tableName);
        $ds = DIRECTORY_SEPARATOR;
        static::assertEquals(app_path("Db{$ds}Admins{$ds}"), $path);
        
        $class = OrmDbClassesUtils::getRecordClassByTableNameInDb($tableName);
        static::assertNotEmpty($class);
        static::assertEquals('App\\Db\\Admins\\Admin', $class);
        
        $class = OrmDbClassesUtils::getTableClassByTableNameInDb($tableName);
        static::assertNotEmpty($class);
        static::assertEquals('App\\Db\\Admins\\AdminsTable', $class);
        
        $class = OrmDbClassesUtils::getTableStructureClassByTableNameInDb($tableName);
        static::assertNotEmpty($class);
        static::assertEquals('App\\Db\\Admins\\AdminsTableStructure', $class);
    }
    
}
