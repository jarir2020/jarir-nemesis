<?php
declare(strict_types=1);

namespace Nemesis\Media;

class Spreadsheet {
    public static function create($type = 'xlsx') {
        if ($type === 'csv') {
            return new SpreadsheetManager(new \Nemesis\Media\Spreadsheet\Drivers\CsvDriver());
        }
        return new SpreadsheetManager(new \Nemesis\Media\Spreadsheet\Drivers\XlsxDriver());
    }

    public static function load($path) {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $driver = ($ext === 'csv') ? new \Nemesis\Media\Spreadsheet\Drivers\CsvDriver() : new \Nemesis\Media\Spreadsheet\Drivers\XlsxDriver();
        $manager = new SpreadsheetManager($driver);
        return $manager->load($path);
    }
}
