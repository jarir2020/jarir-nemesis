<?php
declare(strict_types=1);

namespace Nemesis\UI;

use InvalidArgumentException;
use RuntimeException;

final class ComponentInstaller
{
    public function __construct(
        private ComponentRegistry $registry,
        private string $packageRoot,
        private string $applicationRoot,
    ) {
        $this->packageRoot = rtrim($this->packageRoot, '/\\');
        $this->applicationRoot = rtrim($this->applicationRoot, '/\\');
    }

    public function install(string $name, string $target = 'views/components', bool $force = false): string
    {
        $definition = $this->registry->get($name);
        $target = trim(str_replace('\\', '/', $target), '/');
        if ($target === '' || str_contains($target, '..') || str_starts_with($target, '/')) {
            throw new InvalidArgumentException('The component destination must be a safe relative path.');
        }

        $source = $definition->sourcePath($this->packageRoot);
        $destination = $this->applicationRoot . DIRECTORY_SEPARATOR
            . str_replace('/', DIRECTORY_SEPARATOR, $target)
            . DIRECTORY_SEPARATOR . $definition->name . '.blade.php';

        if (file_exists($destination) && !$force) {
            throw new RuntimeException("Component already exists: {$destination}");
        }

        $directory = dirname($destination);
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new RuntimeException("Unable to create component directory: {$directory}");
        }
        if (!copy($source, $destination)) {
            throw new RuntimeException("Unable to install component [{$name}].");
        }

        return $destination;
    }
}
