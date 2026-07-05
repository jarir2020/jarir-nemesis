<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Nemesis\Admin\AdminComponent;
use Nemesis\Admin\AdminPanel;
use Nemesis\Admin\DashboardWidget;
use Nemesis\Testing\TestCase;

class Phase3AdminEnhancementTest extends TestCase
{
    public function setUp(): void
    {
        AdminPanel::reset();
    }

    public function testDashboardConfigCanBeReadAndUpdated(): void
    {
        $this->assertSame('Admin Dashboard', AdminPanel::dashboard()['title']);

        $config = AdminPanel::dashboard([
            'title' => 'Operations',
            'columns' => 4,
        ]);

        $this->assertSame('Operations', $config['title']);
        $this->assertSame(4, $config['columns']);
    }

    public function testAdminComponentsCanBeRegisteredAndRendered(): void
    {
        $component = AdminPanel::component('stats_card', function (array $meta): string {
            return '<section class="stats-card">' . htmlspecialchars($meta['label'] ?? 'Stats', ENT_QUOTES) . '</section>';
        }, [
            'label' => 'Stats',
            'icon' => 'fa-chart',
            'section' => 'dashboard',
            'order' => 2,
        ])->title('Stats Card');

        $this->assertTrue(AdminComponent::exists('stats_card'));
        $this->assertSame('Stats Card', $component->getTitle());
        $this->assertSame('dashboard', $component->getSection());
        $this->assertStringContainsString('stats-card', $component->render());
        $this->assertCount(1, AdminPanel::components());
    }

    public function testCrudConfigSeedsFormAndTableBuilders(): void
    {
        AdminPanel::register('posts', [
            'label' => 'Blog Posts',
            'columns' => ['title', 'status'],
            'form_fields' => ['title', 'status'],
            'table_columns' => ['title', 'status'],
            'per_page' => 12,
        ]);

        $crud = AdminPanel::crud('posts');
        $this->assertSame(12, $crud['per_page']);
        $this->assertSame(['title', 'status'], $crud['columns']);
        $this->assertSame(['title', 'status'], $crud['form_fields']);
        $this->assertSame(['title', 'status'], $crud['table_columns']);

        $form = AdminPanel::formFor('posts', ['title' => 'Hello']);
        $table = AdminPanel::tableFor('posts', [
            ['title' => 'Hello', 'status' => 'draft'],
        ]);

        $formHtml = (string) $form;
        $tableHtml = (string) $table;

        $this->assertStringContainsString('class="admin-form admin-form-posts"', $formHtml);
        $this->assertStringContainsString('name="title"', $formHtml);
        $this->assertStringContainsString('name="status"', $formHtml);

        $this->assertStringContainsString('class="admin-table admin-table-posts"', $tableHtml);
        $this->assertStringContainsString('<th>title</th>', $tableHtml);
        $this->assertStringContainsString('<td>Hello</td>', $tableHtml);
    }

    public function testCrudRoutesStillCoverRegisteredEntities(): void
    {
        AdminPanel::register('posts');
        $routes = AdminPanel::getCrudRoutes();

        $this->assertContains('dashboard', array_column($routes, 'action'));
        $this->assertCount(7, array_filter($routes, fn($route) => $route['slug'] === 'posts'));
    }
}

$test = new Phase3AdminEnhancementTest();

echo "--- Phase 3 Admin Enhancement Test ---\n";

foreach ([
    'testDashboardConfigCanBeReadAndUpdated',
    'testAdminComponentsCanBeRegisteredAndRendered',
    'testCrudConfigSeedsFormAndTableBuilders',
    'testCrudRoutesStillCoverRegisteredEntities',
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

echo "\n--- Phase 3 Admin Enhancement Test Complete ---\n";
