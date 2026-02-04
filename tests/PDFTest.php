<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Nemesis\Media\PDF;
use Nemesis\Core\Config;

Config::load(__DIR__ . '/../');

echo "--- PDF Operations Test ---\n";

$storage = __DIR__ . '/../storage';
$outputFile = $storage . '/test_pdf.pdf';

try {
    $pdf = new PDF();
    $pdf->setFont('Helvetica', 'B', 16);
    $pdf->text('Nemesis Framework Native PDF', 50, 50);
    
    $pdf->setFont('Helvetica', '', 12);
    $pdf->text('This PDF was generated from scratch in pure PHP.', 50, 80);
    
    // Draw some shapes
    $pdf->line(50, 100, 550, 100);
    $pdf->rect(50, 120, 100, 50, 'S'); // Single rect
    $pdf->rect(170, 120, 100, 50, 'F'); // Filled rect

    // Test Metadata
    $pdf->setMetadata('Title', 'Nemesis PDF Report');
    $pdf->setMetadata('Author', 'Jarir Ahmed');

    // Test Image
    $inputJpg = $storage . '/test_input.jpg'; // We know this exists from ImageTest
    if (file_exists($inputJpg)) {
        $pdf->text('Image Below:', 50, 190);
        $pdf->image($inputJpg, 50, 200, 100, 100);
    }

    // Test Table
    $pdf->addPage();
    $pdf->setFont('Helvetica', 'B', 14);
    $pdf->text('System Report Table', 50, 50);
    $pdf->setXY(50, 70);
    
    $data = [
        ['ID', 'Feature', 'Status'],
        ['1', 'Routing', 'Done'],
        ['2', 'ORM', 'Done'],
        ['3', 'PDF Engine', 'In Progress'],
    ];
    $pdf->table($data, [30, 150, 100]);

    $pdf->save($outputFile);
    
    if (file_exists($outputFile)) {
        echo "SUCCESS: PDF created at $outputFile\n";
        echo "File size: " . filesize($outputFile) . " bytes\n";
    } else {
        echo "FAILURE: PDF not created.\n";
    }
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

echo "\n--- PDF Operations Test Complete ---\n";
