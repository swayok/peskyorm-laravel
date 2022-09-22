<?php

namespace PeskyORMLaravel\Console\Commands;

use Illuminate\Console\Command;
use PeskyORM\Core\DbConnectionsManager;
use PeskyORM\ORM\ClassBuilder;
use PeskyORM\ORM\Record;
use PeskyORM\ORM\Table;
use PeskyORM\ORM\TableStructure;
use PeskyORMLaravel\Db\OrmDbClassesUtils;

class OrmMakeDbClassesCommand extends Command {

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'orm:make-db-classes {table_name} {schema?} {database_classes_app_subfolder=Db}'
                            . ' {--overwrite= : 1|0|y|n|yes|no; what to do if classes already exist}'
                            . ' {--only= : table|record|structure; create only specified class}'
                            . ' {--connection= : name of connection to use}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create classes for DB table.';

    protected function getTableParentClass() {
        return config('peskyorm.base_table_class', Table::class);
    }

    protected function getRecordParentClass() {
        return config('peskyorm.base_record_class', Record::class);
    }

    protected function getTableStructureParentClass() {
        return config('peskyorm.base_table_structure_class', TableStructure::class);
    }

    protected function getClassBuilderClass() {
        return OrmDbClassesUtils::getClassBuilderClass();
    }

    /**
     * @return array (
     *      NameOfTrait1::class,
     *      NameOfTrait2::class,
     * )
     */
    protected function getTraitsForTableConfig() {
        return (array)config('peskyorm.table_structure_traits', []);
    }

    /**
     * Execute the console command.
     *
     * @throws \InvalidArgumentException
     */
    public function handle() {
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
        $schemaName = $this->getDbSchema($connection->getConnectionConfig()->getDefaultSchemaName());
        if (
            !$connection->hasTable($tableName, $schemaName)
            && !$this->confirm("Table {$schemaName}.{$tableName} does not exist. Continue?", true)
        ) {
            return;
        }
        $builder = OrmDbClassesUtils::getClassBuilder($tableName, $connection);
        $builder->setDbSchemaName($schemaName);

        $only = $this->option('only');
        $overwrite = null;
        if (in_array($this->option('overwrite'), ['1', 'yes', 'y'], true)) {
            $overwrite = true;
        } else if (in_array($this->option('overwrite'), ['0', 'no', 'n'], true)) {
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
    
    /**
     * can be overriden in subclass
     */
    protected function getDbSchema(?string $default = null): ?string {
        return $this->argument('schema') ?: $default;
    }

    protected function preapareAndGetDataForViews() {
        $tableName = $this->argument('table_name');
        $namespace = OrmDbClassesUtils::getNamespaceForOrmDbClassesByTableName($tableName);
        /** @var ClassBuilder $builderClass */
        $builderClass = OrmDbClassesUtils::getClassBuilderClass();
        $dataForViews = [
            'folder' => OrmDbClassesUtils::getFolderPathForOrmDbClassesByTableName($tableName),
            'table' => $tableName,
            'namespace' => $namespace,
            'table_class_name' => $builderClass::makeTableClassName($tableName),
            'record_class_name' => $builderClass::makeRecordClassName($tableName),
            'structure_class_name' => $builderClass::makeTableStructureClassName($tableName),
        ];
        $dataForViews['table_file_path'] = $dataForViews['folder'] . $dataForViews['table_class_name'] . '.php';
        $dataForViews['record_file_path'] = $dataForViews['folder'] . $dataForViews['record_class_name'] . '.php';
        $dataForViews['structure_file_path'] = $dataForViews['folder'] . $dataForViews['structure_class_name'] . '.php';
        if (!\File::exists($dataForViews['folder']) || !\File::isDirectory($dataForViews['folder'])) {
            \File::makeDirectory($dataForViews['folder'], 0755, true);
        }
        return $dataForViews;
    }

    protected function createTableClassFile(ClassBuilder $builder, array $info, $overwrite) {
        $filePath = $info['table_file_path'];
        if (\File::exists($filePath)) {
            if ($overwrite === false) {
                $this->line('Table class creation cancelled');
                return;
            } else if ($overwrite === true) {
                \File::delete($filePath);
            } else if ($this->confirm("Table file {$filePath} already exists. Overwrite?")) {
                // overwrite is undefined: ask user what to do
                \File::delete($filePath);
            } else {
                $this->line('Table class creation cancelled');
                return;
            }
        }
        $fileContents = $builder->buildTableClass($info['namespace'], $this->getTableParentClass());
        \File::put($filePath, $fileContents);
        \File::chmod($filePath, 0664);
        $this->line("Table class created ({$filePath})");
    }

    protected function createRecordClassFile(ClassBuilder $builder, array $info, $overwrite) {
        $filePath = $info['record_file_path'];
        if (\File::exists($filePath)) {
            if ($overwrite === false) {
                $this->line('Record class creation cancelled');
                return;
            } else if ($overwrite === true) {
                \File::delete($filePath);
            } else if ($this->confirm("Record file {$filePath} already exists. Overwrite?")) {
                // overwrite is undefined: ask user what to do
                \File::delete($filePath);
            } else {
                $this->line('Record class creation cancelled');
                return;
            }
        }
        $fileContents = $builder->buildRecordClass($info['namespace'], $this->getRecordParentClass());
        \File::put($filePath, $fileContents);
        \File::chmod($filePath, 0664);
        $this->line("Record class created ($filePath)");
    }

    protected function createTableStructureClassFile(ClassBuilder $builder, array $info, $overwrite) {
        $filePath = $info['structure_file_path'];
        if (\File::exists($filePath)) {
            if ($overwrite === false) {
                $this->line('TableStructure class creation cancelled');
                return;
            } else if ($overwrite === true) {
                \File::delete($filePath);
            } else if ($this->confirm("TableStructure file {$filePath} already exists. Overwrite?")) {
                // overwrite is undefined: ask user what to do
                \File::delete($filePath);
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
        \File::put($filePath, $fileContents);
        \File::chmod($filePath, 0664);
        $this->line("TableStructure class created ($filePath)");
    }

}
