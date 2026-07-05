<?php
declare(strict_types=1);
namespace Nemesis\Helpers {
    class Helpers {
    /**
     * Returns the hostname.
     */
    public static function host() {
        return $_SERVER['HTTP_HOST'] ?? 'localhost';
    }

    /**
     * Returns the absolute path to the public directory or a subdirectory.
     *
     * @param string $subdirectory Optional subdirectory within the public folder.
     * @return string Absolute path.
     */
    public static function publicPath($subdirectory = '') {
        $basePath = realpath(__DIR__ . '/../../public');
        return $subdirectory ? $basePath . DIRECTORY_SEPARATOR . trim($subdirectory, DIRECTORY_SEPARATOR) : $basePath;
    }

    // Get the absolute path to the storage directory
    public static function storagePath(string $path = ''): string {
        return __DIR__ . '/../../storage/' . ltrim($path, '/');
    }
    
    // Get the absolute path to the root directory
    public static function basePath(string $path = ''): string {
        return __DIR__ . '/../../' . ltrim($path, '/');
    }

    // Generate a full URL for a given path
    public static function url(string $path = ''): string {
        $host = self::host();
        $base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
        return 'http://' . $host . $base . '/' . ltrim($path, '/');
    }
    
    // Redirect to a given URL
    public static function redirect(string $url) {
        header("Location: " . $url);
        exit();
    }

    // Get the current request method (GET, POST, etc.)
    public static function requestMethod(): string {
        return $_SERVER['REQUEST_METHOD'] ?? 'GET';
    }
    
    // Get a specific query parameter from the URL
    public static function query(string $key, string $default = null): ?string {
        return $_GET[$key] ?? $default;
    }
    
    // Get all query parameters
    public static function allQuery(): array {
        return $_GET;
    }
    
    // Get the raw POST data
    public static function rawInput(): string {
        return file_get_contents('php://input');
    }

    // Convert a string to snake_case
    public static function toSnakeCase(string $str): string {
          return strtolower(preg_replace('/[A-Z]/', '_$0', lcfirst($str)));
    }
    
     // Convert a string to camelCase
    public static function toCamelCase(string $str): string {
        return lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $str))));
    }
    
    // Generate a random string of specified length
    public static function randomString(int $length = 16): string {
         return substr(str_shuffle('0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, $length);
    }
    public static function json(array $data, int $statusCode = 200, ?bool $pretty = null): void {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        $options = self::shouldPrettyJson($pretty) ? JSON_PRETTY_PRINT : 0;
        echo json_encode($data, $options);
        exit;
    }

    // Return a plain text response
    public static function text(string $message, int $status = 200): void {
        http_response_code($status);
        header('Content-Type: text/plain');
        echo $message;
        exit();
    }
    // Check if a key exists in an array
    public static function arrayKeyExists($key, array $array): bool {
        return array_key_exists($key, $array);
    }

    // Flatten a multi-dimensional array
    public static function flattenArray(array $array): array {
        $result = [];
        array_walk_recursive($array, function($a) use (&$result) {
            $result[] = $a;
        });
        return $result;
    }
    public static function getInput(): ?array {
        $input = file_get_contents('php://input');
        return json_decode($input, true) ?? null;
    }

    public static function passwordVerify(string $password, string $hashedPassword): bool {
        return password_verify($password, $hashedPassword);
    }

    public static function passwordHash($password) {
        return password_hash($password, PASSWORD_BCRYPT);
    }

    public static function csrfToken() {
        return \Nemesis\Http\Session::token();
    }

    public static function e($value) {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8', false);
    }

    protected static function shouldPrettyJson(?bool $override = null): bool {
        if ($override !== null) {
            return $override;
        }

        if (function_exists('config')) {
            return (bool) \config('api.pretty_json', \config('app.json_pretty', true));
        }

        if (function_exists('env')) {
            return (bool) \env('APP_JSON_PRETTY', true);
        }

        return true;
    }
}
}

namespace {
    if (!function_exists('csrf_token')) {
        function csrf_token() {
            return \Nemesis\Helpers\Helpers::csrfToken();
        }
    }

