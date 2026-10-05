# Nemesis UI

`jarir/nemesis-ui` is an opt-in component package. It imports the useful part
of Veldora's UI workflow—discoverable metadata plus copy-owned application
templates—without adding a second template compiler or a large catalog to the
Nemesis core.

Install a component into the application:

```bash
php vendor/jarir/nemesis-ui/bin/nemesis-ui ui:list
php vendor/jarir/nemesis-ui/bin/nemesis-ui ui:add badge
```

The default destination is `views/components/badge.blade.php`. The application
owns the copied template and can edit it safely. Use it with Nemesis's existing
component directive:

```php
@component('components.badge', ['label' => 'Active', 'variant' => 'success'])
```

This package deliberately does not enable `<x-...>` tags. Nemesis's existing
compiler remains backward-compatible, and future tag syntax can be introduced
only after dedicated parser, escaping, nested-component, and unknown-component
tests.
