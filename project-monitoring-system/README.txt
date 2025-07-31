/*
 * PROJECT MONITORING SYSTEM - ENHANCED VERSION
 * Deployment Instructions for Hostinger Shared Hosting
 * 
 * OVERVIEW:
 * This is a comprehensive Project Monitoring System built with PHP and MySQL,
 * designed specifically for shared hosting environments like Hostinger.
 * 
 * FEATURES:
 * - User registration and authentication
 * - Project management (add, view, list projects)
 * - Incident tracking with status management
 * - HTTP status code monitoring and visualization
 * - Uptime statistics (7 days, 30 days, 365 days)
 * - SSL certificate and domain expiry tracking
 * - Response time monitoring with charts
 * - Time-filtered incident views
 * - Responsive design for mobile and desktop
 * - Secure implementation with prepared statements
 * 
 * NEW IN THIS VERSION:
 * - Real-time HTTP status code distribution charts
 * - Comprehensive uptime percentage calculations
 * - SSL certificate expiry warnings
 * - Interactive response time charts using Chart.js
 * - Server location tracking
 * - Incident time filtering (24h, 7d, 30d)
 * - Mock data generation for testing
 * 
 * SYSTEM REQUIREMENTS:
 * - PHP 8.0 or higher (uses match expressions)
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
 *    j) Click "Go" to create the initial tables
 *    k) IMPORTANT: Also run db_update.sql to add monitoring tables
 * 
 * 2. FILE UPLOAD:
 *    a) Use File Manager or FTP to access your public_html directory
 *    b) Create a new folder for your project (e.g., "project-monitor")
 *    c) Upload all files maintaining the directory structure:
 *       - All PHP files (*.php)
 *       - db.sql and db_update.sql
 *       - assets/css/style.css
 *       - includes/monitoring_functions.php
 *       - .htaccess (optional but recommended)
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
 *    d) Use the provided .htaccess file for additional security
 * 
 * 5. TESTING:
 *    a) Navigate to your-domain.com/project-monitor/
 *    b) You should be redirected to the login page
 *    c) Click "Register" to create your first account
 *    d) After registration, log in with your credentials
 *    e) Create a new project
 *    f) The system will automatically generate mock monitoring data
 *    g) View the project to see all monitoring features
 * 
 * 6. USING THE MONITORING FEATURES:
 *    - HTTP Status Codes: Shows distribution of response codes
 *    - Uptime Statistics: Displays availability percentages
 *    - SSL Info: Shows certificate and domain expiry dates
 *    - Response Times: Interactive chart of performance metrics
 *    - Incidents: Filter by time period (24h, 7d, 30d)
 * 
 * 7. GENERATING SAMPLE DATA:
 *    If you need to generate sample data for testing:
 *    a) Create a project first
 *    b) Navigate to: your-domain.com/project-monitor/generate_sample_data.php?project_id=X
 *    c) Replace X with your project ID
 *    d) The script will generate realistic monitoring data
 * 
 * 8. TROUBLESHOOTING:
 *    - If you see database connection errors:
 *      * Double-check your database credentials in db.php
 *      * Ensure both db.sql AND db_update.sql were executed
 *      * Check if your hosting IP needs to be whitelisted
 *    
 *    - If monitoring data doesn't appear:
 *      * The system auto-generates mock data on first view
 *      * Try manually visiting generate_sample_data.php
 *      * Check PHP error logs for issues
 *    
 *    - If charts don't display:
 *      * Ensure you have internet connection (Chart.js CDN)
 *      * Check browser console for JavaScript errors
 *      * Verify response_times table has data
 * 
 * 9. MAINTENANCE:
 *    - Regular backups: Use Hostinger's backup feature
 *    - Monitor database size: Clean old monitoring data periodically
 *    - Update PHP version when available
 *    - Check error logs in Hostinger control panel
 *    - Consider setting up a cron job to clean old monitoring data
 * 
 * 10. CUSTOMIZATION:
 *    - Uptime check intervals can be adjusted in monitoring_functions.php
 *    - Chart appearance can be modified in project.php JavaScript
 *    - Status code categories can be customized
 *    - Add more server locations as needed
 * 
 * SUPPORT:
 * For Hostinger-specific issues, contact their support team.
 * For application issues, check the code comments and error messages.
 * 
 * CREDITS:
 * Built with PHP, MySQL, Chart.js, and vanilla CSS/JS for maximum 
 * compatibility with shared hosting environments.
 * 
 * VERSION: 2.0 - Enhanced with comprehensive monitoring features
 */