<?php
declare(strict_types=1);

namespace Nemesis\UI;

use InvalidArgumentException;

final class ComponentRegistry
{
    /** @var array<string, ComponentDefinition> */
    private array $definitions = [];

    /** @param list<ComponentDefinition> $definitions */
    public function __construct(array $definitions = [])
    {
        foreach ($definitions as $definition) {
            $this->register($definition);
        }
    }

    public static function defaults(): self
    {
        return new self([
            new ComponentDefinition(
                'badge',
                'A compact status label with escaped content.',
                'badge.blade.php',
                "@component('components.badge', ['label' => 'Active', 'variant' => 'success'])",
            ),
        ]);
    }

    public function register(ComponentDefinition $definition): void
    {
        if (isset($this->definitions[$definition->name])) {
            throw new InvalidArgumentException("Component [{$definition->name}] is already registered.");
        }
        $this->definitions[$definition->name] = $definition;
    }

    public function has(string $name): bool
    {
        return isset($this->definitions[$name]);
    }

    public function get(string $name): ComponentDefinition
    {
        if (!$this->has($name)) {
            throw new InvalidArgumentException("Component [{$name}] is not registered.");
        }
        return $this->definitions[$name];
    }

    /** @return list<ComponentDefinition> */
    public function all(): array
    {
        $definitions = array_values($this->definitions);
        usort($definitions, fn(ComponentDefinition $a, ComponentDefinition $b) => $a->name <=> $b->name);
        return $definitions;
    }
}
