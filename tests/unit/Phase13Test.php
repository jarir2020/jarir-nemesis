<?php
declare(strict_types=1);

// Nemesis 5.0.0 — Phase 13 Test Suite | Created: 2026-04-03
// Tests: AdminPanel, DashboardWidget, Attachment, MediaLibrary, ImageProcessor

use Nemesis\Testing\TestCase;
use Nemesis\Admin\AdminPanel;
use Nemesis\Admin\DashboardWidget;
use Nemesis\Media\Attachment;
use Nemesis\Media\MediaLibrary;
use Nemesis\Media\ImageProcessor;

// ===========================================================================
// Phase13AdminPanelTest
// ===========================================================================

class Phase13AdminPanelTest extends TestCase
{
    public function setUp(): void
    {
        AdminPanel::reset();
    }

    public function testRegisterAndHas(): void
    {
        AdminPanel::register('posts');
        $this->assertTrue(AdminPanel::has('posts'));
    }

    public function testRegisterWithConfig(): void
    {
        AdminPanel::register('products', [
            'label'   => 'Products',
            'columns' => ['name', 'price', 'created_at'],
            'icon'    => 'fa-box',
        ]);
        $cfg = AdminPanel::getConfig('products');
        $this->assertSame('Products',                    $cfg['label']);
        $this->assertSame(['name', 'price', 'created_at'], $cfg['columns']);
        $this->assertSame('fa-box',                      $cfg['icon']);
    }

    public function testRegisteredReturnsAll(): void
    {
        AdminPanel::register('posts');
        AdminPanel::register('pages');
        $this->assertCount(2, AdminPanel::registered());
        $this->assertArrayHasKey('posts', AdminPanel::registered());
        $this->assertArrayHasKey('pages', AdminPanel::registered());
    }

    public function testNavItemsPopulatedOnRegister(): void
    {
        AdminPanel::register('posts', ['label' => 'Blog Posts']);
        $nav = AdminPanel::navItems();
        $this->assertArrayHasKey('posts', $nav);
        $this->assertSame('Blog Posts', $nav['posts']['label']);
    }

    // Role checks

    public function testAdminCanAccess(): void
    {
        $this->assertTrue(AdminPanel::canAccess(AdminPanel::ROLE_ADMIN));
    }

    public function testEditorCanAccess(): void
    {
        $this->assertTrue(AdminPanel::canAccess(AdminPanel::ROLE_EDITOR));
    }

    public function testAuthorCanAccessDefault(): void
    {
        // Default min role is author
        $this->assertTrue(AdminPanel::canAccess(AdminPanel::ROLE_AUTHOR));
    }

    public function testSubscriberCannotAccess(): void
    {
        $this->assertFalse(AdminPanel::canAccess(AdminPanel::ROLE_SUBSCRIBER));
    }

    public function testCanAccessWithCustomRequiredRole(): void
    {
        $this->assertTrue(AdminPanel::canAccess(AdminPanel::ROLE_ADMIN, AdminPanel::ROLE_EDITOR));
        $this->assertFalse(AdminPanel::canAccess(AdminPanel::ROLE_AUTHOR, AdminPanel::ROLE_ADMIN));
    }

    public function testCanManageEntityByRole(): void
    {
        AdminPanel::register('posts', ['roles' => [AdminPanel::ROLE_EDITOR, AdminPanel::ROLE_ADMIN]]);
        $this->assertTrue(AdminPanel::canManage('posts', AdminPanel::ROLE_ADMIN));
        $this->assertTrue(AdminPanel::canManage('posts', AdminPanel::ROLE_EDITOR));
        $this->assertFalse(AdminPanel::canManage('posts', AdminPanel::ROLE_AUTHOR));
    }

    public function testGetCrudRoutesIncludesDashboard(): void
    {
        $routes = AdminPanel::getCrudRoutes();
        $actions = array_column($routes, 'action');
        $this->assertContains('dashboard', $actions);
    }

    public function testGetCrudRoutesForRegisteredEntity(): void
    {
        AdminPanel::register('posts');
        $routes = AdminPanel::getCrudRoutes();
        $slugs  = array_column($routes, 'slug');
        $this->assertContains('posts', $slugs);

        // Should have 7 CRUD routes for 'posts'
        $postRoutes = array_filter($routes, fn($r) => $r['slug'] === 'posts');
        $this->assertCount(7, $postRoutes);
    }

