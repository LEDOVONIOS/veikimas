<?php
/**
 * Authentication class for user management
 */
class Auth {
    private static $instance = null;
    private $db;
    private $user = null;
    
    private function __construct() {
        $this->db = Database::getInstance();
        $this->startSession();
        $this->checkSession();
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function startSession() {
        if (session_status() === PHP_SESSION_NONE) {
            session_name(SESSION_NAME);
            session_start();
        }
    }
    
    private function checkSession() {
        if (isset($_SESSION['user_id'])) {
            $this->user = $this->db->fetchOne(
                "SELECT * FROM " . DB_PREFIX . "users WHERE id = ? AND status = 'active'",
                [$_SESSION['user_id']]
            );
            
            if (!$this->user) {
                $this->logout();
            }
        }
    }
    
    public function login($username, $password) {
        $user = $this->db->fetchOne(
            "SELECT * FROM " . DB_PREFIX . "users WHERE (username = ? OR email = ?) AND status = 'active'",
            [$username, $username]
        );
        
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_role'] = $user['role'];
            
            // Update last login
            $this->db->update(
                DB_PREFIX . 'users',
                ['last_login' => date('Y-m-d H:i:s')],
                'id = ?',
                [$user['id']]
            );
            
            $this->user = $user;
            return true;
        }
        
        return false;
    }
    
    public function logout() {
        $_SESSION = [];
        session_destroy();
        $this->user = null;
    }
    
    public function isLoggedIn() {
        return $this->user !== null;
    }
    
    public function isAdmin() {
        return $this->user && $this->user['role'] === 'admin';
    }
    
    public function getUser() {
        return $this->user;
    }
    
    public function getUserId() {
        return $this->user ? $this->user['id'] : null;
    }
    
    public function getUserRole() {
        return $this->user ? $this->user['role'] : null;
    }
    
    public function requireLogin() {
        if (!$this->isLoggedIn()) {
            header('Location: login.php');
            exit;
        }
    }
    
    public function requireAdmin() {
        $this->requireLogin();
        if (!$this->isAdmin()) {
            header('Location: dashboard.php');
            exit;
        }
    }
    
    public function canCreateProject() {
        if (!$this->user) {
            return false;
        }
        
        if ($this->isAdmin()) {
            return true;
        }
        
        $projectCount = $this->db->fetchValue(
            "SELECT COUNT(*) FROM " . DB_PREFIX . "projects WHERE user_id = ?",
            [$this->user['id']]
        );
        
        return $projectCount < $this->user['project_limit'];
    }
    
    public function getProjectLimit() {
        return $this->user ? $this->user['project_limit'] : 0;
    }
    
    public function getProjectCount() {
        if (!$this->user) {
            return 0;
        }
        
        return $this->db->fetchValue(
            "SELECT COUNT(*) FROM " . DB_PREFIX . "projects WHERE user_id = ?",
            [$this->user['id']]
        );
    }
    
    public function updatePassword($userId, $newPassword) {
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        return $this->db->update(
            DB_PREFIX . 'users',
            ['password' => $hashedPassword],
            'id = ?',
            [$userId]
        );
    }
    
    public function createUser($data) {
        $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
        return $this->db->insert(DB_PREFIX . 'users', $data);
    }
    
    public function updateUser($userId, $data) {
        if (isset($data['password'])) {
            $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
        }
        return $this->db->update(DB_PREFIX . 'users', $data, 'id = ?', [$userId]);
    }
    
    public function deleteUser($userId) {
        return $this->db->delete(DB_PREFIX . 'users', 'id = ?', [$userId]);
    }
    
    public function getUserById($userId) {
        return $this->db->fetchOne(
            "SELECT * FROM " . DB_PREFIX . "users WHERE id = ?",
            [$userId]
        );
    }
    
    public function getAllUsers() {
        return $this->db->fetchAllArray(
            "SELECT u.*, COUNT(p.id) as project_count 
             FROM " . DB_PREFIX . "users u 
             LEFT JOIN " . DB_PREFIX . "projects p ON u.id = p.user_id 
             GROUP BY u.id 
             ORDER BY u.created_at DESC"
        );
    }
}