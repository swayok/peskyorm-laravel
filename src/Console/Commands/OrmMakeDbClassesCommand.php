<?php

declare(strict_types=1);

namespace PeskyORMLaravel\Console\Commands;

use Illuminate\Config\Repository as ConfigsRepository;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use PeskyORM\Adapter\DbAdapterInterface;
use PeskyORM\Config\Connection\DbConnectionsFacade;
use PeskyORM\ORM\ClassBuilder\ClassBuilder;
use PeskyORM\ORM\Record\Record;
use PeskyORM\ORM\Table\Table;
use PeskyORM\ORM\TableStructure\TableColumnFactoryInterface;
use PeskyORM\ORM\TableStructure\TableStructure;
use PeskyORM\TableDescription\TableDescriptionFacade;
use PeskyORM\Utils\ServiceContainer;
use PeskyORM\Utils\StringUtils;

class OrmMakeDbClassesCommand extends Command
{
    protected $signature = 'orm:make-db-classes 
            {table_name} 
            {schema?} 
            {--overwrite= : 1|0|y|n|yes|no; what to do if classes already exist}
            {--only= : table|record|structure; create only specified class}
            {--connection=default : name of connection to use}
        ';

    protected $description = 'Create classes for DB table.';

    public function __construct(
        protected ConfigsRepository $configsRepository
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $connectionName = $this->option('connection');
        $connection = DbConnectionsFacade::getConnection($connectionName);
        $tableName = $this->argument('table_name');
        $schemaName = $this->argument('schema')
            ?: $connection->getConnectionConfig()
                ->getDefaultSchemaName();
        if (
            !$connection->hasTable($tableName, $schemaName)
            && !$this->confirm(
                "Table {$schemaName}.{$tableName} does not exist. Continue?", true
            )
        ) {
            return self::INVALID;
        }
        $builder = $this->getClassBuilder($connection, $tableName, $schemaName);

        $only = $this->option('only');
        $overwrite = null;
        if (in_array($this->option('overwrite'), ['1', 'yes', 'y'], true)) {
            $overwrite = true;
        } elseif (in_array($this->option('overwrite'), ['0', 'no', 'n'], true)) {
            $overwrite = false;
        }

        $folderPath = $this->getFolderPathToTableClasses($tableName);
        if (!File::exists($folderPath) || !File::isDirectory($folderPath)) {
            File::makeDirectory($folderPath, 0755, true);
        }

        if (!$only || $only === 'table') {
            $this->createTableClassFile($builder, $folderPath, $overwrite);
        }
        if (!$only || $only === 'record') {
            $this->createRecordClassFile($builder, $folderPath, $overwrite);
        }
        if (!$only || $only === 'structure') {
            $this->createTableStructureClassFile($builder, $folderPath, $overwrite);
        }

        $this->line('Done');
        return self::SUCCESS;
    }

    protected function getClassBuilderClass(): string
    {
        return $this->configsRepository->get(
            'peskyorm.class_builder',
            ClassBuilder::class
        );
    }

    protected function getClassBuilder(
        DbAdapterInterface $connection,
        string $tableName,
        ?string $schemaName = null
    ): ClassBuilder {
        /** @var ClassBuilder $class */
        $class = $this->getClassBuilderClass();
        return new $class(
            TableDescriptionFacade::describeTable($connection, $tableName, $schemaName),
            ServiceContainer::getInstance()->make(TableColumnFactoryInterface::class),
            $this->getNamespaceForTableClasses($tableName)
        );
    }

    protected function getRootNamespace(): string
    {
        $namespace = $this->configsRepository->get('peskyorm.classes_namespace', 'App\\Db');
        return trim($namespace, ' \\');
    }

    protected function getNamespaceForTableClasses(string $tableName): string
    {
        return $this->getRootNamespace() . '\\' . StringUtils::toPascalCase($tableName);
    }

    protected function getFolderPathToTableClasses(string $tableName): string
    {
        $relativePath = preg_replace(
            ['%^App\\\\%', '%\\\\%'],
            ['app\\', DIRECTORY_SEPARATOR],
            $this->getNamespaceForTableClasses($tableName)
        );
        return base_path($relativePath) . DIRECTORY_SEPARATOR;
    }

    protected function createTableClassFile(
        ClassBuilder $builder,
        string $folderPath,
        ?bool $overwrite
    ): void {
        $className = $builder->getClassName(ClassBuilder::TEMPLATE_TABLE);
        $filePath = $folderPath . $className . '.php';
        if (File::exists($filePath)) {
            if (
                $overwrite === false
                || !$this->confirm("Table file {$filePath} already exists. Overwrite?")
            ) {
                $this->line(
                    "Table class creation cancelled. File {$filePath} already exists."
                );
                return;
            }
            File::delete($filePath);
        }
        $fileContents = $builder->buildTableClass($this->getTableParentClass());

        File::put($filePath, $fileContents);
        File::chmod($filePath, 0664);
        $this->line("Table class created: {$filePath}");
    }

    protected function getTableParentClass(): string
    {
        return $this->configsRepository->get(
            'peskyorm.base_table_class',
            Table::class
        );
    }

    protected function createRecordClassFile(
        ClassBuilder $builder,
        string $folderPath,
        ?bool $overwrite
    ): void {
        $className = $builder->getClassName(ClassBuilder::TEMPLATE_RECORD);
        $filePath = $folderPath . $className . '.php';
        if (File::exists($filePath)) {
            if (
                $overwrite === false
                || !$this->confirm("Record file {$filePath} already exists. Overwrite?")
            ) {
                $this->line(
                    "Record class creation cancelled. File {$filePath} already exists."
                );
                return;
            }
            File::delete($filePath);
        }
        $fileContents = $builder->buildRecordClass($this->getRecordParentClass());
        File::put($filePath, $fileContents);
        File::chmod($filePath, 0664);
        $this->line("Record class created: {$filePath}");
    }

    protected function getRecordParentClass(): string
    {
        return $this->configsRepository->get(
            'peskyorm.base_record_class',
            Record::class
        );
    }

    protected function createTableStructureClassFile(
        ClassBuilder $builder,
        string $folderPath,
        ?bool $overwrite
    ): void {
        $className = $builder->getClassName(ClassBuilder::TEMPLATE_TABLE_STRUCTURE);
        $filePath = $folderPath . $className . '.php';
        if (File::exists($filePath)) {
            if (
                $overwrite === false
                || !$this->confirm("TableStructure file {$filePath} already exists. Overwrite?")
            ) {
                $this->line(
                    "TableStructure class creation cancelled. File {$filePath} already exists."
                );
                return;
            }
            File::delete($filePath);
        }
        $fileContents = $builder->buildStructureClass($this->getTableStructureParentClass());
        File::put($filePath, $fileContents);
        File::chmod($filePath, 0664);
        $this->line("TableStructure class created: {$filePath}");
    }

    protected function getTableStructureParentClass(): string
    {
        return $this->configsRepository->get(
            'peskyorm.base_table_structure_class',
            TableStructure::class
        );
    }

}
