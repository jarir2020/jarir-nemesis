<?php
namespace Nemesis\Plugins\CloudStorage;

use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use League\Flysystem\AwsS3V3\AwsS3V3Adapter;
use Aws\S3\S3Client;
use Nemesis\Core\Config;

class Storage {
    protected static $disks = [];

    public static function disk($name = null) {
        $name = $name ?: Config::get('filesystems.default', 'local');

        if (!isset(self::$disks[$name])) {
            self::$disks[$name] = self::createFilesystem($name);
        }

        return self::$disks[$name];
    }

    protected static function createFilesystem($name) {
        $config = Config::get("filesystems.disks.{$name}");

        if (!$config) {
            throw new \Exception("Filesystem config not found for disk: {$name}");
        }

        $adapter = null;

        switch ($config['driver']) {
            case 'local':
                $adapter = new LocalFilesystemAdapter($config['root']);
                break;
            case 's3':
                $client = new S3Client([
                    'credentials' => [
                        'key'    => $config['key'],
                        'secret' => $config['secret'],
                    ],
                    'region' => $config['region'],
                    'version' => 'latest',
                    'endpoint' => $config['endpoint'] ?? null,
                ]);
                $adapter = new AwsS3V3Adapter($client, $config['bucket']);
                break;
            default:
                throw new \Exception("Unsupported driver: {$config['driver']}");
        }

        return new Filesystem($adapter);
    }
    
    // Static proxy to default disk
    public static function __callStatic($method, $args) {
        return self::disk()->$method(...$args);
    }
}
