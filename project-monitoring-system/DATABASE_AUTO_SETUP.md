# Automatic Database Setup

## Overview

The Project Monitoring System now includes automatic database table creation. No manual SQL imports are required - all tables are created automatically when needed.

## How It Works

1. **Automatic Detection**: When you access any page, the system checks if all required tables exist
2. **Automatic Creation**: If tables are missing, they are created automatically
3. **Transaction Safety**: All table creation is wrapped in a database transaction for safety
4. **Error Handling**: Any errors are logged and the system provides clear error messages

## Required Tables

The following tables are automatically created:

### Core Tables
- `users` - User accounts and authentication
- `roles` - User roles (Admin/User)
- `projects` - Projects being monitored
- `project_limits` - Per-user project limits
- `incidents` - Incident tracking

### Monitoring Tables
- `http_status_logs` - HTTP status code history
- `uptime_logs` - Uptime monitoring data
- `ssl_certificates` - SSL certificate information
- `response_times` - Response time metrics
- `cron_jobs` - Cron job monitoring

### System Tables
- `notifications` - User notifications
- `notification_settings` - Notification preferences
- `password_resets` - Password reset tokens
- `admin_project_access_log` - Admin access audit trail

### Database View
- `user_project_count` - View for counting user projects vs limits

## Setup Options

### Option 1: Automatic (Default)
Simply configure your database credentials in `db.php` and access any page. Tables will be created automatically.

### Option 2: Manual Setup Script
Run `setup_database.php` from your browser to manually trigger table creation and see detailed status.

### Option 3: Installation Wizard
Use `install.php` for a guided setup process that includes creating an admin account.

## Troubleshooting

### Tables Not Creating
1. Check database credentials in `db.php`
2. Ensure MySQL user has CREATE TABLE permissions
3. Check PHP error logs for detailed error messages

### Permission Errors
Your MySQL user needs these permissions:
- CREATE
- ALTER
- INSERT
- SELECT
- UPDATE
- DELETE
- CREATE VIEW

### Foreign Key Errors
Tables are created in the correct order to handle foreign key dependencies. If you get foreign key errors, it usually means some tables already exist. Drop all tables and let the system recreate them.

## Security Notes

1. The setup process creates two default roles: 'Admin' and 'User'
2. The first registered user automatically becomes an Admin
3. Delete `setup_database.php` after initial setup
4. The automatic setup only runs if tables don't exist - it won't modify existing tables

## Manual SQL Import

If automatic setup fails, you can manually import tables using the SQL files:
1. `db.sql` - Basic tables
2. `db_update.sql` - Monitoring tables  
3. `db_roles_update.sql` - Role system
4. `db_role_enhancement.sql` - Enhanced roles
5. `db_password_reset.sql` - Password reset

However, the automatic setup is recommended as it handles all dependencies correctly.