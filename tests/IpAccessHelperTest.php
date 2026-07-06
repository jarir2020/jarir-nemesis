<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Nemesis\Support\IpAccess;

function assertTrue(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }

    echo "PASS: {$message}\n";
}

function assertFalse(bool $condition, string $message): void
{
    assertTrue(!$condition, $message);
}

function assertSame(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, "FAIL: {$message}\nExpected: " . var_export($expected, true) . "\nActual:   " . var_export($actual, true) . "\n");
        exit(1);
    }

    echo "PASS: {$message}\n";
}

$repoRoot = dirname(__DIR__);
$rulesPath = sys_get_temp_dir() . '/nemesis-ip-rules-' . uniqid('', true) . '.php';
@unlink($rulesPath);

putenv('NEMESIS_IP_RULES_PATH=' . $rulesPath);

echo "--- IP Access Helper Test ---\n";

$policy = new IpAccess();
assertTrue($policy->isAllowed('127.0.0.1'), 'default policy allows localhost');
assertFalse($policy->isBlocked('127.0.0.1'), 'default policy does not block localhost');

$blocked = new IpAccess([
    'block' => ['10.0.0.5'],
]);
assertTrue($blocked->isBlocked('10.0.0.5'), 'exact block rule matches');
assertFalse($blocked->isAllowed('10.0.0.5'), 'blocked IP is denied');

$allowOnly = new IpAccess([
    'allow' => ['192.168.1.*'],
]);
assertTrue($allowOnly->isAllowed('192.168.1.44'), 'wildcard allow rule matches');
assertFalse($allowOnly->isAllowed('192.168.2.44'), 'allow list becomes allow-only mode');

$cidrBlocked = new IpAccess([
    'block' => ['203.0.113.0/24'],
]);
assertTrue($cidrBlocked->isBlocked('203.0.113.17'), 'CIDR block rule matches');
assertFalse($cidrBlocked->isBlocked('198.51.100.7'), 'CIDR block does not leak to other ranges');

$cliBase = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($repoRoot . '/bin/nemesis');
$envPrefix = 'NEMESIS_IP_RULES_PATH=' . escapeshellarg($rulesPath) . ' ';

$allowCmd = $envPrefix . $cliBase . ' ip:allow 198.51.100.10 2>&1';
$allowOutput = [];
$allowCode = 0;
exec($allowCmd, $allowOutput, $allowCode);
assertSame(0, $allowCode, 'ip:allow exits successfully');
assertTrue(file_exists($rulesPath), 'ip:allow creates the rules file');

$saved = require $rulesPath;
assertTrue(is_array($saved), 'saved IP rules file returns an array');
assertSame(false, $saved['allow_all'] ?? null, 'ip:allow switches to allow-only mode');
assertSame(['198.51.100.10'], $saved['allow'] ?? [], 'ip:allow stores the new allow rule');

$listCmd = $envPrefix . $cliBase . ' ip:list --json --brief 2>&1';
$listOutput = [];
$listCode = 0;
exec($listCmd, $listOutput, $listCode);
$listText = trim(implode("\n", $listOutput));
$payload = json_decode($listText, true);
assertSame(0, $listCode, 'ip:list --json --brief exits successfully');
assertTrue(is_array($payload), 'ip:list --json returns JSON');
assertSame(1, $payload['allow_count'] ?? null, 'ip:list reports one allow rule');
assertSame(0, $payload['block_count'] ?? null, 'ip:list reports zero block rules');

$blockCmd = $envPrefix . $cliBase . ' ip:block 203.0.113.4 2>&1';
$blockOutput = [];
$blockCode = 0;
exec($blockCmd, $blockOutput, $blockCode);
assertSame(0, $blockCode, 'ip:block exits successfully');

$saved = require $rulesPath;
assertSame(['203.0.113.4'], $saved['block'] ?? [], 'ip:block stores the new block rule');

$resetCmd = $envPrefix . $cliBase . ' ip:reset 2>&1';
$resetOutput = [];
$resetCode = 0;
exec($resetCmd, $resetOutput, $resetCode);
assertSame(0, $resetCode, 'ip:reset exits successfully');

$saved = require $rulesPath;
assertSame(true, $saved['allow_all'] ?? null, 'ip:reset restores allow-all mode');
assertSame([], $saved['allow'] ?? [], 'ip:reset clears allow rules');
assertSame([], $saved['block'] ?? [], 'ip:reset clears block rules');

@unlink($rulesPath);

echo "--- IP Access Helper Test Complete ---\n";
