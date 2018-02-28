<?php

namespace PeskyORMLaravel\Console\Commands;

use Illuminate\Database\Console\Migrations\BaseCommand;
use Illuminate\Support\Composer;
use Illuminate\Support\Str;
use PeskyORM\Core\ColumnDescription;
use PeskyORM\Core\DbAdapterInterface;
use PeskyORM\Core\DbConnectionsManager;
use PeskyORM\Core\DbExpr;
use PeskyORM\ORM\Column;

class OrmGenerateMigrationCommand extends BaseCommand {

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'orm:generate-migration {table_name} {schema?}
                                {--connection= : name of connection to use}
                                {--path= : The location where the migration file should be created.}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create migration based on existing table in DB';

    /**
     * @var Composer
     */
    protected $composer;

    public function __construct(Composer $composer) {
        parent::__construct();

        $this->composer = $composer;
    }

    public function handle() {
        $connectionName = $this->option('connection');
        if (!empty($connectionName)) {
            $connectionInfo = config('database.connections.' . $connectionName);
            if (!is_array($connectionInfo)) {
                $this->error("There is no configuration info for connection '{$connectionName}'");

                return;
            }
            $connection = DbConnectionsManager::createConnectionFromArray($connectionName, $connectionInfo);
        } else {
            $connection = DbConnectionsManager::getConnection('default');
        }
        $tableName = trim($this->argument('table_name'));
        $schemaName = $this->argument('schema');
        if (!$connection->hasTable($tableName, $schemaName)) {
            if (empty($schemaName)) {
                $schemaName = $connection->getDefaultTableSchema();
            }
            $this->error("Table '{$schemaName}.{$tableName}' not found in database");

            return;
        }

        $this->writeMigration($connection, $tableName, $schemaName);
        $this->composer->dumpAutoloads();
    }

    protected function writeMigration(DbAdapterInterface $connection, $tableName, $schemaName) {
        $data = $this->collectDataForTableSchema($connection, $tableName, $schemaName);
        $className = $this->getClassName($tableName, $schemaName);
        if (class_exists($className)) {
            $this->error("A {$className} class already exists.");
            return;
        }
        $fileContents = str_replace(
            array(':class', ':table', ':columns', ':indexes', ':foreign_keys'),
            array(
                $className,
                $tableName,
                implode("\n            ", $data['columns']),
                implode("\n            ", $data['indexes']),
                implode("\n            ", $data['foreign_keys'])
            ),
            $this->getTemplate()
        );

        $fileName = $this->getFullFileName($tableName, $schemaName);
        file_put_contents($this->getMigrationPath() . '/' . $fileName, $fileContents);

        $this->line("<info>Created Migration:</info> {$fileName}");
    }

    protected function getFullFileName($tableName, $schemaName) {
        return date('Y_m_d_His_') . $this->getBaseFileName($tableName, $schemaName) . '.php';
    }

    protected function getBaseFileName($tableName, $schemaName) {
        $name = 'create_' . $tableName . '_table';
        if (!empty($schemaName)) {
            $name .= '_in_' . $schemaName . '_schema';
        }
        return Str::snake($name);
    }

    protected function getClassName($tableName, $schemaName) {
        return Str::studly($this->getBaseFileName($tableName, $schemaName));
    }

    protected function getMigrationPath() {
        if (null !== ($targetPath = $this->input->getOption('path'))) {
            return $this->laravel->basePath() . '/' . $targetPath;
        }

        return parent::getMigrationPath();
    }

    protected function getTemplate() {
        return file_get_contents(__DIR__ . '/stub/migration');
    }

    /**
     * @param DbAdapterInterface $connection
     * @param string $tableName
     * @param string|null $schemaName
     * @return array
     */
    protected function collectDataForTableSchema(DbAdapterInterface $connection, $tableName, $schemaName) {
        $tableInfo = $connection->describeTable($tableName, $schemaName);
        $columns = [];
        $indexes = [];
        $foreignKeys = [];
        foreach ($tableInfo->getColumns() as $columnDescription) {
            $columns = array_merge($columns, $this->buildColumn($columnDescription));
            $indexes = array_merge($indexes, $this->buildIndexes($columnDescription));
            $foreignKeys = array_merge($foreignKeys, $this->buildForeignKeys($columnDescription));
        }
        return [
            'columns' => $columns,
            'indexes' => $indexes,
            'foreign_keys' => $foreignKeys
        ];
    }