    if (!function_exists('e')) {
        function e($value) {
            return \Nemesis\Helpers\Helpers::e($value);
        }
    }

    if (!function_exists('base_path')) {
        function base_path($path = '') {
            return \Nemesis\Helpers\Helpers::basePath($path);
        }
    }

    if (!function_exists('view')) {
        function view($view, $data = []) {
            return \Nemesis\Core\View::render($view, $data);
        }
    }

    if (!function_exists('env')) {
        function env($key, $default = null) {
            $value = getenv($key);

            if ($value === false) {
                return $default;
            }

            switch (strtolower($value)) {
                case 'true':
                case '(true)':
                    return true;
                case 'false':
                case '(false)':
                    return false;
                case 'empty':
                case '(empty)':
                    return '';
                case 'null':
                case '(null)':
                    return null;
            }

            if (preg_match('/\A([\'"])(.*)\1\z/', $value, $matches)) {
                return $matches[2];
            }

            return $value;
        }
    }

    // --- Nemesis 4.0.0 helpers | Added: 2026-04-02 ---

    if (!function_exists('app')) {
        /**
         * Resolve a class from the service container, or return the container itself.
         */
        function app(string $class = null): mixed {
            $container = \Nemesis\Core\Container::getInstance();
            return $class === null ? $container : $container->make($class);
        }
    }

    if (!function_exists('config')) {
        /**
         * Get a configuration value by dot-notation key.
         */
        function config(string $key, mixed $default = null): mixed {
            return \Nemesis\Core\Config::get($key, $default);
        }
    }

    if (!function_exists('route')) {
        /**
         * Generate a URL for a named route.
         * NOTE: Returns '#' stub until Phase 4 wires the Router.
         */
        function route(string $name, array $params = []): string {
            if (class_exists(\Nemesis\Router\Router::class)) {
                try {
                    return \Nemesis\Router\Router::getInstance()->generate($name, $params);
                } catch (\Throwable) {}
            }
            return '#';
        }
    }

    if (!function_exists('abort')) {
        /**
         * Throw an HTTP exception, stopping execution.
         * abort(404), abort(403, 'Access denied')
         */
        function abort(int $code, string $message = ''): never {
            $map = [
                401 => \Nemesis\Exceptions\UnauthorizedException::class,
                403 => \Nemesis\Exceptions\ForbiddenException::class,
                404 => \Nemesis\Exceptions\NotFoundException::class,
                405 => \Nemesis\Exceptions\MethodNotAllowedException::class,
                429 => \Nemesis\Exceptions\TooManyRequestsException::class,
            ];
            if (isset($map[$code])) {
                $class = $map[$code];
                throw $message ? new $class($message) : new $class();
            }
            // Generic HttpException — must pass code first, then message
            throw new \Nemesis\Exceptions\HttpException($code, $message);
        }
    }

    if (!function_exists('abort_if')) {
        /**
         * Abort if the given condition is true.
         */
        function abort_if(bool $condition, int $code, string $message = ''): void {
            if ($condition) abort($code, $message);
        }
    }

    if (!function_exists('abort_unless')) {
        /**
         * Abort unless the given condition is true.
         */
        function abort_unless(bool $condition, int $code, string $message = ''): void {
            if (!$condition) abort($code, $message);
        }
    }

    if (!function_exists('now')) {
        /**
         * Return the current date/time in the app timezone (APP_TIMEZONE).
         * Pass a timezone string to override: now('Asia/Dhaka')
         */
        function now(string $timezone = null): \DateTime {
            $tz = new \DateTimeZone($timezone ?? (getenv('APP_TIMEZONE') ?: 'UTC'));
            return new \DateTime('now', $tz);
        }
    }

    if (!function_exists('collect')) {
        /**
         * Wrap an array in a Nemesis Collection.
         */
        function collect(array $items = []): \Nemesis\Support\Collection {
            return new \Nemesis\Support\Collection($items);
        }
    }

