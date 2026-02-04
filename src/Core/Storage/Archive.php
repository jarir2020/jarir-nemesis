<?php

namespace Nemesis\Core\Storage;

class Archive {
    public static function zip($path) {
        return new ArchiveManager(new \Nemesis\Core\Storage\Drivers\ZipDriver($path), $path);
    }

    public static function tar($path) {
        return new ArchiveManager(new \Nemesis\Core\Storage\Drivers\PharDriver($path, 'tar'), $path);
    }

    public static function gz($path) {
        return new ArchiveManager(new \Nemesis\Core\Storage\Drivers\PharDriver($path, 'gz'), $path);
    }

    public static function bz2($path) {
        return new ArchiveManager(new \Nemesis\Core\Storage\Drivers\PharDriver($path, 'bz2'), $path);
    }

    public static function extract($source, $destination) {
        $ext = pathinfo($source, PATHINFO_EXTENSION);
        $driver = null;
        
        if ($ext === 'zip') {
            $driver = new \Nemesis\Core\Storage\Drivers\ZipDriver($source);
        } else {
            $driver = new \Nemesis\Core\Storage\Drivers\PharDriver($source);
        }
        
        return $driver->extract($destination);
    }
}
