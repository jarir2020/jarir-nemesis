<?php
declare(strict_types=1);

namespace Nemesis\Core\Storage;

class ArchiveManager {
    protected $driver;
    protected $path;

    public function __construct($driver, $path) {
        $this->driver = $driver;
        $this->path = $path;
        $this->ensureSpace();
    }

    public function addFile($file, $localName = null) {
        if (!file_exists($file)) {
            throw new \Exception("File not found: $file");
        }
        $this->driver->addFile($file, $localName);
        return $this;
    }

    public function addDirectory($dir, $recursive = true) {
        if (!is_dir($dir)) {
            throw new \Exception("Directory not found: $dir");
        }
        $this->driver->addDirectory($dir, $recursive);
        return $this;
    }

    public function extract($destination) {
        if (!is_dir($destination)) {
            mkdir($destination, 0755, true);
        }
        return $this->driver->extract($destination);
    }

    public function download($deleteAfter = true) {
        $this->driver->close();
        if (!file_exists($this->path)) {
            throw new \Exception("Archive not created.");
        }

        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="'.basename($this->path).'"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($this->path));
        
        readfile($this->path);
        
        if ($deleteAfter) {
            unlink($this->path);
        }
        exit;
    }

    public function save() {
        $this->driver->close();
        return $this->path;
    }

    protected function ensureSpace() {
        $dir = dirname($this->path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        // Minimal threshold check
        if (disk_free_space($dir) < 10 * 1024 * 1024) { // 10MB
            throw new \Exception("Not enough disk space.");
        }
    }
}