    public function testSetPrefix(): void
    {
        AdminPanel::setPrefix('/cms');
        $this->assertSame('/cms', AdminPanel::getPrefix());
    }

    public function testRolesReturnsAll(): void
    {
        $roles = AdminPanel::roles();
        $this->assertContains(AdminPanel::ROLE_ADMIN,  $roles);
        $this->assertContains(AdminPanel::ROLE_EDITOR, $roles);
        $this->assertContains(AdminPanel::ROLE_AUTHOR, $roles);
    }
}

// ===========================================================================
// Phase13DashboardWidgetTest
// ===========================================================================

class Phase13DashboardWidgetTest extends TestCase
{
    public function setUp(): void
    {
        DashboardWidget::reset();
    }

    public function testRegisterAndGet(): void
    {
        DashboardWidget::register('posts_count', fn() => '<p>42</p>');
        $this->assertTrue(DashboardWidget::exists('posts_count'));
        $this->assertNotNull(DashboardWidget::get('posts_count'));
    }

    public function testRenderCallsCallable(): void
    {
        DashboardWidget::register('hello', fn() => '<b>Hello</b>');
        $html = DashboardWidget::get('hello')->render();
        $this->assertSame('<b>Hello</b>', $html);
    }

    public function testRenderCatchesException(): void
    {
        DashboardWidget::register('broken', fn() => throw new \RuntimeException('oops'));
        $html = DashboardWidget::get('broken')->render();
        $this->assertStringContainsString('widget-error', $html);
        $this->assertStringContainsString('oops', $html);
    }

    public function testFluentSetters(): void
    {
        $w = DashboardWidget::make('stats', fn() => '')
            ->title('Statistics')
            ->icon('fa-chart')
            ->column(2)
            ->order(5);

        $this->assertSame('Statistics', $w->getTitle());
        $this->assertSame('fa-chart',   $w->getIcon());
        $this->assertSame(2,            $w->getColumn());
        $this->assertSame(5,            $w->getOrder());
    }

    public function testAllSortedByOrder(): void
    {
        DashboardWidget::register('c', fn() => '')->order(3);
        DashboardWidget::register('a', fn() => '')->order(1);
        DashboardWidget::register('b', fn() => '')->order(2);

        $names = array_map(fn(DashboardWidget $w) => $w->getName(), DashboardWidget::all());
        $this->assertSame(['a', 'b', 'c'], $names);
    }

    public function testForgetRemovesWidget(): void
    {
        DashboardWidget::register('temp', fn() => '');
        DashboardWidget::forget('temp');
        $this->assertFalse(DashboardWidget::exists('temp'));
    }
}

// ===========================================================================
// Phase13AttachmentTest
// ===========================================================================

class Phase13AttachmentTest extends TestCase
{
    private function makeImage(array $overrides = []): Attachment
    {
        return new Attachment(array_merge([
            'id'            => 1,
            'filename'      => 'photo-abc.jpg',
            'original_name' => 'photo.jpg',
            'mime_type'     => 'image/jpeg',
            'size'          => 204800,
            'disk'          => 'local',
            'path'          => '2026/04/photo-abc.jpg',
            'alt'           => 'A nice photo',
            'created_at'    => '2026-04-03 12:00:00',
        ], $overrides));
    }

    public function testIsImageTrue(): void
    {
        $this->assertTrue($this->makeImage()->isImage());
    }

    public function testIsImageFalse(): void
    {
        $doc = new Attachment(['mime_type' => 'application/pdf', 'path' => 'doc.pdf']);
        $this->assertFalse($doc->isImage());
    }

    public function testIsPdf(): void
    {
        $doc = new Attachment(['mime_type' => 'application/pdf', 'path' => 'report.pdf']);
        $this->assertTrue($doc->isPdf());
        $this->assertFalse($this->makeImage()->isPdf());
    }

    public function testIsVideo(): void
    {
        $vid = new Attachment(['mime_type' => 'video/mp4', 'path' => 'clip.mp4']);
        $this->assertTrue($vid->isVideo());
    }

    public function testExtension(): void
    {
        $this->assertSame('jpg', $this->makeImage()->extension());
    }

    public function testExtensionFromPath(): void
    {
        $att = new Attachment(['path' => 'uploads/file.png', 'mime_type' => 'image/png']);
        $this->assertSame('png', $att->extension());
    }

