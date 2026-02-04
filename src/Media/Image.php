<?php

namespace Nemesis\Media;

class Image {
    protected static $driver;

    public static function load($path) {
        return self::getDriver()->load($path);
    }

    public static function create($width, $height, $background = null) {
        return self::getDriver()->create($width, $height, $background);
    }

    protected static function getDriver() {
        if (!self::$driver) {
            $driverName = getenv('IMAGE_DRIVER') ?: (extension_loaded('imagick') ? 'imagick' : 'gd');
            
            if ($driverName === 'imagick' && extension_loaded('imagick')) {
                self::$driver = new \Nemesis\Media\Drivers\ImagickDriver();
            } else {
                self::$driver = new \Nemesis\Media\Drivers\GdDriver();
            }
        }
        return self::$driver;
    }
}
