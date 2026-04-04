<?php
// Nemesis 5.0.0 | Phase 10 — Frontend Asset Pipeline Config | Added: 2026-04-03

return [

    /*
    |--------------------------------------------------------------------------
    | Build Driver
    |--------------------------------------------------------------------------
    | Supported: "vite", "webpack", "none"
    | "none" = serve files directly from public/ with no bundler
    */
    'driver' => env('ASSET_DRIVER', 'vite'),

    /*
    |--------------------------------------------------------------------------
    | Public Output Directory
    |--------------------------------------------------------------------------
    | Where the bundler writes compiled assets. Relative to project root.
    */
    'output' => env('ASSET_OUTPUT', 'public/build'),

    /*
    |--------------------------------------------------------------------------
    | Resource Root
    |--------------------------------------------------------------------------
    | Where source JS / CSS / images live before compilation.
    */
    'resources' => env('ASSET_RESOURCES', 'resources'),

    /*
    |--------------------------------------------------------------------------
    | Vite Settings
    |--------------------------------------------------------------------------
    */
    'vite' => [
        // Path to the Vite manifest (relative to project root)
        'manifest'  => env('VITE_MANIFEST', 'public/build/.vite/manifest.json'),
        // Dev server URL (used during `npm run dev`)
        'dev_url'   => env('VITE_DEV_URL', 'http://localhost:5173'),
        // Whether dev server is currently running (auto-detected when null)
        'hot'       => env('VITE_HOT', null),
        // Hot-reload file written by Vite dev server
        'hot_file'  => 'storage/framework/vite.hot',
    ],

    /*
    |--------------------------------------------------------------------------
    | Webpack / Mix Settings
    |--------------------------------------------------------------------------
    */
    'webpack' => [
        // Path to the webpack mix manifest
        'manifest' => env('MIX_MANIFEST', 'public/mix-manifest.json'),
        // webpack-dev-server URL
        'dev_url'  => env('MIX_DEV_URL', 'http://localhost:8080'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Entry Points (used by vite:init / webpack:init scaffolding)
    |--------------------------------------------------------------------------
    */
    'entries' => [
        'js'  => 'resources/js/app.js',
        'css' => 'resources/css/app.css',
    ],

    /*
    |--------------------------------------------------------------------------
    | Asset Version / Cache Busting
    |--------------------------------------------------------------------------
    | Set to a string to append as ?v={version} when no manifest is found.
    | Null = use file mtime as fallback.
    */
    'version' => env('ASSET_VERSION', null),

];
