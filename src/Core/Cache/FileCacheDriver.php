<?php
declare(strict_types=1);
namespace Nemesis\Core\Cache;

class FileCacheDriver implements CacheDriver {
    protected $path;

    public function __construct($path) {
        $this->path = $path;
        if (!is_dir($this->path)) mkdir($this->path, 0755, true);
    }

    public function get($key, $default = null) {
        $file = $this->path . '/' . md5($key) . '.cache';
        if (!file_exists($file)) return $default;

        $content = unserialize(file_get_contents($file));
        if (time() > $content['expires_at']) {
            $this->forget($key);
            return $default;
        }

        return $content['data'];
    }

    public function set($key, $value, $seconds = 3600) {
        $file = $this->path . '/' . md5($key) . '.cache';
        $content = [
            'expires_at' => time() + $seconds,
            'data' => $value
        ];
        return file_put_contents($file, serialize($content));
    }

    public function forget($key) {
        $file = $this->path . '/' . md5($key) . '.cache';
        if (file_exists($file)) unlink($file);
    }

    public function clear() {
        $files = glob($this->path . '/*.cache');
        foreach ($files as $file) {
            if (is_file($file)) unlink($file);
        }
    }
}
