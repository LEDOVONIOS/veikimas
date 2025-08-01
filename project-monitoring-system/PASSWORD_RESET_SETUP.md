# Password Reset & User Management System

## Overview

The system has been updated to:
- **Remove public user registration** - Users cannot self-register
- **Add password reset functionality** - Users can reset their passwords via email
- **Admin-only user creation** - Only admins can create new user accounts

## Database Setup

Run the password reset migration to add the necessary table:
```sql
-- Execute the contents of db_password_reset.sql in your database
```

This creates the `password_reset_tokens` table for secure password reset functionality.

## Key Changes

### 1. User Registration Removed
- `register.php` has been disabled
- Registration link removed from login page
- `.htaccess` blocks access to registration files

### 2. Password Reset System
Users can reset their passwords through:
1. Click "Forgot your password?" on login page
2. Enter their email address
3. Receive reset link via email (1-hour expiration)
4. Set new password using the secure link

### 3. Admin User Creation
Admins can create users through:
- Navigate to "Manage Users" → "Create New User"
- Fill in user details (name, email, password, role)
- Set URL limits for Customer accounts
- Share credentials securely with the new user

## File Structure

### New Files:
- `includes/password_reset.php` - Password reset functions
- `forgot-password.php` - Request password reset
- `reset-password.php` - Set new password
- `admin/create-user.php` - Admin creates new users
- `db_password_reset.sql` - Database migration

### Modified Files:
- `login.php` - Added "Forgot password?" link
- `admin/manage-users.php` - Added "Create New User" button
- `.htaccess` - Blocks registration access

### Disabled Files:
- `register.php.disabled` - Registration page (disabled)

## Email Configuration

The password reset system currently shows reset links in development mode. For production:

1. Edit `includes/password_reset.php`
2. Update the `sendPasswordResetEmail()` function
3. Configure your email settings:

```php
// Example using PHP mail()
$headers = "From: noreply@yourdomain.com\r\n";
$headers .= "Reply-To: support@yourdomain.com\r\n";
return mail($email, $subject, $message, $headers);

// Or use PHPMailer/other email service
```

## Development Mode

To see password reset links during development:
- Access: `forgot-password.php?dev=1`
- The reset link will be displayed on screen

## Security Features

1. **Token Security**:
   - 64-character random tokens
   - 1-hour expiration
   - Single use only
   - Invalidates previous tokens

2. **Password Requirements**:
   - Minimum 8 characters
   - Confirmation required
   - Hashed using bcrypt

3. **Email Security**:
   - Doesn't reveal if email exists
   - Generic success message
   - Rate limiting recommended

## User Workflow

### For Users:
1. Login with credentials provided by admin
2. Use "Forgot password?" to reset password
3. Check email for reset link
4. Set new password

### For Admins:
1. Create users via admin panel
2. Set appropriate roles and limits
3. Share credentials securely
4. Users can self-manage passwords

## Maintenance

### Clean up expired tokens (optional cron job):
```sql
DELETE FROM password_reset_tokens 
WHERE expires_at < NOW() OR used = TRUE;
```

### Monitor failed reset attempts:
```sql
SELECT * FROM password_reset_tokens 
WHERE used = FALSE 
ORDER BY created_at DESC;
```

## Troubleshooting

1. **Reset link not working**:
   - Check token hasn't expired (1 hour limit)
   - Verify database connection
   - Check `password_reset_tokens` table exists

2. **Email not sending**:
   - Configure email settings in `includes/password_reset.php`
   - Check server mail configuration
   - Verify email service credentials

3. **Can't create users**:
   - Ensure logged in as Admin
   - Check roles table exists
   - Verify database permissions