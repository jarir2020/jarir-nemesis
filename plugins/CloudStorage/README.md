# CloudStorage Plugin for Nemesis

A comprehensive file storage abstraction for Nemesis, supporting local disk and Amazon S3.

## Features

- **Unified API**: Switch between local and cloud storage without changing application code.
- **Flysystem Integration**: Built on top of `league/flysystem`.
- **S3 Support**: Helper methods for S3 configuration.
- **`Storage` Facade**: Easy access to file operations.

## Installation

```bash
php nemesis plugin:enable CloudStorage
```

## Configuration

Configure your filesystems in `config/filesystems.php`.

```php
'default' => env('FILESYSTEM_DRIVER', 'local'),

'disks' => [
    'local' => [
        'driver' => 'local',
        'root' => storage_path('app'),
    ],
    's3' => [
        'driver' => 's3',
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION'),
        'bucket' => env('AWS_BUCKET'),
    ],
],
```

## Usage

```php
use Nemesis\Plugins\CloudStorage\Storage;

// Store a file
Storage::put('avatars/1.jpg', $fileContents);

// Retrieve a file
$contents = Storage::get('avatars/1.jpg');

// Check existence
if (Storage::exists('avatars/1.jpg')) {
    // ...
}

// Delete a file
Storage::delete('avatars/1.jpg');

// Public URL (S3)
$url = Storage::url('avatars/1.jpg');
```
