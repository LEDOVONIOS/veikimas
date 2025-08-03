# PHP Website Monitoring Dashboard

A lightweight, self-hosted website monitoring solution designed for shared hosting environments. Monitor website uptime, response times, SSL certificates, and domain expiration dates.

## Features

- 🔍 Monitor unlimited websites with configurable check intervals
- 📊 Beautiful dashboard with uptime statistics and response time graphs
- 🔐 SSL certificate monitoring with expiration alerts
- 🌐 Domain expiration tracking via WHOIS
- 📧 Email notifications for downtime and expiration warnings
- 👥 Multi-user support with role-based access (User/Admin)
- 📱 Responsive design that works on all devices
- ⚡ Lightweight and optimized for shared hosting

## Requirements

- PHP 7.4 or higher
- MySQL/MariaDB database
- Shared hosting with cron job support
- PHP extensions: mysqli, curl, openssl, json

## Installation

1. Upload all files to your web hosting via FTP
2. Create a MySQL database and user
3. Navigate to `/install.php` in your browser
4. Follow the installation wizard
5. Set up a cron job to run `cron.php` every 5 minutes

## Default Login

- Username: `admin`
- Password: `admin123`

**Important:** Change the admin password immediately after first login!

## Cron Setup

Add this to your hosting control panel's cron jobs:

```
*/5 * * * * /usr/bin/php /path/to/your/installation/cron.php
```

Or use wget/curl:

```
*/5 * * * * wget -q -O - https://yourdomain.com/cron.php?key=YOUR_CRON_KEY
```

## License

MIT License - feel free to use this for personal or commercial projects.

## Support

For issues or questions, please contact: info@seorocket.lt 
