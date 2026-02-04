<?php
$zip = new ZipArchive();
$result = $zip->open('d:/Project Nemesis/storage/test_archive.zip');

if ($result === true) {
    echo 'ZIP opened successfully' . PHP_EOL;
    echo 'Num files: ' . $zip->numFiles . PHP_EOL;
    
    for ($i = 0; $i < $zip->numFiles; $i++) {
        echo $zip->getNameIndex($i) . PHP_EOL;
    }
    
    $zip->close();
} else {
    echo 'Failed to open ZIP: ' . $result . PHP_EOL;
}
