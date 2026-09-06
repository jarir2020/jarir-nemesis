<?php
declare(strict_types=1);

namespace Tests\Unit;

use Nemesis\Http\Session;
use Nemesis\Testing\TestCase;

class SessionPathTest extends TestCase
{
    private string $previousSavePath = '';

    public function setUp(): void
    {
        $this->previousSavePath = session_save_path();
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        Session::boot(null);
    }

    public function tearDown(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        @session_save_path($this->previousSavePath);
        Session::boot(null);
    }

    public function test_session_uses_project_local_save_path(): void
    {
        $path = base_path('storage/session');
        $script = <<<'PHP'
require getcwd() . '/vendor/autoload.php';
Nemesis\Core\Config::load(getcwd());
Nemesis\Http\Session::boot(new Nemesis\Config\SessionConfig(
    driver: 'file',
    lifetime: 120,
    cookieName: 'nemesis_session_test',
    secure: false,
    sameSite: 'lax',
    path: getcwd() . '/storage/session',
));
new Nemesis\Http\Session();
echo session_save_path();
PHP;

        $pipes = [];
        $process = proc_open([PHP_BINARY, '-r', $script], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path());
        if (!is_resource($process)) {
            throw new \RuntimeException('Unable to start the session path regression process.');
        }

        $output = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        $this->assertSame(0, $exitCode, $error);
        $this->assertSame(realpath($path), realpath(trim($output)));
    }
}
