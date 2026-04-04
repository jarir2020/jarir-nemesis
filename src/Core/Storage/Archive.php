<?php
declare(strict_types=1);

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
        if (!file_exists($source)) {
            throw new \Exception("Archive file not found: $source");
        }
        
        $ext = pathinfo($source, PATHINFO_EXTENSION);
        
        if ($ext === 'zip') {
            $zip = new \ZipArchive();
            if ($zip->open($source) === true) {
                $zip->extractTo($destination);
                $zip->close();
                return true;
            }
            throw new \Exception("Failed to open ZIP archive for extraction");
        } else {
            // For Phar formats
            $phar = new \PharData($source);
            $phar->extractTo($destination, null, true);
            return true;
        }
    }
}
