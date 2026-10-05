# Optional UI components

Nemesis keeps visual components outside the framework core in the opt-in
`packages/nemesis-ui` package. The package provides a registry, metadata, and a
safe copy-owned template workflow inspired by Veldora's UI commands.

```bash
php packages/nemesis-ui/bin/nemesis-ui ui:list
php packages/nemesis-ui/bin/nemesis-ui ui:add badge
```

The copied template belongs to the application at
`views/components/badge.blade.php`. Use the existing Nemesis component
directive rather than changing the compiler grammar:

```php
@component('components.badge', ['label' => 'Active', 'variant' => 'success'])
```

Installation validates component names, destination paths, source containment,
and overwrite behavior. Component content uses escaped Nemesis echoes and the
starter badge includes a status role. Future `<x-...>` syntax remains a
separate design decision requiring parser, escaping, nested-slot, unknown-tag,
and accessibility regression tests.
