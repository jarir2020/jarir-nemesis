<?php
// Nemesis 5.0.0 | Phase 13 — Media Library config | Created: 2026-04-03

return [

    /*
    |--------------------------------------------------------------------------
    | Default Disk
    |--------------------------------------------------------------------------
    | The disk used when no disk is specified during upload.
    | Supported: 'local', 's3', 'r2'
    */
    'default' => env('MEDIA_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Storage Disks
    |--------------------------------------------------------------------------
    */
    'disks' => [
        'local' => [
            'driver'      => 'local',
            'upload_dir'  => 'storage/uploads',
            'public_base' => '/storage/uploads',
        ],
        // Uncomment to enable S3:
        // 's3' => [
        //     'driver'  => 's3',
        //     'bucket'  => env('AWS_BUCKET'),
        //     'region'  => env('AWS_DEFAULT_REGION', 'us-east-1'),
        //     'key'     => env('AWS_ACCESS_KEY_ID'),
        //     'secret'  => env('AWS_SECRET_ACCESS_KEY'),
        // ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Allowed MIME Types
    |--------------------------------------------------------------------------
    | Empty array = allow all types.
    */
    'allowed_types' => [
        'image/jpeg', 'image/jpg', 'image/png', 'image/gif',
        'image/webp', 'image/svg+xml',
        'application/pdf',
        'video/mp4', 'video/quicktime',
        'audio/mpeg', 'audio/ogg',
        'application/zip',
    ],

    /*
    |--------------------------------------------------------------------------
    | Maximum Upload Size
    |--------------------------------------------------------------------------
    | In bytes. 0 = no limit. Default: 10 MB.
    */
    'max_size' => (int) env('MEDIA_MAX_SIZE', 10 * 1024 * 1024),

    /*
    |--------------------------------------------------------------------------
    | Thumbnail Sizes
    |--------------------------------------------------------------------------
    | Used by ImageProcessor::thumbnail(). key → [w, h, crop]
    */
    'sizes' => [
        'thumb'  => ['w' => 150,  'h' => 150,  'crop' => true],
        'medium' => ['w' => 300,  'h' => 300,  'crop' => false],
        'large'  => ['w' => 800,  'h' => 600,  'crop' => false],
        'full'   => ['w' => 1920, 'h' => 1080, 'crop' => false],
    ],

    /*
    |--------------------------------------------------------------------------
    | Image Quality
    |--------------------------------------------------------------------------
    | Default quality for JPEG/WebP output (1-100).
    */
    'quality' => (int) env('MEDIA_QUALITY', 85),

    /*
    |--------------------------------------------------------------------------
    | DB Table
    |--------------------------------------------------------------------------
    */
    'table' => 'attachments',

];
