<?php
declare(strict_types=1);

// Nemesis 4.0.0 | Phase 7 — Events | Created: 2026-04-03

namespace Nemesis\Events;

use Nemesis\Contracts\EventInterface;
use Nemesis\Contracts\ListenerInterface;

/**
 * EventDispatcher — typed application event bus.
 *
 * Static facade backed by a singleton instance.
 *
 * Usage:
 *   // Register a listener (class-string or callable)
 *   EventDispatcher::listen(UserRegistered::class, SendWelcomeEmail::class);
 *   EventDispatcher::listen(UserRegistered::class, fn(UserRegistered $e) => ...);
 *
 *   // Dispatch an event
 *   EventDispatcher::dispatch(new UserRegistered($user));
 *
 *   // Auto-discover from app/Listeners/ (map in EventServiceProvider)
 *   EventDispatcher::discover(base_path('app/Listeners'));
 */
class EventDispatcher
{
    /** @var array<class-string, list<callable|class-string>> */
    private array $listeners = [];

    private static ?self $instance = null;

    // -------------------------------------------------------------------------
    // Singleton
    // -------------------------------------------------------------------------

    public static function getInstance(): static
    {
        if (static::$instance === null) {
            static::$instance = new static();
        }
        return static::$instance;
    }

    /** Replace the singleton (useful in tests via EventFake). */
    public static function setInstance(?self $instance): void
    {
        static::$instance = $instance;
    }

    // -------------------------------------------------------------------------
    // Static facade
    // -------------------------------------------------------------------------

    /**
     * Register a listener for an event class.
     *
     * @param  class-string          $eventClass
     * @param  callable|class-string $listener
     */
    public static function listen(string $eventClass, callable|string $listener): void
    {
        static::getInstance()->addListener($eventClass, $listener);
    }

    /**
     * Dispatch an event to all registered listeners.
     * Returns the (possibly mutated) event.
     */
    public static function dispatch(object $event): object
    {
        return static::getInstance()->fire($event);
    }

    /**
     * Remove all listeners for an event class (or all listeners if null).
     */
    public static function forget(?string $eventClass = null): void
    {
        $d = static::getInstance();
        if ($eventClass === null) {
            $d->listeners = [];
        } else {
            unset($d->listeners[$eventClass]);
        }
    }

    /**
     * Check whether any listeners are registered for an event class.
     */
    public static function hasListeners(string $eventClass): bool
    {
        $listeners = static::getInstance()->listeners[$eventClass] ?? [];
        return count($listeners) > 0;
    }

    /**
     * Auto-discover listener classes from a directory.
     * Expects files named NameListener.php with a single class implementing ListenerInterface.
     * The class must type-hint the event in its handle() method — discovery maps the type-hint.
     *
     * @param  string $directory  e.g. base_path('app/Listeners')
     */
    public static function discover(string $directory): void
    {
        if (!is_dir($directory)) return;

        foreach (glob($directory . '/*.php') as $file) {
            require_once $file;

            $className = static::classFromFile($file);
            if ($className === null) continue;
            if (!class_exists($className)) continue;

            $ref = new \ReflectionClass($className);
            if (!$ref->implementsInterface(ListenerInterface::class)) continue;
            if ($ref->isAbstract()) continue;

            // Reflect on handle(EventType $e) to get the event class
            try {
                $method = $ref->getMethod('handle');
                $params = $method->getParameters();
                if (empty($params)) continue;

                $type = $params[0]->getType();
                if (!($type instanceof \ReflectionNamedType) || $type->isBuiltin()) continue;

                $eventClass = $type->getName();
                static::listen($eventClass, $className);
            } catch (\ReflectionException) {
                // skip malformed listeners
            }
        }
    }

    // -------------------------------------------------------------------------
    // Instance methods
    // -------------------------------------------------------------------------

    public function addListener(string $eventClass, callable|string $listener): void
    {
        $this->listeners[$eventClass][] = $listener;
    }

    public function fire(object $event): object
    {
        $eventClass = $event::class;

        // Walk up the class hierarchy so parent-event listeners also fire
        $classes = array_merge([$eventClass], class_parents($event), class_implements($event));

        foreach ($classes as $class) {
            foreach ($this->listeners[$class] ?? [] as $listener) {
                if ($event instanceof Event && $event->isPropagationStopped()) break 2;

                $resolved = $this->resolveListener($listener);
                $resolved($event);
            }
        }

        return $event;
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Resolve a listener: class-string → ListenerInterface instance callable;
     *                     callable → returned as-is.
     */
    private function resolveListener(callable|string $listener): callable
    {
        if (is_string($listener) && class_exists($listener)) {
            $instance = new $listener();
            if ($instance instanceof ListenerInterface) {
                return fn(object $e) => $instance->handle($e);
            }
            // Fallback: __invoke
            if (method_exists($instance, '__invoke')) {
                return $instance;
            }
        }
        // Already a callable
        return $listener; // @phpstan-ignore-line
    }

    /**
     * Attempt to extract a fully-qualified class name from a PHP file.
     * Reads the namespace + class declaration (no eval).
     */
    private static function classFromFile(string $file): ?string
    {
        $content  = file_get_contents($file);
        $ns       = '';
        $class    = '';

        if (preg_match('/^namespace\s+([\w\\\\]+)\s*;/m', $content, $m)) {
            $ns = $m[1] . '\\';
        }
        if (preg_match('/^class\s+(\w+)/m', $content, $m)) {
            $class = $m[1];
        }

        return $class !== '' ? $ns . $class : null;
    }
}
