# Template Engine

Nemesis ships a Blade-compatible template engine that compiles `.nemesis.php` templates to cached PHP. All standard Blade syntax is supported out of the box.

---

## Basics

Views live in `views/` (or any namespace-registered directory). The extension is `.nemesis.php`; plain `.php` files also work and are served as-is.

```php
// Render a view
return view('welcome', ['name' => 'Alice']);

// From a controller
return $this->render('dashboard', compact('user', 'posts'));
```

Compiled PHP is cached in `storage/framework/views/`. Delete the cache or call `php nemesis view:clear` to recompile.

---

## Expressions

### Escaped output (HTML-safe)
```
{{ $variable }}
{{ $user->name }}
{{ strtoupper($title) }}
```

### Raw / unescaped output
```
{!! $htmlContent !!}
{!! $post->body !!}
```

### Comments (not rendered to HTML)
```
{{-- This is a template comment --}}
```

---

## Control Structures

### Conditionals
```
@if ($user->isAdmin())
    <p>Welcome, admin.</p>
@elseif ($user->isEditor())
    <p>Welcome, editor.</p>
@else
    <p>Welcome, guest.</p>
@endif

@unless ($user->isVerified())
    <p>Please verify your email.</p>
@endunless
```

### Loops
```
@foreach ($posts as $post)
    <article>
        <h2>{{ $post->title }}</h2>
        <p>{{ $post->excerpt }}</p>
    </article>
@endforeach

@for ($i = 0; $i < 5; $i++)
    <span>{{ $i }}</span>
@endfor

@while ($queue->isNotEmpty())
    {{ $queue->pop() }}
@endwhile

@forelse ($comments as $comment)
    <p>{{ $comment->body }}</p>
@empty
    <p>No comments yet.</p>
@endforelse
```

### Switch
```
@switch ($status)
    @case ('active')
        <span class="green">Active</span>
        @break
    @case ('banned')
        <span class="red">Banned</span>
        @break
    @default
        <span>Unknown</span>
@endswitch
```

---

## Layouts & Sections

**Layout file** (`views/layouts/app.nemesis.php`):
```html
<!DOCTYPE html>
<html>
<head>
    <title>@yield('title', 'Nemesis App')</title>
    @yield('head')
</head>
<body>
    @yield('content')
    @yield('scripts')
</body>
</html>
```

**Child view** (`views/pages/home.nemesis.php`):
```
@extends('layouts.app')

@section('title', 'Home Page')

@section('content')
    <h1>Hello, {{ $name }}!</h1>
@endsection

@section('scripts')
    <script src="/js/home.js"></script>
@endsection
```

---

## Partials & Components

```
{{-- Include a partial --}}
@include('partials.navbar')
@include('partials.alert', ['type' => 'success', 'message' => 'Saved!'])

{{-- Include only if the view exists --}}
@includeIf('partials.sidebar')

{{-- Component (renders views/components/card.nemesis.php) --}}
@component('card', ['title' => 'Stats'])
    <p>Body content here</p>
@endcomponent
```

---

## Stacks

Push scripts/styles from child views, yield them in the layout:

```
{{-- In a partial or child view --}}
@push('scripts')
    <script src="/js/charts.js"></script>
@endpush

{{-- In the layout --}}
@stack('scripts')
```

---

## Asset Helpers

```
{{-- Vite (HMR in dev, hashed in prod) --}}
@vite('resources/js/app.js')
@vite(['resources/js/app.js', 'resources/css/app.css'])

{{-- Generic versioned asset --}}
<img src="{{ asset('images/logo.png') }}">

{{-- Webpack Mix --}}
<script src="{{ mix('/js/app.js') }}"></script>
```

---

## Security Helpers

```
{{-- CSRF hidden field --}}
@csrf

{{-- Method spoofing (PUT, PATCH, DELETE in HTML forms) --}}
@method('PUT')
```

---

## PHP Blocks

```
@php
    $formatted = number_format($total / 100, 2);
    $label = $formatted > 100 ? 'Premium' : 'Standard';
@endphp

<p>Plan: {{ $label }} — ${{ $formatted }}</p>
```

---

## View Namespaces

Register a namespace to load views from any directory:

```php
// In a ServiceProvider or bootstrap
\Nemesis\Core\View::addNamespace('admin', base_path('app/Modules/Admin/Views'));
\Nemesis\Core\View::addNamespace('blog',  base_path('plugins/Blog/views'));
```

Usage:

```php
return view('admin::dashboard');
return view('blog::posts.index', compact('posts'));
```

---

## Cache Management

```bash
# Clear compiled view cache
php nemesis view:clear

# Clear all caches (routes + config + views)
php nemesis cache:clear
```

The cache lives in `storage/framework/views/`. Each view is hashed by its path and modification time; outdated files are recompiled automatically.

---

## Custom Directives

Register a custom directive from a ServiceProvider:

```php
use Nemesis\Core\View;

View::directive('money', function (string $expression): string {
    return "<?php echo '$' . number_format({$expression} / 100, 2); ?>";
});
```

Usage in a template:

```
Price: @money($product->getPriceCents())
```
