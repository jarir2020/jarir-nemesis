<?php
echo "--- Nemesis framework Full Test Suite ---\n\n";

$tests = glob(__DIR__ . '/*Test.php');

foreach ($tests as $test) {
    echo "Running " . basename($test) . ":\n";
    passthru("php \"$test\"");
    echo "---------------------------\n";
}

echo "\nFull Test Suite Run Complete.\n";
