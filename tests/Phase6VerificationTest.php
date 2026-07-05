<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Controllers\FrontendController;
use Nemesis\Admin\AdminPanel;
use Nemesis\Auth\MicroserviceBridge;
use Nemesis\Core\Paginator;
use Nemesis\Core\View;
use Nemesis\Frontend\FrontendManager;
use Nemesis\Http\Request;
use Nemesis\Http\Response;
use Nemesis\Media\MediaLibrary;
use Nemesis\Notifications\Channels\SmsChannel;
use Nemesis\Notifications\Notification;
use Nemesis\Notifications\NotificationManager;
use Nemesis\Scaffolder\Scaffolder;
use Nemesis\Support\Api;
use Nemesis\Support\Form;
use Nemesis\Support\Table;
use Nemesis\Testing\TestCase;

class Phase6ExampleController
{
    public function index(): string
    {
        return 'phase6:index';
    }

    public function show(string $id): string
    {
        return 'phase6:show:' . $id;
    }
}

class Phase6PhoneUser
{
    use \Nemesis\Notifications\Notifiable;

    public string $phone = '+15550000000';
    public string $email = 'phase6@example.com';
}

class Phase6SmsNotification extends Notification
{
    public function via(object $notifiable): array
    {
        return ['sms'];
    }

    public function toSms(object $notifiable): array
    {
        return ['message' => 'Phase 6 smoke test'];
    }
}

class Phase6VerificationTest extends TestCase
{
    private string $tmpRoot;

    public function setUp(): void
    {
        AdminPanel::reset();
        NotificationManager::reset();
        MediaLibrary::reset();
        MicroserviceBridge::reset();

        $this->tmpRoot = sys_get_temp_dir() . '/nemesis-phase6-' . uniqid('', true);
        mkdir($this->tmpRoot . '/views/layouts', 0775, true);
        mkdir($this->tmpRoot . '/resources/views/react', 0775, true);

        file_put_contents($this->tmpRoot . '/views/layouts/app.blade.php', <<<'BLADE'
<!DOCTYPE html>
<html>
<body>
@yield('content')
</body>
</html>
BLADE);

        file_put_contents($this->tmpRoot . '/resources/views/react/dashboard.blade.php', <<<'BLADE'
@extends($layout ?? 'layouts.app')
@section('content')
<h1>React Dashboard</h1>
@endsection
BLADE);

        FrontendManager::boot([
            'default' => 'server',
            'allow' => ['server', 'react'],
            'frameworks' => [
                'server' => [
                    'enabled' => true,
                    'views' => $this->tmpRoot . '/views',
                    'entry' => null,
                    'build' => null,
                    'manifest' => null,
                    'compiler' => 'server',
                    'fallback' => true,
                ],
                'react' => [
                    'enabled' => true,
                    'views' => $this->tmpRoot . '/resources/views/react',
                    'entry' => null,
                    'build' => null,
                    'manifest' => null,
                    'compiler' => 'vite',
                    'fallback' => false,
                ],
            ],
            'runtime' => [],
        ]);

        View::addPath($this->tmpRoot . '/views');
    }

    public function tearDown(): void
    {
        FrontendManager::flush();
        $this->deleteTree($this->tmpRoot);
    }

    public function testBuildersAdminAndResourceHelpersStillWorkTogether(): void
    {
        $form = Form::make('/posts', 'PUT')->text('title', 'Hello')->submit('Save');
        $table = Table::make(['title'], [['title' => 'Hello']]);

        $router = new \Nemesis\Router\Router();
        \Nemesis\Router\Router::setInstance($router);
        Api::resource('phase6-items', Phase6ExampleController::class, $router);

        $this->assertStringContainsString('name="_method" value="PUT"', (string) $form);
        $this->assertStringContainsString('<th>title</th>', (string) $table);
        $this->assertSame('/phase6-items/9', $router->generate('phase6-items.show', ['id' => 9]));
    }

    public function testAdminSurfaceRendersDashboardComponentsAndCrudBuilders(): void
    {
        AdminPanel::dashboard(['title' => 'Ops Dashboard', 'columns' => 4]);
        AdminPanel::register('posts', [
            'columns' => ['title', 'status'],
            'form_fields' => ['title', 'status'],
            'table_columns' => ['title', 'status'],
        ]);

        AdminPanel::component('phase6_widget', fn(array $meta) => '<div class="phase6-widget">' . ($meta['label'] ?? 'ok') . '</div>', [
            'label' => 'Phase 6',
        ]);

        $component = AdminPanel::components()[0];
        $form = AdminPanel::formFor('posts', ['title' => 'Hello']);
        $table = AdminPanel::tableFor('posts', [['title' => 'Hello', 'status' => 'draft']]);

        $this->assertSame('Ops Dashboard', AdminPanel::dashboard()['title']);
        $this->assertStringContainsString('phase6-widget', $component->render());
        $this->assertStringContainsString('name="title"', (string) $form);
        $this->assertStringContainsString('<td>Hello</td>', (string) $table);
    }

    public function testFrontendAuthAndRenderingStillHandOffCleanly(): void
    {
        $request = new Request();
        $request->setMeta('frontend.framework', 'react');
        $request->setMeta('auth', ['sub' => 1, 'role' => 'admin']);

        FrontendManager::getInstance()->setCurrentFramework('react');
        $controller = new FrontendController();

        ob_start();
        $controller->dashboard($request);
        $html = (string) ob_get_clean();

        $this->assertStringContainsString('React Dashboard', $html);
    }

    public function testMediaAuthBridgeAndSmsNotificationRemainFunctional(): void
    {
        SmsChannel::fake();

        MicroserviceBridge::configure(['base_url' => 'https://auth.local']);
        MicroserviceBridge::setTransport(function (string $action, array $payload, array $config): array {
            return compact('action', 'payload', 'config');
        });

        $auth = MicroserviceBridge::authenticate(['email' => 'phase6@example.com', 'password' => 'secret']);
        $attachment = MediaLibrary::store([
            'filename' => 'phase6.jpg',
            'mime_type' => 'image/jpeg',
            'path' => 'phase6/phase6.jpg',
        ]);
        $replacement = MediaLibrary::replace($attachment, [
            'name' => 'phase6-new.jpg',
            'type' => 'image/jpeg',
            'size' => 1000,
            'tmp_name' => '',
            'error' => UPLOAD_ERR_OK,
        ]);

        $user = new Phase6PhoneUser();
        $user->notify(new Phase6SmsNotification());

        $this->assertSame('authenticate', $auth['action']);
        $this->assertInstanceOf(\Nemesis\Media\Attachment::class, $replacement);
        $this->assertTrue(SmsChannel::assertSentTo('+15550000000'));
    }

    private function deleteTree(string $dir): void
    {
        if ($dir === '' || !is_dir($dir)) {
            return;
        }

        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            $path = $item->getPathname();
            if ($item->isDir()) {
                @rmdir($path);
            } else {
                @unlink($path);
            }
        }

        @rmdir($dir);
    }
}

$test = new Phase6VerificationTest();

echo "--- Phase 6 Verification Test ---\n";

foreach ([
    'testBuildersAdminAndResourceHelpersStillWorkTogether',
    'testAdminSurfaceRendersDashboardComponentsAndCrudBuilders',
    'testFrontendAuthAndRenderingStillHandOffCleanly',
    'testMediaAuthBridgeAndSmsNotificationRemainFunctional',
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

echo "\n--- Phase 6 Verification Test Complete ---\n";
