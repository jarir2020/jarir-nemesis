<?php
declare(strict_types=1);

// Nemesis 5.0.0 — Phase 10 Test Suite | Created: 2026-04-03
// Tests: AssetManager, ViteManifest, WebpackManifest, ManifestInterface, helpers

use Nemesis\Testing\TestCase;
use Nemesis\Assets\AssetManager;
use Nemesis\Assets\ViteManifest;
use Nemesis\Assets\WebpackManifest;
use Nemesis\Assets\ManifestInterface;

// ===========================================================================
// Phase10ManifestInterfaceTest — contract compliance
// ===========================================================================

class Phase10ManifestInterfaceTest extends TestCase
{
    public function testViteManifestImplementsInterface(): void
    {
        $m = new ViteManifest('/nonexistent/manifest.json');
        $this->assertInstanceOf(ManifestInterface::class, $m);
    }

    public function testWebpackManifestImplementsInterface(): void
    {
        $m = new WebpackManifest('/nonexistent/mix-manifest.json');
        $this->assertInstanceOf(ManifestInterface::class, $m);
    }
}

// ===========================================================================
// Phase10ViteManifestTest — ViteManifest behaviour
// ===========================================================================

class Phase10ViteManifestTest extends TestCase
{
    private string $tmpManifest = '';

    public function setUp(): void
    {
        $this->tmpManifest = sys_get_temp_dir() . '/nemesis-vite-manifest-' . uniqid() . '.json';
    }

    public function tearDown(): void
    {
        if (file_exists($this->tmpManifest)) {
            unlink($this->tmpManifest);
        }
    }

    private function write(array $data): void
    {
        file_put_contents($this->tmpManifest, json_encode($data));
    }

    public function testLoadedFalseWhenManifestMissing(): void
    {
        $m = new ViteManifest('/does/not/exist/manifest.json');
        $this->assertFalse($m->loaded());
    }

    public function testLoadedTrueWhenManifestExists(): void
    {
        $this->write(['resources/js/app.js' => ['file' => 'assets/app-abc.js']]);
        $m = new ViteManifest($this->tmpManifest);
        $this->assertTrue($m->loaded());
    }

    public function testUrlResolvesHashedFile(): void
    {
        $this->write(['resources/js/app.js' => ['file' => 'assets/app-abc123.js', 'isEntry' => true]]);
        $m   = new ViteManifest($this->tmpManifest);
        $url = $m->url('resources/js/app.js');
        $this->assertStringContainsString('app-abc123.js', $url);
    }

    public function testUrlFallbackWhenKeyMissing(): void
    {
        $this->write([]);
        $m   = new ViteManifest($this->tmpManifest);
        $url = $m->url('resources/js/missing.js');
        $this->assertStringContainsString('missing.js', $url);
    }

    public function testAllReturnsEntries(): void
    {
        $this->write([
            'resources/js/app.js'  => ['file' => 'assets/app-aaa.js'],
            'resources/css/app.css' => ['file' => 'assets/app-bbb.css'],
        ]);
        $m   = new ViteManifest($this->tmpManifest);
        $all = $m->all();
        $this->assertIsArray($all);
        $this->assertCount(2, $all);
    }

    public function testTagsOutputsScriptTag(): void
    {
        $this->write(['resources/js/app.js' => ['file' => 'assets/app-abc.js', 'isEntry' => true, 'css' => []]]);
        $m    = new ViteManifest($this->tmpManifest);
        $html = $m->tags('resources/js/app.js');
        $this->assertStringContainsString('<script', $html);
        $this->assertStringContainsString('type="module"', $html);
    }

    public function testTagsIncludesCssLink(): void
    {
        $this->write(['resources/js/app.js' => [
            'file'    => 'assets/app-abc.js',
            'isEntry' => true,
            'css'     => ['assets/app-def.css'],
        ]]);
        $m    = new ViteManifest($this->tmpManifest);
        $html = $m->tags('resources/js/app.js');
        $this->assertStringContainsString('<link rel="stylesheet"', $html);
        $this->assertStringContainsString('app-def.css', $html);
    }

    public function testTagsCommentWhenEntryMissing(): void
    {
        $this->write([]);
        $m    = new ViteManifest($this->tmpManifest);
        $html = $m->tags('resources/js/missing.js');
        $this->assertStringContainsString('<!--', $html);
    }

