<?php
// Check if config exists
if (!file_exists('config/config.php')) {
    header('Location: install.php');
    exit;
}

require_once 'config/config.php';
require_once 'includes/init.php';

// If already logged in, redirect to dashboard
if ($auth->isLoggedIn()) {
    redirect('dashboard.php');
}

$error = '';

if (isPost()) {
    $username = sanitize(getPost('username'));
    $password = getPost('password');
    
    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
    } else {
        if ($auth->login($username, $password)) {
            redirect('dashboard.php');
        } else {
            $error = 'Invalid username or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .login-container {
            background: white;
            padding: 2.5rem;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            width: 100%;
            max-width: 400px;
            animation: fadeIn 0.5s ease-in;
        }
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        .login-header h1 {
            color: #333;
            font-size: 2rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        .login-header p {
            color: #666;
            margin: 0;
        }
        .form-control {
            border-radius: 25px;
            padding: 0.75rem 1.25rem;
            border: 2px solid #e0e0e0;
            transition: all 0.3s ease;
        }
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.1);
        }
        .input-group-text {
            border-radius: 25px 0 0 25px;
            border: 2px solid #e0e0e0;
            border-right: none;
            background: white;
            color: #667eea;
        }
        .input-group .form-control {
            border-radius: 0 25px 25px 0;
            border-left: none;
        }
        .btn-login {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 25px;
            padding: 0.75rem 2rem;
            font-weight: 600;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
            color: white;
        }
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
            color: white;
        }
        .btn-login:active {
            transform: translateY(0);
        }
        .alert {
            border-radius: 10px;
            border: none;
        }
        .logo {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.3);
        }
        .logo i {
            font-size: 2.5rem;
            color: white;
        }
        .remember-me {
            margin-top: 1rem;
        }
        .footer-text {
            text-align: center;
            margin-top: 2rem;
            color: #666;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <div class="logo">
                <i class="fas fa-chart-line"></i>
            </div>
            <h1><?php echo SITE_NAME; ?></h1>
            <p>Monitor your websites with ease</p>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <div class="input-group">
                    <div class="input-group-prepend">
                        <span class="input-group-text"><i class="fas fa-user"></i></span>
                    </div>
                    <input type="text" class="form-control" name="username" placeholder="Username or Email" 
                           value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" 
                           required autofocus>
                </div>
            </div>
            
            <div class="form-group">
                <div class="input-group">
                    <div class="input-group-prepend">
                        <span class="input-group-text"><i class="fas fa-lock"></i></span>
                    </div>
                    <input type="password" class="form-control" name="password" placeholder="Password" required>
                </div>
            </div>
            
            <div class="form-group remember-me">
                <div class="custom-control custom-checkbox">
                    <input type="checkbox" class="custom-control-input" id="remember" name="remember">
                    <label class="custom-control-label" for="remember">Remember me</label>
                </div>
            </div>
            
            <button type="submit" class="btn btn-primary btn-block btn-login">
                <i class="fas fa-sign-in-alt"></i> Login
            </button>
        </form>
        
        <?php if (ENABLE_REGISTRATION): ?>
        <div class="footer-text">
            Don't have an account? <a href="register.php">Sign up</a>
        </div>
        <?php endif; ?>
        
        <div class="footer-text">
            <small>&copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?>. All rights reserved.</small>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>