<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Nemesis\Testing\TestCase;

class FrontendCliCommandsTest extends TestCase
{
    public function testFrontendListCommandShowsExpandedFrameworkCatalog(): void
    {
        $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(base_path('bin/nemesis')) . ' frontend:list 2>&1';
        $output = [];
        $code = 0;
        exec($cmd, $output, $code);

        $text = implode("\n", $output);
        $this->assertSame(0, $code);
        $this->assertStringContainsString('Nemesis Frontend Frameworks:', $text);
        $this->assertStringContainsString('astro', $text);
        $this->assertStringContainsString('sveltekit', $text);
        $this->assertStringContainsString('jquery', $text);
    }

    public function testFrontendListCommandCanEmitJsonPayload(): void
    {
        $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(base_path('bin/nemesis')) . ' frontend:list --json --brief 2>&1';
        $output = [];
        $code = 0;
        exec($cmd, $output, $code);

        $this->assertSame(0, $code, implode("\n", $output));

        $payload = json_decode(implode("\n", $output), true);
        $this->assertIsArray($payload);
        $this->assertSame('Nemesis Frontend Frameworks', $payload['title'] ?? null);
        $this->assertGreaterThan(0, (int) ($payload['count'] ?? 0));

        $frameworks = array_map(static fn(array $framework): string => $framework['name'] ?? '', $payload['frameworks'] ?? []);
        $this->assertContains('react', $frameworks);
        $this->assertContains('nuxt', $frameworks);
        $this->assertContains('ghost', $frameworks);
    }

    public function testMakeFrontendCommandCreatesStarterBundleForFramework(): void
    {
        $framework = 'astro';
        $name = 'cli-demo-' . uniqid();
        $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(base_path('bin/nemesis')) . ' make:frontend ' . escapeshellarg($name) . ' --framework=' . escapeshellarg($framework) . ' 2>&1';
        $output = [];
        $code = 0;
        exec($cmd, $output, $code);

        $this->assertSame(0, $code, implode("\n", $output));
        $this->assertStringContainsString("Frontend starter scaffold created for {$framework}", implode("\n", $output));

        $componentBase = preg_replace('/[^A-Za-z0-9]+/', ' ', $name);
        $componentName = str_replace(' ', '', ucwords(trim((string) $componentBase)));
        $componentName = $componentName === '' ? 'Starter' : $componentName;
        $componentSlug = strtolower(preg_replace('/(?<!^)[A-Z]/', '-$0', $componentName . 'Card'));

        $layout = base_path("resources/views/{$framework}/layouts/app.blade.php");
        $page = base_path("resources/views/{$framework}/{$name}.blade.php");
        $componentJs = base_path("resources/js/{$framework}/components/" . $componentName . 'Card.js');
        $componentView = base_path("resources/views/{$framework}/components/" . $componentSlug . '.blade.php');
        $starterJs = base_path("resources/js/views/{$framework}/{$name}.js");

        $this->assertTrue(file_exists($layout));
        $this->assertTrue(file_exists($page));
        $this->assertTrue(file_exists($componentJs));
        $this->assertTrue(file_exists($componentView));
        $this->assertTrue(file_exists($starterJs));

        foreach ([$layout, $page, $componentJs, $componentView, $starterJs] as $path) {
            @unlink($path);
        }

        @rmdir(dirname($layout));
        @rmdir(dirname($page));
        @rmdir(dirname($componentJs));
        @rmdir(dirname($componentView));
        @rmdir(dirname($starterJs));
        @rmdir(base_path("resources/js/views/{$framework}"));
    }

    public function testMakeFrontendCommandShowsHelpBlock(): void
    {
        $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(base_path('bin/nemesis')) . ' make:frontend --help 2>&1';
        $output = [];
        $code = 0;
        exec($cmd, $output, $code);

        $text = implode("\n", $output);
        $this->assertSame(0, $code, $text);
        $this->assertStringContainsString('Usage: php nemesis make:frontend <name> [--framework=name]', $text);
        $this->assertStringContainsString('frontend:list --json', $text);
        $this->assertStringContainsString('Use php nemesis frontend:list to inspect supported framework names.', $text);
    }

    public function testSyntaxCheckCommandCanLintSingleFileAndTree(): void
    {
        $tmpFile = sys_get_temp_dir() . '/nemesis-syntax-' . uniqid('', true) . '.php';
        file_put_contents($tmpFile, "<?php\necho 'ok';\n");

        $fileCmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(base_path('bin/nemesis')) . ' syntax:check ' . escapeshellarg($tmpFile) . ' 2>&1';
        $fileOutput = [];
        $fileCode = 0;
        exec($fileCmd, $fileOutput, $fileCode);

        $this->assertSame(0, $fileCode, implode("\n", $fileOutput));
        $this->assertStringContainsString('Syntax check complete', implode("\n", $fileOutput));
        $this->assertStringContainsString('1 passed, 0 failed', implode("\n", $fileOutput));

        $treeCmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(base_path('bin/nemesis')) . ' syntax:check --all 2>&1';
        $treeOutput = [];
        $treeCode = 0;
        exec($treeCmd, $treeOutput, $treeCode);

        $this->assertSame(0, $treeCode, implode("\n", $treeOutput));
        $this->assertStringContainsString('Syntax check complete', implode("\n", $treeOutput));

        @unlink($tmpFile);
    }
}

$test = new FrontendCliCommandsTest();

echo "--- Frontend CLI Commands Test ---\n";

foreach ([
    'testFrontendListCommandShowsExpandedFrameworkCatalog',
    'testFrontendListCommandCanEmitJsonPayload',
    'testMakeFrontendCommandCreatesStarterBundleForFramework',
    'testMakeFrontendCommandShowsHelpBlock',
    'testSyntaxCheckCommandCanLintSingleFileAndTree',
] as $method) {
    echo "Running {$method}... ";
    try {
        $test->setUp();
        $test->{$method}();
        $test->tearDown();
        echo "PASS\n";
    } catch (\Throwable $e) {
        echo "FAIL: " . $e->getMessage() . "\n";
        exit(1);
    }
}

echo "\n--- Frontend CLI Commands Test Complete ---\n";
