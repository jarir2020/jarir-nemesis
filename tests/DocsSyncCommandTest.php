<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Nemesis\Testing\TestCase;

class DocsSyncCommandTest extends TestCase
{
    private string $root;
    private string $docsRoot;
    private string $publicDocsRoot;

    public function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/nemesis-docs-sync-' . uniqid('', true);
        $this->docsRoot = $this->root . '/docs';
        $this->publicDocsRoot = $this->root . '/public/docs';

        mkdir($this->docsRoot, 0775, true);
        mkdir($this->publicDocsRoot, 0775, true);
    }

    public function tearDown(): void
    {
        if (is_dir($this->root)) {
            $this->deleteDirectory($this->root);
        }
    }

    public function testDocsSyncDryRunIsCompactAndDoesNotWriteFiles(): void
    {
        file_put_contents($this->docsRoot . '/guide.md', "docs version\n");
        touch($this->docsRoot . '/guide.md', time() + 5);

        $result = $this->runSync('--json --brief --dry-run');
        $payload = json_decode($result['output'], true);

        $this->assertSame(0, $result['code'], $result['output']);
        $this->assertIsArray($payload);
        $this->assertSame(true, $payload['dry_run'] ?? null);
        $this->assertSame(1, (int) ($payload['count'] ?? 0));
        $this->assertFalse(file_exists($this->publicDocsRoot . '/guide.md'));
    }

    public function testDocsSyncCopiesNewerFilesBothDirections(): void
    {
        file_put_contents($this->docsRoot . '/guide.md', "docs version\n");
        file_put_contents($this->publicDocsRoot . '/guide.md', "public version\n");

        sleep(1);
        touch($this->docsRoot . '/guide.md');

        file_put_contents($this->docsRoot . '/mirror.md', "docs mirror\n");
        file_put_contents($this->publicDocsRoot . '/notes.md', "public notes\n");
        sleep(1);
        touch($this->publicDocsRoot . '/notes.md');

        $result = $this->runSync('--json --brief');
        $payload = json_decode($result['output'], true);

        $this->assertSame(0, $result['code'], $result['output']);
        $this->assertIsArray($payload);
        $this->assertGreaterThanOrEqual(2, (int) ($payload['count'] ?? 0));

        $this->assertTrue(file_exists($this->publicDocsRoot . '/mirror.md'));
        $this->assertSame("docs mirror\n", file_get_contents($this->publicDocsRoot . '/mirror.md'));

        $this->assertTrue(file_exists($this->docsRoot . '/notes.md'));
        $this->assertSame("public notes\n", file_get_contents($this->docsRoot . '/notes.md'));
    }

    public function testDocsSyncPrefersDocsWhenTimestampsMatch(): void
    {
        $stamp = time();
        file_put_contents($this->docsRoot . '/shared.md', "docs version\n");
        file_put_contents($this->publicDocsRoot . '/shared.md', "public version\n");
        touch($this->docsRoot . '/shared.md', $stamp);
        touch($this->publicDocsRoot . '/shared.md', $stamp);

        $result = $this->runSync('--json --brief');
        $payload = json_decode($result['output'], true);

        $this->assertSame(0, $result['code'], $result['output']);
        $this->assertIsArray($payload);
        $this->assertGreaterThanOrEqual(1, (int) ($payload['count'] ?? 0));
        $this->assertSame("docs version\n", file_get_contents($this->publicDocsRoot . '/shared.md'));
        $this->assertSame("docs version\n", file_get_contents($this->docsRoot . '/shared.md'));
    }

    private function runSync(string $flags = ''): array
    {
        $cmd = sprintf(
            'NEMESIS_DOCS_ROOT=%s NEMESIS_PUBLIC_DOCS_ROOT=%s %s %s docs:sync %s 2>&1',
            escapeshellarg($this->docsRoot),
            escapeshellarg($this->publicDocsRoot),
            escapeshellarg(PHP_BINARY),
            escapeshellarg(base_path('bin/nemesis')),
            $flags
        );

        $output = [];
        $code = 0;
        exec($cmd, $output, $code);

        return [
            'code' => $code,
            'output' => implode("\n", $output),
        ];
    }

    private function deleteDirectory(string $directory): void
    {
        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            if ($item->isDir()) {
                @rmdir($item->getPathname());
            } else {
                @unlink($item->getPathname());
            }
        }

        @rmdir($directory);
    }
}

$test = new DocsSyncCommandTest();

echo "--- Docs Sync Command Test ---\n";

foreach ([
    'testDocsSyncDryRunIsCompactAndDoesNotWriteFiles',
    'testDocsSyncCopiesNewerFilesBothDirections',
    'testDocsSyncPrefersDocsWhenTimestampsMatch',
] as $method) {
    echo "Running {$method}... ";
    try {
        $test->setUp();
        $test->{$method}();
        $test->tearDown();
        echo "PASS\n";
    } catch (\Throwable $e) {
        echo "FAIL: " . $e->getMessage() . "\n";
        try {
            $test->tearDown();
        } catch (\Throwable) {
        }
        exit(1);
    }
}

echo "\n--- Docs Sync Command Test Complete ---\n";
