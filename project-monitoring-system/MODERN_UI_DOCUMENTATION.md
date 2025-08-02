# Modern UI Dashboard Documentation

## Overview

The dashboard has been updated with a modern, clean interface following the latest UI/UX best practices.

## Design Specifications

### Color Palette
- **Background**: `#f9fafb` (Light gray)
- **Card Background**: `#ffffff` (White)
- **Primary Text**: `#111827` (Near black)
- **Secondary Text**: `#6b7280` (Gray)
- **Primary Button**: `#6366f1` (Indigo)
- **Primary Button Hover**: `#4f46e5` (Darker indigo)
- **Link Color**: `#3b82f6` (Blue)
- **Success/Operational**: `#10b981` (Green) with `#d1fae5` background
- **Error/Down**: `#dc2626` (Red) with `#fee2e2` background
- **Warning**: `#92400e` (Amber) with `#fef3c7` background

### Typography
- **Font Family**: Inter (Google Fonts), with system font fallbacks
- **Page Title**: 28px, bold (700)
- **Project Name**: 18px, bold (700)
- **Body Text**: 14px, regular (400)
- **Small Text**: 13px (metadata)
- **Button Text**: 14px, bold (700)

### Layout Structure

#### Navigation Bar
- Fixed height with sticky positioning
- White background with bottom border
- Logo on left, menu items centered, user info on right
- Responsive: collapses user info on mobile

#### Main Container
- Max width: 1200px
- Padding: 30px top, 40px horizontal
- Responsive: 20px padding on mobile

#### Page Header
- Flexbox layout with title left, action button right
- Title: "My Projects" in large, bold text
- Add button: Indigo background with plus icon
- Responsive: Stacks vertically on mobile

#### Project Cards
- **Dimensions**: 300px width, auto height
- **Spacing**: 24px gap between cards
- **Layout**: CSS Grid with auto-fill
- **Border Radius**: 12px
- **Shadow**: Subtle (0px 1px 4px rgba(0,0,0,0.08))
- **Hover Effect**: Deeper shadow
- **Padding**: 20px

### Card Components

#### Card Header
- Project name (clickable link)
- Status badge (pill-shaped)
- Flexbox layout with space-between

#### Project URL
- Blue color with underline
- Word-break for long URLs
- 4px top margin

#### Divider
- 1px gray line (#e5e7eb)
- 16px vertical margin

#### Card Footer
- Metadata in small gray text
- "Total Incidents" left, "Created date" right
- Flexbox with space-between

### Status Badge States
1. **Operational**
   - Text: `#10b981` (green)
   - Background: `#d1fae5` (light green)
   - Label: "Operational"

2. **Down**
   - Text: `#dc2626` (red)
   - Background: `#fee2e2` (light red)
   - Label: "Down"

3. **Degraded** (future)
   - Text: `#d97706` (amber)
   - Background: `#fef3c7` (light amber)
   - Label: "Degraded"

### Interactive Elements

#### Buttons
- **Primary Button** (Add New Project)
  - Background: `#6366f1`
  - Hover: `#4f46e5`
  - Padding: 10px 16px
  - Border radius: 8px
  - Includes plus icon

#### Links
- Project names hover to indigo color
- URLs hover to darker blue
- Smooth color transitions (0.2s)

### Responsive Design

#### Breakpoints
- **Desktop**: > 768px
- **Tablet**: 640px - 768px
- **Mobile**: < 640px

#### Mobile Adaptations
- Navigation: Hide user name, show only logout
- Cards: Full width with max 300px
- Grid: Single column, center-aligned
- Header: Stack title and button vertically
- Button: Full width on mobile

### Empty State
- Centered content with 60px padding
- Heading: "No projects yet"
- Subtext with call-to-action
- Primary button to add first project

### URL Limit Warning (Customers only)
- Yellow/amber warning box
- Shows current usage and limit
- Red text when limit reached
- Only visible for Customer role

## Implementation Notes

1. **No External CSS**: All styles are embedded for simplicity
2. **Google Fonts**: Inter font loaded from CDN
3. **CSS Variables**: Not used, direct color values for clarity
4. **Flexbox & Grid**: Modern layout techniques
5. **BEM-like Naming**: Clear, semantic class names

## Browser Support
- Chrome/Edge (latest)
- Firefox (latest)
- Safari (latest)
- Mobile browsers (iOS Safari, Chrome Android)

## Future Enhancements
- Dark mode support
- Animation transitions
- Loading states
- Real-time status updates
- Additional status types (Warning, Maintenance)