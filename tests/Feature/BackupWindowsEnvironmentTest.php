<?php

namespace Tests\Feature;

use App\Services\BackupService;
use Tests\TestCase;

/**
 * The web page ("Backup Now") failed on Windows with
 * "mysqldump: Got error: 2004: Can't create TCP/IP socket (10106)"
 * while `php artisan clinic:backup` worked: the web process had no SystemRoot.
 */
class BackupWindowsEnvironmentTest extends TestCase
{
    private function env(bool $windows): array
    {
        $method = new \ReflectionMethod(BackupService::class, 'systemEnvironment');
        $method->setAccessible(true);

        return $method->invoke(new BackupService(), $windows);
    }

    public function test_on_windows_the_system_root_is_always_passed_to_mysqldump(): void
    {
        $env = $this->env(true);

        $this->assertNotEmpty($env['SystemRoot']);
        $this->assertSame($env['SystemRoot'], $env['SYSTEMROOT']);
        $this->assertSame($env['SystemRoot'], $env['windir']);
    }

    public function test_windows_falls_back_to_the_standard_folder_when_the_variable_is_missing(): void
    {
        $saved = [getenv('SystemRoot'), getenv('SYSTEMROOT'), getenv('windir')];
        putenv('SystemRoot'); putenv('SYSTEMROOT'); putenv('windir');

        try {
            $this->assertSame('C:\\Windows', $this->env(true)['SystemRoot']);
        } finally {
            foreach (['SystemRoot', 'SYSTEMROOT', 'windir'] as $i => $name) {
                if ($saved[$i] !== false) {
                    putenv("$name={$saved[$i]}");
                }
            }
        }
    }

    public function test_nothing_is_added_on_linux_or_hostinger(): void
    {
        $this->assertSame([], $this->env(false));
    }

    public function test_the_system_environment_is_really_handed_to_the_process(): void
    {
        $source = file_get_contents(base_path('app/Services/BackupService.php'));

        $this->assertMatchesRegularExpression('/->env\(\$this->systemEnvironment\(\)\)\s*->run\(\$command\)/', $source);
    }
}