    public function testHumanSizeBytes(): void
    {
        $att = new Attachment(['size' => 500]);
        $this->assertSame('500 B', $att->humanSize());
    }

    public function testHumanSizeKB(): void
    {
        $att = new Attachment(['size' => 204800]); // 200 KB
        $this->assertSame('200 KB', $att->humanSize());
    }

    public function testHumanSizeMB(): void
    {
        $att = new Attachment(['size' => 3 * 1024 * 1024]); // 3 MB
        $this->assertSame('3 MB', $att->humanSize());
    }

    public function testHumanSizeZero(): void
    {
        $att = new Attachment(['size' => 0]);
        $this->assertSame('0 B', $att->humanSize());
    }

    public function testToArray(): void
    {
        $arr = $this->makeImage()->toArray();
        $this->assertSame(1,           $arr['id']);
        $this->assertSame('image/jpeg',$arr['mime_type']);
        $this->assertSame('photo.jpg', $arr['original_name']);
    }

    public function testImgTag(): void
    {
        $html = $this->makeImage()->imgTag(['class' => 'img-fluid']);
        $this->assertStringContainsString('<img', $html);
        $this->assertStringContainsString('img-fluid', $html);
        $this->assertStringContainsString('A nice photo', $html);
    }

    public function testImgTagEmptyForNonImage(): void
    {
        $doc = new Attachment(['mime_type' => 'application/pdf', 'path' => 'doc.pdf']);
        $this->assertSame('', $doc->imgTag());
    }
}

// ===========================================================================
// Phase13MediaLibraryTest
// ===========================================================================

class Phase13MediaLibraryTest extends TestCase
{
    public function setUp(): void
    {
        MediaLibrary::reset();
    }

    private function fakeFile(array $overrides = []): array
    {
        return array_merge([
            'name'     => 'test.jpg',
            'type'     => 'image/jpeg',
            'size'     => 1024,
            'tmp_name' => '',    // no actual file
            'error'    => UPLOAD_ERR_OK,
        ], $overrides);
    }

    public function testStoreCreatesAttachment(): void
    {
        $att = MediaLibrary::store([
            'filename'      => 'test-abc.jpg',
            'original_name' => 'test.jpg',
            'mime_type'     => 'image/jpeg',
            'size'          => 1024,
            'disk'          => 'local',
            'path'          => '2026/04/test-abc.jpg',
        ]);
        $this->assertInstanceOf(Attachment::class, $att);
        $this->assertSame('image/jpeg', $att->getMimeType());
    }

    public function testStoreAssignsAutoId(): void
    {
        $a = MediaLibrary::store(['filename' => 'a.jpg']);
        $b = MediaLibrary::store(['filename' => 'b.jpg']);
        $this->assertGreaterThan(0, $a->getId());
        $this->assertGreaterThan($a->getId(), $b->getId());
    }

    public function testFindReturnsStoredAttachment(): void
    {
        $att = MediaLibrary::store(['filename' => 'find-me.jpg', 'mime_type' => 'image/jpeg']);
        $found = MediaLibrary::find($att->getId());
        $this->assertNotNull($found);
        $this->assertSame('find-me.jpg', $found->getFilename());
    }

    public function testFindReturnsNullForMissingId(): void
    {
        $this->assertNull(MediaLibrary::find(9999));
    }

    public function testAllReturnsStoredAttachments(): void
    {
        MediaLibrary::store(['filename' => 'x.jpg']);
        MediaLibrary::store(['filename' => 'y.pdf']);
        $this->assertCount(2, MediaLibrary::all());
    }

    public function testDeleteRemovesFromMemory(): void
    {
        $att = MediaLibrary::store(['filename' => 'del.jpg']);
        MediaLibrary::delete($att);
        $this->assertNull(MediaLibrary::find($att->getId()));
    }

    public function testUrlBuildsPath(): void
    {
        $att = MediaLibrary::store(['path' => '2026/04/photo.jpg']);
        $url = MediaLibrary::url($att);
        $this->assertStringContainsString('2026/04/photo.jpg', $url);
    }

    public function testValidateRejectsDisallowedType(): void
    {
        MediaLibrary::setAllowedTypes(['image/jpeg']);
        $this->expectException(\InvalidArgumentException::class);
        MediaLibrary::validateFile($this->fakeFile(['type' => 'application/exe']));
    }

