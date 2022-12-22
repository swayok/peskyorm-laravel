<?php

declare(strict_types=1);

namespace PeskyORMLaravel\Tests\Unit;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use PeskyORMLaravel\Tests\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;

class CommandsTest extends TestCase
{
    public function testMakeMigrationCommand(): void
    {
        $outputBuffer = new BufferedOutput();
        $exitCode = Artisan::call(
            'orm:generate-migration',
            [
                'table_name' => 'admins',
                '--no-interaction'
            ],
            $outputBuffer
        );
        $output = $outputBuffer->fetch();
        echo "Command output: \n" . $output . "\n";
        static::assertEquals(0, $exitCode);
        static::assertStringContainsString('Created Migration:', $output);
        File::deleteDirectory($this->app->basePath('database/migrations'));
        File::makeDirectory($this->app->basePath('database/migrations'));
    }
    
    public function testMakeDbClassesCommand(): void
    {
        File::deleteDirectory($this->app->basePath('app/Db/Admins'));
        $outputBuffer = new BufferedOutput();
        $exitCode = Artisan::call(
            'orm:make-db-classes',
            [
                'table_name' => 'admins',
                '--no-interaction'
            ],
            $outputBuffer
        );
        $output = $outputBuffer->fetch();
        echo "Command output: \n" . $output . "\n";
        static::assertEquals(0, $exitCode);
        static::assertStringContainsString('Table class created:', $output);
        static::assertStringContainsString('Record class created:', $output);
        static::assertStringContainsString('TableStructure class created:', $output);
    }
}