    if (!function_exists('dd')) {
        /**
         * Dump one or more variables and die.
         */
        function dd(mixed ...$vars): never {
            foreach ($vars as $var) {
                var_dump($var);
            }
            die(1);
        }
    }

    if (!function_exists('dump')) {
        /**
         * Dump one or more variables without stopping execution.
         */
        function dump(mixed ...$vars): void {
            foreach ($vars as $var) {
                var_dump($var);
            }
        }
    }

    if (!function_exists('flash')) {
        /**
         * Store a flash message in the session (read-once).
         */
        function flash(string $key, mixed $value): void {
            \Nemesis\Http\Session::flash($key, $value);
        }
    }

    if (!function_exists('old')) {
        /**
         * Retrieve old form input from the previous request.
         */
        function old(string $key, mixed $default = null): mixed {
            return \Nemesis\Http\Session::getOldInput($key, $default);
        }
    }

    if (!function_exists('class_basename')) {
        /**
         * Get the class "basename" (short name without namespace).
         * Moved here from Model.php to be universally available.
         */
        function class_basename(string|object $class): string {
            $class = is_object($class) ? get_class($class) : $class;
            return basename(str_replace('\\', '/', $class));
        }
    }

    // -------------------------------------------------------------------------
    // i18n helpers — added Phase 9 (2026-04-03)
    // -------------------------------------------------------------------------

    if (!function_exists('__')) {
        /**
         * Translate a language key.
         *
         * @param  string               $key     e.g. 'app.welcome'
         * @param  array<string,mixed>  $replace Placeholder replacements
         * @param  string|null          $locale  Override locale for this call
         */
        function __(string $key, array $replace = [], ?string $locale = null): string {
            return \Nemesis\I18n\Language::get($key, $replace, $locale);
        }
    }

    if (!function_exists('trans')) {
        /**
         * Alias of __() — translate a language key.
         */
        function trans(string $key, array $replace = [], ?string $locale = null): string {
            return \Nemesis\I18n\Language::get($key, $replace, $locale);
        }
    }

    if (!function_exists('trans_choice')) {
        /**
         * Translate a key with pluralisation.
         * Language line format: 'item|items'  or  '{0} no items|{1} one item|[2,*] :count items'
         */
        function trans_choice(string $key, int $count, array $replace = [], ?string $locale = null): string {
            $line = \Nemesis\I18n\Language::get($key, $replace, $locale);
            $replace['count'] = $count;
            // Simple pipe-separated plural: 'singular|plural'
            if (str_contains($line, '|')) {
                $parts = explode('|', $line);
                $line  = $count === 1 ? ($parts[0] ?? $line) : ($parts[1] ?? $line);
            }
            return \Nemesis\I18n\Language::get($line === $key ? $key : $line, $replace, $locale);
        }
    }

    if (!function_exists('app_locale')) {
        /**
         * Get or set the application locale.
         */
        function app_locale(?string $locale = null): string {
            if ($locale !== null) {
                \Nemesis\I18n\Language::setLocale($locale);
            }
            return \Nemesis\I18n\Language::getLocale();
        }
    }

    // -------------------------------------------------------------------------
    // Machine-translation helper — Phase 10 fine-tune (2026-04-03)
    // -------------------------------------------------------------------------

    if (!function_exists('translate')) {
        /**
         * Translate text via the unofficial Google Translate API.
         *
         * translate('Hello', 'bn')        → 'হ্যালো'
         * translate('Hello', 'fr', 'en')  → 'Bonjour'
         * translate('مرحبا', 'en', 'auto') → 'Hello'
         *
         * @param  string $text    Text to translate.
         * @param  string $target  Target language BCP-47 code (e.g. 'bn', 'ar', 'fr').
         * @param  string $source  Source language code, or 'auto' (default).
         * @return string          Translated text, or original on network failure.
         */
        function translate(string $text, string $target, string $source = 'auto'): string {
            return \Nemesis\I18n\Translator::translate($text, $target, $source);
        }
    }

    // -------------------------------------------------------------------------
    // Asset / Frontend helpers — Phase 10 (2026-04-03)
    // -------------------------------------------------------------------------

