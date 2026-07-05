<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Nemesis\Testing\TestCase;
use Nemesis\Console\VendorCompressor;
use Nemesis\Reactor\CommandBus;

class VendorCompressTest extends TestCase
{
    private string $root;

    public function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/nemesis_vendor_compress_' . uniqid();
        mkdir($this->root . '/vendor/acme/tool/src', 0755, true);
        mkdir($this->root . '/vendor/acme/tool/bin', 0755, true);
        mkdir($this->root . '/vendor/other/pkg/src', 0755, true);
        mkdir($this->root . '/vendor/dynamic/pkg/src', 0755, true);
        mkdir($this->root . '/app/Services', 0755, true);
        mkdir($this->root . '/vendor/composer', 0755, true);

        file_put_contents($this->root . '/vendor/autoload.php', "<?php\n");
        file_put_contents($this->root . '/vendor/composer/autoload_psr4.php', "<?php\n");
        file_put_contents($this->root . '/vendor/acme/tool/bootstrap.php', "<?php\nfunction acme_bootstrap() {}\n");
        file_put_contents($this->root . '/vendor/acme/tool/bin/tool.php', "<?php\n");
        file_put_contents($this->root . '/vendor/acme/tool/src/UsedClass.php', "<?php\nnamespace Acme\\Tool;\nclass UsedClass {}\n");
        file_put_contents($this->root . '/vendor/acme/tool/src/UnusedClass.php', "<?php\nnamespace Acme\\Tool;\nclass UnusedClass {}\n");
        file_put_contents($this->root . '/vendor/other/pkg/src/LooseClass.php', "<?php\nnamespace Other\\Pkg;\nclass LooseClass {}\n");
        file_put_contents($this->root . '/vendor/dynamic/pkg/src/DynamicResolver.php', "<?php\nnamespace Dynamic\\Pkg;\nuse ReflectionClass;\nclass DynamicResolver { public function load(string \$class): object { return new ReflectionClass(\$class); } }\n");
        file_put_contents($this->root . '/vendor/dynamic/pkg/src/DynamicTarget.php', "<?php\nnamespace Dynamic\\Pkg;\nclass DynamicTarget {}\n");
        file_put_contents($this->root . '/vendor/acme/tool/composer.json', json_encode([
            'name' => 'acme/tool',
            'bin' => ['bin/tool.php'],
            'autoload' => ['files' => ['bootstrap.php']],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        file_put_contents($this->root . '/app/Services/UsesVendor.php', "<?php\nnamespace App\\Services;\nuse Acme\\Tool\\UsedClass as ToolClass;\nclass UsesVendor { public function make(): ToolClass { return new ToolClass(); } }\n");

        CommandBus::resetInstance();
    }

    public function tearDown(): void
    {
        $this->removeDir($this->root);
    }

    public function testCommandBusDiscoversVendorCompressCommand(): void
    {
        CommandBus::getInstance()->discoverIn(base_path('app/Console/Commands'));
        $this->assertTrue(CommandBus::getInstance()->has('vendor:compress'));
    }

    public function testDryRunDetectsCandidatesAndPreservesProtectedFiles(): void
    {
        $compressor = new VendorCompressor($this->root);
        $report = $compressor->compress([
            'dry_run' => true,
            'report' => $this->root . '/reports/vendor-compress.json',
            'archive' => $this->root . '/backups/vendor-compress.zip',
        ]);

        $this->assertSame('dry-run', $report['mode']);
        $this->assertGreaterThan(0, $report['summary']['candidates']);
        $this->assertGreaterThan(0, $report['summary']['preserved']);
        $this->assertContains('vendor/acme/tool/src/UnusedClass.php', array_column($report['candidates'], 'path'));
        $this->assertContains('vendor/other/pkg/src/LooseClass.php', array_column($report['candidates'], 'path'));
        $this->assertContains('vendor/dynamic/pkg/src/DynamicTarget.php', array_column($report['preserved'], 'path'));
        $this->assertContains('vendor/acme/tool/src/UsedClass.php', array_column($report['preserved'], 'path'));
    }

    public function testCompressCreatesArchiveAndRestoreRehydratesFiles(): void
    {
        $compressor = new VendorCompressor($this->root);
        $archive = $this->root . '/backups/vendor-compress.zip';
        $report = $compressor->compress([
            'report' => $this->root . '/reports/vendor-compress.json',
            'archive' => $archive,
        ]);

        $this->assertTrue(file_exists($archive));
        $this->assertFalse(file_exists($this->root . '/vendor/acme/tool/src/UnusedClass.php'));
        $this->assertFalse(file_exists($this->root . '/vendor/other/pkg/src/LooseClass.php'));
        $this->assertTrue(file_exists($this->root . '/vendor/acme/tool/src/UsedClass.php'));

        $restoreReport = $compressor->restore($report['restore']['manifest_path']);
        $this->assertSame('restore', $restoreReport['mode']);
        $this->assertTrue(file_exists($this->root . '/vendor/acme/tool/src/UnusedClass.php'));
        $this->assertTrue(file_exists($this->root . '/vendor/other/pkg/src/LooseClass.php'));
    }

    public function testRestoreFromZipArchivePathRehydratesRemovedFiles(): void
    {
        $compressor = new VendorCompressor($this->root);
        $archive = $this->root . '/backups/vendor-compress.zip';
        $compressor->compress([
            'report' => $this->root . '/reports/vendor-compress.json',
            'archive' => $archive,
        ]);

        $this->assertFalse(file_exists($this->root . '/vendor/acme/tool/src/UnusedClass.php'));
        $this->assertFalse(file_exists($this->root . '/vendor/other/pkg/src/LooseClass.php'));

        $restoreReport = $compressor->restore($archive);
        $this->assertSame('restore', $restoreReport['mode']);
        $this->assertGreaterThan(0, $restoreReport['summary']['restored']);
        $this->assertTrue(file_exists($this->root . '/vendor/acme/tool/src/UnusedClass.php'));
        $this->assertTrue(file_exists($this->root . '/vendor/other/pkg/src/LooseClass.php'));
    }

    public function testReflectionHeavyVendorPackageIsPreserved(): void
    {
        $compressor = new VendorCompressor($this->root);
        $report = $compressor->compress([
            'dry_run' => true,
            'report' => $this->root . '/reports/vendor-compress.json',
            'archive' => $this->root . '/backups/vendor-compress.zip',
        ]);

        $paths = array_column($report['preserved'], 'path');
        $this->assertContains('vendor/dynamic/pkg/src/DynamicResolver.php', $paths);
        $this->assertContains('vendor/dynamic/pkg/src/DynamicTarget.php', $paths);
        $this->assertContains('reflection-heavy-package', array_column($report['preserved'], 'reason'));
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            $path = $item->getRealPath();
            if ($path === false) {
                continue;
            }

            $item->isDir() ? @rmdir($path) : @unlink($path);
        }

        @rmdir($dir);
    }
}
