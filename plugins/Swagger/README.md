# Swagger Plugin for Nemesis

Auto-generates OpenAPI documentation for your Nemesis application.

## Features

- **OpenAPI 3.0 Support**: Generates specification compatible with modern tools.
- **Annotation Scanning**: Automatically finds `OpenApi\Attributes` in your `app/` directory.
- **Swagger UI**: Integrated UI to explore and test your API.

## Installation

```bash
php nemesis plugin:enable Swagger
```

## Usage

### Viewing Documentation

Once enabled, navigate to:

- **UI**: `/api/documentation`
- **JSON Spec**: `/api/docs`

### Annotating Code

Add annotations to your Controllers and Models:

```php
use OpenApi\Attributes as OA;

#[OA\Info(title: "My API", version: "1.0.0")]
#[OA\Get(path: "/api/users", summary: "List users")]
#[OA\Response(response: 200, description: "Successful operation")]
public function index() {
    // ...
}
```