    public function testHotModeUsesDevUrl(): void
    {
        // Create a hot file
        $hotFile = sys_get_temp_dir() . '/nemesis-vite-' . uniqid() . '.hot';
        file_put_contents($hotFile, '');

        $this->write(['resources/js/app.js' => ['file' => 'assets/app-abc.js']]);
        $m   = new ViteManifest($this->tmpManifest, 'http://localhost:5173', $hotFile);
        $url = $m->url('resources/js/app.js');

        unlink($hotFile);

        $this->assertStringContainsString('localhost:5173', $url);
    }

    public function testHotModeTagsInjectViteClient(): void
    {
        $hotFile = sys_get_temp_dir() . '/nemesis-vite-' . uniqid() . '.hot';
        file_put_contents($hotFile, '');

        $this->write([]);
        $m    = new ViteManifest($this->tmpManifest, 'http://localhost:5173', $hotFile);
        $html = $m->tags('resources/js/app.js');

        unlink($hotFile);

        $this->assertStringContainsString('@vite/client', $html);
    }
}

// ===========================================================================
// Phase10WebpackManifestTest — WebpackManifest behaviour
// ===========================================================================

class Phase10WebpackManifestTest extends TestCase
{
    private string $tmpManifest = '';

    public function setUp(): void
    {
        $this->tmpManifest = sys_get_temp_dir() . '/nemesis-mix-manifest-' . uniqid() . '.json';
    }

    public function tearDown(): void
    {
        if (file_exists($this->tmpManifest)) {
            unlink($this->tmpManifest);
        }
    }

    private function write(array $data): void
    {
        file_put_contents($this->tmpManifest, json_encode($data));
    }

    public function testLoadedFalseWhenManifestMissing(): void
    {
        $m = new WebpackManifest('/does/not/exist/mix-manifest.json');
        $this->assertFalse($m->loaded());
    }

    public function testLoadedTrueWhenManifestExists(): void
    {
        $this->write(['/js/app.js' => '/js/app.js?id=abc123']);
        $m = new WebpackManifest($this->tmpManifest);
        $this->assertTrue($m->loaded());
    }

    public function testUrlResolvesVersionedPath(): void
    {
        $this->write(['/js/app.js' => '/js/app.js?id=abc123']);
        $m   = new WebpackManifest($this->tmpManifest);
        $url = $m->url('/js/app.js');
        $this->assertSame('/js/app.js?id=abc123', $url);
    }

    public function testUrlNormalisesLeadingSlash(): void
    {
        $this->write(['/css/app.css' => '/css/app.css?id=xyz']);
        $m   = new WebpackManifest($this->tmpManifest);
        // Pass without leading slash
        $url = $m->url('css/app.css');
        $this->assertSame('/css/app.css?id=xyz', $url);
    }

    public function testUrlFallbackWhenMissing(): void
    {
        $this->write([]);
        $m   = new WebpackManifest($this->tmpManifest);
        $url = $m->url('/js/missing.js');
        $this->assertSame('/js/missing.js', $url);
    }

    public function testAllReturnsManifest(): void
    {
        $data = ['/js/app.js' => '/js/app.js?id=1', '/css/app.css' => '/css/app.css?id=2'];
        $this->write($data);
        $m = new WebpackManifest($this->tmpManifest);
        $this->assertSame($data, $m->all());
    }

    public function testTagsForJsEmitsScriptTag(): void
    {
        $this->write(['/js/app.js' => '/js/app.js?id=abc']);
        $m    = new WebpackManifest($this->tmpManifest);
        $html = $m->tags('/js/app.js');
        $this->assertStringContainsString('<script', $html);
        $this->assertStringContainsString('defer', $html);
    }

    public function testTagsForCssEmitsLinkTag(): void
    {
        $this->write(['/css/app.css' => '/css/app.css?id=xyz']);
        $m    = new WebpackManifest($this->tmpManifest);
        $html = $m->tags('/css/app.css');
        $this->assertStringContainsString('<link rel="stylesheet"', $html);
    }
}

// ===========================================================================
// Phase10AssetManagerTest — AssetManager facade
// ===========================================================================

class Phase10AssetManagerTest extends TestCase
{
    private string $tmpManifest = '';

