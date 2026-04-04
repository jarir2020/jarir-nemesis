<?php
declare(strict_types=1);

namespace Nemesis\Media;

class PDF {
    protected $objects = [];
    protected $pages = [];
    protected $fonts = [];
    protected $currentFont = 'F1';
    protected $fontSize = 12;
    
    protected $width = 595.28; // A4 width in pts
    protected $height = 841.89; // A4 height in pts

    protected $x = 50;
    protected $y = 50;
    protected $margins = ['left' => 50, 'top' => 50, 'right' => 50, 'bottom' => 50];
    protected $metadata = [];
    protected $images = [];

    public function __construct() {
        $this->addFont('Helvetica');
        $this->metadata = [
            'Producer' => 'Nemesis Framework',
            'CreationDate' => 'D:' . date('YmdHis')
        ];
        $this->addPage();
    }

    public function addPage() {
        $pageIdx = count($this->pages) + 1;
        $this->pages[$pageIdx] = [
            'content' => '',
            'resources' => ''
        ];
        // Reset x, y to margins for new page
        $this->x = $this->margins['left'];
        $this->y = $this->margins['top'];
        return $this;
    }

    public function setXY($x, $y) {
        $this->x = $x;
        $this->y = $y;
        return $this;
    }

    public function getX() { return $this->x; }
    public function getY() { return $this->y; }

    public function table(array $data, array $widths = [], array $options = []) {
        $xStart = $this->x;
        $rowHeight = $options['row_height'] ?? 20;
        
        foreach ($data as $rowIndex => $row) {
            $this->x = $xStart;
            $maxH = $rowHeight;

            foreach ($row as $colIndex => $cell) {
                $w = $widths[$colIndex] ?? 100;
                $this->rect($this->x, $this->y, $w, $maxH, 'S');
                $this->multiCell($w, $maxH, $cell);
                $this->x += $w;
            }
            
            $this->y += $maxH;
            
            if ($this->y > ($this->height - $this->margins['bottom'])) {
                $this->addPage();
            }
        }
        return $this;
    }

    public function multiCell($w, $h, $txt, $border = 0, $align = 'L', $fill = false) {
        // Very simple logic: center horizontally and vertically
        $this->text($txt, $this->x + 5, $this->y + ($h / 2) + 4);
        return $this;
    }

    public function setMetadata($key, $value) {
        $this->metadata[$key] = $value;
        return $this;
    }

    public function setFont($family, $style = '', $size = 12) {
        $key = $family . $style;
        if (!isset($this->fonts[$key])) {
            $this->addFont($family, $style);
        }
        $this->currentFont = $this->fonts[$key]['id'];
        $this->fontSize = $size;
        return $this;
    }

    protected function addFont($family, $style = '') {
        $id = 'F' . (count($this->fonts) + 1);
        $this->fonts[$family . $style] = [
            'id' => $id,
            'family' => $family,
            'style' => $style
        ];
    }

    public function text($text, $x, $y) {
        $y = $this->height - $y;
        $this->pages[count($this->pages)]['content'] .= "BT /{$this->currentFont} {$this->fontSize} Tf {$x} {$y} Td (" . $this->escape($text) . ") Tj ET\n";
        return $this;
    }

    public function line($x1, $y1, $x2, $y2) {
        $y1 = $this->height - $y1;
        $y2 = $this->height - $y2;
        $this->pages[count($this->pages)]['content'] .= "{$x1} {$y1} m {$x2} {$y2} l S\n";
        return $this;
    }

    public function rect($x, $y, $w, $h, $style = 'S') {
        $y = $this->height - $y - $h;
        $op = ($style === 'F') ? 'f' : (($style === 'FD' || $style === 'DF') ? 'B' : 's');
        $this->pages[count($this->pages)]['content'] .= "{$x} {$y} {$w} {$h} re {$op}\n";
        return $this;
    }