    public function testValidateRejectsOversizedFile(): void
    {
        MediaLibrary::setMaxSize(500);
        $this->expectException(\InvalidArgumentException::class);
        MediaLibrary::validateFile($this->fakeFile(['size' => 1024]));
    }

    public function testValidatePassesForAllowedType(): void
    {
        MediaLibrary::setAllowedTypes(['image/jpeg']);
        // Should not throw
        MediaLibrary::validateFile($this->fakeFile(['type' => 'image/jpeg']));
        $this->assertTrue(true);
    }
}

// ===========================================================================
// Phase13ImageProcessorTest
// ===========================================================================

class Phase13ImageProcessorTest extends TestCase
{
    public function setUp(): void
    {
        ImageProcessor::resetSizes();
    }

    public function testDefaultSizesExist(): void
    {
        $this->assertNotNull(ImageProcessor::getSize('thumb'));
        $this->assertNotNull(ImageProcessor::getSize('medium'));
        $this->assertNotNull(ImageProcessor::getSize('large'));
        $this->assertNotNull(ImageProcessor::getSize('full'));
    }

    public function testThumbSize(): void
    {
        $size = ImageProcessor::getSize('thumb');
        $this->assertSame(150, $size['w']);
        $this->assertSame(150, $size['h']);
        $this->assertTrue($size['crop']);
    }

    public function testMediumSize(): void
    {
        $size = ImageProcessor::getSize('medium');
        $this->assertSame(300, $size['w']);
        $this->assertFalse($size['crop']);
    }

    public function testRegisterCustomSize(): void
    {
        ImageProcessor::registerSize('hero', 1600, 500, false);
        $size = ImageProcessor::getSize('hero');
        $this->assertSame(1600, $size['w']);
        $this->assertSame(500,  $size['h']);
    }

    public function testForgetSize(): void
    {
        ImageProcessor::forgetSize('full');
        $this->assertNull(ImageProcessor::getSize('full'));
    }

    public function testAllSizesReturnsAll(): void
    {
        $all = ImageProcessor::allSizes();
        $this->assertArrayHasKey('thumb',  $all);
        $this->assertArrayHasKey('medium', $all);
    }

    public function testBuildOutputPath(): void
    {
        $out = ImageProcessor::buildOutputPath('/uploads/photo.jpg', '800x600');
        $this->assertSame('/uploads/photo-800x600.jpg', $out);
    }

    public function testBuildOutputPathNoDirectory(): void
    {
        $out = ImageProcessor::buildOutputPath('photo.jpg', '300x300');
        $this->assertSame('photo-300x300.jpg', $out);
    }

    public function testSrcsetGeneratesDescriptors(): void
    {
        $srcset = ImageProcessor::srcset('/uploads/photo.jpg', [480, 768]);
        $this->assertStringContainsString('480w', $srcset);
        $this->assertStringContainsString('768w', $srcset);
        $this->assertStringContainsString('photo-480x0.jpg', $srcset);
        $this->assertStringContainsString('photo-768x0.jpg', $srcset);
    }

    public function testSrcsetDefaultWidths(): void
    {
        $srcset = ImageProcessor::srcset('/uploads/img.jpg');
        $this->assertStringContainsString('480w', $srcset);
        $this->assertStringContainsString('768w', $srcset);
        $this->assertStringContainsString('1200w', $srcset);
    }

    public function testResizeReturnsMissingFilePath(): void
    {
        // When source doesn't exist, still returns the computed output path
        $out = ImageProcessor::resize('/nonexistent/photo.jpg', 400, 300);
        $this->assertStringContainsString('photo-400x300.jpg', $out);
    }

    public function testThumbnailThrowsForUnknownSize(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        ImageProcessor::thumbnail('/some/photo.jpg', 'giant');
    }

    public function testToWebPReturnsWebpPath(): void
    {
        $out = ImageProcessor::toWebP('/uploads/photo.jpg');
        $this->assertSame('/uploads/photo.webp', $out);
    }

    public function testDefaultQualityGetSet(): void
    {
        ImageProcessor::setDefaultQuality(75);
        $this->assertSame(75, ImageProcessor::getDefaultQuality());
        ImageProcessor::setDefaultQuality(85); // restore
    }
}
