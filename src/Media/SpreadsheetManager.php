<?php
declare(strict_types=1);

namespace Nemesis\Media;

class SpreadsheetManager {
    protected $driver;
    protected $activeSheet = 'Sheet1';

    public function __construct($driver) {
        $this->driver = $driver;
        $this->driver->create();
    }

    public function load($path) {
        $this->driver->load($path);
        return $this;
    }

    public function setSheet($title) {
        $this->driver->addSheet($title);
        $this->activeSheet = $title;
        return $this;
    }

    public function setCellValue($cell, $value, $options = []) {
        $this->driver->setCellValue($this->activeSheet, $cell, $value, $options);
        return $this;
    }

    public function getCellValue($cell) {
        return $this->driver->getCellValue($this->activeSheet, $cell);
    }

    public function getRows() {
        return $this->driver->getRows($this->activeSheet);
    }

    public function setStyle($range, $style) {
        $this->driver->setStyle($this->activeSheet, $range, $style);
        return $this;
    }

    public function mergeCells($range) {
        $this->driver->mergeCells($this->activeSheet, $range);
        return $this;
    }

    public function save($path) {
        $this->driver->save($path);
        return $this;
    }

    public function download($filename = 'spreadsheet.xlsx') {
        $temp = tempnam(sys_get_temp_dir(), 'ss');
        $this->save($temp);
        
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $types = [
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'xls' => 'application/vnd.ms-excel',
            'csv' => 'text/csv'
        ];

        header('Content-Type: ' . ($types[$ext] ?? 'application/octet-stream'));
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        readfile($temp);
        unlink($temp);
        exit;
    }
}
