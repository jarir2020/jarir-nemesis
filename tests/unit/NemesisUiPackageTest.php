<?php
declare(strict_types=1);

$nemesisUiRoot = dirname(__DIR__, 2) . '/packages/nemesis-ui';
require_once $nemesisUiRoot . '/src/ComponentDefinition.php';
require_once $nemesisUiRoot . '/src/ComponentRegistry.php';
require_once $nemesisUiRoot . '/src/ComponentInstaller.php';

use Nemesis\Testing\TestCase;
use Nemesis\UI\ComponentDefinition;
use Nemesis\UI\ComponentInstaller;
use Nemesis\UI\ComponentRegistry;

class NemesisUiPackageTest extends TestCase
{
    private string $applicationRoot;

    public function setUp(): void
    {
        $this->applicationRoot = sys_get_temp_dir() . '/nemesis-ui-' . uniqid('', true);
        mkdir($this->applicationRoot, 0755, true);
    }

    public function tearDown(): void
    {
        $installed = $this->applicationRoot . '/views/components/badge.blade.php';
        @unlink($installed);
        @rmdir($this->applicationRoot . '/views/components');
        @rmdir($this->applicationRoot . '/views');
        @rmdir($this->applicationRoot);
    }

    public function testRegistryExposesSafeCopyOwnedComponentMetadata(): void
    {
        $registry = ComponentRegistry::defaults();
        $this->assertTrue($registry->has('badge'));
        $component = $registry->get('badge');
        $this->assertSame('badge', $component->name);
        $this->assertStringContainsString('@component', $component->usage);
        $this->assertStringContainsString('badge.blade.php', $component->sourcePath(dirname(__DIR__, 2) . '/packages/nemesis-ui'));
    }

    public function testInstallerCopiesTemplateWithoutOverwritingApplicationWork(): void
    {
        $root = dirname(__DIR__, 2) . '/packages/nemesis-ui';
        $installer = new ComponentInstaller(ComponentRegistry::defaults(), $root, $this->applicationRoot);
        $path = $installer->install('badge');

        $this->assertTrue(is_file($path));
        $this->assertStringContainsString('role="status"', file_get_contents($path));

        $this->expectException(RuntimeException::class);
        $installer->install('badge');
    }

    public function testInstallerSupportsExplicitForceAndRejectsUnsafeDestination(): void
    {
        $root = dirname(__DIR__, 2) . '/packages/nemesis-ui';
        $installer = new ComponentInstaller(ComponentRegistry::defaults(), $root, $this->applicationRoot);
        $installer->install('badge');
        $this->assertTrue(is_file($installer->install('badge', 'views/components', true)));

        $this->expectException(InvalidArgumentException::class);
        $installer->install('badge', '../outside');
    }

    public function testDefinitionsRejectPathTraversalAndInvalidSlugs(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new ComponentDefinition('../badge', 'bad', 'badge.blade.php', '');
    }
}
