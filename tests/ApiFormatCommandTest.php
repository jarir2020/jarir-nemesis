<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Nemesis\Http\Response;
use Nemesis\Testing\TestCase;

class ApiFormatCommandTest extends TestCase
{
    private string $configFile;
    private ?string $configBackup = null;
    private ?string $configHiddenBackup = null;

    public function setUp(): void
    {
        $this->configFile = base_path('config/api.php');

        if (file_exists($this->configFile)) {
            $this->configBackup = file_get_contents($this->configFile);
        }
    }

    public function tearDown(): void
    {
        $hiddenPath = $this->configFile . '.hidden';
        if (file_exists($hiddenPath)) {
            rename($hiddenPath, $this->configFile);
        }

        if ($this->configBackup !== null) {
            file_put_contents($this->configFile, $this->configBackup);
        }
    }

    public function testApiFormatCommandCanTogglePrettyJson(): void
    {
        $offCmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(base_path('bin/nemesis')) . ' api:format off 2>&1';
        $offOutput = [];
        $offCode = 0;
        exec($offCmd, $offOutput, $offCode);

        $this->assertSame(0, $offCode);
        $this->assertStringContainsString('API JSON format set to brief', implode("\n", $offOutput));

        require_once __DIR__ . '/../vendor/autoload.php';
        \Nemesis\Core\Config::load(base_path());

        $compact = Response::json(['x' => 1], 200, [], null);
        $this->assertSame('{"x":1}', $compact->getContent());

        $statusCmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(base_path('bin/nemesis')) . ' api:format status 2>&1';
        $statusOutput = [];
        $statusCode = 0;
        exec($statusCmd, $statusOutput, $statusCode);

        $statusText = implode("\n", $statusOutput);
        $this->assertSame(0, $statusCode);
        $this->assertStringContainsString('API JSON format: brief', $statusText);

        $jsonStatusCmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(base_path('bin/nemesis')) . ' api:format status --json 2>&1';
        $jsonStatusOutput = [];
        $jsonStatusCode = 0;
        exec($jsonStatusCmd, $jsonStatusOutput, $jsonStatusCode);

        $jsonStatusText = implode("\n", $jsonStatusOutput);
        $payload = json_decode($jsonStatusText, true);
        $this->assertSame(0, $jsonStatusCode);
        $this->assertIsArray($payload);
        $this->assertSame('brief', $payload['mode']);
        $this->assertFalse($payload['pretty_json']);
        $this->assertFalse(isset($payload['source']));
        $this->assertStringContainsString("\n    \"mode\"", $jsonStatusText);

        $sourceStatusCmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(base_path('bin/nemesis')) . ' api:format status --json --source 2>&1';
        $sourceStatusOutput = [];
        $sourceStatusCode = 0;
        exec($sourceStatusCmd, $sourceStatusOutput, $sourceStatusCode);

        $sourceStatusText = implode("\n", $sourceStatusOutput);
        $sourcePayload = json_decode($sourceStatusText, true);
        $this->assertSame(0, $sourceStatusCode);
        $this->assertIsArray($sourcePayload);
        $this->assertSame('config/api.php', $sourcePayload['source']);
        $this->assertStringContainsString("\n    \"source\"", $sourceStatusText);

        $compactSourceCmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(base_path('bin/nemesis')) . ' api:format status --json --source --brief 2>&1';
        $compactSourceOutput = [];
        $compactSourceCode = 0;
        exec($compactSourceCmd, $compactSourceOutput, $compactSourceCode);

        $compactSourceText = trim(implode("\n", $compactSourceOutput));
        $compactSourcePayload = json_decode($compactSourceText, true);
        $this->assertSame(0, $compactSourceCode);
        $this->assertIsArray($compactSourcePayload);
        $this->assertSame('config/api.php', $compactSourcePayload['source']);
        $this->assertStringNotContainsString("\n    \"source\"", $compactSourceText);

        $briefStatusCmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(base_path('bin/nemesis')) . ' api:format --json --brief 2>&1';
        $briefStatusOutput = [];
        $briefStatusCode = 0;
        exec($briefStatusCmd, $briefStatusOutput, $briefStatusCode);

        $briefStatusText = trim(implode("\n", $briefStatusOutput));
        $briefPayload = json_decode($briefStatusText, true);
        $this->assertSame(0, $briefStatusCode);
        $this->assertIsArray($briefPayload);
        $this->assertSame('brief', $briefPayload['mode']);
        $this->assertFalse($briefPayload['pretty_json']);
        $this->assertStringNotContainsString("\n    \"mode\"", $briefStatusText);

        $onCmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(base_path('bin/nemesis')) . ' api:format on 2>&1';
        $onOutput = [];
        $onCode = 0;
        exec($onCmd, $onOutput, $onCode);

        $this->assertSame(0, $onCode);
        $this->assertStringContainsString('API JSON format set to pretty', implode("\n", $onOutput));

        $prettyBriefCmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(base_path('bin/nemesis')) . ' api:format --json --brief 2>&1';
        $prettyBriefOutput = [];
        $prettyBriefCode = 0;
        exec($prettyBriefCmd, $prettyBriefOutput, $prettyBriefCode);

        $prettyBriefText = trim(implode("\n", $prettyBriefOutput));
        $prettyBriefPayload = json_decode($prettyBriefText, true);
        $this->assertSame(0, $prettyBriefCode);
        $this->assertIsArray($prettyBriefPayload);
        $this->assertTrue($prettyBriefPayload['pretty_json']);
        $this->assertStringNotContainsString("\n    \"mode\"", $prettyBriefText);

        $prettyStatusCmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(base_path('bin/nemesis')) . ' api:format --json --pretty 2>&1';
        $prettyStatusOutput = [];
        $prettyStatusCode = 0;
        exec($prettyStatusCmd, $prettyStatusOutput, $prettyStatusCode);

        $prettyStatusText = implode("\n", $prettyStatusOutput);
        $prettyStatusPayload = json_decode($prettyStatusText, true);
        $this->assertSame(0, $prettyStatusCode);
        $this->assertIsArray($prettyStatusPayload);
        $this->assertTrue($prettyStatusPayload['pretty_json']);
        $this->assertStringContainsString("\n    \"mode\"", $prettyStatusText);

        $prettySourceCmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(base_path('bin/nemesis')) . ' api:format status --json --source --pretty 2>&1';
        $prettySourceOutput = [];
        $prettySourceCode = 0;
        exec($prettySourceCmd, $prettySourceOutput, $prettySourceCode);

        $prettySourceText = implode("\n", $prettySourceOutput);
        $prettySourcePayload = json_decode($prettySourceText, true);
        $this->assertSame(0, $prettySourceCode);
        $this->assertIsArray($prettySourcePayload);
        $this->assertSame('config/api.php', $prettySourcePayload['source']);
        $this->assertStringContainsString("\n    \"source\"", $prettySourceText);
    }

