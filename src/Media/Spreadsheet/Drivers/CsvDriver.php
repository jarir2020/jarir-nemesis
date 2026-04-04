<?php
declare(strict_types=1);

namespace Nemesis\Media\Spreadsheet\Drivers;

class CsvDriver implements SpreadsheetDriver {
    protected $data = ['Sheet1' => []];

    public function create() {
        $this->data = ['Sheet1' => []];
    }

    public function load($path) {
        if (($handle = fopen($path, "r")) !== false) {
            $this->data['Sheet1'] = [];
            while (($row = fgetcsv($handle, 1000, ",")) !== false) {
                $this->data['Sheet1'][] = $row;
            }
            fclose($handle);
        }
    }

    public function save($path) {
        $fp = fopen($path, 'w');
        foreach ($this->data['Sheet1'] as $row) {
            fputcsv($fp, $row);
        }
        fclose($fp);
    }

    public function setCellValue($sheet, $cell, $value, $options = []) {
        // Convert A1 to [0,0]
        [$col, $row] = $this->parseCell($cell);
        $this->data['Sheet1'][$row][$col] = $value;
    }

    public function getCellValue($sheet, $cell) {
        [$col, $row] = $this->parseCell($cell);
        return $this->data['Sheet1'][$row][$col] ?? null;
    }

    public function getRows($sheet) {
        return $this->data['Sheet1'] ?? [];
    }

    public function addSheet($title) {
        // CSV only supports one sheet
    }

    public function setStyle($sheet, $range, $style) {
        // CSV does not support styles
    }

    public function mergeCells($sheet, $range) {
        // CSV does not support merging
    }

    protected function parseCell($cell) {
        preg_match('/([A-Z]+)([0-9]+)/', strtoupper($cell), $matches);
        $colStr = $matches[1];
        $row = (int)$matches[2] - 1;
        
        $col = 0;
        $len = strlen($colStr);
        for ($i = 0; $i < $len; $i++) {
            $col = $col * 26 + (ord($colStr[$i]) - 64);
        }
        return [$col - 1, $row];
    }
}
