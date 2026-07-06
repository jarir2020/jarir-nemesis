<?php
declare(strict_types=1);

// Nemesis 7.1.0 | Frontend framework routing and build config | Draft

return [
    /*
    |--------------------------------------------------------------------------
    | Default Frontend Target
    |--------------------------------------------------------------------------
    | Used when a route does not declare a framework-specific renderer.
    | Supported values: 'server', 'react', 'vue', 'next', 'ghost', 'alpine', 'nuxt', 'svelte', 'angular', 'preact', 'solid', 'remix', 'astro', 'qwik', 'lit', 'ember', 'sveltekit', 'inertia', 'livewire', 'htmx', 'jquery'
    */
    'default' => 'server',

    /*
    |--------------------------------------------------------------------------
    | Allowed Frameworks
    |--------------------------------------------------------------------------
    | Only frameworks in this allowlist can be selected by routes or middleware.
    */
    'allow' => ['server', 'react', 'vue', 'next', 'ghost', 'alpine', 'nuxt', 'svelte', 'angular', 'preact', 'solid', 'remix', 'astro', 'qwik', 'lit', 'ember', 'sveltekit', 'inertia', 'livewire', 'htmx', 'jquery'],

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
        'nuxt' => [
            'enabled'   => true,
            'entry'     => 'resources/js/nuxt/app.js',
            'views'     => 'resources/views/nuxt',
            'build'     => 'public/build/nuxt',
            'manifest'  => 'public/build/nuxt/manifest.json',
            'middleware'=> 'framework:nuxt',
            'compiler'  => 'vite',
            'fallback'  => false,
        ],
        'svelte' => [
            'enabled'   => true,
            'entry'     => 'resources/js/svelte/app.js',
            'views'     => 'resources/views/svelte',
            'build'     => 'public/build/svelte',
            'manifest'  => 'public/build/svelte/manifest.json',
            'middleware'=> 'framework:svelte',
            'compiler'  => 'vite',
            'fallback'  => false,
        ],
        'angular' => [
            'enabled'   => true,
            'entry'     => 'resources/js/angular/app.js',
            'views'     => 'resources/views/angular',
            'build'     => 'public/build/angular',
            'manifest'  => 'public/build/angular/manifest.json',
            'middleware'=> 'framework:angular',
            'compiler'  => 'webpack',
            'fallback'  => false,
        ],
        'preact' => [
            'enabled'   => true,
            'entry'     => 'resources/js/preact/app.js',
            'views'     => 'resources/views/preact',
            'build'     => 'public/build/preact',
            'manifest'  => 'public/build/preact/manifest.json',
            'middleware'=> 'framework:preact',
            'compiler'  => 'vite',
            'fallback'  => false,
        ],
        'solid' => [
            'enabled'   => true,
            'entry'     => 'resources/js/solid/app.js',
            'views'     => 'resources/views/solid',
            'build'     => 'public/build/solid',
            'manifest'  => 'public/build/solid/manifest.json',
            'middleware'=> 'framework:solid',
            'compiler'  => 'vite',
            'fallback'  => false,
        ],
        'remix' => [
            'enabled'   => true,
            'entry'     => 'resources/js/remix/app.js',
            'views'     => 'resources/views/remix',
            'build'     => 'public/build/remix',
            'manifest'  => 'public/build/remix/manifest.json',
            'middleware'=> 'framework:remix',
            'compiler'  => 'vite',
            'fallback'  => false,
        ],
        'astro' => [
            'enabled'   => true,
            'entry'     => 'resources/js/astro/app.js',
            'views'     => 'resources/views/astro',
            'build'     => 'public/build/astro',
            'manifest'  => 'public/build/astro/manifest.json',
            'middleware'=> 'framework:astro',
            'compiler'  => 'vite',
            'fallback'  => false,
        ],
        'qwik' => [
            'enabled'   => true,
            'entry'     => 'resources/js/qwik/app.js',
            'views'     => 'resources/views/qwik',
            'build'     => 'public/build/qwik',
            'manifest'  => 'public/build/qwik/manifest.json',
            'middleware'=> 'framework:qwik',
            'compiler'  => 'vite',
            'fallback'  => false,
        ],
        'lit' => [
            'enabled'   => true,
            'entry'     => 'resources/js/lit/app.js',
            'views'     => 'resources/views/lit',
            'build'     => 'public/build/lit',
            'manifest'  => 'public/build/lit/manifest.json',
            'middleware'=> 'framework:lit',
            'compiler'  => 'vite',
            'fallback'  => false,
        ],
        'ember' => [
            'enabled'   => true,
            'entry'     => 'resources/js/ember/app.js',
            'views'     => 'resources/views/ember',
            'build'     => 'public/build/ember',
            'manifest'  => 'public/build/ember/manifest.json',
            'middleware'=> 'framework:ember',
            'compiler'  => 'ember-cli',
            'fallback'  => false,
        ],
        'sveltekit' => [
            'enabled'   => true,
            'entry'     => 'resources/js/sveltekit/app.js',
            'views'     => 'resources/views/sveltekit',
            'build'     => 'public/build/sveltekit',
            'manifest'  => 'public/build/sveltekit/manifest.json',
            'middleware'=> 'framework:sveltekit',
            'compiler'  => 'vite',
            'fallback'  => false,
        ],
        'inertia' => [
            'enabled'   => true,
            'entry'     => 'resources/js/inertia/app.js',
            'views'     => 'resources/views/inertia',
            'build'     => 'public/build/inertia',
            'manifest'  => 'public/build/inertia/manifest.json',
            'middleware'=> 'framework:inertia',
            'compiler'  => 'vite',
            'fallback'  => false,
        ],
        'livewire' => [
            'enabled'   => true,
            'entry'     => 'resources/js/livewire/app.js',
            'views'     => 'resources/views/livewire',
            'build'     => 'public/build/livewire',
            'manifest'  => 'public/build/livewire/manifest.json',
            'middleware'=> 'framework:livewire',
            'compiler'  => 'server',
            'fallback'  => false,
        ],
        'htmx' => [
            'enabled'   => true,
            'entry'     => 'resources/js/htmx/app.js',
            'views'     => 'resources/views/htmx',
            'build'     => 'public/build/htmx',
            'manifest'  => 'public/build/htmx/manifest.json',
            'middleware'=> 'framework:htmx',
            'compiler'  => 'server',
            'fallback'  => false,
        ],
        'jquery' => [
            'enabled'   => true,
            'entry'     => 'resources/js/jquery/app.js',
            'views'     => 'resources/views/jquery',
            'build'     => 'public/build/jquery',
            'manifest'  => 'public/build/jquery/manifest.json',
            'middleware'=> 'framework:jquery',
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
