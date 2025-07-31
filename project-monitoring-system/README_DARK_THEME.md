# Dark Theme Monitoring Dashboard

## Overview
The Project Monitoring System now includes a modern dark theme interface inspired by BetterStack and UptimeRobot. This provides a professional, visually rich monitoring experience with enhanced readability and reduced eye strain.

## Features

### 🎨 Design System
- **Dark Mode**: Navy/deep gray background (#0f1419) with carefully selected contrast ratios
- **Color Scheme**: Green for success, red for errors, yellow for warnings
- **Typography**: Modern sans-serif fonts with clear hierarchy
- **Card-Based Layout**: Clean separation between sections with soft shadows

### 📊 Dashboard Components

#### 1. Top Header Block
- Project name with colored status indicator (Up/Down)
- Clickable URL to monitored site
- Uptime duration display (e.g., "Up for 18d 19h 15m")
- Action buttons: Test Notification, Pause Monitoring, Edit

#### 2. Uptime Overview Cards
- **Last 7 days**: Percentage, incidents, downtime
- **Last 30 days**: Same metrics
- **Last 365 days**: Long-term statistics
- Color-coded percentages (green >99%, yellow for warnings, red <95%)

#### 3. Response Time Graph
- Interactive line chart with dark theme
- Shows Average, Minimum, and Maximum response times
- Time range filtering: Last hour, 24 hours, 7 days
- Smooth animations and hover tooltips

#### 4. Latest Incidents Table
- Status icons (resolved/open)
- Root cause, start time, duration
- Export logs functionality
- Clean alternating row design

#### 5. Sidebar Widgets
- **Domain & SSL Info**: Expiry dates with visual warnings
- **Next Maintenance**: Setup button
- **Regions**: Geographic monitoring location
- **To Be Notified**: User notification settings
- **Last Checked**: Timestamp display

### 🌐 Monitor List View
- Grid layout showing all monitors
- Real-time status indicators with pulse animation
- Uptime percentage bars
- SSL expiry warning badges
- Monitoring interval display

## Usage

### Access Dark Theme Pages
1. **Dashboard**: `dashboard-dark.php`
2. **Project View**: `project-dark.php?id=PROJECT_ID`

### Features
- Fully responsive design (mobile, tablet, desktop)
- Real-time status updates
- Interactive charts with Chart.js
- Smooth transitions and hover effects
- Accessible color contrast ratios

## Technical Details

### CSS Variables
```css
--bg-primary: #0f1419;     /* Main background */
--bg-secondary: #1a1f2e;   /* Secondary background */
--bg-card: #202937;        /* Card backgrounds */
--status-up: #10b981;      /* Success/Up status */
--status-down: #ef4444;    /* Error/Down status */
--status-warning: #f59e0b; /* Warning status */
```

### Responsive Breakpoints
- Desktop: 1024px+
- Tablet: 768px - 1023px
- Mobile: <768px

### Browser Support
- Chrome/Edge (latest)
- Firefox (latest)
- Safari (latest)
- Mobile browsers

## Migration from Light Theme

To use the dark theme versions:
1. Replace `dashboard.php` with `dashboard-dark.php`
2. Replace `project.php` with `project-dark.php`
3. The dark theme CSS is automatically loaded

## Future Enhancements
- Dark/Light theme toggle
- Custom color preferences
- Enhanced animations
- Additional chart types
- Real-time WebSocket updates