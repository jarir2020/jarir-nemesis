<?php
declare(strict_types=1);

namespace Nemesis\Media\Drivers;

class ImagickDriver implements ImageDriver {
    protected $imagick;

    public function load($path) {
        if (!file_exists($path)) {
            throw new \Exception("Image file not found: $path");
        }
        $this->imagick = new \Imagick($path);
        return $this;
    }

    public function create($width, $height, $background = null) {
        $this->imagick = new \Imagick();
        $this->imagick->newImage($width, $height, $background ?: 'white');
        return $this;
    }

    public function save($path, $quality = 90) {
        $this->imagick->setImageCompressionQuality($quality);
        $this->imagick->writeImage($path);
        return $this;
    }

    public function resize($width, $height) {
        $this->imagick->resizeImage($width, $height, \Imagick::FILTER_LANCZOS, 1);
        return $this;
    }

    public function thumbnail($width, $height) {
        $this->imagick->thumbnailImage($width, $height, true);
        return $this;
    }

    public function crop($width, $height, $x = 0, $y = 0) {
        $this->imagick->cropImage($width, $height, $x, $y);
        return $this;
    }

    public function rotate($angle) {
        $this->imagick->rotateImage('black', $angle);
        return $this;
    }

    public function flip($mode) {
        if ($mode === 'horizontal' || $mode === 'both') $this->imagick->flopImage();
        if ($mode === 'vertical' || $mode === 'both') $this->imagick->flipImage();
        return $this;
    }

    public function filter($type, ...$args) {
        switch ($type) {
            case 'grayscale':  $this->imagick->modulateImage(100, 0, 100); break;
            case 'brightness': $this->imagick->modulateImage($args[0] ?? 100, 100, 100); break;
            case 'blur':       $this->imagick->blurImage($args[0] ?? 5, $args[1] ?? 3); break;
            case 'negate':     $this->imagick->negateImage(false); break;
        }
        return $this;
    }

    public function text($text, $x, $y, array $options = []) {
        $draw = new \ImagickDraw();
        $draw->setFillColor($options['color'] ?? 'black');
        $draw->setFontSize($options['size'] ?? 12);
        if (isset($options['font_file'])) $draw->setFont($options['font_file']);
        
        $this->imagick->annotateImage($draw, $x, $y, $options['angle'] ?? 0, $text);
        return $this;
    }

    public function drawLine($x1, $y1, $x2, $y2, $color) {
        $draw = new \ImagickDraw();
        $draw->setStrokeColor($color);
        $draw->line($x1, $y1, $x2, $y2);
        $this->imagick->drawImage($draw);
        return $this;
    }

    public function drawRectangle($x1, $y1, $x2, $y2, $color, $filled = false) {
        $draw = new \ImagickDraw();
        if ($filled) {
            $draw->setFillColor($color);
        } else {
            $draw->setStrokeColor($color);
            $draw->setFillColor('none');
        }
        $draw->rectangle($x1, $y1, $x2, $y2);
        $this->imagick->drawImage($draw);
        return $this;
    }

    public function composite($source, $x = 0, $y = 0, $opacity = 100) {
        if ($source instanceof ImagickDriver) {
            $this->imagick->compositeImage($source->getImageResource(), \Imagick::COMPOSITE_OVER, $x, $y);
        }
        return $this;
    }

    public function getImageResource() {
        return $this->imagick;
    }
}
