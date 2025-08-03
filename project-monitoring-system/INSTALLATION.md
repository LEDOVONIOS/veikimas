# Project Monitoring System - Installation Guide

## Table of Contents
1. [System Requirements](#system-requirements)
2. [Quick Installation](#quick-installation)
3. [Manual Installation](#manual-installation)
4. [Creating Administrator Account](#creating-administrator-account)
5. [Configuration](#configuration)
6. [Setting Up Monitoring](#setting-up-monitoring)
7. [User Management](#user-management)
8. [Troubleshooting](#troubleshooting)

## System Requirements

- **PHP**: 7.4 or higher
- **MySQL**: 5.7 or higher
- **Web Server**: Apache/Nginx with mod_rewrite enabled
- **PHP Extensions**: 
  - PDO MySQL
  - cURL
  - JSON
  - OpenSSL
  - mbstring

## Quick Installation

1. **Upload Files**
   - Upload all files to your web server
   - Ensure the project is accessible via your domain

2. **Run Installation Wizard**
   - Navigate to: `http://your-domain.com/project-monitoring-system/install.php`
   - Follow the installation wizard:
     - Step 1: Enter database credentials
     - Step 2: Import database schema
     - Step 3: Create administrator account
     - Step 4: Complete installation

3. **Security Steps**
   - Delete `install.php` after installation
   - Set proper file permissions:
     ```bash
     chmod 644 config.php
     chmod 755 scripts/
     ```

## Manual Installation

If you prefer manual installation or the wizard fails:

1. **Create Database**
   ```sql
   CREATE DATABASE project_monitoring CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

2. **Import Database Schema**
   - Open phpMyAdmin
   - Select your database
   - Import `db_complete.sql`

3. **Configure Database Connection**
   - Copy `config.php.template` to `config.php`
   - Edit `config.php` with your database credentials:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'your_database_name');
   define('DB_USER', 'your_database_user');
   define('DB_PASS', 'your_database_password');
   ```

4. **Create Admin Account Manually**
   
   First, generate a password hash using PHP:
   ```php
   <?php
   echo password_hash('your_desired_password', PASSWORD_DEFAULT);
   ?>
   ```
   
   Then insert the admin user:
   ```sql
   INSERT INTO users (full_name, email, password_hash, role_id) 
   VALUES ('Admin Name', 'admin@email.com', '$2y$10$hash_from_above', 1);
   ```

## Creating Administrator Account

### During Installation
The installation wizard will create your first administrator account automatically.

### After Installation
Since user registration is disabled by default, new users must be created by administrators.

**Option 1: Through Admin Panel**
1. Login as administrator
2. Navigate to Admin → User Management
3. Click "Add New User"
4. Fill in user details and assign role

**Option 2: Direct Database Insert**
```sql
-- For admin user (role_id = 1)
INSERT INTO users (full_name, email, password_hash, role_id) 
VALUES ('User Name', 'user@email.com', '$2y$10$...', 1);

-- For regular user (role_id = 2)
INSERT INTO users (full_name, email, password_hash, role_id) 
VALUES ('User Name', 'user@email.com', '$2y$10$...', 2);
```

**Option 3: Using Admin CLI Script**
```bash
php scripts/make_admin.php "Full Name" "email@example.com" "password"
```

## Configuration

### Basic Configuration
Edit `config.php` after installation:

```php
// Site Settings
define('SITE_NAME', 'Project Monitoring System');
define('SITE_URL', 'http://your-domain.com/project-monitoring-system');

// Email Configuration (for notifications)
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'your-email@gmail.com');
define('SMTP_PASS', 'your-email-password');

// Monitoring Settings
define('CHECK_INTERVAL', 300); // 5 minutes
define('TIMEOUT_SECONDS', 10);
```

### Advanced Settings
- **Session Lifetime**: Adjust `SESSION_LIFETIME` for security
- **Debug Mode**: Set `DEBUG_MODE` to `true` for development
- **Timezone**: Update `date_default_timezone_set()` to your timezone

## Setting Up Monitoring

### Automatic Monitoring (Cron Job)

1. **Linux/Unix Cron Setup**
   ```bash
   # Edit crontab
   crontab -e
   
   # Add this line to run monitoring every 5 minutes
   */5 * * * * /usr/bin/php /path/to/project-monitoring-system/scripts/monitor_projects.php
   ```

2. **cPanel Cron Job**
   - Login to cPanel
   - Navigate to "Cron Jobs"
   - Add new cron job:
     - Command: `/usr/bin/php /home/username/public_html/project-monitoring-system/scripts/monitor_projects.php`
     - Schedule: Every 5 minutes

3. **Windows Task Scheduler**
   - Create new task
   - Trigger: Every 5 minutes
   - Action: `C:\php\php.exe C:\path\to\scripts\monitor_projects.php`

### Manual Monitoring
You can also run monitoring manually:
```bash
php scripts/monitor_projects.php
```

## User Management

### User Roles
The system has two default roles:

1. **Admin**
   - Full system access
   - User management
   - View all projects
   - System configuration

2. **User**
   - Create/manage own projects
   - View own projects only
   - Limited to project quota

### Setting Project Limits
Admins can set project limits for users:
```sql
INSERT INTO project_limits (user_id, max_projects, set_by_admin_id) 
VALUES (2, 20, 1);
```

## Troubleshooting

### Common Issues

1. **"Database connection failed"**
   - Check database credentials in `config.php`
   - Ensure MySQL service is running
   - Verify database user permissions

2. **"404 Not Found" errors**
   - Enable mod_rewrite in Apache
   - Check `.htaccess` file exists
   - Verify URL structure in config

3. **"Registration is disabled"**
   - This is intentional for security
   - Create users as admin or via database

4. **Monitoring not working**
   - Check cron job is set up correctly
   - Verify PHP CLI path in cron command
   - Check scripts/monitor_projects.php permissions

5. **Email notifications not working**
   - Configure SMTP settings in `config.php`
   - Check firewall allows SMTP port
   - Verify email credentials

### Debug Mode
Enable debug mode for detailed error messages:
```php
define('DEBUG_MODE', true);
```

### Logs
Check these locations for errors:
- PHP error log: `/var/log/php_errors.log`
- Apache error log: `/var/log/apache2/error.log`
- Application logs: Check database notifications table

## Security Recommendations

1. **File Permissions**
   ```bash
   chmod 644 config.php
   chmod 755 -R scripts/
   chmod 755 -R includes/
   ```

2. **Remove Installation Files**
   ```bash
   rm install.php
   rm db_complete.sql
   ```

3. **Secure Admin Access**
   - Use strong passwords
   - Consider IP whitelisting
   - Enable HTTPS

4. **Regular Updates**
   - Keep PHP updated
   - Update dependencies
   - Apply security patches

## Support

For issues or questions:
1. Check this documentation
2. Review error logs
3. Test in debug mode
4. Contact your system administrator

---

**Version**: 2.0  
**Last Updated**: 2024  
**License**: MIT