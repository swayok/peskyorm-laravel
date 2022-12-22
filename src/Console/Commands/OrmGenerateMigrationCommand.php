<?php

declare(strict_types=1);

namespace PeskyORMLaravel\Console\Commands;

use Illuminate\Database\Console\Migrations\BaseCommand;
use Illuminate\Support\Composer;
use Illuminate\Support\Str;
use PeskyORM\Adapter\DbAdapterInterface;
use PeskyORM\Config\Connection\DbConnectionsFacade;
use PeskyORM\DbExpr;
use PeskyORM\TableDescription\ColumnDescriptionDataType;
use PeskyORM\TableDescription\ColumnDescriptionInterface;
use PeskyORM\TableDescription\TableDescriptionFacade;

class OrmGenerateMigrationCommand extends BaseCommand
{

    protected $signature = 'orm:generate-migration 
                    {table_name} 
                    {schema?}
                    {--connection=default : name of connection to use}
                    {--path= : The location where the migration file should be created.}';

    protected $description = 'Create migration based on existing table in DB';

    public function __construct(
        protected Composer $composer
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $connectionName = $this->option('connection');
        $connection = DbConnectionsFacade::getConnection($connectionName);
        $tableName = trim($this->argument('table_name'));
        $schemaName = $this->argument('schema');
        if (!$connection->hasTable($tableName, $schemaName)) {
            if (empty($schemaName)) {
                $schemaName = $connection->getDefaultTableSchema();
            }
            $this->error("Table '{$schemaName}.{$tableName}' not found in database");
            return self::INVALID;
        }

        $this->writeMigration($connection, $tableName, $schemaName);
        $this->composer->dumpAutoloads();
        return self::SUCCESS;
    }

    protected function writeMigration(DbAdapterInterface $connection, $tableName, $schemaName): void
    {
        $data = $this->collectDataForTableSchema($connection, $tableName, $schemaName);
        $className = $this->getClassName($tableName, $schemaName);
        if (class_exists($className)) {
            $this->error("A {$className} class already exists.");
            return;
        }
        $fileContents = str_replace(
            [':class', ':table', ':columns', ':indexes', ':foreign_keys'],
            [
                $className,
                $tableName,
                implode("\n            ", $data['columns']),
                implode("\n            ", $data['indexes']),
                implode("\n            ", $data['foreign_keys']),
            ],
            $this->getTemplate()
        );

        $fileName = $this->getFullFileName($tableName, $schemaName);
        file_put_contents($this->getMigrationPath() . '/' . $fileName, $fileContents);

        $this->line("<info>Created Migration:</info> {$fileName}");
    }

    protected function getFullFileName($tableName, $schemaName): string
    {
        return date('Y_m_d_His_') . $this->getBaseFileName($tableName, $schemaName) . '.php';
    }

    protected function getBaseFileName($tableName, $schemaName): string
    {
        $name = 'create_' . $tableName . '_table';
        if (!empty($schemaName)) {
            $name .= '_in_' . $schemaName . '_schema';
        }
        return Str::snake($name);
    }

    protected function getClassName($tableName, $schemaName): string
    {
        return Str::studly($this->getBaseFileName($tableName, $schemaName));
    }

    protected function getMigrationPath(): string
    {
        if (null !== ($targetPath = $this->input->getOption('path'))) {
            return $this->laravel->basePath() . '/' . $targetPath;
        }

        return parent::getMigrationPath();
    }

    protected function getTemplate(): string
    {
        return file_get_contents(__DIR__ . '/stub/migration');
    }

    /**
     * @param DbAdapterInterface $connection
     * @param string $tableName
     * @param string|null $schemaName
     * @return array
     */
    protected function collectDataForTableSchema(
        DbAdapterInterface $connection,
        string $tableName,
        ?string $schemaName
    ): array {
        $tableInfo = TableDescriptionFacade::describeTable(
            $connection,
            $tableName,
            $schemaName
        );
        $columns = [];
        $indexes = [];
        $foreignKeys = [];
        foreach ($tableInfo->getColumns() as $columnDescription) {
            $columns = array_merge(
                $columns,
                $this->buildColumn($columnDescription)
            );
            $indexes = array_merge(
                $indexes,
                $this->buildIndexes($columnDescription)
            );
            $foreignKeys = array_merge(
                $foreignKeys,
                $this->buildForeignKeys($columnDescription)
            );
        }
        return [
            'columns' => $columns,
            'indexes' => $indexes,
            'foreign_keys' => $foreignKeys,
        ];
    }

