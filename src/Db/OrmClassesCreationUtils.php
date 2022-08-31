<?php

declare(strict_types=1);

namespace PeskyORMLaravel\Db;

use PeskyORM\Core\DbAdapterInterface;
use PeskyORM\Core\DbConnectionsManager;
use PeskyORM\ORM\ClassBuilder;
use PeskyORM\ORM\RecordInterface;
use PeskyORM\ORM\TableInterface;
use PeskyORM\ORM\TableStructure;
use PeskyORM\ORM\TableStructureInterface;

abstract class OrmClassesCreationUtils
{
    
    /**
     * @return string|ClassBuilder
     */
    public static function getClassBuilderClass(): string
    {
        return config('peskyorm.class_builder', ClassBuilder::class);
    }
    
    public static function getClassBuilder(string $tableName, ?DbAdapterInterface $connection = null): ClassBuilder
    {
        $classBuilder = static::getClassBuilderClass();
        return new $classBuilder($tableName, $connection ?: DbConnectionsManager::getConnection('default'));
    }
    
    public static function getNamespaceForOrmDbClassesByTableName(string $tableName): string
    {
        /** @var ClassBuilder $builderClass */
        $builderClass = static::getClassBuilderClass();
        return trim(config('peskyorm.classes_namespace', 'App\\Db'), ' \\') . '\\' . $builderClass::convertTableNameToClassName($tableName);
    }
    
    public static function getFolderPathForOrmDbClassesByTableName(string $tableName): string
    {
        /** @var ClassBuilder $builderClass */
        $builderClass = static::getClassBuilderClass();
        $basePath = config('peskyorm.classes_path', app_path('Db'));
        return $basePath . DIRECTORY_SEPARATOR . $builderClass::convertTableNameToClassName($tableName) . DIRECTORY_SEPARATOR;
    }
    
    /**
     * @return string|RecordInterface
     */
    public static function getRecordClassByTableNameInDb(string $tableName): string
    {
        /** @var ClassBuilder $builderClass */
        $builderClass = static::getClassBuilderClass();
        return static::getNamespaceForOrmDbClassesByTableName($tableName) . '\\' . $builderClass::makeRecordClassName($tableName);
    }
    
    public static function getRecordInstanceByTableNameInDb(string $tableName): ?RecordInterface
    {
        $recordClass = static::getRecordClassByTableNameInDb($tableName);
        return class_exists($recordClass) ? new $recordClass() : null;
    }
    
    /**
     * @return string|TableInterface
     */
    public static function getTableClassByTableNameInDb(string $tableName): string
    {
        /** @var ClassBuilder $builderClass */
        $builderClass = static::getClassBuilderClass();
        return static::getNamespaceForOrmDbClassesByTableName($tableName) . '\\' . $builderClass::makeTableClassName($tableName);
    }
    
    public static function getTableInstanceByTableNameInDb(string $tableName): ?TableInterface
    {
        $tableClass = static::getTableClassByTableNameInDb($tableName);
        return class_exists($tableClass) ? $tableClass::getInstance() : null;
    }
    
    /**
     * @return string|TableStructureInterface|TableStructure
     */
    public static function getTableStructureClassByTableNameInDb(string $tableName): string
    {
        /** @var ClassBuilder $builderClass */
        $builderClass = static::getClassBuilderClass();
        return static::getNamespaceForOrmDbClassesByTableName($tableName) . '\\' . $builderClass::makeTableStructureClassName($tableName);
    }
    
    public static function getTableStructureInstanceByTableNameInDb(string $tableName): ?TableStructureInterface
    {
        $tableStructureClass = static::getTableStructureClassByTableNameInDb($tableName);
        return class_exists($tableStructureClass) ? $tableStructureClass::getInstance() : null;
    }
}