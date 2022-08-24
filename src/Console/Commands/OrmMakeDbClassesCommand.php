<?php

declare(strict_types=1);

namespace PeskyORMLaravel\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use PeskyORM\Core\DbConnectionsManager;
use PeskyORM\ORM\ClassBuilder;
use PeskyORM\ORM\Record;
use PeskyORM\ORM\Table;
use PeskyORM\ORM\TableStructure;
use PeskyORMLaravel\Db\OrmClassesCreationUtils;

class OrmMakeDbClassesCommand extends Command
{
    
    /**
     * @var string
     */
    protected $signature = 'orm:make-db-classes {table_name} {schema?} {database_classes_app_subfolder=Db}'
    . ' {--overwrite= : 1|0|y|n|yes|no; what to do if classes already exist}'
    . ' {--only= : table|record|structure; create only specified class}'
    . ' {--connection= : name of connection to use}';
    
    /**
     * @var string
     */
    protected $description = 'Create classes for DB table.';
    
    protected function getTableParentClass(): string
    {
        return config('peskyorm.base_table_class', Table::class);
    }
    
    protected function getRecordParentClass(): string
    {
        return config('peskyorm.base_record_class', Record::class);
    }
    
    protected function getTableStructureParentClass(): string
    {
        return config('peskyorm.base_table_structure_class', TableStructure::class);
    }
    
    protected function getClassBuilderClass(): string
    {
        return OrmClassesCreationUtils::getClassBuilderClass();
    }
    
    /**
     * @return array (
     *      NameOfTrait1::class,
     *      NameOfTrait2::class,
     * )
     */
    protected function getTraitsForTableConfig(): array
    {
        return (array)config('peskyorm.table_structure_traits', []);
    }
    
    public function handle(): void
    {
        $connectionName = $this->option('connection');
        if (!empty($connectionName)) {
            $connectionInfo = config('database.connections.' . $connectionName);
            if (!is_array($connectionInfo)) {
                $this->line("- There is no configuration info for connection '{$connectionName}'");
                return;
            }
            $connection = DbConnectionsManager::createConnectionFromArray($connectionName, $connectionInfo);
        } else {
            $connection = DbConnectionsManager::getConnection('default');
        }
        $tableName = $this->argument('table_name');
        $schemaName = $this->argument('schema')
            ?: $connection->getConnectionConfig()
                ->getDefaultSchemaName();
        if (
            !$connection->hasTable($tableName, $schemaName)
            && !$this->confirm("Table {$schemaName}.{$tableName} does not exist. Continue?", true)
        ) {
            return;
        }
        $builder = OrmClassesCreationUtils::getClassBuilder($tableName, $connection);
        $builder->setDbSchemaName($schemaName);
        
        $only = $this->option('only');
        $overwrite = null;
        if (in_array($this->option('overwrite'), ['1', 'yes', 'y'], true)) {
            $overwrite = true;
        } elseif (in_array($this->option('overwrite'), ['0', 'no', 'n'], true)) {
            $overwrite = false;
        }
        
        $info = $this->preapareAndGetDataForViews();
        
        if (!$only || $only === 'table') {
            $this->createTableClassFile($builder, $info, $overwrite);
        }
        if (!$only || $only === 'record') {
            $this->createRecordClassFile($builder, $info, $overwrite);
        }
        if (!$only || $only === 'structure') {
            $this->createTableStructureClassFile($builder, $info, $overwrite);
        }
        
        $this->line('Done');
    }
    
    protected function preapareAndGetDataForViews(): array
    {
        $tableName = $this->argument('table_name');
        $namespace = OrmClassesCreationUtils::getNamespaceForOrmDbClassesByTableName($tableName);
        /** @var ClassBuilder $builderClass */
        $builderClass = OrmClassesCreationUtils::getClassBuilderClass();
        $dataForViews = [
            'folder' => OrmClassesCreationUtils::getFolderPathForOrmDbClassesByTableName($tableName),
            'table' => $tableName,
            'namespace' => $namespace,
            'table_class_name' => $builderClass::makeTableClassName($tableName),
            'record_class_name' => $builderClass::makeRecordClassName($tableName),
            'structure_class_name' => $builderClass::makeTableStructureClassName($tableName),
        ];
        $dataForViews['table_file_path'] = $dataForViews['folder'] . $dataForViews['table_class_name'] . '.php';
        $dataForViews['record_file_path'] = $dataForViews['folder'] . $dataForViews['record_class_name'] . '.php';
        $dataForViews['structure_file_path'] = $dataForViews['folder'] . $dataForViews['structure_class_name'] . '.php';
        if (!File::exists($dataForViews['folder']) || !File::isDirectory($dataForViews['folder'])) {
            File::makeDirectory($dataForViews['folder'], 0755, true);
        }
        return $dataForViews;
    }
    
    protected function createTableClassFile(ClassBuilder $builder, array $info, ?bool $overwrite): void
    {
        $filePath = $info['table_file_path'];
        if (File::exists($filePath)) {
            if ($overwrite === false) {
                $this->line('Table class creation cancelled');
                return;
            } elseif ($overwrite === true) {
                File::delete($filePath);
            } elseif ($this->confirm("Table file {$filePath} already exists. Overwrite?")) {
                // overwrite is undefined: ask user what to do
                File::delete($filePath);
            } else {
                $this->line('Table class creation cancelled');
                return;
            }
        }
        $fileContents = $builder->buildTableClass($info['namespace'], $this->getTableParentClass());
        File::put($filePath, $fileContents);
        File::chmod($filePath, 0664);
        $this->line("Table class created ({$filePath})");
    }
    
    protected function createRecordClassFile(ClassBuilder $builder, array $info, ?bool $overwrite): void
    {
        $filePath = $info['record_file_path'];
        if (File::exists($filePath)) {
            if ($overwrite === false) {
                $this->line('Record class creation cancelled');
                return;
            } elseif ($overwrite === true) {
                File::delete($filePath);
            } elseif ($this->confirm("Record file {$filePath} already exists. Overwrite?")) {
                // overwrite is undefined: ask user what to do
                File::delete($filePath);
            } else {
                $this->line('Record class creation cancelled');
                return;
            }
        }
        $fileContents = $builder->buildRecordClass($info['namespace'], $this->getRecordParentClass());
        File::put($filePath, $fileContents);
        File::chmod($filePath, 0664);
        $this->line("Record class created ($filePath)");
    }
    
    protected function createTableStructureClassFile(ClassBuilder $builder, array $info, ?bool $overwrite): void
    {
        $filePath = $info['structure_file_path'];
        if (File::exists($filePath)) {
            if ($overwrite === false) {
                $this->line('TableStructure class creation cancelled');
                return;
            } elseif ($overwrite === true) {
                File::delete($filePath);
            } elseif ($this->confirm("TableStructure file {$filePath} already exists. Overwrite?")) {
                // overwrite is undefined: ask user what to do
                File::delete($filePath);
            } else {
                $this->line('TableStructure class creation cancelled');
                return;
            }
        }
        $fileContents = $builder->buildStructureClass(
            $info['namespace'],
            $this->getTableStructureParentClass(),
            $this->getTraitsForTableConfig()
        );
        File::put($filePath, $fileContents);
        File::chmod($filePath, 0664);
        $this->line("TableStructure class created ($filePath)");
    }
    
}
