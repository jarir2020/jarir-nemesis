<?php
declare(strict_types=1);

namespace Nemesis\Media\Spreadsheet\Drivers;

class XlsxDriver implements SpreadsheetDriver {
    protected $data = [];
    protected $styles = [];
    protected $sheets = ['Sheet1'];

    public function create() {
        $this->data = ['Sheet1' => []];
        $this->sheets = ['Sheet1'];
    }

    public function load($path) {
        // Basic loading logic would involve unzipping and parsing XML.
        // For a from-scratch implementation, we'll focus on the Writer first.
        throw new \Exception("XLSX loading not implemented in this native version.");
    }

    public function save($path) {
        if (!class_exists('\ZipArchive')) {
            throw new \Exception("The [zip] extension is required for XLSX generation.");
        }
        $zip = new \ZipArchive();
        if ($zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \Exception("Cannot create XLSX file.");
        }

        // 1. [Content_Types].xml
        $zip->addFromString('[Content_Types].xml', $this->buildContentTypes());

        // 2. _rels/.rels
        $zip->addFromString('_rels/.rels', $this->buildGlobalRels());

        // 3. xl/workbook.xml
        $zip->addFromString('xl/workbook.xml', $this->buildWorkbook());

        // 4. xl/_rels/workbook.xml.rels
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->buildWorkbookRels());

        // 5. xl/worksheets/sheetN.xml
        foreach ($this->sheets as $index => $name) {
            $zip->addFromString('xl/worksheets/sheet' . ($index + 1) . '.xml', $this->buildWorksheet($name));
        }

        // 6. xl/styles.xml
        $zip->addFromString('xl/styles.xml', $this->buildStyles());

        $zip->close();
    }

    public function setCellValue($sheet, $cell, $value, $options = []) {
        if (!isset($this->data[$sheet])) $this->addSheet($sheet);
        $this->data[$sheet][$cell] = $value;
    }

    public function getCellValue($sheet, $cell) {
        return $this->data[$sheet][$cell] ?? null;
    }

    public function getRows($sheet) {
        // Convert A1 array to rows for reading
        return $this->data[$sheet] ?? [];
    }

    public function addSheet($name) {
        if (!in_array($name, $this->sheets)) {
            $this->sheets[] = $name;
            $this->data[$name] = [];
        }
    }

    public function setStyle($sheet, $range, $style) {
        // Placeholder for style mapping
    }

    public function mergeCells($sheet, $range) {
        // Placeholder for merge logic
    }

    protected function buildContentTypes() {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
        $xml .= '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">';
        $xml .= '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>';
        $xml .= '<Default Extension="xml" ContentType="application/xml"/>';
        $xml .= '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>';
        $xml .= '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>';
        foreach ($this->sheets as $i => $n) {
            $xml .= '<Override PartName="/xl/worksheets/sheet' . ($i + 1) . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }
        $xml .= '</Types>';
        return $xml;
    }

    protected function buildGlobalRels() {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
        $xml .= '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
        $xml .= '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>';
        $xml .= '</Relationships>';
        return $xml;
    }

    protected function buildWorkbook() {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
        $xml .= '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">';
        $xml .= '<sheets>';
        foreach ($this->sheets as $i => $name) {
            $xml .= '<sheet name="' . $name . '" sheetId="' . ($i + 1) . '" r:id="rId' . ($i + 1) . '"/>';
        }
        $xml .= '</sheets></workbook>';
        return $xml;
    }

    protected function buildWorkbookRels() {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
        $xml .= '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
        foreach ($this->sheets as $i => $name) {
            $xml .= '<Relationship Id="rId' . ($i + 1) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . ($i + 1) . '.xml"/>';
        }
        $xml .= '<Relationship Id="rIdStyle" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';
        $xml .= '</Relationships>';
        return $xml;
    }

    protected function buildWorksheet($name) {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
        $xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
        $xml .= '<sheetData>';
        
        // Group data by rows
        $rows = [];
        if (isset($this->data[$name])) {
            foreach ($this->data[$name] as $cell => $val) {
                preg_match('/[0-9]+/', $cell, $m);
                $r = $m[0];
                $rows[$r][$cell] = $val;
            }
        }
        ksort($rows);

        foreach ($rows as $r => $cells) {
            $xml .= '<row r="' . $r . '">';
            ksort($cells);
            foreach ($cells as $c => $v) {
                $type = is_numeric($v) ? 'n' : 'inlineStr';
                $xml .= '<c r="' . $c . '" t="' . $type . '">';
                if ($type === 'inlineStr') {
                    $xml .= '<is><t>' . htmlspecialchars($v) . '</t></is>';
                } else {
                    $xml .= '<v>' . $v . '</v>';
                }
                $xml .= '</c>';
            }
            $xml .= '</row>';
        }

        $xml .= '</sheetData></worksheet>';
        return $xml;
    }

    protected function buildStyles() {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n" .
               '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="1"><font><sz val="11"/><name val="Calibri"/></font></fonts><fills count="1"><fill><patternFill patternType="none"/></fill></fills><borders count="1"><border><left/><right/><top/><bottom/></border></borders><cellStyleXfs count="1"><xf fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/></cellXfs></styleSheet>';
    }
}
