# Cron Job Quick Reference

## Testing Before Setup

```bash
# Test system compatibility
php scripts/test-monitoring.php

# Test monitoring manually
php scripts/monitor_projects.php --verbose

# Test specific project
php scripts/monitor_projects.php --verbose --project-id=1
```

## Setup Commands

```bash
# Interactive setup (recommended)
./scripts/setup-cron.sh

# Manual cron entry (add to crontab -e)
*/5 * * * * /usr/bin/php /path/to/project-monitoring-system/scripts/monitor_projects.php >> /path/to/project-monitoring-system/logs/monitor.log 2>&1
```

## Management Commands

```bash
# View current cron jobs
crontab -l

# Edit cron jobs
crontab -e

# Remove all cron jobs (careful!)
crontab -r

# View monitoring logs
tail -f logs/monitor.log

# Check last 100 lines of logs
tail -n 100 logs/monitor.log

# Search for errors
grep ERROR logs/monitor.log

# Count successful checks today
grep "SUCCESS" logs/monitor.log | grep "$(date +%Y-%m-%d)" | wc -l
```

## Common Cron Schedules

```bash
# Every minute (testing only)
* * * * * /usr/bin/php /path/to/monitor_projects.php

# Every 5 minutes (recommended)
*/5 * * * * /usr/bin/php /path/to/monitor_projects.php

# Every 10 minutes
*/10 * * * * /usr/bin/php /path/to/monitor_projects.php

# Every hour at minute 0
0 * * * * /usr/bin/php /path/to/monitor_projects.php

# Every day at 2:30 AM
30 2 * * * /usr/bin/php /path/to/monitor_projects.php

# Monday to Friday, 9-5, every 5 minutes
*/5 9-17 * * 1-5 /usr/bin/php /path/to/monitor_projects.php
```

## Troubleshooting

```bash
# Check if cron is running
systemctl status cron
# or
service cron status

# View system cron logs
grep CRON /var/log/syslog

# Test PHP path
which php

# Check PHP version
php -v

# Verify script permissions
ls -la scripts/monitor_projects.php

# Run with error reporting
php -d display_errors=1 scripts/monitor_projects.php --verbose
```

## Log Analysis

```bash
# Today's summary
grep "$(date +%Y-%m-%d)" logs/monitor.log | grep "COMPLETE"

# Count DOWN incidents
grep "is DOWN" logs/monitor.log | wc -l

# View specific project logs
grep "Project Name" logs/monitor.log

# Check SSL warnings
grep "SSL certificate" logs/monitor.log

# Monitor log file size
du -h logs/monitor.log

# Rotate logs manually
mv logs/monitor.log logs/monitor.log.old
touch logs/monitor.log
```

## Performance Metrics

```bash
# Check execution time
grep "completed in" logs/monitor.log | tail -10

# Count total checks per day
grep "COMPLETE" logs/monitor.log | cut -d' ' -f1 | uniq -c

# Find slow checks
grep "response_time" logs/monitor.log | awk '{print $NF}' | sort -n | tail -10
```

## Emergency Commands

```bash
# Stop monitoring temporarily
crontab -l | grep -v monitor_projects > /tmp/cron.tmp && crontab /tmp/cron.tmp

# Kill running monitor process
pkill -f monitor_projects.php

# Clear all logs (careful!)
> logs/monitor.log

# Backup cron jobs
crontab -l > ~/cron_backup_$(date +%Y%m%d).txt
```