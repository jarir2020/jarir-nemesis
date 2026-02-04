<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Nemesis\Media\Spreadsheet;
use Nemesis\Core\Config;

Config::load(__DIR__ . '/../');

echo "--- Spreadsheet Operations Test ---\n";

$storage = __DIR__ . '/../storage';
$csvFile = $storage . '/test_output.csv';
$xlsxFile = $storage . '/test_output.xlsx';

// 1. Test CSV
echo "Testing CSV Creation: ";
try {
    $csv = Spreadsheet::create('csv');
    $csv->setCellValue('A1', 'Name')
        ->setCellValue('B1', 'Email')
        ->setCellValue('A2', 'John Doe')
        ->setCellValue('B2', 'john@example.com')
        ->save($csvFile);
    
    echo (file_exists($csvFile) ? "PASS" : "FAIL") . "\n";
} catch (\Exception $e) {
    echo "FAIL (" . $e->getMessage() . ")\n";
}

// 2. Test XLSX
echo "Testing XLSX Creation: ";
try {
    if (!class_exists('\ZipArchive')) {
        echo "SKIPPED (zip extension missing)\n";
    } else {
        $xlsx = Spreadsheet::create('xlsx');
        $xlsx->setSheet('Report')
             ->setCellValue('A1', 'Total Sales')
             ->setCellValue('B1', 5000)
             ->setCellValue('A2', 'Average')
             ->setCellValue('B2', 250)
             ->save($xlsxFile);
        
        echo (file_exists($xlsxFile) ? "PASS" : "FAIL") . "\n";
    }
} catch (\Exception $e) {
    echo "FAIL (" . $e->getMessage() . ")\n";
}

// 3. Test Loading (CSV)
echo "Testing CSV Loading: ";
try {
    $loader = Spreadsheet::load($csvFile);
    $rows = $loader->getRows();
    echo (count($rows) === 2 && $rows[1][0] === 'John Doe' ? "PASS" : "FAIL") . "\n";
} catch (\Exception $e) {
    echo "FAIL (" . $e->getMessage() . ")\n";
}

echo "\n--- Spreadsheet Operations Test Complete ---\n";
