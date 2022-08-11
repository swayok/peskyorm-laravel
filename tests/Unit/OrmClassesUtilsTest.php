<?php

declare(strict_types=1);

namespace PeskyORMLaravel\Tests\Unit;

use PeskyORMLaravel\Db\OrmClassesUtils;
use PeskyORMLaravel\Tests\TestCase;

class OrmClassesUtilsTest extends TestCase
{
    
    public function testDbClassesUtils(): void
    {
        $tableName = 'admins';
        
        $namespace = OrmClassesUtils::getNamespaceForOrmDbClassesByTableName($tableName);
        static::assertEquals('App\\Db\\Admins', $namespace);
        
        $path = OrmClassesUtils::getFolderPathForOrmDbClassesByTableName($tableName);
        $ds = DIRECTORY_SEPARATOR;
        static::assertEquals(app_path("Db{$ds}Admins{$ds}"), $path);
        
        $class = OrmClassesUtils::getRecordClassByTableNameInDb($tableName);
        static::assertNotEmpty($class);
        static::assertEquals('App\\Db\\Admins\\Admin', $class);
        
        $class = OrmClassesUtils::getTableClassByTableNameInDb($tableName);
        static::assertNotEmpty($class);
        static::assertEquals('App\\Db\\Admins\\AdminsTable', $class);
        
        $class = OrmClassesUtils::getTableStructureClassByTableNameInDb($tableName);
        static::assertNotEmpty($class);
        static::assertEquals('App\\Db\\Admins\\AdminsTableStructure', $class);
    }
    
}
