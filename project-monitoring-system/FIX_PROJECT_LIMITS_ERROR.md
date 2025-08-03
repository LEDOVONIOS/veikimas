# Fix for Project Limits Table Error

## Problem
After installation, the script encounters this error:
```
SQLSTATE[42S02]: Base table or view not found: 1146 
project_limits' doesn't exist in 
getUserUrlLimit() #3 {main} thrown in includes/roles.php on line 66
```

## Cause
The `project_limits` table is missing from the database. This table is required by the role management system to track how many projects each user can create.

## Solution

### Option 1: Run the PHP Fix Script (Recommended)
1. Navigate to your project directory
2. Run the fix script:
   ```bash
   php fix_database_error.php
   ```
3. The script will:
   - Check if the table exists
   - Create the missing table if needed
   - Add default project limits for existing users
   - Test the functionality

### Option 2: Run the SQL Script Manually
1. Connect to your MySQL database
2. Run the SQL script:
   ```bash
   mysql -u your_username -p your_database < fix_project_limits_table.sql
   ```

### Option 3: Run SQL Commands Directly
1. Connect to your MySQL database:
   ```bash
   mysql -u your_username -p your_database
   ```
2. Execute the following SQL:
   ```sql
   CREATE TABLE IF NOT EXISTS `project_limits` (
       `id` INT(11) NOT NULL AUTO_INCREMENT,
       `user_id` INT(11) NOT NULL,
       `max_projects` INT(11) NOT NULL DEFAULT 10,
       `set_by_admin_id` INT(11) DEFAULT NULL,
       `limit_message` TEXT DEFAULT NULL,
       `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
       `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
       PRIMARY KEY (`id`),
       UNIQUE KEY `unique_user_limit` (`user_id`),
       KEY `fk_set_by_admin` (`set_by_admin_id`),
       CONSTRAINT `fk_project_limits_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
       CONSTRAINT `fk_project_limits_admin` FOREIGN KEY (`set_by_admin_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
   ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
   ```

## What This Table Does
The `project_limits` table:
- Stores the maximum number of projects each user can create
- Default limit is 10 projects per user
- Administrators can set custom limits for specific users
- Includes optional messages explaining why limits were set

## Prevention
To prevent this issue in future installations:
1. Run the installation script (`install.php`) which should create all tables
2. Or manually run the auto-setup database script
3. Check that all required tables are created after installation

## Related Files
- `includes/roles.php` - Contains functions that use this table
- `includes/auto_setup_database.php` - Contains the table creation logic
- `fix_database_error.php` - Script to fix the issue
- `fix_project_limits_table.sql` - SQL script to create the table