    if (!function_exists('asset')) {
        /**
         * Resolve a public asset to a versioned URL.
         * Falls back to plain /path?v=mtime when no manifest is loaded.
         *
         * asset('js/app.js')       → '/build/assets/app-3a7b92f.js'
         * asset('images/logo.png') → '/images/logo.png?v=1712100000'
         */
        function asset(string $path): string {
            return \Nemesis\Assets\AssetManager::url($path);
        }
    }

    if (!function_exists('vite')) {
        /**
         * Emit full Vite <script type="module"> + <link> tags for an entry point.
         * In dev mode (HMR), injects @vite/client automatically.
         *
         * Usage in templates:  <?= vite('resources/js/app.js') ?>
         */
        function vite(string $path): string {
            return \Nemesis\Assets\AssetManager::viteTags($path);
        }
    }

    if (!function_exists('mix')) {
        /**
         * Resolve a Webpack Mix versioned URL.
         *
         * mix('/js/app.js') → '/js/app.js?id=3a7b92f'
         */
        function mix(string $path): string {
            return \Nemesis\Assets\AssetManager::mix($path);
        }
    }

    if (!function_exists('asset_tags')) {
        /**
         * Emit driver-appropriate HTML tags (<script> / <link>) for an asset.
         * Works with both Vite and Webpack; falls back to plain tags.
         */
        function asset_tags(string $path): string {
            return \Nemesis\Assets\AssetManager::tags($path);
        }
    }

    // -------------------------------------------------------------------------
    // Hook & Filter helpers — Phase 12 CMS (2026-04-03)
    // -------------------------------------------------------------------------

    if (!function_exists('addHook')) {
        /**
         * Register a callback for a named action hook.
         * addHook('nemesis.boot', fn() => ..., priority: 10)
         */
        function addHook(string $hook, callable $callback, int $priority = 10): void {
            \Nemesis\Hooks\HookDispatcher::addAction($hook, $callback, $priority);
        }
    }

    if (!function_exists('removeHook')) {
        /**
         * Remove a previously registered action callback.
         */
        function removeHook(string $hook, callable $callback, int $priority = 10): void {
            \Nemesis\Hooks\HookDispatcher::removeAction($hook, $callback, $priority);
        }
    }

    if (!function_exists('doHook')) {
        /**
         * Fire all callbacks registered for a named action hook.
         * doHook('nemesis.boot')
         */
        function doHook(string $hook, mixed ...$args): void {
            \Nemesis\Hooks\HookDispatcher::doAction($hook, ...$args);
        }
    }

    if (!function_exists('addFilter')) {
        /**
         * Register a callback for a named filter hook.
         * addFilter('post.title', fn(string $t) => strtoupper($t))
         */
        function addFilter(string $hook, callable $callback, int $priority = 10): void {
            \Nemesis\Hooks\HookDispatcher::addFilter($hook, $callback, $priority);
        }
    }

    if (!function_exists('removeFilter')) {
        /**
         * Remove a previously registered filter callback.
         */
        function removeFilter(string $hook, callable $callback, int $priority = 10): void {
            \Nemesis\Hooks\HookDispatcher::removeFilter($hook, $callback, $priority);
        }
    }

    if (!function_exists('applyFilters')) {
        /**
         * Pass a value through all callbacks registered for a filter hook.
         * $title = applyFilters('post.title', $rawTitle)
         */
        function applyFilters(string $hook, mixed $value, mixed ...$args): mixed {
            return \Nemesis\Hooks\HookDispatcher::applyFilters($hook, $value, ...$args);
        }
    }

    if (!function_exists('menu')) {
        /**
         * Retrieve a registered Menu instance, or render it as HTML.
         * menu('main')         → Menu instance
         * menu('main', true)   → rendered <ul>...</ul> string
         */
        function menu(string $name, bool $render = false): \Nemesis\CMS\Menu|string|null {
            $m = \Nemesis\CMS\Menu::get($name);
            if ($m === null) return $render ? '' : null;
            return $render ? $m->render() : $m;
        }
    }
}
