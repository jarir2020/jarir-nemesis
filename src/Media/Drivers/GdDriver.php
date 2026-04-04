<?php
declare(strict_types=1);

namespace Nemesis\Media\Drivers;

class GdDriver implements ImageDriver {
    protected $image;
    protected $width;
    protected $height;
    protected $path;

    public function load($path) {
        if (!file_exists($path)) {
            throw new \Exception("Image file not found: $path");
        }

        $type = exif_imagetype($path);
        switch ($type) {
            case IMAGETYPE_JPEG: $this->image = imagecreatefromjpeg($path); break;
            case IMAGETYPE_PNG:  $this->image = imagecreatefrompng($path); break;
            case IMAGETYPE_GIF:  $this->image = imagecreatefromgif($path); break;
            case IMAGETYPE_WEBP: $this->image = imagecreatefromwebp($path); break;
            default: throw new \Exception("Unsupported image type.");
        }

        $this->path = $path;
        $this->updateDimensions();
        return $this;
    }

    public function create($width, $height, $background = null) {
        $this->image = imagecreatetruecolor($width, $height);
        if ($background) {
            $color = $this->parseColor($background);
            imagefill($this->image, 0, 0, $color);
        }
        $this->updateDimensions();
        return $this;
    }

    public function save($path, $quality = 90) {
        $ext = pathinfo($path, PATHINFO_EXTENSION);
        switch (strtolower($ext)) {
            case 'jpg':
            case 'jpeg': imagejpeg($this->image, $path, $quality); break;
            case 'png':  imagepng($this->image, $path, round(9 - ($quality * 9 / 100))); break;
            case 'gif':  imagegif($this->image, $path); break;
            case 'webp': imagewebp($this->image, $path, $quality); break;
            default: imagejpeg($this->image, $path, $quality); break;
        }
        return $this;
    }

    public function resize($width, $height) {
        $this->image = imagescale($this->image, $width, $height);
        $this->updateDimensions();
        return $this;
    }

    public function thumbnail($width, $height) {
        // Simple aspect-ratio aware resize
        $ratio = $this->width / $this->height;
        if ($width / $height > $ratio) {
            $width = $height * $ratio;
        } else {
            $height = $width / $ratio;
        }
        return $this->resize($width, $height);
    }

    public function crop($width, $height, $x = 0, $y = 0) {
        $this->image = imagecrop($this->image, ['x' => $x, 'y' => $y, 'width' => $width, 'height' => $height]);
        $this->updateDimensions();
        return $this;
    }

    public function rotate($angle) {
        $this->image = imagerotate($this->image, $angle, 0);
        $this->updateDimensions();
        return $this;
    }

    public function flip($mode) {
        $map = [
            'horizontal' => IMG_FLIP_HORIZONTAL,
            'vertical'   => IMG_FLIP_VERTICAL,
            'both'       => IMG_FLIP_BOTH
        ];
        imageflip($this->image, $map[$mode] ?? IMG_FLIP_HORIZONTAL);
        return $this;
    }

    public function filter($type, ...$args) {
        $map = [
            'grayscale'  => IMG_FILTER_GRAYSCALE,
            'brightness' => IMG_FILTER_BRIGHTNESS,
            'contrast'   => IMG_FILTER_CONTRAST,
            'colorize'   => IMG_FILTER_COLORIZE,
            'negate'     => IMG_FILTER_NEGATE,
            'blur'       => IMG_FILTER_GAUSSIAN_BLUR,
        ];
        if (isset($map[$type])) {
            imagefilter($this->image, $map[$type], ...$args);
        }
        return $this;
    }

    public function text($text, $x, $y, array $options = []) {
        $color = $this->parseColor($options['color'] ?? '#000000');
        if (isset($options['font_file'])) {
            imagettftext($this->image, $options['size'] ?? 12, $options['angle'] ?? 0, $x, $y, $color, $options['font_file'], $text);
        } else {
            imagestring($this->image, $options['font'] ?? 5, $x, $y, $text, $color);
        }
        return $this;
    }

    public function drawLine($x1, $y1, $x2, $y2, $color) {
        imageline($this->image, $x1, $y1, $x2, $y2, $this->parseColor($color));
        return $this;
    }

    public function drawRectangle($x1, $y1, $x2, $y2, $color, $filled = false) {
        $col = $this->parseColor($color);
        if ($filled) {
            imagefilledrectangle($this->image, $x1, $y1, $x2, $y2, $col);
        } else {
            imagerectangle($this->image, $x1, $y1, $x2, $y2, $col);
        }
        return $this;
    }

    public function composite($source, $x = 0, $y = 0, $opacity = 100) {
        if ($source instanceof GdDriver) {
            imagecopymerge($this->image, $source->getImageResource(), $x, $y, 0, 0, $source->width, $source->height, $opacity);
        }
        return $this;
    }

    public function getImageResource() {
        return $this->image;
    }

    protected function updateDimensions() {
        $this->width = imagesx($this->image);
        $this->height = imagesy($this->image);
    }

    protected function parseColor($hex) {
        if (strpos($hex, '#') === 0) {
            $hex = substr($hex, 1);
        }
        if (strlen($hex) == 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        return imagecolorallocate($this->image, $r, $g, $b);
    }
}
