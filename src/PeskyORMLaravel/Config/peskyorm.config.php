<?php

return [

    /**
     * Directory where DB classes are stored.
     * Used by OrmMakeDbClassesCommand command
     */
    'classes_path' => app_path('Db'),

    /**
     * Base namespace for DB classes
     * Used by OrmMakeDbClassesCommand command
     */
    'classes_namespace' => 'App\\Db',

    /**
     * Classes to use as parents for classes generated by OrmMakeDbClassesCommand command
     */
    'base_table_class' => \PeskyORM\ORM\Table::class,
    'base_record_class' => \PeskyORM\ORM\Record::class,
    'base_table_structure_class' => \PeskyORM\ORM\TableStructure::class,

    /**
     * DB classes builder class used to generate DB classes by table name
     * Used by OrmMakeDbClassesCommand command
     */
    'class_builder' => \PeskyORM\ORM\ClassBuilder::class,

    /**
     * Traits with DB table columns declarations to use instead of declaring that columns
     * Used by OrmMakeDbClassesCommand command (passed to class_builder during table structure class generation)
     */
    'table_structure_traits' => [
        \PeskyORMLaravel\Db\TableStructureTraits\IdColumn::class,              // id
        \PeskyORMLaravel\Db\TableStructureTraits\IsActiveColumn::class,        // is_active
        \PeskyORMLaravel\Db\TableStructureTraits\IsPublishedColumn::class,     // is_published
        \PeskyORMLaravel\Db\TableStructureTraits\UserAuthColumns::class,       // password and remember_token
        \PeskyORMLaravel\Db\TableStructureTraits\PasswordColumn::class,        // password
        \PeskyORMLaravel\Db\TableStructureTraits\TimestampColumns::class,      // created_at and updated_at
        \PeskyORMLaravel\Db\TableStructureTraits\CreatedAtColumn::class,       // created_at
        \PeskyORMLaravel\Db\TableStructureTraits\PositionColumn::class,        // position
    ],

    /**
     * Class that wraps PDO instance in order to modify something or collect some data
     * By default there are 2 options:
     * 1. \PeskyORMLaravel\Profiling\PeskyOrmDebugBarPdoTracer::class - used to provide data for DebugBar library
     * 2. \PeskyORM\Profiling\TraceablePDO::class - native PeskyORM wrapper that collects stats about
     *    query execution and stores it into \PeskyORM\Profiling\PeskyOrmPdoProfiler
     */
    'pdo_wrapper' => null,
];