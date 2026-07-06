<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Nemesis\Core\Database;
use Nemesis\Core\Fluent;
use Nemesis\Core\Model;
use Nemesis\Testing\TestCase;

class ConnectionSelectionDefaultRecord extends Model
{
    protected $table = 'default_records';
    public bool $timestamps = false;
}

class ConnectionSelectionAnalyticsRecord extends Model
{
    protected $table = 'analytics_records';
    protected ?string $connection = 'analytics';
    public bool $timestamps = false;
}

class ConnectionSelectionAnalyticsFluent extends Fluent
{
    public function __construct()
    {
        parent::__construct('fluent_records', 'analytics');
    }
}

class DatabaseConnectionSelectionTest extends TestCase
{
    private string $defaultDatabase;
    private string $analyticsDatabase;

    public function setUp(): void
    {
        $this->defaultDatabase = sys_get_temp_dir() . '/nemesis-default-' . uniqid('', true) . '.sqlite';
        $this->analyticsDatabase = sys_get_temp_dir() . '/nemesis-analytics-' . uniqid('', true) . '.sqlite';

        Database::disconnect();
        Database::configure([
            'default_connection' => 'default',
            'connections' => [
                'default' => [
                    'driver' => 'sqlite',
                    'database' => $this->defaultDatabase,
                ],
                'analytics' => [
                    'driver' => 'sqlite',
                    'database' => $this->analyticsDatabase,
                ],
            ],
        ], 'default');

        Database::connection('default')->exec(
            'CREATE TABLE IF NOT EXISTS default_records (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL
            )'
        );

        Database::connection('analytics')->exec(
            'CREATE TABLE IF NOT EXISTS analytics_records (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL
            )'
        );

        Database::connection('analytics')->exec(
            'CREATE TABLE IF NOT EXISTS fluent_records (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL
            )'
        );
    }

    public function tearDown(): void
    {
        Database::disconnect();

        foreach ([$this->defaultDatabase, $this->analyticsDatabase] as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
    }

    public function testModelsCanRouteToDifferentNamedConnections(): void
    {
        $default = ConnectionSelectionDefaultRecord::create(['name' => 'Default']);
        $analytics = ConnectionSelectionAnalyticsRecord::create(['name' => 'Analytics']);

        $this->assertNotNull($default->getKey());
        $this->assertNotNull($analytics->getKey());
        $this->assertSame('analytics', $analytics->getConnectionName());
        $this->assertNull($default->getConnectionName());

        $defaultRows = Database::view('SELECT COUNT(*) AS aggregate FROM default_records', [], 'default');
        $analyticsRows = Database::view('SELECT COUNT(*) AS aggregate FROM analytics_records', [], 'analytics');

        $this->assertSame(1, (int) ($defaultRows[0]['aggregate'] ?? 0));
        $this->assertSame(1, (int) ($analyticsRows[0]['aggregate'] ?? 0));
    }

    public function testFluentKeepsNamedConnectionThroughNestedQueries(): void
    {
        $builder = new ConnectionSelectionAnalyticsFluent();
        $builder->insert(['name' => 'Nested']);

        $rows = Database::view('SELECT COUNT(*) AS aggregate FROM fluent_records', [], 'analytics');
        $this->assertSame(1, (int) ($rows[0]['aggregate'] ?? 0));

        $nestedCount = $builder
            ->whereNested(function (Fluent $query): void {
                $query->where('name', 'Nested');
            })
            ->count();

        $this->assertSame(1, $nestedCount);
    }

    public function testMakeModelCommandGeneratesAConnectionAwareConstructor(): void
    {
        $name = 'DbChoice' . uniqid();
        $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(base_path('bin/nemesis')) . ' make:model ' . escapeshellarg($name) . ' --connection=analytics 2>&1';
        $output = [];
        $code = 0;
        exec($cmd, $output, $code);

        $text = implode("\n", $output);
        $this->assertSame(0, $code, $text);
        $this->assertStringContainsString('Model created at', $text);

        $path = base_path('app/Models/' . $name . '.php');
        $this->assertTrue(file_exists($path));

        require_once $path;
        $class = 'App\\Models\\' . $name;
        $model = new $class();

        $this->assertSame('analytics', $model->getConnectionName());
        $this->assertStringContainsString("protected ?string \$connection = 'analytics';", file_get_contents($path));
        $this->assertStringContainsString('parent::__construct($this->table, $connection ?? $this->connection);', file_get_contents($path));

        @unlink($path);
    }

    public function testDbListConnectionsCommandShowsConfiguredTargets(): void
    {
        $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(base_path('bin/nemesis')) . ' db:list-connections 2>&1';
        $output = [];
        $code = 0;
        exec($cmd, $output, $code);

        $text = implode("\n", $output);
        $this->assertSame(0, $code, $text);
        $this->assertStringContainsString('Nemesis Database Connections:', $text);
        $this->assertStringContainsString('default (default)', $text);
        $this->assertStringContainsString('analytics', $text);
    }

    public function testDbListConnectionsCommandCanEmitJsonPayload(): void
    {
        $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(base_path('bin/nemesis')) . ' db:list-connections --json --pretty 2>&1';
        $output = [];
        $code = 0;
        exec($cmd, $output, $code);

        $text = implode("\n", $output);
        $this->assertSame(0, $code, $text);

        $payload = json_decode($text, true);
        $this->assertIsArray($payload);
        $this->assertSame('Nemesis Database Connections', $payload['title'] ?? null);
        $this->assertSame('default', $payload['default'] ?? null);
        $this->assertSame(2, (int) ($payload['count'] ?? 0));

        $names = array_map(static fn(array $item): string => $item['name'] ?? '', $payload['connections'] ?? []);
        $this->assertContains('default', $names);
        $this->assertContains('analytics', $names);
    }

    public function testDbListConnectionsCommandCanEmitBriefJsonPayload(): void
    {
        $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(base_path('bin/nemesis')) . ' db:list-connections --json --brief 2>&1';
        $output = [];
        $code = 0;
        exec($cmd, $output, $code);

        $text = implode("\n", $output);
        $this->assertSame(0, $code, $text);
        $this->assertStringNotContainsString("\n    ", $text);

        $payload = json_decode($text, true);
        $this->assertIsArray($payload);
        $this->assertSame('Nemesis Database Connections', $payload['title'] ?? null);
    }

    public function testMakeModelCanListConnectionsInline(): void
    {
        $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(base_path('bin/nemesis')) . ' make:model --list-connections 2>&1';
        $output = [];
        $code = 0;
        exec($cmd, $output, $code);

        $text = implode("\n", $output);
        $this->assertSame(0, $code, $text);
        $this->assertStringContainsString('Nemesis Database Connections:', $text);
        $this->assertStringContainsString('default (default)', $text);
        $this->assertStringContainsString('analytics', $text);
    }
}

$test = new DatabaseConnectionSelectionTest();

echo "--- Database Connection Selection Test ---\n";

foreach ([
    'testModelsCanRouteToDifferentNamedConnections',
    'testFluentKeepsNamedConnectionThroughNestedQueries',
    'testMakeModelCommandGeneratesAConnectionAwareConstructor',
    'testDbListConnectionsCommandShowsConfiguredTargets',
    'testDbListConnectionsCommandCanEmitJsonPayload',
    'testDbListConnectionsCommandCanEmitBriefJsonPayload',
    'testMakeModelCanListConnectionsInline',
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

echo "\n--- Database Connection Selection Test Complete ---\n";
