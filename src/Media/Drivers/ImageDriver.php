<?php
declare(strict_types=1);

namespace Nemesis\Media\Drivers;

interface ImageDriver {
    public function load($path);
    public function create($width, $height, $background = null);
    public function save($path, $quality = 90);
    public function resize($width, $height);
    public function thumbnail($width, $height);
    public function crop($width, $height, $x = 0, $y = 0);
    public function rotate($angle);
    public function flip($mode); // 'horizontal', 'vertical', 'both'
    public function filter($type, ...$args); // grayscale, brightness, etc.
    public function text($text, $x, $y, array $options = []);
    public function drawLine($x1, $y1, $x2, $y2, $color);
    public function drawRectangle($x1, $y1, $x2, $y2, $color, $filled = false);
    public function composite($source, $x = 0, $y = 0, $opacity = 100);
}