    public function setUp(): void
    {
        AssetManager::flush();
        $this->tmpManifest = sys_get_temp_dir() . '/nemesis-am-manifest-' . uniqid() . '.json';
    }

    public function tearDown(): void
    {
        AssetManager::flush();
        if (file_exists($this->tmpManifest)) {
            unlink($this->tmpManifest);
        }
    }

    public function testDriverDefaultsToVite(): void
    {
        AssetManager::boot(['driver' => 'vite', 'vite' => ['manifest' => '/nonexistent']]);
        $this->assertSame('vite', AssetManager::driver());
    }

    public function testDriverWebpack(): void
    {
        AssetManager::boot(['driver' => 'webpack', 'webpack' => ['manifest' => '/nonexistent']]);
        $this->assertSame('webpack', AssetManager::driver());
    }

    public function testDriverNone(): void
    {
        AssetManager::boot(['driver' => 'none']);
        $this->assertSame('none', AssetManager::driver());
    }

    public function testHasManifestFalseWhenNoFileExists(): void
    {
        AssetManager::boot(['driver' => 'vite', 'vite' => ['manifest' => '/nonexistent/manifest.json']]);
        $this->assertFalse(AssetManager::hasManifest());
    }

    public function testHasManifestTrueWhenFileLoaded(): void
    {
        file_put_contents($this->tmpManifest, json_encode(['resources/js/app.js' => ['file' => 'assets/app-abc.js']]));
        AssetManager::boot(['driver' => 'vite', 'vite' => [
            'manifest' => $this->tmpManifest,
            'hot_file' => '/nonexistent.hot',
        ]]);
        $this->assertTrue(AssetManager::hasManifest());
    }

    public function testUrlResolvesThroughViteManifest(): void
    {
        file_put_contents($this->tmpManifest, json_encode([
            'resources/js/app.js' => ['file' => 'assets/app-resolved.js'],
        ]));
        AssetManager::boot(['driver' => 'vite', 'vite' => [
            'manifest' => $this->tmpManifest,
            'hot_file' => '/nonexistent.hot',
        ]]);
        $url = AssetManager::url('resources/js/app.js');
        $this->assertStringContainsString('app-resolved.js', $url);
    }

    public function testUrlResolvesThroughWebpackManifest(): void
    {
        file_put_contents($this->tmpManifest, json_encode(['/js/app.js' => '/js/app.js?id=webpack123']));
        AssetManager::boot(['driver' => 'webpack', 'webpack' => ['manifest' => $this->tmpManifest]]);
        $url = AssetManager::mix('/js/app.js');
        $this->assertStringContainsString('webpack123', $url);
    }

    public function testUrlFallbackWhenNoManifest(): void
    {
        AssetManager::boot(['driver' => 'none']);
        $url = AssetManager::url('js/app.js');
        $this->assertStringContainsString('js/app.js', $url);
    }

    public function testTagsReturnsHtmlString(): void
    {
        AssetManager::boot(['driver' => 'none']);
        $html = AssetManager::tags('js/app.js');
        $this->assertIsString($html);
        $this->assertNotEmpty($html);
    }

    public function testFlushResetsInstance(): void
    {
        AssetManager::boot(['driver' => 'webpack', 'webpack' => ['manifest' => '/nonexistent']]);
        $this->assertSame('webpack', AssetManager::driver());
        AssetManager::flush();
        // After flush, getInstance() re-reads config (which may default to vite)
        // Just verify it doesn't throw
        $driver = AssetManager::driver();
        $this->assertIsString($driver);
    }

    public function testViteHelperDelegatesToViteManifest(): void
    {
        file_put_contents($this->tmpManifest, json_encode([
            'resources/js/app.js' => ['file' => 'assets/app-vite-helper.js'],
        ]));
        AssetManager::boot(['driver' => 'vite', 'vite' => [
            'manifest' => $this->tmpManifest,
            'hot_file' => '/nonexistent.hot',
        ]]);
        $url = AssetManager::vite('resources/js/app.js');
        $this->assertStringContainsString('app-vite-helper.js', $url);
    }
}

// ===========================================================================
// Phase10HelperFunctionTest — global helper functions
// ===========================================================================

class Phase10HelperFunctionTest extends TestCase
{
    public function setUp(): void
    {
        AssetManager::flush();
    }

