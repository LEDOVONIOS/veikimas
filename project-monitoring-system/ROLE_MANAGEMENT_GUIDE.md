# User Role Management System Guide

## Overview

The Uptime Monitoring System now includes a comprehensive role-based access control (RBAC) system with two distinct roles:

- **Admin**: Full system access with user management capabilities
- **User**: Regular users with project contribution limits

## Key Features

### 1. Automatic Admin Assignment
- The **first registered user** is automatically assigned the Admin role
- All subsequent users are assigned the User role by default
- This ensures there's always at least one administrator in the system

### 2. Admin Capabilities

Admins have access to a dedicated admin panel where they can:

- **View all users** in the system
- **Change user roles** (except their own)
- **Set project contribution limits** for individual users
- **Add custom messages** when setting limits
- **View any user's projects** (read-only access)
- **Access detailed project information** for all users

### 3. Project Limit Management

- Each user can be assigned a specific project limit
- Default limit is 10 projects per user
- Admins have unlimited projects
- When users exceed their limit, they see: *"You have exceeded your project limit. Please contact info@seorocket.lt"*

### 4. Admin Panel Access

Admins can access the admin panel through:
- Navigation menu: "Admin" link (only visible to admins)
- Direct URL: `/admin/users.php`

## Database Changes

### New/Updated Tables

1. **roles** - Stores role definitions (Admin, User)
2. **project_limits** - Stores user-specific project limits
3. **admin_project_access_log** - Audit trail for admin access to user projects

### SQL Migration

To upgrade an existing system, run:
```sql
-- Run the role enhancement SQL
mysql -u your_username -p your_database < db_role_enhancement.sql
```

## Implementation Details

### Role Check Functions

```php
// Check if user is admin
if (isAdmin()) {
    // Admin-only code
}

// Check if user is regular user
if (isUser()) {
    // User-specific code
}

// Require admin access (redirects if not admin)
requireAdmin();
```

### Project Limit Functions

```php
// Check if user can add more projects
if (canAddMoreProjects($userId)) {
    // Allow project creation
}

// Get user's project limit details
$limitDetails = getUserProjectLimitDetails($userId);
echo "Projects: " . $limitDetails['current_projects'] . "/" . $limitDetails['max_projects'];

// Set project limit (admin only)
setUserProjectLimit($userId, $newLimit, $adminId, "Optional message");
```

## Admin Interface

### User Management Page (`/admin/users.php`)

Features:
- List all users with their current project usage
- Change user roles
- Set project limits with optional messages
- View individual user's projects
- Color-coded usage indicators:
  - Green: Under 80% usage
  - Orange: 80-99% usage
  - Red: 100% or over limit

### User Projects Page (`/admin/user-projects.php`)

Features:
- View all projects for a specific user
- Read-only access (clearly marked)
- Project status and uptime information
- SSL certificate status
- Quick access to project details

## Security Features

1. **Session Management**: All role checks use secure session handling
2. **Audit Trail**: Admin access to user projects is logged
3. **Self-Protection**: Admins cannot change their own role
4. **Read-Only Access**: Admins can view but not modify user projects

## SEO Rocket Branding

The system now includes SEO Rocket branding:
- Logo appears in navigation header on all pages
- Banner URL: `https://uptime.seorocket.lt/images/seorocket.png`
- Consistent branding across login, dashboard, and admin pages

## Configuration

### Setting Up First Admin

1. Register the first user account
2. System automatically assigns Admin role
3. Login to access full admin capabilities

### Managing Users

1. Navigate to Admin → Users
2. View user list with project usage
3. Update roles or limits as needed
4. Click "View Projects" to inspect user's monitors

### Handling Limit Exceeded Users

When users exceed their limit:
1. They see the contact message
2. Admin can increase their limit
3. Admin can add a custom message explaining the limit

## Best Practices

1. **Regular Monitoring**: Check user usage regularly
2. **Fair Limits**: Set reasonable project limits based on user needs
3. **Clear Communication**: Use limit messages to explain restrictions
4. **Audit Review**: Periodically review admin access logs
5. **Role Assignment**: Only assign Admin role to trusted users

## Troubleshooting

### Common Issues

1. **First user not admin**: Run the SQL update to fix role assignment
2. **Limits not enforcing**: Check if role functions are properly included
3. **Admin menu not visible**: Verify user has Admin role in database

### Support

For any issues or questions, contact: info@seorocket.lt