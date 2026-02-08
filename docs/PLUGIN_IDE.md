# IDE Helper Plugin for Nemesis

Generates a helper file for IDEs (like PhpStorm and VSCode) to provide accurate autocompletion for Nemesis framework.

## Features

- **Model Properties**: Automatically documents database columns as properties on Model classes.
- **Relationships**: Adds relationship methods to Model docblocks.
- **Facades**: (Future) Documents Facade methods for better static analysis.

## Installation

```bash
php nemesis plugin:enable IdeHelper
```

## Usage

Run the generate command:

```bash
php nemesis ide-helper:generate
```

This will create a `_ide_helper.php` file in your project root.

> **Note**: Add `_ide_helper.php` to your `.gitignore` file to avoid committing generated code.

## Troubleshooting

If models are not detected, ensure they are located in `app/Models` and extend `Nemesis\Core\Model`.
