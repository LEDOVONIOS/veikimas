# Project Monitoring System - Cron Job Setup Guide

## Overview

The Project Monitoring System includes automated monitoring capabilities through cron jobs. This guide explains how to set up scheduled monitoring checks for your projects.

## Quick Setup

### Using the Setup Script (Recommended)

1. Navigate to the project directory:
   ```bash
   cd /path/to/project-monitoring-system
   ```

2. Run the setup script:
   ```bash
   ./scripts/setup-cron.sh
   ```

3. Follow the interactive prompts to:
   - Choose monitoring frequency (every 5 minutes recommended)
   - Enable/disable verbose logging
   - Confirm the cron job setup

### Manual Setup

If you prefer to set up the cron job manually:

1. Open your crontab:
   ```bash
   crontab -e
   ```

2. Add one of the following lines:
   ```bash
   # Run every 5 minutes (recommended)
   */5 * * * * /usr/bin/php /path/to/project-monitoring-system/scripts/monitor_projects.php >> /path/to/project-monitoring-system/logs/monitor.log 2>&1
   
   # Run every minute (for testing)
   * * * * * /usr/bin/php /path/to/project-monitoring-system/scripts/monitor_projects.php >> /path/to/project-monitoring-system/logs/monitor.log 2>&1
   
   # Run every 10 minutes
   */10 * * * * /usr/bin/php /path/to/project-monitoring-system/scripts/monitor_projects.php >> /path/to/project-monitoring-system/logs/monitor.log 2>&1
   ```

## Monitoring Script Features

The `monitor_projects.php` script provides:

- **URL Monitoring**: Checks if projects are accessible
- **Response Time Tracking**: Measures and logs response times
- **SSL Certificate Monitoring**: Tracks SSL certificate expiry (30-day warning)
- **Incident Management**: Automatically creates and resolves incidents
- **HTTP Status Logging**: Records HTTP status codes
- **Automatic Cleanup**: Removes logs older than 90 days
- **Email Notifications**: Sends alerts when projects go down/up

## Command Line Options

```bash
php scripts/monitor_projects.php [options]

Options:
  --verbose         Show detailed output for each check
  --project-id=X    Monitor only specific project ID (useful for testing)
```

### Examples:

```bash
# Test monitoring for all projects with verbose output
php scripts/monitor_projects.php --verbose

# Test monitoring for project ID 1
php scripts/monitor_projects.php --verbose --project-id=1

# Run silently (for cron)
php scripts/monitor_projects.php
```

## Monitoring Frequency Recommendations

| Frequency | Use Case | Cron Schedule |
|-----------|----------|---------------|
| Every minute | Testing only | `* * * * *` |
| Every 5 minutes | **Recommended for production** | `*/5 * * * *` |
| Every 10 minutes | Light monitoring | `*/10 * * * *` |
| Every 15 minutes | Basic monitoring | `*/15 * * * *` |
| Every 30 minutes | Minimal monitoring | `*/30 * * * *` |
| Every hour | Very light monitoring | `0 * * * *` |

## Log Files

Monitoring logs are stored in:
```
/path/to/project-monitoring-system/logs/monitor.log
```

### Viewing Logs

```bash
# View recent log entries
tail -n 50 logs/monitor.log

# Follow logs in real-time
tail -f logs/monitor.log

# Search for errors
grep ERROR logs/monitor.log

# View logs for specific project
grep "Project Name" logs/monitor.log
```

## Monitoring Process

When the cron job runs, it:

1. **Loads all active projects** from the database
2. **For each project:**
   - Performs HTTP/HTTPS request
   - Measures response time
   - Checks SSL certificate (if HTTPS)
   - Logs results to database
   - Creates/resolves incidents as needed
   - Sends notifications if status changes
3. **Cleans up old logs** (>90 days)
4. **Reports summary** of checks

## Database Tables Used

The monitoring script writes to these tables:

- `uptime_logs` - Records up/down status
- `http_status_logs` - Stores HTTP status codes
- `response_times` - Tracks response times
- `ssl_certificates` - SSL certificate information
- `incidents` - Incident tracking

## Troubleshooting

### Common Issues

1. **"PHP is not installed or not in PATH"**
   - Install PHP: `sudo apt-get install php-cli php-curl`
   - Or specify full PHP path in cron job

2. **"Permission denied" when running setup script**
   ```bash
   chmod +x scripts/setup-cron.sh
   ```

3. **No monitoring data appearing**
   - Check if cron job is running: `crontab -l`
   - Check logs for errors: `tail logs/monitor.log`
   - Test manually: `php scripts/monitor_projects.php --verbose`

4. **Database connection errors**
   - Verify database credentials in `db.php`
   - Ensure database user has necessary permissions

### Checking Cron Job Status

```bash
# List current cron jobs
crontab -l

# Check if cron service is running
systemctl status cron  # or 'crond' on some systems

# View cron logs
grep CRON /var/log/syslog  # Ubuntu/Debian
tail /var/log/cron         # CentOS/RHEL
```

## Performance Considerations

- Each URL check has a 10-second timeout
- 0.5-second delay between project checks
- SSL checks add ~1-2 seconds per HTTPS site
- For 10 projects: ~15-20 seconds total runtime
- For 100 projects: ~2-3 minutes total runtime

## Security Notes

1. The monitoring script disables SSL verification by default
2. Credentials should not be included in monitored URLs
3. Log files may contain sensitive URLs - secure appropriately
4. Consider using a dedicated system user for cron jobs

## Advanced Configuration

### Custom Cron Schedules

```bash
# Run Monday-Friday, 9 AM to 5 PM, every 5 minutes
*/5 9-17 * * 1-5 /usr/bin/php /path/to/monitor_projects.php

# Run every hour on weekends
0 * * * 0,6 /usr/bin/php /path/to/monitor_projects.php

# Run at specific times
0 8,12,16,20 * * * /usr/bin/php /path/to/monitor_projects.php
```

### Email Configuration for Cron

Add to the top of your crontab to receive error emails:
```bash
MAILTO=your-email@example.com
```

### Using Different PHP Versions

```bash
# Use specific PHP version
*/5 * * * * /usr/bin/php7.4 /path/to/monitor_projects.php
*/5 * * * * /usr/bin/php8.1 /path/to/monitor_projects.php
```

## Monitoring Multiple Environments

For different environments (dev, staging, production):

```bash
# Development - every 30 minutes
*/30 * * * * /usr/bin/php /path/to/dev/monitor_projects.php

# Staging - every 10 minutes
*/10 * * * * /usr/bin/php /path/to/staging/monitor_projects.php

# Production - every 5 minutes
*/5 * * * * /usr/bin/php /path/to/production/monitor_projects.php
```

## Disabling Monitoring

To temporarily disable monitoring:

```bash
# Using the setup script
./scripts/setup-cron.sh
# Choose option 8 to remove cron job

# Or manually
crontab -e
# Comment out or remove the monitoring line
```

## Integration with System Monitoring

The monitoring script can be integrated with:

- **Nagios/Icinga**: Parse log output for alerts
- **Zabbix**: Use log monitoring or custom scripts
- **Prometheus**: Export metrics via custom exporter
- **Grafana**: Visualize data from database

## Next Steps

1. Set up the cron job using `./scripts/setup-cron.sh`
2. Test monitoring manually first
3. Monitor the logs for the first few runs
4. Adjust frequency based on your needs
5. Configure email notifications in the web interface