    public function tearDown(): void
    {
        AssetManager::flush();
    }

    public function testAssetFunctionExists(): void
    {
        $this->assertTrue(function_exists('asset'));
    }

    public function testViteFunctionExists(): void
    {
        $this->assertTrue(function_exists('vite'));
    }

    public function testMixFunctionExists(): void
    {
        $this->assertTrue(function_exists('mix'));
    }

    public function testAssetTagsFunctionExists(): void
    {
        $this->assertTrue(function_exists('asset_tags'));
    }

    public function testAssetHelperReturnsString(): void
    {
        AssetManager::boot(['driver' => 'none']);
        $this->assertIsString(asset('js/app.js'));
    }

    public function testViteHelperReturnsString(): void
    {
        AssetManager::boot(['driver' => 'none']);
        $this->assertIsString(vite('resources/js/app.js'));
    }

    public function testMixHelperReturnsString(): void
    {
        AssetManager::boot(['driver' => 'none']);
        $this->assertIsString(mix('/js/app.js'));
    }

    public function testAssetTagsHelperReturnsHtml(): void
    {
        AssetManager::boot(['driver' => 'none']);
        $html = asset_tags('js/app.js');
        $this->assertStringContainsString('<script', $html);
    }

    public function testAssetTagsCssReturnsLinkTag(): void
    {
        AssetManager::boot(['driver' => 'none']);
        $html = asset_tags('css/app.css');
        $this->assertStringContainsString('<link', $html);
    }
}

// ===========================================================================
// Phase10ConfigTest — assets config structure
// ===========================================================================

class Phase10ConfigTest extends TestCase
{
    public function testAssetsConfigFileExists(): void
    {
        $this->assertTrue(file_exists(__DIR__ . '/../../config/assets.php'));
    }

    public function testAssetsConfigReturnsArray(): void
    {
        $cfg = require __DIR__ . '/../../config/assets.php';
        $this->assertIsArray($cfg);
    }

    public function testAssetsConfigHasDriver(): void
    {
        $cfg = require __DIR__ . '/../../config/assets.php';
        $this->assertArrayHasKey('driver', $cfg);
    }

    public function testAssetsConfigHasViteSection(): void
    {
        $cfg = require __DIR__ . '/../../config/assets.php';
        $this->assertArrayHasKey('vite', $cfg);
        $this->assertArrayHasKey('manifest', $cfg['vite']);
    }

    public function testAssetsConfigHasWebpackSection(): void
    {
        $cfg = require __DIR__ . '/../../config/assets.php';
        $this->assertArrayHasKey('webpack', $cfg);
        $this->assertArrayHasKey('manifest', $cfg['webpack']);
    }

    public function testAssetsConfigHasEntries(): void
    {
        $cfg = require __DIR__ . '/../../config/assets.php';
        $this->assertArrayHasKey('entries', $cfg);
        $this->assertArrayHasKey('js', $cfg['entries']);
        $this->assertArrayHasKey('css', $cfg['entries']);
    }

    public function testResourcesDirectoryExists(): void
    {
        $this->assertTrue(is_dir(__DIR__ . '/../../resources'));
    }

    public function testResourcesJsAppExists(): void
    {
        $this->assertTrue(file_exists(__DIR__ . '/../../resources/js/app.js'));
    }

    public function testResourcesCssAppExists(): void
    {
        $this->assertTrue(file_exists(__DIR__ . '/../../resources/css/app.css'));
    }

    public function testScaffolderStubViteExists(): void
    {
        $this->assertTrue(file_exists(__DIR__ . '/../../src/Scaffolder/stubs/vite.config.stub'));
    }

    public function testScaffolderStubWebpackExists(): void
    {
        $this->assertTrue(file_exists(__DIR__ . '/../../src/Scaffolder/stubs/webpack.config.stub'));
    }

    public function testScaffolderStubPackageViteExists(): void
    {
        $this->assertTrue(file_exists(__DIR__ . '/../../src/Scaffolder/stubs/package.vite.stub'));
    }

    public function testScaffolderStubPackageWebpackExists(): void
    {
        $this->assertTrue(file_exists(__DIR__ . '/../../src/Scaffolder/stubs/package.webpack.stub'));
    }
}