    /**
     * @param ColumnDescriptionInterface $columnDescription
     * @return array
     */
    protected function buildColumn(ColumnDescriptionInterface $columnDescription): array
    {
        if (
            $columnDescription->isPrimaryKey()
            && $columnDescription->getOrmType() === ColumnDescriptionDataType::INT
        ) {
            /** @noinspection PhpSwitchCanBeReplacedWithMatchExpressionInspection */
            switch ($columnDescription->getDbType()) {
                case 'tinyint':
                    return ["\$table->tinyIncrements('{$columnDescription->getName()}');"];
                case 'int2';
                case 'smallint';
                    return ["\$table->smallIncrements('{$columnDescription->getName()}');"];
                case 'int8';
                case 'bigint';
                    return ["\$table->bigIncrements('{$columnDescription->getName()}');"];
                default:
                    return ["\$table->increments('{$columnDescription->getName()}');"];
            }
        }
        $column = [$this->generateColumnType($columnDescription)];
        if ($columnDescription->isPrimaryKey()) {
            $column[] = '    ->primary()';
        }
        if ($columnDescription->isUnique()) {
            $column[] = '    ->unique()';
        }
        if ($columnDescription->isNullable()) {
            $column[] = '    ->nullable()';
        }
        $default = $columnDescription->getDefault();
        if ($default !== null) {
            if ($default instanceof DbExpr) {
                $default = $default->setWrapInBrackets(false)
                    ->get();
                $column[] = "    ->default(\DB::raw('{$default}'))";
            } elseif (is_string($default)) {
                $column[] = "    ->default('{$default}')";
            } elseif (is_bool($default)) {
                $default = $default ? 'true' : 'false';
                $column[] = "    ->default({$default})";
            } else {
                $column[] = "    ->default({$default})";
            }
        }
        $column[count($column) - 1] .= ';';
        return $column;
    }

    protected function generateColumnType(ColumnDescriptionInterface $columnDescription): string
    {
        $name = $columnDescription->getName();
        switch ($columnDescription->getOrmType()) {
            case ColumnDescriptionDataType::INT:
                /** @noinspection PhpSwitchCanBeReplacedWithMatchExpressionInspection */
                switch ($columnDescription->getDbType()) {
                    case 'tinyint':
                        return "\$table->tinyInteger('{$name}')";
                    case 'int2';
                    case 'smallint';
                        return "\$table->smallInteger('{$name}')";
                    case 'int8';
                    case 'bigint';
                        return "\$table->bigInteger('{$name}')";
                    default:
                        return "\$table->integer('{$name}')";
                }
            case ColumnDescriptionDataType::FLOAT:
                $limit = $columnDescription->getLimit();
                if ($columnDescription->getNumberPrecision()) {
                    // this is very important in case of postgresql because float()
                    // ignores limit and precision:
                    // see Illuminate\Database\Schema\Grammars\PostgresGrammar::typeFloat
                    // - it generates 'double precision' while we need
                    // 'decimal(limit, precision)' or 'numeric(limit, precision)'
                    $precision = $columnDescription->getNumberPrecision();
                    return "\$table->decimal('{$name}', {$limit}, {$precision})";
                }
                return "\$table->float('{$name}', {$limit})";
            case ColumnDescriptionDataType::BOOL:
                return "\$table->boolean('{$name}')";
            case ColumnDescriptionDataType::TEXT:
                return match ($columnDescription->getDbType()) {
                    'mediumtext' => "\$table->mediumText('{$name}')",
                    'longtext' => "\$table->longText('{$name}')",
                    default => "\$table->text('{$name}')",
                };
            case ColumnDescriptionDataType::JSON:
                if ($columnDescription->getDbType() === 'jsonb') {
                    return "\$table->jsonb('{$name}')";
                }
                return "\$table->json('{$name}')";
            case ColumnDescriptionDataType::BLOB:
                return "\$table->binary('{$name}')";
            case ColumnDescriptionDataType::STRING:
                $limit = $columnDescription->getLimit();
                return match ($columnDescription->getDbType()) {
                    'char' => "\$table->char('{$name}', {$limit})",
                    'inet' => "\$table->ipAddress('{$name}')",
                    default => "\$table->string('{$name}', {$limit})"
                };
            case ColumnDescriptionDataType::DATE:
                return "\$table->date('{$name}')";
            case ColumnDescriptionDataType::TIME:
                return "\$table->time('{$name}')";
            case ColumnDescriptionDataType::TIME_WITH_TZ:
                return "\$table->timeTz('{$name}')";
            case ColumnDescriptionDataType::TIMESTAMP:
                return "\$table->timestamp('{$name}')";
            case ColumnDescriptionDataType::TIMESTAMP_WITH_TZ:
                return "\$table->timestampTz('{$name}')";
            default:
                return "\$table->string('{$name}', {$columnDescription->getLimit()})";
        }
    }

    /** @noinspection PhpUnusedParameterInspection */
    protected function buildIndexes(ColumnDescriptionInterface $columnDescription): array
    {
        return [];
    }

    protected function buildForeignKeys(ColumnDescriptionInterface $columnDescription): array
    {
        if ($columnDescription->isForeignKey()) {
            $possibleTableName = '';
            if (preg_match('%^(.*?)_id$%is', $columnDescription->getName(), $matches)) {
                $possibleTableName = Str::plural($matches[1]);
            }
            return [
                "\$table->foreign('{$columnDescription->getName()}')",
                "    ->references('id')",
                "    ->on('$possibleTableName')",
                "    ->onDelete('cascade')",
                "    ->onUpdate('cascade');",
            ];
        }
        return [];
    }

}