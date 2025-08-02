# Troubleshooting HTTP 500 Error After Login

## Common Causes and Solutions

### 1. **Database Configuration Issues** ⚠️ MOST COMMON
The default `db.php` file contains placeholder values that MUST be updated.

**Check:**
```bash
# Look at db.php - if you see these values, they need to be changed:
define('DB_NAME', 'your_database_name'); // MUST CHANGE
define('DB_USER', 'your_database_user'); // MUST CHANGE
define('DB_PASS', 'your_database_password'); // MUST CHANGE
```

**Solution:**
1. Edit `db.php` and update with your actual database credentials
2. Get these from your hosting control panel (Hostinger/cPanel)

### 2. **Missing Database Tables**
The system requires specific tables to function.

**Check:**
- Run `check_config.php` in your browser
- Or run `install.php` to set up tables

**Required Tables:**
- users
- projects
- incidents
- uptime_logs
- roles

### 3. **Session Issues**
PHP sessions might not be properly configured.

**Check:**
- Session save path permissions
- PHP session extension enabled

**Solution:**
Add to `.htaccess` or php.ini:
```
session.save_path = "/tmp"
session.auto_start = 0
```

### 4. **File Permission Issues**
Web server needs read access to all PHP files.

**Check:**
```bash
# All PHP files should be readable (644 or 755)
chmod 644 *.php
chmod 755 includes/
chmod 644 includes/*.php
```

### 5. **PHP Version/Extension Issues**
Minimum PHP 7.0 required with specific extensions.

**Required Extensions:**
- PDO
- PDO_MySQL
- Session
- JSON

## Quick Diagnostic Steps

1. **Run Configuration Check:**
   ```
   http://yourdomain.com/project-monitoring-system/check_config.php
   ```

2. **Check Error Logs:**
   - Check your hosting control panel for PHP error logs
   - Enable error display temporarily in `db.php`:
     ```php
     error_reporting(E_ALL);
     ini_set('display_errors', 1);
     ```

3. **Test Database Connection:**
   ```
   http://yourdomain.com/project-monitoring-system/test_db_connection.php
   ```

4. **Verify Installation:**
   ```
   http://yourdomain.com/project-monitoring-system/install.php
   ```

## Step-by-Step Fix Process

1. **First, update database credentials:**
   - Edit `db.php`
   - Replace placeholder values with actual credentials
   - Save the file

2. **Run installation script:**
   - Navigate to `install.php`
   - Follow the setup wizard
   - Create admin account

3. **Test login:**
   - Try logging in with created account
   - If still failing, check error logs

4. **Use Fixed Files (if needed):**
   - Replace `db.php` with `db_fixed.php`
   - Replace `dashboard.php` with `dashboard_fixed.php`
   - These have better error handling

## Emergency Debug Mode

Create a file `debug_mode.php`:
```php
<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

echo "Debug mode enabled. Check error.log file.";
?>
```

## Common Error Messages and Solutions

| Error | Cause | Solution |
|-------|-------|----------|
| "Database connection failed" | Wrong credentials | Update db.php |
| "Table 'users' doesn't exist" | Missing tables | Run install.php |
| "Cannot start session" | Session issues | Check session path |
| "Call to undefined function" | Missing includes | Check file paths |
| "Access denied for user" | DB permissions | Check user privileges |

## Contact Support

If issues persist after trying these solutions:
1. Check web server error logs
2. Verify PHP configuration with phpinfo()
3. Contact your hosting provider for:
   - Database access issues
   - PHP configuration problems
   - File permission restrictions

## Prevention

1. Always update `db.php` before first use
2. Run `install.php` to set up database
3. Keep error logging enabled
4. Regular backups of database
5. Monitor PHP error logs