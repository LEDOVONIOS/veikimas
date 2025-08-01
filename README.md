# My Projects Dashboard

A modern, responsive user interface dashboard for managing projects with dynamic content support.

## Features

- **Responsive Design**: Adapts seamlessly to desktop, tablet, and mobile screens
- **Dynamic Content**: Support for multiple project cards with real-time updates
- **Status Indicators**: Visual status badges (Operational, Degraded, Down) with color coding
- **Modern UI**: Clean, professional design with subtle shadows and hover effects
- **Accessibility**: Semantic HTML structure and keyboard navigation support

## Design Specifications

### Layout
- Background color: #f9fafb (light gray)
- Two-column grid layout with cards aligned left
- Container padding: 30px top, 40px horizontal
- Card dimensions: 300px width, auto height

### Typography
- Font: Inter (with fallbacks to system fonts)
- Page title: 28px, bold, black (#111827)
- Project names: 18px, bold
- Metadata: 13px, gray (#6b7280)

### Components
- **Project Cards**: White background, 12px border radius, subtle shadow
- **Status Badges**: Pill-shaped with dynamic colors
  - Operational: Green (#10b981) on light green (#d1fae5)
  - Degraded: Yellow (#f59e0b) on light yellow (#fef3c7)
  - Down: Red (#ef4444) on light red (#fee2e2)
- **Add Button**: Indigo (#6366f1) with hover effect

## Usage

### Basic Setup
Simply open `index.html` in a web browser. The dashboard will display with one example project card.

### JavaScript API

The dashboard provides a global `dashboardAPI` object for programmatic control:

```javascript
// Add a new project
dashboardAPI.addProject({
    name: "My New Project",
    url: "https://myproject.com",
    status: "operational", // or "degraded", "down"
    incidents: 0,
    createdDate: "Aug 1, 2025"
});

// Update project status
dashboardAPI.updateStatus(projectId, "degraded");

// Update incident count
dashboardAPI.updateIncidents(projectId, 3);

// Get all projects
const projects = dashboardAPI.getProjects();

// Remove a project
dashboardAPI.removeProject(projectId);
```

### Dynamic Content Structure

Each project card accepts the following data:
- `id`: Unique identifier
- `name`: Project name (displayed in bold)
- `url`: Project URL (clickable link)
- `status`: "operational", "degraded", or "down"
- `incidents`: Number of incidents (integer)
- `createdDate`: Creation date string

### Responsive Behavior

- **Desktop (>768px)**: Multiple cards in grid layout
- **Tablet (641-768px)**: Flexible grid with minimum card width
- **Mobile (<640px)**: Single column, centered cards, button below title

## File Structure

```
/
├── index.html      # Main HTML structure
├── styles.css      # All styling rules
├── script.js       # Dynamic functionality
└── README.md       # This file
```

## Browser Support

- Chrome/Edge (latest)
- Firefox (latest)
- Safari (latest)
- Mobile browsers (iOS Safari, Chrome Mobile)

## Customization

To customize the dashboard:

1. **Colors**: Edit the color values in `styles.css`
2. **Fonts**: Change the Google Fonts link in `index.html`
3. **Card Layout**: Modify `.project-card` styles
4. **Grid Behavior**: Adjust `.projects-grid` properties

## Notes

- The dashboard uses CSS Grid for layout flexibility
- All text content is escaped to prevent XSS attacks
- The design follows modern UI/UX best practices
- Status badges automatically adapt to content 
