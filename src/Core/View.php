<?php
namespace Nemesis\Core;

class View {
    protected static $namespaces = [];

    /**
     * Add a view namespace.
     *
     * @param string $namespace
     * @param string $path
     */
    public static function addNamespace($namespace, $path) {
        self::$namespaces[$namespace] = rtrim($path, '/\\');
    }

    /**
     * Render a view.
     *
     * @param string $view
     * @param array $data
     * @throws \Exception
     */
    public static function render($view, $data = []) {
        $path = self::getViewPath($view);

        if (!file_exists($path)) {
            throw new \Exception("View [{$view}] not found at [{$path}].");
        }

        extract($data);
        require $path;
    }

    /**
     * Get the actual path to a view file.
     *
     * @param string $view
     * @return string
     */
    protected static function getViewPath($view) {
        if (strpos($view, '::') !== false) {
            list($namespace, $viewName) = explode('::', $view, 2);
            if (isset(self::$namespaces[$namespace])) {
                return self::$namespaces[$namespace] . '/' . str_replace('.', '/', $viewName) . '.php';
            }
        }

        // Default path
        return __DIR__ . "/../../app/Views/" . str_replace('.', '/', $view) . '.php';
    }
}
