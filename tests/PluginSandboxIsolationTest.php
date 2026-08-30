<?php
declare(strict_types=1);

// Nemesis 7.1.1 | Tests for Gap 3 — PluginSandbox path validation
// Updated: 2026-08-30

namespace Tests\Unit;

use Nemesis\Testing\TestCase;
use Nemesis\Core\PluginSandbox;

class PluginSandboxIsolationTest extends TestCase
{
    protected function tearDown(): void
    {
        // Always restore open_basedir after each test.
        @ini_set('open_basedir', '');
    }

    public function test_has_permission_works(): void
    {
        $sandbox = new PluginSandbox('demo', ['filesystem', 'db']);
        $this->assertTrue($sandbox->hasPermission('filesystem'));
        $this->assertTrue($sandbox->hasPermission('db'));
        $this->assertFalse($sandbox->hasPermission('network'));
    }

    public function test_require_permission_throws_for_missing(): void
    {
        $sandbox = new PluginSandbox('demo', ['filesystem']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/lacks permission: network/');
        $sandbox->requirePermission('network');
    }

    public function test_check_file_access_rejects_outside_base_path(): void
    {
        // Create a sandbox rooted at this project.
        $sandbox = new PluginSandbox('demo', ['filesystem']);

        // /etc/passwd must never be accessible.
        $this->expectException(\RuntimeException::class);
        $sandbox->checkFileAccess('/etc/passwd');
    }

    public function test_check_file_access_rejects_stream_wrappers(): void
    {
        $sandbox = new PluginSandbox('demo', ['filesystem']);

        $this->expectException(\RuntimeException::class);
        $sandbox->checkFileAccess('phar://malicious.phar/file');
    }

    public function test_check_file_access_rejects_null_bytes(): void
    {
        $sandbox = new PluginSandbox('demo', ['filesystem']);

        $this->expectException(\RuntimeException::class);
        $sandbox->checkFileAccess("/etc/passwd\0.txt");
    }

    public function test_check_file_access_rejects_relative_escape(): void
    {
        $sandbox = new PluginSandbox('demo', ['filesystem']);

        $this->expectException(\RuntimeException::class);
        $sandbox->checkFileAccess('../../../etc/passwd');
    }

    public function test_check_file_access_passes_for_existing_file_in_project(): void
    {
        $sandbox = new PluginSandbox('demo', ['filesystem']);

        // composer.json sits at the project root; realpath() will resolve it
        // and the sandbox should accept it.
        $ok = $sandbox->checkFileAccess(__DIR__ . '/../composer.json');
        $this->assertTrue($ok);
    }

    public function test_setup_and_teardown_modify_open_basedir(): void
    {
        $sandbox = new PluginSandbox('demo', ['filesystem']);
        $sandbox->run(function () {
            // Inside the sandbox, open_basedir should be set.
            $basedir = ini_get('open_basedir');
            $this->assertNotEmpty($basedir, 'open_basedir should be set inside the sandbox');
        });

        // After the sandbox exits, open_basedir should be empty again.
        $this->assertSame('', ini_get('open_basedir'), 'open_basedir should be restored after teardown');
    }
}
