# Project Monitoring System

A comprehensive website monitoring system with uptime tracking, incident management, and real-time notifications.

## Features

- **Website Monitoring**: Track uptime, response times, and HTTP status codes
- **Incident Management**: Log and track website incidents with root cause analysis
- **SSL Certificate Monitoring**: Get alerts before SSL certificates expire
- **Real-time Notifications**: Email alerts for downtime and certificate expiry
- **User Management**: Role-based access control (Admin/Customer)
- **Visual Analytics**: Interactive charts for response times and status codes
- **Cron Job Monitoring**: Track scheduled task execution
- **Dark Theme**: Modern UI with dark theme support

## Quick Installation

### Prerequisites
- PHP 7.0 or higher
- MySQL 5.6 or higher
- Web server (Apache/Nginx)

### Web-Based Installation (Recommended)

1. **Upload Files**
   ```bash
   # Upload all files to your web server directory
   ```

2. **Run Installation Wizard**
   - Navigate to `http://your-domain.com/install.php`
   - Follow the 3-step wizard:
     - **Step 1**: Enter MySQL database credentials
     - **Step 2**: Create administrator account  
     - **Step 3**: Complete installation

3. **Secure Your Installation**
   ```bash
   # Delete the installer
   rm install.php
   
   # Set proper permissions
   chmod 644 config.php
   ```

That's it! The installer handles everything automatically:
- Creates the database (if needed)
- Sets up all tables and relationships
- Configures the system
- Creates your admin account

## Post-Installation Setup

### Configure Email Notifications

Edit `config.php` to enable email notifications:

```php
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'your-email@gmail.com');
define('SMTP_PASS', 'your-app-password');
```

### Set Up Monitoring Cron Job

Add to your crontab to check websites every 5 minutes:

```bash
*/5 * * * * php /path/to/your/scripts/monitoring_cron.php
```

## Usage

1. **Login** with your administrator account
2. **Add Projects** to monitor
3. **View Dashboard** for real-time status
4. **Manage Incidents** when issues occur
5. **Check Notifications** for alerts

## User Roles

- **Admin**: Full system access, user management, all projects
- **Customer**: Limited to their own projects, URL restrictions apply

## Screenshots

The system features a modern, responsive design with:
- Real-time status dashboard
- Interactive charts and graphs
- Mobile-friendly interface
- Dark theme support

## Troubleshooting

See `INSTALLATION_GUIDE.md` for detailed troubleshooting steps.

Common issues:
- **Database connection failed**: Check MySQL credentials
- **Installation failed**: Ensure PHP PDO extension is enabled
- **Can't login**: Clear browser cache, check email/password

## Security

The system includes:
- Password hashing with bcrypt
- SQL injection prevention
- XSS protection
- Session security
- Role-based access control

## License

This project is proprietary software. All rights reserved.

## Support

For installation help, see:
- `INSTALLATION_GUIDE.md` - Detailed installation instructions
- `CRON_SETUP.md` - Monitoring automation setup
- `ROLES_SETUP.md` - User management guide