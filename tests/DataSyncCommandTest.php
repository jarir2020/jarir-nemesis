<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Nemesis\Testing\TestCase;

class DataSyncCommandTest extends TestCase
{
    private string $root;
    private string $dataRoot;
    private string $bangladeshSource;

    public function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/nemesis-data-sync-' . uniqid('', true);
        $this->dataRoot = $this->root . '/public/data';
        mkdir($this->dataRoot, 0775, true);

        $this->bangladeshSource = $this->root . '/bangladesh-source.json';
        file_put_contents($this->bangladeshSource, json_encode([
            'country' => 'Bangladesh',
            'regions' => [
                ['name' => 'Dhaka', 'districts' => ['Dhaka', 'Gazipur']],
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    public function tearDown(): void
    {
        if (is_dir($this->root)) {
            $this->deleteDirectory($this->root);
        }
    }

    public function testDataSyncScaffoldsCoreJsonFiles(): void
    {
        $result = $this->runCommand('--json --brief');
        $payload = json_decode($result['output'], true);

        $this->assertSame(0, $result['code'], $result['output']);
        $this->assertIsArray($payload);
        $this->assertSame('Nemesis Public Data Packs', $payload['title'] ?? null);
        $this->assertGreaterThanOrEqual(1, (int) ($payload['count'] ?? 0));

        foreach ([
            'countries.json',
            'states.json',
            'cities.json',
            'languages.json',
            'timezones.json',
            'currencies.json',
            'countries_states_cities.json',
            'countries_states_cities_languages.json',
            'countries_states_cities_languages_timezones.json',
            'countries_states_cities_languages_timezones_currencies.json',
            'countries_states_cities_languages_timezones_currencies_phone_codes.json',
            'countries_states_cities_languages_timezones_currencies_phone_codes_emails.json',
            'countries_states_cities_languages_timezones_currencies_phone_codes_emails_urls.json',
        ] as $file) {
            $path = $this->dataRoot . '/' . $file;
            $this->assertTrue(file_exists($path), $file);
            $this->assertNotEmpty(json_decode((string) file_get_contents($path), true));
        }
    }

    public function testDataSyncCanImportBangladeshLocationsFromLocalSource(): void
    {
        $result = $this->runCommand('--json --brief --bangladesh-source=' . escapeshellarg($this->bangladeshSource));
        $payload = json_decode($result['output'], true);

        $this->assertSame(0, $result['code'], $result['output']);
        $this->assertIsArray($payload);

        $path = $this->dataRoot . '/bangladesh_locations.json';
        $this->assertTrue(file_exists($path));
        $this->assertSame('Bangladesh', json_decode((string) file_get_contents($path), true)['country'] ?? null);
    }

    private function runCommand(string $flags = ''): array
    {
        $cmd = sprintf(
            'NEMESIS_PUBLIC_DATA_ROOT=%s %s %s data:sync %s 2>&1',
            escapeshellarg($this->dataRoot),
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

$test = new DataSyncCommandTest();

echo "--- Data Sync Command Test ---\n";

foreach ([
    'testDataSyncScaffoldsCoreJsonFiles',
    'testDataSyncCanImportBangladeshLocationsFromLocalSource',
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

echo "\n--- Data Sync Command Test Complete ---\n";
