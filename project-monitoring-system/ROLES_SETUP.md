# User Roles System Setup Guide

## Overview

The user roles system has been implemented with two main roles:
- **Admin**: Full system access with unlimited URLs
- **Customer**: Limited access with configurable URL limits

## Database Setup

1. **Run the migration script** to add the roles system to your database:
   ```sql
   -- Execute the contents of db_roles_update.sql in your database
   ```

2. The migration will:
   - Create a `roles` table with Admin and Customer roles
   - Add `role_id` column to the `users` table
   - Create `url_limits` table for managing customer URL restrictions
   - Set all existing users to Customer role by default
   - Create a view `user_url_count` for easy URL counting

## Making Your First Admin

After setting up the database, you need to make at least one user an admin:

### Option 1: Using the Script (Recommended)
```bash
# From command line
php scripts/make_admin.php your-email@example.com

# Or via web browser (replace your-secret-key-here with a secure key)
http://yoursite.com/scripts/make_admin.php?key=your-secret-key-here
```

### Option 2: Direct Database Update
```sql
UPDATE users 
SET role_id = (SELECT id FROM roles WHERE name = 'Admin')
WHERE email = 'your-email@example.com';
```

## Features

### For Customers:
- URL limit enforcement when adding projects
- Dashboard shows current URL usage and remaining slots
- Error message when limit exceeded: "You have exceeded the allowed number of URLs. Please contact support at info@seorocket.lt."
- Default limit: 10 URLs per customer

### For Admins:
- Access to "Manage Users" panel in navigation
- Can change user roles (Admin/Customer)
- Can set custom URL limits for each customer
- Unlimited URLs for their own projects
- View all users with their URL usage

## Admin Panel

Access the admin panel at: `/admin/manage-users.php`

Features:
- View all users with their roles and URL counts
- Change user roles with dropdown
- Set custom URL limits for customers
- Pagination for large user lists
- Visual indicators for users who exceeded their limits

## File Structure

New/Modified files:
- `includes/roles.php` - Role management functions
- `admin/manage-users.php` - Admin user management panel
- `db_roles_update.sql` - Database migration script
- `scripts/make_admin.php` - Script to make users admin
- Modified: `add_project.php` - Added URL limit checking
- Modified: `dashboard.php` - Added role display and URL usage info
- Modified: `register.php` - New users get Customer role by default

## Security Notes

1. The admin panel checks for admin role before allowing access
2. Non-admin users are redirected to dashboard if they try to access admin pages
3. URL limits only apply to customers, not admins
4. All role checks use server-side validation

## Customization

To change the support email message:
1. Edit `includes/roles.php`
2. Find the `getUrlLimitExceededMessage()` function
3. Update the email address or message as needed