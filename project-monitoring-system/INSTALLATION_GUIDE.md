# Project Monitoring System - Installation Guide

## Overview

The Project Monitoring System includes multiple installation options:
1. **Automatic Setup** - Database tables are created automatically on first access
2. **Web-based Installation Wizard** - Guided setup through install.php
3. **Manual Setup Script** - Run setup_database.php for direct table creation

## Installation Steps

### 1. Upload Files

Upload all files from the `project-monitoring-system` directory to your web server.

### 2. Configure Database Connection

Edit `db.php` and update the database credentials:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'your_database_name');
define('DB_USER', 'your_database_user');
define('DB_PASS', 'your_database_password');
```

### 3. Choose Installation Method

#### Method A: Automatic Setup (Simplest)
Just access any page of your site. The database tables will be created automatically when db.php is loaded.

#### Method B: Manual Setup Script
1. Navigate to `http://your-domain.com/setup_database.php`
2. The script will create all tables and show the status
3. **Delete setup_database.php after completion**

#### Method C: Installation Wizard

1. Navigate to `http://your-domain.com/install.php` in your web browser
2. You'll see the installation wizard with 3 simple steps

### 3. Step 1: Database Configuration

Enter your MySQL database details:
- **Database Host**: Usually `localhost` for most hosting providers
- **Database Name**: The installer will create this database if it doesn't exist
- **Database Username**: Your MySQL username
- **Database Password**: Your MySQL password

Click "Test Connection & Continue" to proceed.

### 4. Step 2: Administrator Account

Create your administrator account:
- **Full Name**: Your name
- **Email Address**: This will be your login email
- **Password**: Choose a strong password (minimum 8 characters)
- **Confirm Password**: Re-enter your password

Click "Create Account & Continue" to proceed.

### 5. Step 3: Installation

Review what will be installed:
- All necessary database tables
- User roles and permissions
- Administrator account
- Configuration files
- Monitoring capabilities

Click "Install Now" to complete the installation.

### 6. Installation Complete

Once installation is complete, you'll see:
- Confirmation message
- Your administrator email
- Security recommendations

Click "Go to Login" to access the system.

## Post-Installation Security

### Important: After successful installation:

1. **Delete or rename `install.php`** to prevent unauthorized access
   ```bash
   rm install.php
   # or
   mv install.php install.php.backup
   ```

2. **Set proper file permissions** on `config.php`:
   ```bash
   chmod 644 config.php
   ```

3. **Configure email settings** in `config.php` for notifications:
   ```php
   // Email settings
   define('SMTP_HOST', 'smtp.gmail.com');
   define('SMTP_PORT', 587);
   define('SMTP_USER', 'your-email@gmail.com');
   define('SMTP_PASS', 'your-app-password');
   ```

## Features Automatically Installed

The installation wizard automatically sets up:

### Database Tables
- Users with role-based access control
- Projects and monitoring data
- Incidents tracking
- HTTP status logs
- Uptime monitoring
- SSL certificate tracking
- Response time metrics
- Cron job monitoring
- Notifications system
- Password reset functionality
- URL limits for customers

### User Roles
- **Admin**: Full system access
- **Customer**: Limited access with URL restrictions

### System Configuration
- Database connection settings
- Site URL and timezone
- Security settings (session lifetime, password reset timeout, etc.)
- Email configuration placeholders

## Troubleshooting

### Database Connection Failed
- Verify your database credentials
- Ensure MySQL user has CREATE DATABASE privileges
- Check if your hosting provider requires a specific database prefix

### Installation Failed
- Check PHP error logs for specific error messages
- Ensure all files were uploaded correctly
- Verify PHP version is 7.0 or higher
- Ensure PDO MySQL extension is enabled

### Can't Access After Installation
- Make sure you're using the correct email and password
- Clear your browser cache and cookies
- Check if `config.php` was created successfully

## System Requirements

- PHP 7.0 or higher
- MySQL 5.6 or higher
- PDO MySQL extension
- Write permissions for the installation directory

## Manual Installation (Alternative)

If the web installer doesn't work, you can still install manually:

1. Create a MySQL database
2. Import the SQL files in this order:
   - `db.sql`
   - `db_update.sql`
   - `db_password_reset.sql`
   - `db_roles_update.sql`
3. Copy `db.php` and update the database credentials
4. Create an admin user manually in the database

## Support

For issues or questions:
1. Check the error logs
2. Review the installation guide
3. Ensure all requirements are met
4. Contact your hosting provider for server-specific issues