# Modern UI Update - Uptime Monitoring Dashboard

## Overview
This update transforms the uptime monitoring dashboard into a modern, professional dark-themed interface that matches the visual quality of industry leaders like UptimeRobot and BetterStack. All timestamps are displayed in Lithuanian local time (Europe/Vilnius timezone).

## Key Features Implemented

### 1. **Dark Theme Design System**
- Background colors: `#0e1525` (primary), `#1a1f2e` (secondary), `#202937` (cards)
- Text colors: White (primary), `#94a3b8` (secondary)
- Status colors: `#00FFB2` (success), `#FACC15` (warning), `#EF4444` (danger)
- Modern shadows and rounded corners throughout

### 2. **Uptime Statistics (Redesigned)**
- **3 horizontal cards** showing Last 7, 30, and 365 days
- Large bold percentage display
- Color-coded status (green >99.99%, yellow >99.9%, red below)
- Incident count and downtime duration
- Responsive grid layout with hover effects

### 3. **Response Time Chart**
- Interactive line chart with Chart.js
- Shows Average (solid line), Maximum (dotted), Minimum (ghost line)
- X-axis with 10-minute intervals over 24 hours
- Lithuanian timezone labels with "LT" suffix
- Stats display below: avg, min, max response times
- Time range selector: 1h, 24h, 7d

### 4. **Latest Incidents (Grid Layout)**
- **3 square tiles per row** replacing table format
- Status icons: 🟢 (Resolved), 🔴 (Open), 🟡 (Investigating)
- Shows: Status, Root Cause, Start Time (LT), Duration
- Color-coded left border based on status
- Hover effects and "Load More" button
- All times in Lithuanian timezone

### 5. **Domain & SSL Info**
- Compact card with two-column layout
- Domain expiry date
- SSL validity and issuer
- Color-coded values (green = valid, yellow = expiring soon)
- Lock icon for security visualization

### 6. **Status Overview**
- Large "Up" or "Down" status pill with pulse animation
- "Currently up for: XXd XXh XXm" duration display
- Last checked timestamp in Lithuanian time
- 24-hour status timeline with hover tooltips

### 7. **Monitoring Location**
- Miniature world map visualization
- Region marker with pulse animation
- Clear location label (e.g., "North America")

### 8. **Controls and Tools**
- Top-right action buttons: Test Notification, Pause, Edit
- Export Logs functionality
- Icon buttons with hover effects
- Consistent spacing and styling

## Lithuanian Timezone Implementation

All timestamps throughout the UI are displayed in Lithuanian local time (Europe/Vilnius):

### Server-side (PHP):
```php
date_default_timezone_set('Europe/Vilnius');

function formatLithuanianTime($timestamp) {
    if (!$timestamp) return 'N/A';
    $dt = new DateTime($timestamp);
    $dt->setTimezone(new DateTimeZone('Europe/Vilnius'));
    // Format based on age
}
```

### Client-side (JavaScript):
```javascript
const ltFormatter = new Intl.DateTimeFormat('lt-LT', {
    timeZone: 'Europe/Vilnius',
    hour: '2-digit',
    minute: '2-digit',
    hour12: false
});
```

### Time Display Rules:
- **< 24 hours old**: Show time only (e.g., "14:32")
- **> 24 hours old**: Show date and time (e.g., "2025-01-07 14:32")
- **Timezone indicator**: "LT" suffix or "Europe/Vilnius" badge
- **24-hour format**: Always use 24-hour time

## File Structure

### New Files Created:
- `project-modern.php` - Modernized project monitoring page
- `dashboard-modern.php` - Modernized dashboard page
- `assets/css/modern-dark-theme.css` - Modern dark theme styles
- `MODERN_UI_UPDATE.md` - This documentation

### Updated Files:
- Modified existing files to support Lithuanian timezone
- Enhanced CSS for dark theme compatibility

## Usage Instructions

1. **Access the modern UI**:
   - Dashboard: `/dashboard-modern.php`
   - Project view: `/project-modern.php?id=X`

2. **Switch from old UI**:
   - Update navigation links to point to modern pages
   - Or rename modern files to replace originals

3. **Customize colors**:
   - Edit CSS variables in `:root` section
   - Maintain contrast ratios for accessibility

## Key Improvements

1. **Visual Hierarchy**: Clear separation between sections with cards and proper spacing
2. **Responsive Design**: Fully responsive grid layouts that adapt to all screen sizes
3. **Performance**: Optimized animations and transitions
4. **Accessibility**: Proper contrast ratios, focus states, and ARIA labels
5. **User Experience**: Hover effects, loading states, and interactive elements
6. **Time Clarity**: All times in Lithuanian timezone with clear indicators

## Browser Support

- Chrome/Edge (latest)
- Firefox (latest)
- Safari (latest)
- Mobile browsers (iOS Safari, Chrome)

## Future Enhancements

1. Dark/Light theme toggle
2. Custom monitoring intervals
3. Advanced filtering options
4. Real-time WebSocket updates
5. Multi-language support
6. Custom alert thresholds