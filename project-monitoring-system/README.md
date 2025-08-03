# Project Monitoring System

A comprehensive web-based monitoring system for tracking website uptime, SSL certificates, domain expiration, and project incidents.

## Features

- **Website Monitoring**: Track uptime and response times
- **SSL Certificate Monitoring**: Get alerts before certificates expire
- **Domain Expiration Tracking**: Never miss a domain renewal
- **Incident Management**: Log and track website incidents
- **User Management**: Role-based access control (Admin/User)
- **Email Notifications**: Get alerts for downtime and expiring certificates
- **Responsive Dashboard**: Modern, mobile-friendly interface
- **Dark Mode**: Built-in dark theme support
- **Cron Job Monitoring**: Track scheduled task execution

## Quick Start

1. **Installation**
   ```bash
   # Upload files to your web server
   # Navigate to:
   http://your-domain.com/project-monitoring-system/install.php
   ```

2. **Follow Installation Wizard**
   - Enter database credentials
   - Import database schema automatically
   - Create administrator account
   - Complete setup

3. **Post-Installation**
   - Delete `install.php` for security
   - Set up cron job for automatic monitoring
   - Configure email settings in `config.php`

## System Requirements

- PHP 7.4+
- MySQL 5.7+
- PDO MySQL extension
- cURL extension
- Apache/Nginx with mod_rewrite

## Documentation

- [Full Installation Guide](INSTALLATION.md)
- [Cron Job Setup](CRON_SETUP.md)
- [Role Management](ROLE_MANAGEMENT_GUIDE.md)
- [Quick Reference](CRON_QUICK_REFERENCE.md)

## Key Directories

```
project-monitoring-system/
├── admin/              # Admin panel pages
├── assets/             # CSS, JS, images
├── includes/           # PHP includes and functions
├── scripts/            # Monitoring and CLI scripts
├── config.php.template # Configuration template
├── db_complete.sql     # Database schema
├── install.php         # Installation wizard
└── index.php          # Entry point
```

## User Registration

User registration is **disabled by default** for security. New users must be created:
- Through the admin panel (Admin → User Management)
- Via database insert (see [Installation Guide](INSTALLATION.md))
- Using the CLI script: `php scripts/make_admin.php`

## Monitoring Setup

Add to crontab for automatic monitoring every 5 minutes:
```bash
*/5 * * * * /usr/bin/php /path/to/scripts/monitor_projects.php
```

## Security Notes

1. Delete `install.php` after installation
2. Set proper file permissions (644 for config.php)
3. Use strong passwords for all accounts
4. Enable HTTPS for production use
5. Keep PHP and dependencies updated

## Support

For detailed instructions and troubleshooting, see the [Installation Guide](INSTALLATION.md).

## License

This project is licensed under the MIT License.

---

**Version**: 2.0  
**Last Updated**: 2024