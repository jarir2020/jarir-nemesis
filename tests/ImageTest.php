<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Nemesis\Media\Image;
use Nemesis\Core\Config;

Config::load(__DIR__ . '/../');

echo "--- Image Operations Test ---\n";

$storage = __DIR__ . '/../storage';
$input = $storage . '/test_input.jpg';
$output = $storage . '/test_output.png';

// 1. Create a base image for testing if none exists
if (!extension_loaded('gd')) {
    die("GD extension required for this test.\n");
}

$img = imagecreatetruecolor(100, 100);
$red = imagecolorallocate($img, 255, 0, 0);
imagefill($img, 0, 0, $red);
imagejpeg($img, $input);
imagedestroy($img);

echo "Testing Load and Resize: ";
try {
    Image::load($input)
         ->resize(50, 50)
         ->save($output);
    
    if (file_exists($output)) {
        $info = getimagesize($output);
        echo ($info[0] === 50 ? "PASS" : "FAIL (Size mismatch)") . "\n";
    } else {
        echo "FAIL (File not saved)\n";
    }
} catch (\Exception $e) {
    echo "FAIL (" . $e->getMessage() . ")\n";
}

echo "Testing Filter (Grayscale): ";
try {
    $outputGrayscale = $storage . '/test_grayscale.png';
    Image::load($input)
         ->filter('grayscale')
         ->save($outputGrayscale);
    echo (file_exists($outputGrayscale) ? "PASS" : "FAIL") . "\n";
} catch (\Exception $e) {
    echo "FAIL (" . $e->getMessage() . ")\n";
}

echo "Testing Drawing (Rectangle): ";
try {
    $outputDraw = $storage . '/test_draw.png';
    Image::create(200, 200, '#FFFFFF')
         ->drawRectangle(10, 10, 50, 50, '#0000FF', true)
         ->save($outputDraw);
    echo (file_exists($outputDraw) ? "PASS" : "FAIL") . "\n";
} catch (\Exception $e) {
    echo "FAIL (" . $e->getMessage() . ")\n";
}

echo "\n--- Image Operations Test Complete ---\n";
