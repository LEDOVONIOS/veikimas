/*
 * PROJECT MONITORING SYSTEM
 * Deployment Instructions for Hostinger Shared Hosting
 * 
 * OVERVIEW:
 * This is a lightweight Project Monitoring System built with PHP and MySQL,
 * designed specifically for shared hosting environments like Hostinger.
 * 
 * FEATURES:
 * - User registration and authentication
 * - Project management (add, view, list projects)
 * - Incident tracking with status management
 * - Responsive design for mobile and desktop
 * - Secure implementation with prepared statements
 * 
 * SYSTEM REQUIREMENTS:
 * - PHP 7.0 or higher
 * - MySQL 5.6 or higher
 * - Apache/LiteSpeed web server
 * 
 * DEPLOYMENT STEPS:
 * 
 * 1. DATABASE SETUP:
 *    a) Log into your Hostinger control panel
 *    b) Navigate to "MySQL Databases"
 *    c) Create a new database (note the database name)
 *    d) Create a database user with a strong password
 *    e) Add the user to the database with ALL PRIVILEGES
 *    f) Open phpMyAdmin from the control panel
 *    g) Select your new database
 *    h) Click on the "SQL" tab
 *    i) Copy and paste the entire contents of db.sql
 *    j) Click "Go" to create the tables
 * 
 * 2. FILE UPLOAD:
 *    a) Use File Manager or FTP to access your public_html directory
 *    b) Create a new folder for your project (e.g., "project-monitor")
 *    c) Upload all files maintaining the directory structure:
 *       - index.php
 *       - register.php
 *       - login.php
 *       - logout.php
 *       - dashboard.php
 *       - add_project.php
 *       - project.php
 *       - add_incident.php
 *       - db.php
 *       - assets/css/style.css
 * 
 * 3. CONFIGURATION:
 *    a) Edit db.php file
 *    b) Update the following constants with your database details:
 *       - DB_HOST: Usually 'localhost' (check Hostinger docs)
 *       - DB_NAME: Your database name from step 1c
 *       - DB_USER: Your database username from step 1d
 *       - DB_PASS: Your database password from step 1d
 * 
 * 4. SECURITY RECOMMENDATIONS:
 *    a) Move db.php outside public_html if possible:
 *       - Create a folder above public_html (e.g., "includes")
 *       - Move db.php there
 *       - Update all require_once paths to: require_once '../includes/db.php';
 *    b) Set proper file permissions:
 *       - PHP files: 644
 *       - Directories: 755
 *    c) Enable SSL certificate (usually free with Hostinger)
 *    d) Add .htaccess file for additional security (optional)
 * 
 * 5. TESTING:
 *    a) Navigate to your-domain.com/project-monitor/
 *    b) You should be redirected to the login page
 *    c) Click "Register" to create your first account
 *    d) After registration, log in with your credentials
 *    e) Test all features: add project, add incident, view details
 * 
 * 6. TROUBLESHOOTING:
 *    - If you see database connection errors:
 *      * Double-check your database credentials in db.php
 *      * Ensure the database and tables were created
 *      * Check if your hosting IP needs to be whitelisted
 *    
 *    - If pages don't load properly:
 *      * Check PHP version (must be 7.0+)
 *      * Verify all files were uploaded
 *      * Check file permissions
 *    
 *    - If CSS doesn't load:
 *      * Check the path to assets/css/style.css
 *      * Clear browser cache
 * 
 * 7. MAINTENANCE:
 *    - Regular backups: Use Hostinger's backup feature
 *    - Monitor database size: Clean old incidents periodically
 *    - Update PHP version when available
 *    - Check error logs in Hostinger control panel
 * 
 * SUPPORT:
 * For Hostinger-specific issues, contact their support team.
 * For application issues, check the code comments and error messages.
 * 
 * CREDITS:
 * Built with PHP, MySQL, and vanilla CSS/JS for maximum compatibility
 * with shared hosting environments.
 */