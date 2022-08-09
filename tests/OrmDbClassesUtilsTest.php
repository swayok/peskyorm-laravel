<?php

declare(strict_types=1);

namespace Tests;

use PeskyORMLaravel\Db\OrmDbClassesUtils;
use PHPUnit\Framework\TestCase;

class OrmDbClassesUtilsTest extends TestCase {
    
    public function testDbClassesUtils() {
        $tableName = 'admins';
        
        $namespace = OrmDbClassesUtils::getNamespaceForOrmDbClassesByTableName($tableName);
        static::assertEquals('App\\Db\\Admins', $namespace);
    
        $path = OrmDbClassesUtils::getFolderPathForOrmDbClassesByTableName($tableName);
        $ds = DIRECTORY_SEPARATOR;
        static::assertEquals(app_path("Db{$ds}Admins{$ds}"), $path);
        
        $class = OrmDbClassesUtils::getRecordClassByTableNameInDb($tableName);
        static::assertNotEmpty($class);
        static::assertEquals('App\Db\Admins\Admin', $class);
        
        $record = OrmDbClassesUtils::getRecordInstanceByTableNameInDb($tableName);
        static::assertNotNull($record);
        static::assertInstanceOf('App\Db\Admins\Admin', $record);
    
        $class = OrmDbClassesUtils::getTableClassByTableNameInDb($tableName);
        static::assertNotEmpty($class);
        static::assertEquals('App\Db\Admins\AdminsTable', $class);
    
        $table = OrmDbClassesUtils::getTableInstanceByTableNameInDb($tableName);
        static::assertNotNull($table);
        static::assertInstanceOf('App\Db\Admins\AdminsTable', $table);
    
        $class = OrmDbClassesUtils::getTableStructureClassByTableNameInDb($tableName);
        static::assertNotEmpty($class);
        static::assertEquals('App\Db\Admins\AdminsTableStructure', $class);
    
        $tableStructure = OrmDbClassesUtils::getTableStructureInstanceByTableNameInDb($tableName);
        static::assertNotNull($tableStructure);
        static::assertInstanceOf('App\Db\Admins\AdminsTableStructure', $tableStructure);
    }
    
}
