<?php
namespace JarirAhmed\File;

class FileManager
{
    public function exists($path)
    {
        return file_exists($path);
    }

    public function get($path)
    {
        return file_get_contents($path);
    }

    public function put($path, $contents, $lock = false)
    {
        return file_put_contents($path, $contents, $lock ? LOCK_EX : 0);
    }

    public function prepend($path, $data)
    {
        $existing = $this->get($path);
        return $this->put($path, $data . $existing);
    }

    public function append($path, $data)
    {
        return file_put_contents($path, $data, FILE_APPEND);
    }

    public function delete($paths)
    {
        foreach ((array) $paths as $path) {
            if (file_exists($path)) {
                unlink($path);
            }
        }
    }

    public function move($path, $target)
    {
        return rename($path, $target);
    }

    public function copy($path, $target)
    {
        return copy($path, $target);
    }

    public function name($path)
    {
        return pathinfo($path, PATHINFO_FILENAME);
    }

    public function basename($path)
    {
        return basename($path);
    }

    public function dirname($path)
    {
        return dirname($path);
    }

    public function extension($path)
    {
        return pathinfo($path, PATHINFO_EXTENSION);
    }

    public function type($path)
    {
        return filetype($path);
    }

    public function mimeType($path)
    {
        return mime_content_type($path);
    }

    public function size($path)
    {
        return filesize($path);
    }

    public function lastModified($path)
    {
        return filemtime($path);
    }

    public function isDirectory($directory)
    {
        return is_dir($directory);
    }

    public function isFile($file)
    {
        return is_file($file);
    }

    public function glob($pattern, $flags = 0)
    {
        return glob($pattern, $flags);
    }

    public function files($directory, $hidden = false)
    {
        $files = scandir($directory);
        return array_diff($files, ['.', '..']);
    }

    public function allFiles($directory, $hidden = false)
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

    public function directories($directory)
    {
        return glob($directory . '/*', GLOB_ONLYDIR);
    }

    public function allDirectories($directory)
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

    public function makeDirectory($path, $mode = 0755, $recursive = false, $force = false)
    {
        return mkdir($path, $mode, $recursive);
    }

    public function deleteDirectory($directory, $preserve = false)
    {
        if ($preserve) {
            return $this->cleanDirectory($directory);
        }
        return rmdir($directory);
    }

    public function cleanDirectory($directory)
    {
        $files = $this->files($directory);
        foreach ($files as $file) {
            unlink($directory . '/' . $file);
        }
    }

    public function touch($path)
    {
        return touch($path);
    }

    public function isReadable($path)
    {
        return is_readable($path);
    }

    public function isWritable($path)
    {
        return is_writable($path);
    }

    public function chmod($path, $permissions)
    {
        return chmod($path, $permissions);
    }

    public function chown($path, $user)
    {
        return chown($path, $user);
    }

    public function getLines($path, $start = 0, $length = null)
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

    public function findAndReplace($path, $search, $replace)
    {
        $content = $this->get($path);
        $updatedContent = str_replace($search, $replace, $content);
        return $this->put($path, $updatedContent);
    }

    public function getFileCreationTime($path)
    {
        return filectime($path);
    }


    public function getFileOwner($path)
    {
        return fileowner($path);
    }

    public function getFilePermissions($path)
    {
        return substr(sprintf('%o', fileperms($path)), -4);
    }

    public function countLines($path)
    {
        $file = new \SplFileObject($path, 'r');
        $file->seek(PHP_INT_MAX);
        return $file->key() + 1;
    }

    public function isSymbolicLink($path)
    {
        return is_link($path);
    }

    public function createSymbolicLink($target, $link)
    {
        return symlink($target, $link);
    }

    public function readFromLine($path, $lineNumber)
    {
        $file = new \SplFileObject($path);
        $file->seek($lineNumber);
        return $file->current();
    }

    public function getFileHash($path, $algorithm = 'md5')
    {
        return hash_file($algorithm, $path);
    }

    public function copyDirectory($source, $destination)
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

    public function moveDirectory($source, $destination)
    {
        $this->copyDirectory($source, $destination);
        $this->deleteDirectory($source);
    }

    public function isEmpty($path)
    {
        return filesize($path) === 0;
    }
    
    public function readCsv($path, $delimiter = ',', $enclosure = '"', $escape = '\\')
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

    public function writeCsv($path, array $data, $delimiter = ',', $enclosure = '"', $escape = '\\')
    {
        $handle = fopen($path, 'w');
        foreach ($data as $row) {
            fputcsv($handle, $row, $delimiter, $enclosure, $escape);
        }
        fclose($handle);
    }

    public function appendToFile($path, $content)
    {
        return file_put_contents($path, $content, FILE_APPEND);
    }

    public function getFileSize($path)
    {
        return filesize($path);
    }

    public function getFileExtension($path)
    {
        return pathinfo($path, PATHINFO_EXTENSION);
    }
        public function clearFile($path)
    {
        return file_put_contents($path, '');
    }

    public function replaceFile($source, $destination)
    {
        $this->moveFile($source, $destination);
    }

    public function readFileToArray($path)
    {
        return file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    }

    public function writeArrayToFile($path, array $data)
    {
        return file_put_contents($path, implode(PHP_EOL, $data));
    }

    public function getFileModificationTime($path)
    {
        return filemtime($path);
    }

    public function getFileInfo($path)
    {
        return pathinfo($path);
    }

    public function createDirectory($path, $permissions = 0755)
    {
        return mkdir($path, $permissions, true);
    }

    public function getDirectoryContents($path)
    {
        return scandir($path);
    }

    public function downloadFile($url, $saveTo)
    {
        return file_put_contents($saveTo, fopen($url, 'r'));
    }

    public function searchInFile($path, $searchTerm)
    {
        $lines = $this->readFileToArray($path);
        return array_filter($lines, function($line) use ($searchTerm) {
            return strpos($line, $searchTerm) !== false;
        });
    }

    public function copyFile($source, $destination)
    {
        return copy($source, $destination);
    }

        
}
