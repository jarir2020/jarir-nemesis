<?php
declare(strict_types=1);

namespace Nemesis\Core\Storage\Drivers;

interface ArchiveDriver {
    public function addFile($file, $localName = null);
    public function addDirectory($dir, $recursive = true);
    public function extract($destination);
    public function close();
}
