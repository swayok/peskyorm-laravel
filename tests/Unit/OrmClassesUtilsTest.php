<?php

declare(strict_types=1);

namespace PeskyORMLaravel\Tests\Unit;

use PeskyORMLaravel\Db\OrmClassesCreationUtils;
use PeskyORMLaravel\Tests\TestCase;

class OrmClassesUtilsTest extends TestCase
{
    
    public function testDbClassesUtils(): void
    {
        $tableName = 'admins';
        
        $namespace = OrmClassesCreationUtils::getNamespaceForOrmDbClassesByTableName($tableName);
        static::assertEquals('App\\Db\\Admins', $namespace);
        
        $path = OrmClassesCreationUtils::getFolderPathForOrmDbClassesByTableName($tableName);
        $ds = DIRECTORY_SEPARATOR;
        static::assertEquals(app_path("Db{$ds}Admins{$ds}"), $path);
        
        $class = OrmClassesCreationUtils::getRecordClassByTableNameInDb($tableName);
        static::assertNotEmpty($class);
        static::assertEquals('App\\Db\\Admins\\Admin', $class);
        
        $class = OrmClassesCreationUtils::getTableClassByTableNameInDb($tableName);
        static::assertNotEmpty($class);
        static::assertEquals('App\\Db\\Admins\\AdminsTable', $class);
        
        $class = OrmClassesCreationUtils::getTableStructureClassByTableNameInDb($tableName);
        static::assertNotEmpty($class);
        static::assertEquals('App\\Db\\Admins\\AdminsTableStructure', $class);
    }
    
}
