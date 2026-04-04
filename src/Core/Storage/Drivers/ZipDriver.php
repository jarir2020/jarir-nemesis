<?php
declare(strict_types=1);

namespace Nemesis\Core\Storage\Drivers;

class ZipDriver implements ArchiveDriver {
    protected $zip;
    protected $path;

    public function __construct($path) {
        if (!class_exists('\ZipArchive')) {
            throw new \Exception("ZipArchive extension is not enabled.");
        }
        $this->path = $path;
        $this->zip = new \ZipArchive();
        if ($this->zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \Exception("Could not open ZIP file: $path");
        }
    }

    public function addFile($file, $localName = null) {
        $this->zip->addFile($file, $localName ?: basename($file));
        return $this;
    }

    public function addDirectory($dir, $recursive = true) {
        $files = $recursive ? 
            new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS), \RecursiveIteratorIterator::LEAVES_ONLY) :
            new \DirectoryIterator($dir);

        foreach ($files as $file) {
            if (!$file->isDir()) {
                $filePath = $file->getRealPath();
                $dirPath = realpath($dir);
                $relativePath = substr($filePath, strlen($dirPath) + 1);
                $this->zip->addFile($filePath, $relativePath);
            }
        }
        return $this;
    }

    public function extract($destination) {
        return $this->zip->extractTo($destination);
    }

    public function close() {
        $this->zip->close();
    }
}
