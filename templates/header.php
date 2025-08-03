<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle . ' - ' : ''; ?><?php echo SITE_NAME; ?></title>
    
    <!-- CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css">
    
    <!-- Custom CSS -->
    <style>
        :root {
            --primary-color: #667eea;
            --secondary-color: #764ba2;
            --success-color: #10b981;
            --danger-color: #ef4444;
            --warning-color: #f59e0b;
            --info-color: #3b82f6;
            --light-color: #f8f9fa;
            --dark-color: #343a40;
            
            /* Dark theme colors */
            --bg-primary: #0f1419;
            --bg-secondary: #1a1f2e;
            --bg-tertiary: #252d3d;
            --bg-hover: #2a3444;
            --text-primary: #ffffff;
            --text-secondary: #94a3b8;
            --text-muted: #666666;
            --border-color: #334155;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background-color: var(--bg-primary);
            color: var(--text-primary);
        }
        
        /* Navbar */
        .navbar {
            background: var(--bg-secondary);
            border-bottom: 1px solid var(--border-color);
            box-shadow: 0 1px 3px rgba(0,0,0,.3);
            padding: 1rem 0;
        }
        
        .navbar-brand {
            font-weight: 600;
            font-size: 1.4rem;
            color: var(--text-primary) !important;
        }
        
        .navbar-nav .nav-link {
            color: var(--text-secondary) !important;
            font-weight: 500;
            margin: 0 0.5rem;
            transition: all 0.3s ease;
        }
        
        .navbar-nav .nav-link:hover {
            color: var(--text-primary) !important;
            background: var(--bg-hover);
            border-radius: 6px;
        }
        
        .navbar-nav .nav-link.active {
            color: var(--text-primary) !important;
            background: var(--bg-hover);
            border-radius: 6px;
            padding: 0.5rem 1rem;
        }
        
        /* Cards */
        .card {
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.3);
            transition: all 0.3s ease;
            margin-bottom: 1.5rem;
        }
        
        .card:hover {
            border-color: var(--primary-color);
            box-shadow: 0 4px 12px rgba(0,0,0,0.4);
        }
        
        .card-header {
            background: var(--bg-tertiary);
            border-bottom: 1px solid var(--border-color);
            font-weight: 600;
            font-size: 1.1rem;
            color: var(--text-primary);
        }
        
        /* Status Cards */
        .status-card {
            border-radius: 10px;
            padding: 1.5rem;
            color: white;
            position: relative;
            overflow: hidden;
        }
        
        .status-card.up {
            background: linear-gradient(135deg, #00b09b 0%, #96c93d 100%);
        }
        
        .status-card.down {
            background: linear-gradient(135deg, #eb3349 0%, #f45c43 100%);
        }
        
        .status-card .icon {
            position: absolute;
            right: 20px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 3rem;
            opacity: 0.3;
        }
        
        /* Buttons */
        .btn-primary {
            background: var(--primary-color);
            border: none;
            border-radius: 6px;
            padding: 0.5rem 1.5rem;
            font-weight: 500;
            transition: all 0.3s ease;
            color: white;
        }
        
        .btn-primary:hover {
            background: var(--secondary-color);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
            color: white;
        }
        
        .btn-secondary {
            background: var(--bg-tertiary);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
        }
        
        .btn-secondary:hover {
            background: var(--bg-hover);
            color: var(--text-primary);
        }
        
        .btn-outline-light {
            color: var(--text-primary);
            border-color: var(--border-color);
        }
        
        .btn-outline-light:hover {
            background: var(--bg-hover);
            color: var(--text-primary);
            border-color: var(--primary-color);
        }
        
        /* Tables */
        .table {
            background: var(--bg-secondary);
            color: var(--text-primary);
            border-radius: 8px;
            overflow: hidden;
        }
        
        .table thead th {
            background: var(--bg-tertiary);
            border: none;
            border-bottom: 1px solid var(--border-color);
            font-weight: 600;
            color: var(--text-primary);
            text-transform: uppercase;
            font-size: 0.85rem;
            letter-spacing: 0.5px;
        }
        
        .table tbody tr {
            transition: all 0.3s ease;
            border-bottom: 1px solid var(--border-color);
        }
        
        .table tbody tr:hover {
            background: var(--bg-hover);
        }
        
        .table td, .table th {
            border-top: none;
        }
        
        /* Badges */
        .badge {
            padding: 0.4rem 0.8rem;
            font-weight: 500;
            border-radius: 20px;
        }
        
        /* Charts */
        .chart-container {
            position: relative;
            height: 300px;
            width: 100%;
        }
        
        /* Animations */
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
        
        .fade-in {
            animation: fadeIn 0.5s ease-in;
        }
        
        /* Loading Spinner */
        .spinner-border {
            width: 1.5rem;
            height: 1.5rem;
            border-width: 0.2rem;
        }
        
        /* Form Controls */
        .form-control {
            background: var(--bg-tertiary);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
            border-radius: 6px;
        }
        
        .form-control:focus {
            background: var(--bg-tertiary);
            border-color: var(--primary-color);
            color: var(--text-primary);
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        
        .custom-select {
            background: var(--bg-tertiary) url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' width='4' height='5' viewBox='0 0 4 5'%3e%3cpath fill='%23ffffff' d='M2 0L0 2h4zm0 5L0 3h4z'/%3e%3c/svg%3e") no-repeat right .75rem center/8px 10px;
            border: 1px solid var(--border-color);
            color: var(--text-primary);
        }
        
        .custom-control-label::before {
            background: var(--bg-tertiary);
            border-color: var(--border-color);
        }
        
        /* Dropdowns */
        .dropdown-menu {
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        }
        
        .dropdown-item {
            color: var(--text-primary);
        }
        
        .dropdown-item:hover {
            background: var(--bg-hover);
            color: var(--text-primary);
        }
        
        .dropdown-divider {
            border-color: var(--border-color);
        }
        
        /* Alerts */
        .alert {
            border: none;
            border-radius: 8px;
        }
        
        .alert-warning {
            background: rgba(245, 158, 11, 0.1);
            color: var(--warning-color);
            border: 1px solid rgba(245, 158, 11, 0.2);
        }
        
        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
        }
        
        ::-webkit-scrollbar-track {
            background: var(--bg-secondary);
        }
        
        ::-webkit-scrollbar-thumb {
            background: var(--bg-hover);
            border-radius: 4px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: var(--primary-color);
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .navbar-nav {
                text-align: center;
            }
            
            .card {
                margin-bottom: 1rem;
            }
        }
    </style>
    
    <?php if (isset($additionalCSS)) echo $additionalCSS; ?>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">
                <i class="fas fa-chart-line"></i> <?php echo SITE_NAME; ?>
            </a>
            
            <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ml-auto">
                    <li class="nav-item">
                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>" 
                           href="dashboard.php">
                            <i class="fas fa-tachometer-alt"></i> Dashboard
                        </a>
                    </li>
                    
                    <?php if ($auth->isAdmin()): ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'users.php' ? 'active' : ''; ?>" 
                           href="users.php">
                            <i class="fas fa-users"></i> Users
                        </a>
                    </li>
                    <?php endif; ?>
                    
                    <li class="nav-item">
                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : ''; ?>" 
                           href="profile.php">
                            <i class="fas fa-user-circle"></i> Profile
                        </a>
                    </li>
                    
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    
    <!-- Main Content -->
    <main class="py-4">
        <div class="container-fluid">