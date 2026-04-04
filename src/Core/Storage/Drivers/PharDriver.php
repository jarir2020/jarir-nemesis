<?php
declare(strict_types=1);

namespace Nemesis\Core\Storage\Drivers;

class PharDriver implements ArchiveDriver {
    protected $phar;
    protected $path;
    protected $format;

    public function __construct($path, $format = 'tar') {
        $this->path = $path;
        $this->format = $format;
        $this->phar = new \PharData($path);
    }

    public function addFile($file, $localName = null) {
        $this->phar->addFile($file, $localName ?: basename($file));
        return $this;
    }

    public function addDirectory($dir, $recursive = true) {
        $this->phar->buildFromDirectory($dir);
        return $this;
    }

    public function extract($destination) {
        return $this->phar->extractTo($destination, null, true);
    }

    public function close() {
        if ($this->format === 'gz') {
            $this->phar->compress(\Phar::GZ);
            unlink($this->path); // Remove original tar if compressed
        } elseif ($this->format === 'bz2') {
            $this->phar->compress(\Phar::BZ2);
            unlink($this->path);
        }
    }
}