    /**
     * @param ColumnDescription $columnDescription
     * @return array
     */
    protected function buildColumn(ColumnDescription $columnDescription) {
        if ($columnDescription->isPrimaryKey() && $columnDescription->getOrmType() === Column::TYPE_INT) {
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
                $default = $default->setWrapInBrackets(false)->get();
                $column[] = "    ->default(\DB::raw('{$default}'))";
            } else if (is_string($default)) {
                $column[] = "    ->default('{$default}')";
            } else if (is_bool($default)) {
                $default = $default ? 'true' : 'false';
                $column[] = "    ->default({$default})";
            } else {
                $column[] = "    ->default({$default})";
            }
        }
        $column[count($column) - 1] .= ';';
        return $column;
    }

    protected function generateColumnType(ColumnDescription $columnDescription) {
        switch ($columnDescription->getOrmType()) {
            case Column::TYPE_INT:
                switch ($columnDescription->getDbType()) {
                    case 'tinyint':
                        return "\$table->tinyInteger('{$columnDescription->getName()}')";
                    case 'int2';
                    case 'smallint';
                        return "\$table->smallInteger('{$columnDescription->getName()}')";
                    case 'int8';
                    case 'bigint';
                        return "\$table->bigInteger('{$columnDescription->getName()}')";
                    default:
                        return "\$table->integer('{$columnDescription->getName()}')";
                }
            case Column::TYPE_FLOAT:
                if ($columnDescription->getNumberPrecision()) {
                    // this is very important in case of postgresql because float() ignores limit and precision:
                    // see Illuminate\Database\Schema\Grammars\PostgresGrammar::typeFloat - it generates 'double precision'
                    // while we need 'decimal(limit, precision)' or 'numeric(limit, precision)'
                    return "\$table->decimal('{$columnDescription->getName()}', {$columnDescription->getLimit()}, {$columnDescription->getNumberPrecision()})";
                } else {
                    return "\$table->float('{$columnDescription->getName()}', {$columnDescription->getLimit()})";
                }
            case Column::TYPE_BOOL:
                return "\$table->boolean('{$columnDescription->getName()}')";
            case Column::TYPE_TEXT:
                switch ($columnDescription->getDbType()) {
                    case 'mediumtext':
                        return "\$table->mediumText('{$columnDescription->getName()}')";
                    case 'longtext':
                        return "\$table->longText('{$columnDescription->getName()}')";
                    default:
                        return "\$table->text('{$columnDescription->getName()}')";
                }
            case Column::TYPE_JSON:
                return "\$table->json('{$columnDescription->getName()}')";
            case Column::TYPE_JSONB:
                return "\$table->jsonb('{$columnDescription->getName()}')";
            case Column::TYPE_BLOB:
                return "\$table->binary('{$columnDescription->getName()}')";
            case Column::TYPE_STRING:
                switch ($columnDescription->getDbType()) {
                    case 'char':
                        return "\$table->char('{$columnDescription->getName()}', {$columnDescription->getLimit()})";
                    default:
                        return "\$table->string('{$columnDescription->getName()}', {$columnDescription->getLimit()})";
                }
            case Column::TYPE_DATE:
                return "\$table->date('{$columnDescription->getName()}')";
            case Column::TYPE_TIME:
                if ($columnDescription->getDbType() === 'timetz') {
                    return "\$table->timeTz('{$columnDescription->getName()}')";
                } else {
                    return "\$table->time('{$columnDescription->getName()}')";
                }
            case Column::TYPE_TIMESTAMP:
                return "\$table->timestamp('{$columnDescription->getName()}')";
            case Column::TYPE_TIMESTAMP_WITH_TZ:
                return "\$table->timestampTz('{$columnDescription->getName()}')";
            case Column::TYPE_UNIX_TIMESTAMP:
                return "\$table->bigInteger('{$columnDescription->getName()}')";
            case Column::TYPE_IPV4_ADDRESS:
                return "\$table->ipAddress('{$columnDescription->getName()}')";
            default:
                return "\$table->string('{$columnDescription->getName()}', {$columnDescription->getLimit()})";
        }
    }

    protected function buildIndexes(ColumnDescription $columnDescription) {
        return [];
    }

    protected function buildForeignKeys(ColumnDescription $columnDescription) {
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
                "    ->onUpdate('cascade');"
            ];
        }
        return [];
    }

}