<?php
namespace JarirAhmed\File;

class FileManager
{
    public static function exists($path)
    {
        return file_exists($path);
    }

    public static function get($path)
    {
        return file_get_contents($path);
    }

    public static function put($path, $contents, $lock = false)
    {
        return file_put_contents($path, $contents, $lock ? LOCK_EX : 0);
    }

    public static function prepend($path, $data)
    {
        $existing = $this->get($path);
        return $this->put($path, $data . $existing);
    }

    public static function append($path, $data)
    {
        return file_put_contents($path, $data, FILE_APPEND);
    }

    public static function delete($paths)
    {
        foreach ((array) $paths as $path) {
            if (file_exists($path)) {
                unlink($path);
            }
        }
    }

    public static function move($path, $target)
    {
        return rename($path, $target);
    }

    public static function copy($path, $target)
    {
        return copy($path, $target);
    }

    public static function name($path)
    {
        return pathinfo($path, PATHINFO_FILENAME);
    }

    public static function basename($path)
    {
        return basename($path);
    }

    public static function dirname($path)
    {
        return dirname($path);
    }

    public static function extension($path)
    {
        return pathinfo($path, PATHINFO_EXTENSION);
    }

    public static function type($path)
    {
        return filetype($path);
    }

    public static function mimeType($path)
    {
        return mime_content_type($path);
    }

    public static function size($path)
    {
        return filesize($path);
    }

    public static function lastModified($path)
    {
        return filemtime($path);
    }

    public static function isDirectory($directory)
    {
        return is_dir($directory);
    }

    public static function isFile($file)
    {
        return is_file($file);
    }

    public static function glob($pattern, $flags = 0)
    {
        return glob($pattern, $flags);
    }

    public static function files($directory, $hidden = false)
    {
        $files = scandir($directory);
        return array_diff($files, ['.', '..']);
    }

    public static function allFiles($directory, $hidden = false)
    {
        $rii = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory));
        $files = [];
        foreach ($rii as $file) {
            if ($file->isDir()){
                continue;
            }
            $files[] = $file->getPathname();
        }
        return $files;
    }

    public static function directories($directory)
    {
        return glob($directory . '/*', GLOB_ONLYDIR);
    }

    public static function allDirectories($directory)
    {
        $directories = [];
        $rii = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory), \RecursiveIteratorIterator::SELF_FIRST);
        foreach ($rii as $file) {
            if ($file->isDir()) {
                $directories[] = $file->getPathname();
            }
        }
        return $directories;
    }

    public static function makeDirectory($path, $mode = 0755, $recursive = false, $force = false)
    {
        return mkdir($path, $mode, $recursive);
    }

    public static function deleteDirectory($directory, $preserve = false)
    {
        if ($preserve) {
            return $this->cleanDirectory($directory);
        }
        return rmdir($directory);
    }

    public static function cleanDirectory($directory)
    {
        $files = $this->files($directory);
        foreach ($files as $file) {
            unlink($directory . '/' . $file);
        }
    }

    public static function touch($path)
    {
        return touch($path);
    }

    public static function isReadable($path)
    {
        return is_readable($path);
    }

    public static function isWritable($path)
    {
        return is_writable($path);
    }

    public static function chmod($path, $permissions)
    {
        return chmod($path, $permissions);
    }

    public static function chown($path, $user)
    {
        return chown($path, $user);
    }

    public static function getLines($path, $start = 0, $length = null)
    {
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

    public static function findAndReplace($path, $search, $replace)
    {
        $content = $this->get($path);
        $updatedContent = str_replace($search, $replace, $content);
        return $this->put($path, $updatedContent);
    }

    public static function getFileCreationTime($path)
    {
        return filectime($path);
    }


    public static function getFileOwner($path)
    {
        return fileowner($path);
    }

    public static function getFilePermissions($path)
    {
        return substr(sprintf('%o', fileperms($path)), -4);
    }

    public static function countLines($path)
    {
        $file = new \SplFileObject($path, 'r');
        $file->seek(PHP_INT_MAX);
        return $file->key() + 1;
    }

    public static function isSymbolicLink($path)
    {
        return is_link($path);
    }

    public static function createSymbolicLink($target, $link)
    {
        return symlink($target, $link);
    }

    public static function readFromLine($path, $lineNumber)
    {
        $file = new \SplFileObject($path);
        $file->seek($lineNumber);
        return $file->current();
    }

    public static function getFileHash($path, $algorithm = 'md5')
    {
        return hash_file($algorithm, $path);
    }

    public static function copyDirectory($source, $destination)
    {
        $directory = opendir($source);
        mkdir($destination);

        while (($file = readdir($directory)) !== false) {
            if ($file != '.' && $file != '..') {
                $srcFile = $source . '/' . $file;
                $dstFile = $destination . '/' . $file;

                if (is_dir($srcFile)) {
                    $this->copyDirectory($srcFile, $dstFile);
                } else {
                    copy($srcFile, $dstFile);
                }
            }
        }
        closedir($directory);
    }

    public static function moveDirectory($source, $destination)
    {
        $this->copyDirectory($source, $destination);
        $this->deleteDirectory($source);
    }

    public static function isEmpty($path)
    {
        return filesize($path) === 0;
    }
    
    public static function readCsv($path, $delimiter = ',', $enclosure = '"', $escape = '\\')
    {
        $csvData = [];
        if (($handle = fopen($path, 'r')) !== false) {
            while (($data = fgetcsv($handle, 1000, $delimiter, $enclosure, $escape)) !== false) {
                $csvData[] = $data;
            }
            fclose($handle);
        }
        return $csvData;
    }

    public static function writeCsv($path, array $data, $delimiter = ',', $enclosure = '"', $escape = '\\')
    {
        $handle = fopen($path, 'w');
        foreach ($data as $row) {
            fputcsv($handle, $row, $delimiter, $enclosure, $escape);
        }
        fclose($handle);
    }

    public static function appendToFile($path, $content)
    {
        return file_put_contents($path, $content, FILE_APPEND);
    }

    public static function getFileSize($path)
    {
        return filesize($path);
    }

    public static function getFileExtension($path)
    {
        return pathinfo($path, PATHINFO_EXTENSION);
    }
        public static function clearFile($path)
    {
        return file_put_contents($path, '');
    }

    public static function replaceFile($source, $destination)
    {
        $this->moveFile($source, $destination);
    }

    public static function readFileToArray($path)
    {
        return file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    }

    public static function writeArrayToFile($path, array $data)
    {
        return file_put_contents($path, implode(PHP_EOL, $data));
    }

    public static function getFileModificationTime($path)
    {
        return filemtime($path);
    }

    public static function getFileInfo($path)
    {
        return pathinfo($path);
    }

    public static function createDirectory($path, $permissions = 0755)
    {
        return mkdir($path, $permissions, true);
    }

    public static function getDirectoryContents($path)
    {
        return scandir($path);
    }

    public static function downloadFile($url, $saveTo)
    {
        return file_put_contents($saveTo, fopen($url, 'r'));
    }

    public static function searchInFile($path, $searchTerm)
    {
        $lines = $this->readFileToArray($path);
        return array_filter($lines, function($line) use ($searchTerm) {
            return strpos($line, $searchTerm) !== false;
        });
    }

    public static function copyFile($source, $destination)
    {
        return copy($source, $destination);
    }

        
}
