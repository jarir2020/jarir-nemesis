# Validation Documentation

## Overview

Nemesis provides a powerful validation system for validating incoming request data. Validation rules are simple, expressive, and prevent invalid data from reaching your application logic.

---

## Basic Validation

### In Controllers

```php
public function store(Request $request) {
    $validated = $request->validate([
        'email' => 'required|email',
        'name' => 'required|min:3',
        'age' => 'required|integer|min:18'
    ]);
    
    // $validated contains only valid data
    User::create($validated);
}
```

### Handling Validation Errors

```php
try {
    $validated = $request->validate($rules);
} catch (\\Nemesis\\Core\\ValidationException $e) {
    return ApiResponse::validationError($e->errors());
}
```

---

## Available Validation Rules

### Required Fields

- `required` - Field must be present and not empty
- `nullable` - Field can be null

### Type Validation

- `string` - Must be a string
- `integer` - Must be an integer
- `numeric` - Must be numeric
- `boolean` - Must be true/false
- `array` - Must be an array
- `email` - Must be valid email format
- `url` - Must be valid URL

### Size Validation

- `min:3` - Minimum length/value
- `max:100` - Maximum length/value
- `between:5,10` - Between min and max
- `size:10` - Exact size

### Pattern Validation

- `regex:/^[A-Z]/` - Must match regex pattern
- `alpha` - Only alphabetic characters
- `alpha_num` - Only alphanumeric characters

### Database Validation

- `unique:table,column` - Must be unique in database
- `exists:table,column` - Must exist in database

### Comparison

- `confirmed` - Must match `{field}_confirmation`
- `same:other_field` - Must match another field
- `different:other_field` - Must be different from another field

---

## Multiple Rules

### Pipe Syntax

```php
$rules = [
    'username' => 'required|alpha_num|min:3|max:20|unique:users,username',
    'password' => 'required|min:8|confirmed',
    'email' => 'required|email|unique:users,email'
];
```

### Array Syntax

```php
$rules = [
    'username' => ['required', 'alpha_num', 'min:3', 'max:20'],
    'email' => ['required', 'email']
];
```

---

## Custom Error Messages

```php
$validator = new \\Nemesis\\Core\\Validator();
$validator->setMessages([
    'email.required' => 'We need your email address!',
    'email.email' => 'Please provide a valid email.',
    'password.min' => 'Password must be at least 8 characters.'
]);

if (!$validator->validate($data, $rules)) {
    return $validator->errors();
}
```

---

## Validation Examples

### User Registration

```php
$rules = [
    'name' => 'required|string|min:2|max:100',
    'email' => 'required|email|unique:users,email',
    'password' => 'required|min:8|confirmed',
    'age' => 'required|integer|min:18',
    'terms' => 'required|boolean'
];

$validated = $request->validate($rules);
```

### API Request

```php
$rules = [
    'title' => 'required|string|max:200',
    'content' => 'required|string',
    'category_id' => 'required|integer|exists:categories,id',
    'tags' => 'array',
    'published' => 'boolean'
];

try {
    $validated = $request->validate($rules);
    return ApiResponse::success($validated);
} catch (ValidationException $e) {
    return ApiResponse::validationError($e->errors());
}
```

---

## Best Practices

1. **Validate early** - Validate at the controller entry point
2. **Use specific rules** - Be explicit about requirements
3. **Whitelist fields** - Only validate and use expected fields
4. **Custom messages** - Provide user-friendly error messages
5. **Database rules** - Use `unique` and `exists` for data integrity
