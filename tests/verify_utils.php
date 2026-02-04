<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Nemesis\Support\File;
use Nemesis\Support\UserAgent;
use Nemesis\Support\Time;
use Nemesis\Support\Codec;
use Nemesis\Support\Str;
use Nemesis\Support\Arr;
use Nemesis\Support\Collection;
use Nemesis\Http\Status;

// Test Status FIRST before any output
Status::teapot();
$statusPass = (http_response_code() === 418);

echo "--- Verifying Vendor Utilities ---\n";
echo "Status::teapot: " . ($statusPass ? 'PASS' : 'FAIL') . "\n";

// File Test
$csvFile = __DIR__ . '/../storage/test.csv';
File::writeCsv($csvFile, [['Name', 'Age'], ['Jarir', 25]]);
$data = File::readCsv($csvFile);
echo "File::csv: " . ($data[1][0] === 'Jarir' ? 'PASS' : 'FAIL') . "\n";
@unlink($csvFile);

// UserAgent
echo "UserAgent::os: " . (is_string(UserAgent::os()) ? 'PASS' : 'FAIL') . "\n";

// Time
$days = Time::businessDays('2023-01-01', '2023-01-07'); 
echo "Time::businessDays: " . ($days === 5 ? 'PASS' : 'FAIL (' . $days . ')') . "\n";
echo "Time::isLeapYear: " . (Time::isLeapYear(2024) === true ? 'PASS' : 'FAIL') . "\n";

// Arr
$array = ['user' => ['name' => 'Jarir', 'meta' => ['role' => 'admin']]];
$role = Arr::get($array, 'user.meta.role');
echo "Arr::get (dot): " . ($role === 'admin' ? 'PASS' : 'FAIL') . "\n";

// Collection
$col = Collection::make([1, 2, 3])
    ->map(function($n) { return $n * 2; })
    ->filter(function($n) { return $n > 2; });
echo "Collection::fluent: " . ($col->count() === 2 && $col->first() === 4 ? 'PASS' : 'FAIL') . "\n";

// Codec
$ntlm = Codec::ntlm('password');
echo "Codec::ntlm: " . (strlen($ntlm) === 32 ? 'PASS' : 'FAIL') . "\n";

// Str
$rand = Str::random(10);
echo "Str::random: " . (strlen($rand) === 10 ? 'PASS' : 'FAIL') . "\n";

echo "Utilities Verified.\n";
