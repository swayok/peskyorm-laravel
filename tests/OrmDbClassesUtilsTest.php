<?php

namespace Tests;

use App\Db\Admins\Admin;
use App\Db\Admins\AdminsTable;
use App\Db\Admins\AdminsTableStructure;
use PeskyORMLaravel\Db\OrmDbClassesUtils;

class OrmDbClassesUtilsTest extends TestCase {
    
    public function testDbClassesUtils() {
        $tableName = 'admins';
        
        $namespace = OrmDbClassesUtils::getNamespaceForOrmDbClassesByTableName($tableName);
        $this->assertEquals('App\\Db\\Admins', $namespace);
    
        $path = OrmDbClassesUtils::getFolderPathForOrmDbClassesByTableName($tableName);
        $ds = DIRECTORY_SEPARATOR;
        $this->assertEquals(app_path("Db{$ds}Admins{$ds}"), $path);
        
        $class = OrmDbClassesUtils::getRecordClassByTableNameInDb($tableName);
        $this->assertNotEmpty($class);
        $this->assertEquals(Admin::class, $class);
        
        $record = OrmDbClassesUtils::getRecordInstanceByTableNameInDb($tableName);
        $this->assertNotNull($record);
        $this->assertInstanceOf(Admin::class, $record);
    
        $class = OrmDbClassesUtils::getTableClassByTableNameInDb($tableName);
        $this->assertNotEmpty($class);
        $this->assertEquals(AdminsTable::class, $class);
    
        $table = OrmDbClassesUtils::getTableInstanceByTableNameInDb($tableName);
        $this->assertNotNull($table);
        $this->assertInstanceOf(AdminsTable::class, $table);
    
        $class = OrmDbClassesUtils::getTableStructureClassByTableNameInDb($tableName);
        $this->assertNotEmpty($class);
        $this->assertEquals(AdminsTableStructure::class, $class);
    
        $tableStructure = OrmDbClassesUtils::getTableStructureInstanceByTableNameInDb($tableName);
        $this->assertNotNull($tableStructure);
        $this->assertInstanceOf(AdminsTableStructure::class, $tableStructure);
    }
    
}
