<?php
require_once '../db.php';
require_once '../includes/roles.php';

// Ensure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

requireLogin();
requireAdmin(); // Only admins can create users

$errors = [];
$success = false;

// Get all roles for the dropdown
$roles = getAllRoles();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize inputs
    $fullName = sanitizeInput($_POST['full_name'] ?? '');
    $email = sanitizeInput($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $roleId = intval($_POST['role_id'] ?? 0);
    $urlLimit = intval($_POST['url_limit'] ?? 10);
    
    // Validation
    if (empty($fullName)) {
        $errors[] = "Full name is required.";
    }
    
    if (empty($email)) {
        $errors[] = "Email is required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please enter a valid email address.";
    }
    
    if (empty($password)) {
        $errors[] = "Password is required.";
    } elseif (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters long.";
    }
    
    if ($roleId == 0) {
        $errors[] = "Please select a role.";
    }
    
    // Check if email already exists
    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $errors[] = "Email already registered.";
        }
    }
    
    // Create user if no errors
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            
            // Insert user
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (full_name, email, password_hash, role_id) VALUES (?, ?, ?, ?)");
            $stmt->execute([$fullName, $email, $passwordHash, $roleId]);
            $userId = $pdo->lastInsertId();
            
            // Get role name to check if it's Customer
            $roleStmt = $pdo->prepare("SELECT name FROM roles WHERE id = ?");
            $roleStmt->execute([$roleId]);
            $role = $roleStmt->fetch();
            
            // If Customer role, set URL limit
            if ($role && $role['name'] === 'Customer') {
                setUserUrlLimit($userId, $urlLimit);
            }
            
            $pdo->commit();
            $success = true;
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            $errors[] = "Failed to create user. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create User - Admin Panel</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .create-user-container {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .form-card {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
        }
        
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
        }
        
        .form-group small {
            display: block;
            margin-top: 5px;
            color: #666;
        }
        
        .url-limit-group {
            display: none;
        }
        
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .btn-group {
            display: flex;
            gap: 10px;
            margin-top: 25px;
        }
        
        .password-info {
            background-color: #e3f2fd;
            border: 1px solid #90caf9;
            color: #1565c0;
            padding: 12px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
    </style>
    <script>
        function toggleUrlLimit() {
            const roleSelect = document.getElementById('role_id');
            const urlLimitGroup = document.getElementById('url_limit_group');
            const selectedOption = roleSelect.options[roleSelect.selectedIndex];
            
            if (selectedOption && selectedOption.text === 'Customer') {
                urlLimitGroup.style.display = 'block';
            } else {
                urlLimitGroup.style.display = 'none';
            }
        }
    </script>
</head>
<body>
    <nav class="navbar">
        <div class="container">
            <div class="nav-brand">
                <h2>Project Monitor - Admin</h2>
            </div>
            <ul class="nav-menu">
                <li><a href="../dashboard.php">Dashboard</a></li>
                <li><a href="manage-users.php">Manage Users</a></li>
                <li><a href="create-user.php" class="active">Create User</a></li>
                <li class="nav-user">
                    <span>Admin: <?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
                    <a href="../logout.php" class="btn-logout">Logout</a>
                </li>
            </ul>
        </div>
    </nav>

    <main class="create-user-container">
        <h1>Create New User</h1>
        
        <div class="form-card">
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <p>User created successfully!</p>
                    <p>The user can now login with their credentials.</p>
                </div>
                <div class="btn-group">
                    <a href="create-user.php" class="btn btn-primary">Create Another User</a>
                    <a href="manage-users.php" class="btn btn-secondary">View All Users</a>
                </div>
            <?php else: ?>
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-error">
                        <?php foreach ($errors as $error): ?>
                            <p><?php echo htmlspecialchars($error); ?></p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                
                <div class="password-info">
                    <strong>Note:</strong> Make sure to securely share the login credentials with the new user. 
                    They can reset their password using the "Forgot Password" link on the login page.
                </div>
                
                <form method="POST" action="create-user.php">
                    <div class="form-group">
                        <label for="full_name">Full Name *</label>
                        <input type="text" id="full_name" name="full_name" 
                               value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>" 
                               required>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email Address *</label>
                        <input type="email" id="email" name="email" 
                               value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" 
                               required>
                        <small>The user will use this email to login</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="password">Initial Password *</label>
                        <input type="password" id="password" name="password" required>
                        <small>Minimum 8 characters. The user can change this later.</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="role_id">Role *</label>
                        <select id="role_id" name="role_id" required onchange="toggleUrlLimit()">
                            <option value="">Select a role</option>
                            <?php foreach ($roles as $role): ?>
                                <option value="<?php echo $role['id']; ?>" 
                                        <?php echo (isset($_POST['role_id']) && $_POST['role_id'] == $role['id']) ? 'selected' : ''; ?>>
                                    <?php echo $role['name']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group url-limit-group" id="url_limit_group">
                        <label for="url_limit">URL Limit</label>
                        <input type="number" id="url_limit" name="url_limit" 
                               value="<?php echo htmlspecialchars($_POST['url_limit'] ?? '10'); ?>" 
                               min="1" max="9999">
                        <small>Maximum number of URLs this customer can add (only applies to Customer role)</small>
                    </div>
                    
                    <div class="btn-group">
                        <button type="submit" class="btn btn-primary">Create User</button>
                        <a href="manage-users.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
                
                <script>
                    // Initialize URL limit visibility on page load
                    toggleUrlLimit();
                </script>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>