    public function testApiFormatCommandReportsEnvFallbackSourceWhenConfigFileIsMissing(): void
    {
        $hiddenPath = $this->configFile . '.hidden';
        if (file_exists($hiddenPath)) {
            unlink($hiddenPath);
        }

        rename($this->configFile, $hiddenPath);
        putenv('APP_JSON_PRETTY=false');

        $cmd = 'APP_JSON_PRETTY=false ' . escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(base_path('bin/nemesis')) . ' api:format status --json --source 2>&1';
        $output = [];
        $code = 0;
        exec($cmd, $output, $code);

        $text = implode("\n", $output);
        $payload = json_decode($text, true);
        $this->assertSame(0, $code);
        $this->assertIsArray($payload);
        $this->assertSame('env:APP_JSON_PRETTY', $payload['source']);
        $this->assertFalse($payload['pretty_json']);
    }

    public function testHelpIncludesApiFormatMatrixExamples(): void
    {
        $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(base_path('bin/nemesis')) . ' help 2>&1';
        $output = [];
        $code = 0;
        exec($cmd, $output, $code);

        $text = implode("\n", $output);
        $this->assertSame(0, $code);
        $this->assertStringContainsString('API Format Examples:', $text);
        $this->assertStringContainsString('api:format status --json --brief', $text);
        $this->assertStringContainsString('api:format status --json --source --pretty', $text);
    }

    public function testApiFormatExamplesAliasPrintsOnlyMatrix(): void
    {
        $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(base_path('bin/nemesis')) . ' api:format examples 2>&1';
        $output = [];
        $code = 0;
        exec($cmd, $output, $code);

        $text = implode("\n", $output);
        $this->assertSame(0, $code);
        $this->assertStringContainsString('API Format Examples:', $text);
        $this->assertStringContainsString('api:format status --json --source --brief', $text);
        $this->assertStringNotContainsString('Nemesis Framework Available Commands', $text);
    }

    public function testApiFormatExamplesAliasCanReturnJson(): void
    {
        $prettyCmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(base_path('bin/nemesis')) . ' api:format examples --json --pretty 2>&1';
        $prettyOutput = [];
        $prettyCode = 0;
        exec($prettyCmd, $prettyOutput, $prettyCode);

        $prettyText = implode("\n", $prettyOutput);
        $prettyPayload = json_decode($prettyText, true);
        $this->assertSame(0, $prettyCode);
        $this->assertIsArray($prettyPayload);
        $this->assertSame('API Format Examples', $prettyPayload['title']);
        $this->assertCount(6, $prettyPayload['examples']);
        $this->assertStringContainsString("\n    \"examples\"", $prettyText);

        $briefCmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(base_path('bin/nemesis')) . ' api:format examples --json --brief 2>&1';
        $briefOutput = [];
        $briefCode = 0;
        exec($briefCmd, $briefOutput, $briefCode);

        $briefText = trim(implode("\n", $briefOutput));
        $briefPayload = json_decode($briefText, true);
        $this->assertSame(0, $briefCode);
        $this->assertIsArray($briefPayload);
        $this->assertSame('API Format Examples', $briefPayload['title']);
        $this->assertCount(6, $briefPayload['examples']);
        $this->assertStringNotContainsString("\n    \"examples\"", $briefText);
    }
}

$test = new ApiFormatCommandTest();

echo "--- API Format Command Test ---\n";

foreach ([
    'testApiFormatCommandCanTogglePrettyJson',
    'testApiFormatCommandReportsEnvFallbackSourceWhenConfigFileIsMissing',
    'testHelpIncludesApiFormatMatrixExamples',
    'testApiFormatExamplesAliasPrintsOnlyMatrix',
    'testApiFormatExamplesAliasCanReturnJson',
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

echo "\n--- API Format Command Test Complete ---\n";
