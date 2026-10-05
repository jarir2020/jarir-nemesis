<?php
declare(strict_types=1);

namespace Nemesis\UI;

use InvalidArgumentException;
use RuntimeException;

final class ComponentDefinition
{
    public readonly string $name;
    public readonly string $description;
    public readonly string $template;
    public readonly string $usage;

    public function __construct(
        string $name,
        string $description,
        string $template,
        string $usage,
    ) {
        $name = trim($name);
        $template = trim(str_replace('\\', '/', $template));
        if (!preg_match('/^[a-z][a-z0-9-]*$/D', $name)) {
            throw new InvalidArgumentException('Component names must be lowercase slugs.');
        }
        if ($template === '' || str_contains($template, '..') || str_starts_with($template, '/')) {
            throw new InvalidArgumentException('Component templates must be safe relative paths.');
        }

        $this->name = $name;
        $this->description = trim($description);
        $this->template = $template;
        $this->usage = trim($usage);
    }

    public function sourcePath(string $packageRoot): string
    {
        $resourceRoot = realpath(rtrim($packageRoot, '/\\') . '/resources/components');
        $source = realpath(($resourceRoot ?: '') . '/' . $this->template);
        if ($resourceRoot === false || $source === false || !is_file($source)) {
            throw new RuntimeException("Component template [{$this->name}] is unavailable.");
        }

        $resourceRoot = rtrim($resourceRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        if (!str_starts_with($source, $resourceRoot)) {
            throw new RuntimeException('Component template resolves outside the package resources.');
        }

        return $source;
    }
}
