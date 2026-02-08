# Nemesis Framework Requirements

**Current Version:** 3.0.0 (Enterprise Edition)

Nemesis has evolved into a modular, plugin-based enterprise framework. To run Nemesis 3.0.0, your server must meet the following requirements.

## Minimum Requirements

- **PHP**: >= 8.1 (8.2+ Recommended)
- **Composer**: >= 2.0
- **Database**: 
  - MySQL 5.7+ or MariaDB 10.3+
  - PostgreSQL 12+ (Supported via PDO)
  - SQLite 3 (For development/testing)

## Required PHP Extensions

Ensure the following extensions are enabled in your `php.ini`:

- `bcmath` (Required for accurate float calculations)
- `ctype`
- `curl` (Required for CloudStorage/S3)
- `dom` (Required for XML parsing)
- `fileinfo` (Required for file uploads validation)
- `json`
- `mbstring` (Required for string manipulation)
- `openssl` (Required for encryption/security)
- `pcre`
- `pdo` (Required for Database abstraction)
- `pdo_mysql` (or your database driver of choice)
- `tokenizer`
- `xml`
- `ziparchive`

## Optional Recommendations

- **Redis**: Recommended for high-performance caching and session storage.
- **Nginx**: Recommended web server (with PHP-FPM).
- **Supervisor**: For managing queue workers (if using queue plugin).

## Version History

| Version | Codename | Key Features |
|---------|----------|--------------|
| **3.0.0** | **Enterprise** | **Plugin System**, **Module System**, Audit Logging, Swagger Integration, Cloud Storage, IDE Helper. |
| 2.0.0 | Core | Basic MVC, Router, ORM (Zero-Dependency Philosophy). |
| 1.0.0 | Genesis | Initial Release. |

## Upgrading from 2.0.0

Due to the introduction of the Plugin System and namespace changes, a direct upgrade requires:
1. Updating `composer.json` dependencies.
2. Running `php nemesis plugin:discover`.
3. Reviewing `config/app.php` (if applicable) for new provider registrations.
