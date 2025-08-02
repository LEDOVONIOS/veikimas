#!/bin/bash

# Project Monitoring System - Cron Setup Script
# This script helps you set up automated monitoring via cron

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Get the directory where this script is located
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"
MONITOR_SCRIPT="$SCRIPT_DIR/monitor_projects.php"

echo -e "${GREEN}Project Monitoring System - Cron Setup${NC}"
echo "========================================"
echo ""

# Check if monitoring script exists
if [ ! -f "$MONITOR_SCRIPT" ]; then
    echo -e "${RED}Error: Monitoring script not found at $MONITOR_SCRIPT${NC}"
    exit 1
fi

# Check if PHP is installed
if ! command -v php &> /dev/null; then
    echo -e "${RED}Error: PHP is not installed or not in PATH${NC}"
    exit 1
fi

# Get PHP path
PHP_PATH=$(which php)
echo -e "PHP path: ${GREEN}$PHP_PATH${NC}"

# Function to add cron job
add_cron_job() {
    local schedule=$1
    local verbose=$2
    
    # Build the cron command
    local cron_cmd="$PHP_PATH $MONITOR_SCRIPT"
    if [ "$verbose" == "yes" ]; then
        cron_cmd="$cron_cmd --verbose"
    fi
    cron_cmd="$cron_cmd >> $PROJECT_ROOT/logs/monitor.log 2>&1"
    
    # Full cron line
    local cron_line="$schedule $cron_cmd"
    
    # Check if cron job already exists
    if crontab -l 2>/dev/null | grep -q "$MONITOR_SCRIPT"; then
        echo -e "${YELLOW}Warning: A cron job for this monitoring script already exists${NC}"
        echo "Current cron job:"
        crontab -l | grep "$MONITOR_SCRIPT"
        echo ""
        read -p "Do you want to replace it? (y/n): " -n 1 -r
        echo ""
        if [[ ! $REPLY =~ ^[Yy]$ ]]; then
            echo "Cron setup cancelled."
            return
        fi
        # Remove existing cron job
        (crontab -l 2>/dev/null | grep -v "$MONITOR_SCRIPT") | crontab -
    fi
    
    # Add new cron job
    (crontab -l 2>/dev/null; echo "$cron_line") | crontab -
    
    echo -e "${GREEN}✓ Cron job added successfully!${NC}"
    echo "Cron line: $cron_line"
}

# Create logs directory if it doesn't exist
if [ ! -d "$PROJECT_ROOT/logs" ]; then
    mkdir -p "$PROJECT_ROOT/logs"
    echo -e "${GREEN}✓ Created logs directory${NC}"
fi

# Menu
echo "Select monitoring frequency:"
echo "1) Every minute (for testing)"
echo "2) Every 5 minutes (recommended)"
echo "3) Every 10 minutes"
echo "4) Every 15 minutes"
echo "5) Every 30 minutes"
echo "6) Every hour"
echo "7) Custom schedule"
echo "8) Remove existing cron job"
echo "9) View current cron jobs"
echo "0) Exit"
echo ""

read -p "Enter your choice (0-9): " choice

case $choice in
    1)
        schedule="* * * * *"
        desc="every minute"
        ;;
    2)
        schedule="*/5 * * * *"
        desc="every 5 minutes"
        ;;
    3)
        schedule="*/10 * * * *"
        desc="every 10 minutes"
        ;;
    4)
        schedule="*/15 * * * *"
        desc="every 15 minutes"
        ;;
    5)
        schedule="*/30 * * * *"
        desc="every 30 minutes"
        ;;
    6)
        schedule="0 * * * *"
        desc="every hour"
        ;;
    7)
        echo "Enter custom cron schedule (e.g., '*/5 * * * *' for every 5 minutes):"
        read -p "Schedule: " schedule
        desc="custom schedule"
        ;;
    8)
        if crontab -l 2>/dev/null | grep -q "$MONITOR_SCRIPT"; then
            (crontab -l 2>/dev/null | grep -v "$MONITOR_SCRIPT") | crontab -
            echo -e "${GREEN}✓ Monitoring cron job removed${NC}"
        else
            echo -e "${YELLOW}No monitoring cron job found${NC}"
        fi
        exit 0
        ;;
    9)
        echo -e "${GREEN}Current cron jobs:${NC}"
        crontab -l 2>/dev/null | grep "$MONITOR_SCRIPT" || echo "No monitoring cron jobs found"
        exit 0
        ;;
    0)
        echo "Setup cancelled."
        exit 0
        ;;
    *)
        echo -e "${RED}Invalid choice${NC}"
        exit 1
        ;;
esac

if [ -n "$schedule" ]; then
    echo ""
    read -p "Enable verbose logging? (y/n): " -n 1 -r
    echo ""
    
    verbose="no"
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        verbose="yes"
    fi
    
    echo ""
    echo "Setting up monitoring to run $desc..."
    add_cron_job "$schedule" "$verbose"
    
    echo ""
    echo -e "${GREEN}Setup complete!${NC}"
    echo ""
    echo "Monitoring logs will be saved to: $PROJECT_ROOT/logs/monitor.log"
    echo "To view logs in real-time: tail -f $PROJECT_ROOT/logs/monitor.log"
    echo ""
    echo "To test the monitoring script manually:"
    echo "  $PHP_PATH $MONITOR_SCRIPT --verbose"
    echo ""
    echo "To monitor a specific project:"
    echo "  $PHP_PATH $MONITOR_SCRIPT --verbose --project-id=1"
fi