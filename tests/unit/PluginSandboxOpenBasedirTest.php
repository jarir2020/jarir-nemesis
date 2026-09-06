<?php
declare(strict_types=1);

namespace Tests\Unit;

use Nemesis\Core\PluginSandbox;
use Nemesis\Testing\TestCase;

class PluginSandboxOpenBasedirTest extends TestCase
{
    public function test_run_preserves_request_open_basedir(): void
    {
        $sandbox = new PluginSandbox('demo', ['filesystem']);
        $before = ini_get('open_basedir');

        $sandbox->run(function () use ($before): void {
            $this->assertSame($before, ini_get('open_basedir'));
        });

        $this->assertSame($before, ini_get('open_basedir'));
    }
}
