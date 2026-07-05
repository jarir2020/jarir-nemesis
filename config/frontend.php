<?php
declare(strict_types=1);

// Nemesis 7.0.0 | Frontend framework routing and build config | Draft

return [
    /*
    |--------------------------------------------------------------------------
    | Default Frontend Target
    |--------------------------------------------------------------------------
    | Used when a route does not declare a framework-specific renderer.
    | Supported values: 'server', 'react', 'vue', 'next', 'ghost', 'alpine'
    */
    'default' => 'server',

    /*
    |--------------------------------------------------------------------------
    | Allowed Frameworks
    |--------------------------------------------------------------------------
    | Only frameworks in this allowlist can be selected by routes or middleware.
    */
    'allow' => ['server', 'react', 'vue', 'next', 'ghost', 'alpine'],

    /*
    |--------------------------------------------------------------------------
    | Framework Definitions
    |--------------------------------------------------------------------------
    | Each framework has isolated source, view, build, and manifest roots.
    */
    'frameworks' => [
        'server' => [
            'enabled'  => true,
            'entry'    => null,
            'views'    => 'views',
            'build'    => null,
            'manifest' => null,
            'middleware' => null,
            'compiler' => 'server',
            'fallback' => true,
        ],
        'react' => [
            'enabled'   => true,
            'entry'     => 'resources/js/react/app.js',
            'views'     => 'resources/views/react',
            'build'     => 'public/build/react',
            'manifest'  => 'public/build/react/manifest.json',
            'middleware'=> 'framework:react',
            'compiler'  => 'vite',
            'fallback'  => false,
        ],
        'vue' => [
            'enabled'   => true,
            'entry'     => 'resources/js/vue/app.js',
            'views'     => 'resources/views/vue',
            'build'     => 'public/build/vue',
            'manifest'  => 'public/build/vue/manifest.json',
            'middleware'=> 'framework:vue',
            'compiler'  => 'vite',
            'fallback'  => false,
        ],
        'next' => [
            'enabled'   => true,
            'entry'     => 'resources/js/next/app.js',
            'views'     => 'resources/views/next',
            'build'     => 'public/build/next',
            'manifest'  => 'public/build/next/manifest.json',
            'middleware'=> 'framework:next',
            'compiler'  => 'webpack',
            'fallback'  => false,
        ],
        'ghost' => [
            'enabled'   => true,
            'entry'     => 'resources/js/ghost/app.js',
            'views'     => 'resources/views/ghost',
            'build'     => 'public/build/ghost',
            'manifest'  => 'public/build/ghost/manifest.json',
            'middleware'=> 'framework:ghost',
            'compiler'  => 'vite',
            'fallback'  => false,
        ],
        'alpine' => [
            'enabled'   => true,
            'entry'     => 'resources/js/alpine/app.js',
            'views'     => 'resources/views/alpine',
            'build'     => 'public/build/alpine',
            'manifest'  => 'public/build/alpine/manifest.json',
            'middleware'=> 'framework:alpine',
            'compiler'  => 'vite',
            'fallback'  => false,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Runtime Isolation
    |--------------------------------------------------------------------------
    | Build/dev/watch modes must never share cache or manifest paths.
    */
    'runtime' => [
        'build_dir'   => 'dist/build',
        'dev_dir'     => 'dist/dev',
        'watch_dir'   => 'dist/watch',
        'cache_root'  => '.ghost/cache',
        'manifest_root' => '.ghost/manifests',
    ],
];
