<?php
declare(strict_types=1);

namespace Nemesis\Media\Spreadsheet\Drivers;

interface SpreadsheetDriver {
    public function create();
    public function load($path);
    public function save($path);
    public function setCellValue($sheet, $cell, $value, $options = []);
    public function getCellValue($sheet, $cell);
    public function getRows($sheet);
    public function addSheet($title);
    public function setStyle($sheet, $range, $style);
    public function mergeCells($sheet, $range);
}
