<?php
declare(strict_types=1);

namespace Nemesis\Frontend;

/**
 * Frontend framework context and path resolver.
 *
 * Keeps per-request frontend routing state separate from the generic view
 * engine so controllers, middleware, and templates can agree on which
 * framework owns the current request.
 */
class FrontendManager
{
    private static ?self $instance = null;

    private array $config = [];
    private ?string $currentFramework = null;
    private array $currentContext = [];

    public function __construct(array $config = [])
    {
        $this->config = $config ?: $this->loadDefaultConfig();
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public static function boot(array $config): self
    {
        self::$instance = new self($config);
        return self::$instance;
    }

    public static function flush(): void
    {
        self::$instance = null;
    }

    public function config(): array
    {
        return $this->config;
    }

    public function defaultFramework(): string
    {
        $default = $this->config['default'] ?? 'server';
        return $this->normalizeFramework((string) $default);
    }

    public function allowedFrameworks(): array
    {
        $allowed = $this->config['allow'] ?? [];
        return array_values(array_map([$this, 'normalizeFramework'], (array) $allowed));
    }

    /**
     * Return every framework declared in the frontend config.
     *
     * @return list<string>
     */
    public function supportedFrameworks(): array
    {
        $frameworks = $this->config['frameworks'] ?? [];
        if (!is_array($frameworks)) {
            return [];
        }

        return array_values(array_map([$this, 'normalizeFramework'], array_keys($frameworks)));
    }

    public function isAllowed(string $framework): bool
    {
        return in_array($this->normalizeFramework($framework), $this->allowedFrameworks(), true);
    }

    public function supportsFramework(string $framework): bool
    {
        $framework = $this->normalizeFramework($framework);
        return in_array($framework, $this->supportedFrameworks(), true);
    }

    public function isEnabled(string $framework): bool
    {
        $config = $this->frameworkConfig($framework);
        return (bool) ($config['enabled'] ?? false);
    }

    public function frameworkConfig(string $framework): array
    {
        $framework = $this->normalizeFramework($framework);
        $frameworks = $this->config['frameworks'] ?? [];

        return is_array($frameworks) && isset($frameworks[$framework]) && is_array($frameworks[$framework])
            ? $frameworks[$framework]
            : [];
    }

    public function setCurrentFramework(?string $framework, array $context = []): void
    {
        if ($framework === null || $framework === '') {
            $this->clearCurrentFramework();
            return;
        }

        $framework = $this->normalizeFramework($framework);

        if (!$this->isAllowed($framework)) {
            throw new \InvalidArgumentException("Frontend framework [{$framework}] is not allowed.");
        }

        if (!$this->isEnabled($framework)) {
            throw new \InvalidArgumentException("Frontend framework [{$framework}] is disabled.");
        }

        $this->currentFramework = $framework;
        $this->currentContext   = $context;
    }

    public function clearCurrentFramework(): void
    {
        $this->currentFramework = null;
        $this->currentContext    = [];
    }

    public function currentFramework(): ?string
    {
        return $this->currentFramework;
    }

    public function currentContext(): array
    {
        return $this->currentContext;
    }

    public function currentFrameworkConfig(): array
    {
        if ($this->currentFramework === null) {
            return [];
        }

        return $this->frameworkConfig($this->currentFramework);
    }

    public function currentViewPath(): ?string
    {
        if ($this->currentFramework === null) {
            return null;
        }

        return $this->frameworkViewPath($this->currentFramework);
    }

    public function currentEntry(): ?string
    {
        if ($this->currentFramework === null) {
            return null;
        }

        return $this->frameworkEntry($this->currentFramework);
    }

    public function currentBuildPath(): ?string
    {
        if ($this->currentFramework === null) {
            return null;
        }

        return $this->frameworkBuildPath($this->currentFramework);
    }

    public function currentManifestPath(): ?string
    {
        if ($this->currentFramework === null) {
            return null;
        }

        return $this->frameworkManifestPath($this->currentFramework);
    }

    public function currentCompiler(): ?string
    {
        if ($this->currentFramework === null) {
            return null;
        }

        return $this->frameworkCompiler($this->currentFramework);
    }

    public function frameworkViewPath(string $framework): ?string
    {
        return $this->resolvePath($this->frameworkConfig($framework)['views'] ?? null);
    }

    public function frameworkEntry(string $framework): ?string
    {
        return $this->resolvePath($this->frameworkConfig($framework)['entry'] ?? null);
    }

    public function frameworkBuildPath(string $framework): ?string
    {
        return $this->resolvePath($this->frameworkConfig($framework)['build'] ?? null);
    }

    public function frameworkManifestPath(string $framework): ?string
    {
        return $this->resolvePath($this->frameworkConfig($framework)['manifest'] ?? null);
    }

    public function frameworkCompiler(string $framework): ?string
    {
        $value = $this->frameworkConfig($framework)['compiler'] ?? null;
        return $value === null ? null : (string) $value;
    }

    public function frameworkMiddleware(string $framework): ?string
    {
        $value = $this->frameworkConfig($framework)['middleware'] ?? null;
        return $value === null ? null : (string) $value;
    }

    public function frameworkFallbackAllowed(string $framework): bool
    {
        return (bool) ($this->frameworkConfig($framework)['fallback'] ?? false);
    }

    public function runtimePath(string $key): ?string
    {
        return $this->resolvePath($this->config['runtime'][$key] ?? null);
    }

    public function normalizeFramework(string $framework): string
    {
        return strtolower(trim($framework));
    }

    protected function loadDefaultConfig(): array
    {
        $configPath = dirname(__DIR__, 2) . '/config/frontend.php';
        if (function_exists('config')) {
            $loaded = config('frontend');
            if (is_array($loaded) && !empty($loaded)) {
                return $loaded;
            }
        }

        if (file_exists($configPath)) {
            $loaded = require $configPath;
            return is_array($loaded) ? $loaded : [];
        }

        return [];
    }

    protected function resolvePath(mixed $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        $path = (string) $path;
        if ($this->isAbsolutePath($path)) {
            return rtrim($path, '/\\');
        }

        return rtrim(dirname(__DIR__, 2) . '/' . ltrim($path, '/'), '/\\');
    }

    protected function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, DIRECTORY_SEPARATOR)
            || preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) === 1;
    }
}
