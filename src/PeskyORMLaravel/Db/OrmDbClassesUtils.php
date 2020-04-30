<?php

namespace PeskyORMLaravel\Db;

use PeskyORM\Core\DbAdapterInterface;
use PeskyORM\Core\DbConnectionsManager;
use PeskyORM\ORM\ClassBuilder;
use PeskyORM\ORM\Record;
use PeskyORM\ORM\RecordInterface;
use PeskyORM\ORM\Table;
use PeskyORM\ORM\TableInterface;
use PeskyORM\ORM\TableStructure;
use PeskyORM\ORM\TableStructureInterface;

abstract class OrmDbClassesUtils {
    
    /**
     * @return ClassBuilder
     */
    static public function getClassBuilderClass(): string {
        return config('peskyorm.class_builder', ClassBuilder::class);
    }
    
    static public function getClassBuilder(string $tableName, ?DbAdapterInterface $connection = null): ClassBuilder {
        $classBuilder = static::getClassBuilderClass();
        return new $classBuilder($tableName, $connection?: DbConnectionsManager::getConnection('default'));
    }
    
    static public function getNamespaceForOrmDbClassesByTableName(string $tableName): string {
        /** @var ClassBuilder $builderClass */
        $builderClass = static::getClassBuilderClass();
        return trim(config('peskyorm.classes_namespace', 'App\\Db'), ' \\') . '\\' . $builderClass::convertTableNameToClassName($tableName);
    }
    
    static public function getFolderPathForOrmDbClassesByTableName(string $tableName): string {
        /** @var ClassBuilder $builderClass */
        $builderClass = static::getClassBuilderClass();
        $basePath = config('peskyorm.classes_path', app_path('Db'));
        return $basePath . DIRECTORY_SEPARATOR . $builderClass::convertTableNameToClassName($tableName) . DIRECTORY_SEPARATOR;
    }
    
    /**
     * @param string $tableName
     * @return string|RecordInterface|Record
     */
    static public function getRecordClassByTableNameInDb(string $tableName): string {
        /** @var ClassBuilder $builderClass */
        $builderClass = static::getClassBuilderClass();
        return static::getNamespaceForOrmDbClassesByTableName($tableName) . '\\' . $builderClass::makeRecordClassName($tableName);
    }
    
    /**
     * @param string $tableName
     * @return RecordInterface|Record|null
     */
    static public function getRecordInstanceByTableNameInDb(string $tableName): ?RecordInterface {
        $recordClass = static::getRecordClassByTableNameInDb($tableName);
        return class_exists($recordClass) ? new $recordClass() : null;
    }
    
    /**
     * @param string $tableName
     * @return string|TableInterface|Table
     */
    static public function getTableClassByTableNameInDb(string $tableName): string {
        /** @var ClassBuilder $builderClass */
        $builderClass = static::getClassBuilderClass();
        return static::getNamespaceForOrmDbClassesByTableName($tableName) . '\\' . $builderClass::makeTableClassName($tableName);
    }
    
    /**
     * @param string $tableName
     * @return TableInterface|Table|null
     */
    static public function getTableInstanceByTableNameInDb(string $tableName): ?TableInterface {
        $tableClass = static::getTableClassByTableNameInDb($tableName);
        return class_exists($tableClass) ? $tableClass::getInstance() : null;
    }
    
    /**
     * @param string $tableName
     * @return string|TableStructureInterface|TableStructure
     */
    static public function getTableStructureClassByTableNameInDb(string $tableName): string {
        /** @var ClassBuilder $builderClass */
        $builderClass = static::getClassBuilderClass();
        return static::getNamespaceForOrmDbClassesByTableName($tableName) . '\\' . $builderClass::makeTableStructureClassName($tableName);
    }
    
    /**
     * @param string $tableName
     * @return TableStructureInterface|TableStructure|null
     */
    static public function getTableStructureInstanceByTableNameInDb(string $tableName): ?TableStructureInterface {
        $tableStructureClass = static::getTableStructureClassByTableNameInDb($tableName);
        return class_exists($tableStructureClass) ? $tableStructureClass::getInstance() : null;
    }
}