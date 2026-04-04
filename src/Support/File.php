<?php
declare(strict_types=1);

namespace Nemesis\Support;

class File {
    public static function exists($path) {
        return file_exists($path);
    }

    public static function get($path) {
        return file_get_contents($path);
    }

    public static function put($path, $contents, $lock = false) {
        return file_put_contents($path, $contents, $lock ? LOCK_EX : 0);
    }

    public static function delete($paths) {
        foreach ((array) $paths as $path) {
            if (file_exists($path)) {
                unlink($path);
            }
        }
    }

    public static function size($path) {
        return filesize($path);
    }

    public static function extension($path) {
        return pathinfo($path, PATHINFO_EXTENSION);
    }

    public static function mimeType($path) {
        return mime_content_type($path);
    }

    public static function allFiles($directory) {
        $files = [];
        $rii = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory));
        foreach ($rii as $file) {
            if ($file->isDir()) continue;
            $files[] = $file->getPathname();
        }
        return $files;
    }

    public static function copyDirectory($source, $destination) {
        if (!is_dir($destination)) {
            mkdir($destination, 0755, true);
        }
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($iterator as $item) {
            $destPath = $destination . DIRECTORY_SEPARATOR . $iterator->getSubPathName();
            if ($item->isDir()) {
                if (!is_dir($destPath)) mkdir($destPath);
            } else {
                copy($item, $destPath);
            }
        }
    }

    public static function deleteDirectory($directory) {
        if (!is_dir($directory)) return false;
        $files = array_diff(scandir($directory), ['.', '..']);
        foreach ($files as $file) {
            (is_dir("$directory/$file")) ? self::deleteDirectory("$directory/$file") : unlink("$directory/$file");
        }
        return rmdir($directory);
    }

    public static function readCsv($path) {
        $data = [];
        if (($handle = fopen($path, 'r')) !== false) {
            while (($row = fgetcsv($handle, 1000, ',')) !== false) {
                $data[] = $row;
            }
            fclose($handle);
        }
        return $data;
    }

    public static function writeCsv($path, array $data) {
        $fp = fopen($path, 'w');
        foreach ($data as $row) {
            fputcsv($fp, $row);
        }
        fclose($fp);
    }

    // --- Added Utilities ---

    public static function prepend($path, $data) {
        return self::put($path, $data . (self::exists($path) ? self::get($path) : ''));
    }

    public static function append($path, $data) {
        return file_put_contents($path, $data, FILE_APPEND);
    }

    public static function copy($path, $target) {
        return copy($path, $target);
    }

    public static function move($path, $target) {
        return rename($path, $target);
    }

    public static function name($path) {
        return pathinfo($path, PATHINFO_FILENAME);
    }

    public static function dirname($path) {
        return pathinfo($path, PATHINFO_DIRNAME);
    }

    public static function type($path) {
        return filetype($path);
    }

    public static function isDirectory($directory) {
        return is_dir($directory);
    }

    public static function isFile($file) {
        return is_file($file);
    }

    public static function makeDirectory($path, $mode = 0755, $recursive = false) {
        return mkdir($path, $mode, $recursive);
    }

    public static function cleanDirectory($directory) {
        return self::deleteDirectory($directory); // Re-use delete logic or empty it
    }

    public static function chmod($path, $mode = null) {
        return chmod($path, $mode);
    }

    public static function lastModified($path) {
        return filemtime($path);
    }

    // --- Final Parity Additions ---

    public static function basename($path) { return basename($path); }
    public static function chown($path, $user) { return chown($path, $user); }
    public static function touch($path) { return touch($path); }
    public static function isReadable($path) { return is_readable($path); }
    public static function isWritable($path) { return is_writable($path); }
    public static function glob($pattern, $flags = 0) { return glob($pattern, $flags); }
    
    public static function files($directory) { 
        return array_diff(scandir($directory), ['.', '..']);
    }
    
    public static function directories($directory) {
        return glob($directory . '/*', GLOB_ONLYDIR);
    }

    public static function allDirectories($directory) {
        $directories = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($iterator as $item) {
            if ($item->isDir()) $directories[] = $item->getPathname();
        }
        return $directories;
    }

    public static function getLines($path, $start = 0, $length = null) {
        $file = new \SplFileObject($path);
        $file->seek($start);
        $lines = [];
        $count = 0;
        while (!$file->eof() && ($length === null || $count < $length)) {
            $lines[] = $file->current();
            $file->next();
            $count++;
        }
        return $lines;
    }

    public static function findAndReplace($path, $search, $replace) {
        return self::put($path, str_replace($search, $replace, self::get($path)));
    }

    public static function countLines($path) {
        $file = new \SplFileObject($path, 'r');
        $file->seek(PHP_INT_MAX);
        return $file->key() + 1;
    }

    public static function isSymbolicLink($path) { return is_link($path); }
    public static function createSymbolicLink($target, $link) { return symlink($target, $link); }
    public static function fileHash($path, $algo = 'md5') { return hash_file($algo, $path); }
    public static function isEmpty($path) { return filesize($path) === 0; }
    public static function clear($path) { return self::put($path, ''); }
    public static function download($url, $saveTo) { return self::put($saveTo, fopen($url, 'r')); }
    
    public static function search($path, $term) {
        return array_filter(file($path), function($line) use ($term) {
            return strpos($line, $term) !== false;
        });
    }

    // Added: 2026-04-03

    /**
     * Create a directory if it does not already exist.
     * Silent no-op when the path already exists.
     *
     * @param  string $path      Absolute directory path.
     * @param  int    $mode      Unix permission bits (default 0755).
     * @param  bool   $recursive Create intermediate directories.
     */
    public static function ensureDirectoryExists(string $path, int $mode = 0755, bool $recursive = true): void
    {
        if (!is_dir($path)) {
            mkdir($path, $mode, $recursive);
        }
    }

    // Added: 2026-04-03

    /**
     * Read a JSON file and return its decoded contents as an array.
     * Returns [] when the file doesn't exist or is invalid JSON.
     */
    public static function readJson(string $path): array
    {
        if (!file_exists($path)) return [];
        $raw = file_get_contents($path);
        if ($raw === false) return [];
        $data = json_decode($raw, true);
        return (json_last_error() === JSON_ERROR_NONE && is_array($data)) ? $data : [];
    }

    /**
     * Write an array to a JSON file (pretty-printed).
     * Creates parent directories automatically.
     */
    public static function writeJson(string $path, array $data): bool
    {
        self::ensureDirectoryExists(dirname($path));
        $encoded = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if ($encoded === false) return false;
        return file_put_contents($path, $encoded) !== false;
    }

    /**
     * Sort an array of file paths by last-modified time, newest first.
     * Sorts in-place (reference) and also returns the array.
     *
     * @param  string[] $files
     * @return string[]
     */
    public static function sortByMtime(array &$files): array
    {
        usort($files, fn($a, $b) => filemtime($b) - filemtime($a));
        return $files;
    }

    /**
     * Search for files matching a glob pattern.
     * Returns an array of matching paths (empty array on no match).
     *
     * @param  string $pattern  e.g. 'storage/logs/*.log'
     * @return string[]
     */
    public static function searchByPattern(string $pattern): array
    {
        return glob($pattern) ?: [];
    }
}
