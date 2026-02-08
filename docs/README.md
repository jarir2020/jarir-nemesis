# Nemesis Framework Documentation

Welcome to the Nemesis Framework documentation. This guide will help you understand and use all the features of the framework.

## 📚 Table of Contents

### 🚀 Getting Started
- **[Installation](INSTALLATION.md)** - Setup and configuration
- **[Directory Structure](STRUCTURE.md)** - Application organization
- **[CLI Commands](CLI_COMMANDS.md)** - Command line reference

### 🏗️ Architecture
- **[Module System](MODULES.md)** - Build modular applications
- **[Plugin System](PLUGINS.md)** - Extend the framework
- **[Routing](ROUTING.md)** - URL routing (Web & API)
- **[Middleware](MIDDLEWARE.md)** - Request filtering
- **[Dependency Injection](DEPENDENCY_INJECTION.md)** - Service container
- **[API Standards](API_STANDARDS.md)** - Response standards and CORS

### 💾 Database
- **[Database](DATABASE.md)** - Query builder and basics
- **[Models (ORM)](MODELS.md)** - Eloquent-style ORM
- **[Migrations](MIGRATIONS.md)** - Schema version control

### 🛡️ Security
- **[Authentication](AUTHENTICATION.md)** - User login and management
- **[Authorization](AUTHORIZATION.md)** - Roles and Permissions (RBAC)
- **[Security](SECURITY.md)** - Encryption, CSRF, and Protection

### ⚡ Advanced Features
- **[Queues](QUEUES.md)** - Background job processing
- **[Task Scheduling](SCHEDULING.md)** - Cron jobs and tasks
- **[WebSockets](WEBSOCKETS.md)** - Real-time communication
- **[Multi-Tenancy](MULTI_TENANCY.md)** - SaaS application support
- **[Media & Files](MEDIA.md)** - Uploads, Images, PDF, Excel
- **[Validation](VALIDATION.md)** - Input validation

### 🧪 Development
- **[Testing](TESTING.md)** - Unit and feature testing

---

## Quick Start

### Module System

Create self-contained, feature-based modules:

```bash
# Create a new module
php nemesis make:module Blog

# Module structure is auto-generated
# Routes are auto-discovered
# Views use namespaced syntax: blog::index
```
**[Read Module Documentation →](MODULES.md)**

---

### Plugin System

Extend the framework with plugins:

```bash
# Create a new plugin
php nemesis plugin:create Auth2FA

# Enable a plugin
php nemesis plugin:enable auth2fa
```
**[Read Plugin Documentation →](PLUGINS.md)**

---

### API Standards

Build consistent APIs with standardized responses:

```php
use Nemesis\Http\ApiResponse;

// Success response
ApiResponse::success($data, 'Operation successful');

// Error responses
ApiResponse::notFound('Resource not found');
```
**[Read API Standards Documentation →](API_STANDARDS.md)**

---

## 🏛️ Architecture Overview

```
Nemesis Framework
│
├── Modules (Application Layer)
│   └── Extend your application
│       ├── Blog Module
│       ├── Shop Module
│       └── Forum Module
│
├── Plugins (Framework Layer)
│   └── Extend the framework
│       ├── Auth2FA Plugin
│       ├── Cache Plugin
│       └── Analytics Plugin
│
└── Core Framework
    ├── Routing
    ├── Database (ORM)
    ├── Validation
    ├── Middleware
    └── API Standards
```

---

## Support

For more information, visit the individual documentation files or check the framework source code.

**Framework Version:** 3.0.0  
**PHP Requirement:** >= 8.0
