# Database Testing Setup Guide

## Prerequisites
- MySQL server running
- Database created (e.g., `nemesis_db`)
- `.env` file configured with database credentials

## Step-by-Step Setup

### 1. Verify .env Configuration
Ensure your `.env` file has these settings:
```
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=nemesis_db
DB_USERNAME=root
DB_PASSWORD=your_password
```

### 2. Run Setup Script
This will create tables and insert sample data:
```bash
php setup_test_db.php
```

### 3. Run Tests
Once setup is complete, run the database-dependent tests:

```bash
# Test database relationships
php tests/RelationshipTest.php

# Test pagination
php tests/PaginationTest.php

# Test database enhancements
php tests/DatabaseEnhancementsTest.php
```

## What Gets Created

### Tables
- **users** (5 sample users)
- **posts** (10 sample posts)
- **comments** (8 sample comments)
- **tags** (10 sample tags)
- **post_tag** (pivot table for many-to-many)

### Sample Data Summary
- 5 users (John, Jane, Bob, Alice, Charlie)
- 10 posts (various statuses)
- 8 comments on different posts
- 10 tags (php, laravel, javascript, etc.)
- Multiple post-tag associations

## Manual Setup (Alternative)

If you prefer manual setup:

### Step 1: Create Tables
```bash
mysql -u root -p nemesis_db < database/test_schema.sql
```

### Step 2: Insert Data
```bash
mysql -u root -p nemesis_db < database/test_data.sql
```

## Troubleshooting

### Connection Error
**Error:** "Database configuration is required"
**Solution:** Check `.env` file has correct credentials

### Permission Error
**Error:** "Access denied for user"
**Solution:** Verify MySQL username/password in `.env`

### Foreign Key Constraint Error
**Error:** "Cannot add or update a child row"
**Solution:** Run `test_schema.sql` before `test_data.sql`

## Cleanup

To remove test data and start fresh:
```bash
php setup_test_db.php
```
(The script automatically truncates tables before inserting)

## Expected Test Results

After setup, all tests should **PASS**:
- ✅ Relationship tests (hasMany, belongsTo, belongsToMany)
- ✅ Pagination tests (metadata, navigation)
- ✅ Database enhancement tests (transactions, events, scopes)
