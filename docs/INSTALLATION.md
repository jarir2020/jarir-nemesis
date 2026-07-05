# Installation Guide

This guide will help you get Nemesis Framework v3.0.0 Enterprise Edition up and running on your server.

> Note: The `php nemesis vendor:compress` maintenance command does not change normal installation steps. If you use it after installing dependencies, run it in dry-run mode first and keep a restore archive.

## Prerequisites

Before installing Nemesis, ensure your server meets the following requirements:

- **PHP**: >= 8.1 (8.2+ Recommended)
- **Composer**: >= 2.0
- **Database**: 
  - MySQL 5.7+ or MariaDB 10.3+
  - PostgreSQL 12+ (Supported via PDO)
  - SQLite 3 (For development/testing)

### Required PHP Extensions
- `bcmath`, `ctype`, `curl`, `dom`, `fileinfo`, `json`, `mbstring`, `openssl`, `pcre`, `pdo`, `pdo_mysql`, `tokenizer`, `xml`, `ziparchive`

---

## Getting Started

### 1. Create a New Project

The easiest way to start a new Nemesis project is via Composer:

```bash
composer create-project jarir/nemesis-framework my-project
```

### 2. Environment Configuration

Copy the `.env.example` to `.env` and configure your settings:

```bash
cp .env.example .env
```

Open `.env` and configure your database and application settings:

```ini
APP_NAME=Nemesis
APP_ENV=local
APP_KEY=base64:your_generated_key
DEBUG=true

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=nemesis_db
DB_USERNAME=root
DB_PASSWORD=
```

### 3. Generate Application Key

Generate a secure application key:

```bash
php nemesis key:generate
```

### 4. Database Setup

Once your database is configured, run the migrations to create the core tables:

```bash
php nemesis migrate
```

---

## Web Server Configuration

### Apache

Nemesis includes a `.htaccess` file in the `public/` directory that handles URL rewriting. Ensure that `mod_rewrite` is enabled.

### Nginx

If you are using Nginx, you can use the following configuration as a starting point:

```nginx
server {
    listen 80;
    server_name example.com;
    root /path/to/project/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;

    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

---

## Documentation Link

For more details, visit the **[Documentation Dashboard](index.html)**.
