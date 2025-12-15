<?php
// includes/auth.php
require_once __DIR__ . '/../config.php';

class Auth {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    // Register user baru
    public function register($username, $password, $email, $nama_lengkap, $no_ktp, $no_telepon, $role) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        $sql = "INSERT INTO users (username, password, email, nama_lengkap, no_ktp, no_telepon, role) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$username, $hashed_password, $email, $nama_lengkap, $no_ktp, $no_telepon, $role]);
            return $this->pdo->lastInsertId();
        } catch(PDOException $e) {
            return false;
        }
    }
    
    // Login user
    public function login($username, $password) {
        $sql = "SELECT * FROM users WHERE username = ? OR email = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['nama_lengkap'] = $user['nama_lengkap'];
            return true;
        }
        return false;
    }
    
    // Logout user
    public function logout() {
        session_destroy();
        header("Location: ../index.php");
        exit();
    }
    
    // Cek apakah user sudah login - METHOD INI YANG HILANG
    public function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }
    
    // Get user data
    public function getUser($id) {
        $sql = "SELECT * FROM users WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // Cek role user
    public function checkRole($required_role) {
        return isset($_SESSION['role']) && $_SESSION['role'] === $required_role;
    }
    
    // Redirect berdasarkan role
    public function redirectByRole() {
        if (isset($_SESSION['role'])) {
            switch ($_SESSION['role']) {
                case 'penjual':
                    header("Location: seller/dashboard.php");
                    break;
                case 'pembeli':
                    header("Location: buyer/dashboard.php");
                    break;
                case 'admin':
                    header("Location: admin/dashboard.php");
                    break;
                default:
                    header("Location: index.php");
            }
            exit();
        }
    }
}

// Hanya inisialisasi $auth jika $pdo tersedia
if (isset($pdo)) {
    $auth = new Auth($pdo);
} else {
    die("Database connection not available");
}
?>