    public function image($path, $x, $y, $w = 0, $h = 0) {
        $info = getimagesize($path);
        if (!$info) throw new \Exception("Invalid image file.");
        
        $imgId = 'I' . (count($this->images) + 1);
        
        // Simple JPEG support for now
        if ($info[2] !== IMAGETYPE_JPEG) {
             throw new \Exception("Only JPEG images are supported in this native version currently.");
        }

        $this->images[] = [
            'id' => $imgId,
            'path' => $path,
            'w' => $info[0],
            'h' => $info[1]
        ];

        if ($w == 0) $w = $info[0] * 72 / 96; // 96 DPI to 72 DPI
        if ($h == 0) $h = $info[1] * $w / $info[0];

        $y = $this->height - $y - $h;
        $this->pages[count($this->pages)]['content'] .= "q {$w} 0 0 {$h} {$x} {$y} cm /{$imgId} Do Q\n";
        return $this;
    }

    public function save($path) {
        return file_put_contents($path, $this->render());
    }

    public function download($filename = 'document.pdf') {
        if (!headers_sent()) {
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
        }
        echo $this->render();
        exit;
    }

    protected function render() {
        $out = "%PDF-1.4\n";
        $objList = [];
        $offsets = [];
        
        $objId = 1;

        // 1. Catalog & Metadata
        $catalogId = $objId++;
        $pagesRootId = $objId++;
        $infoId = $objId++;
        
        $infoStr = "<< ";
        foreach($this->metadata as $k => $v) $infoStr .= "/$k (" . $this->escape($v) . ") ";
        $objList[$infoId] = $infoStr . ">>";
        
        // 2. Pages Root
        $pageKidsIds = [];
        // Placeholder for pageKidsIds, will be filled later
        // foreach($this->pages as $i => $void) $pageKidsIds[] = 0; 

        // 3. Fonts
        $fontIds = [];
        foreach ($this->fonts as $key => $f) {
            $fontIds[$f['id']] = $objId++;
            $objList[$fontIds[$f['id']]] = "<< /Type /Font /Subtype /Type1 /BaseFont /" . $f['family'] . ($f['style'] ? "-".$f['style'] : "") . " >>";
        }

        // 4. Images
        $imgOids = [];
        foreach ($this->images as $img) {
            $imgOids[$img['id']] = $objId++;
            $content = file_get_contents($img['path']);
            $objList[$imgOids[$img['id']]] = "<< /Type /XObject /Subtype /Image /Width {$img['w']} /Height {$img['h']} /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length " . strlen($content) . " >> stream\n" . $content . "endstream";
        }

        // 5. Pages and Contents
        foreach($this->pages as $i => $page) {
            $pId = $objId++;
            $cId = $objId++;
            $pageKidsIds[] = $pId;
            
            $content = $page['content'];
            $objList[$cId] = "<< /Length " . strlen($content) . " >> stream\n" . $content . "endstream";
            
            $fontRes = "";
            foreach($fontIds as $fid => $oid) $fontRes .= " /$fid $oid 0 R";
            
            $imgRes = "";
            foreach($imgOids as $iid => $oid) $imgRes .= " /$iid $oid 0 R";
            
            $objList[$pId] = "<< /Type /Page /Parent $pagesRootId 0 R /Resources << /Font << $fontRes >> /XObject << $imgRes >> >> /Contents $cId 0 R >>";
        }

        // Finalize Catalog and Pages Root
        $objList[$catalogId] = "<< /Type /Catalog /Pages $pagesRootId 0 R >>";
        $pageKidsStr = "";
        foreach($pageKidsIds as $id) $pageKidsStr .= "$id 0 R ";
        $objList[$pagesRootId] = "<< /Type /Pages /Kids [ $pageKidsStr] /Count " . count($this->pages) . " /MediaBox [0 0 {$this->width} {$this->height}] >>";

        ksort($objList);
        foreach($objList as $id => $val) {
            $offsets[$id] = strlen($out);
            $out .= "$id 0 obj\n$val\nendobj\n";
        }
        
        $xrefPos = strlen($out);
        $out .= "xref\n0 " . (count($objList) + 1) . "\n0000000000 65535 f \n";
        foreach($objList as $id => $val) {
            $out .= sprintf("%010d 00000 n \n", $offsets[$id]);
        }
        
        $out .= "trailer\n<< /Size " . (count($objList) + 1) . " /Root $catalogId 0 R /Info $infoId 0 R >>\nstartxref\n$xrefPos\n%%EOF";
        
        return $out;
    }

    protected function escape($s) {
        return str_replace(['(', ')', '\\'], ['\\(', '\\)', '\\\\'], $s);
    }
}
