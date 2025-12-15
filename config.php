<?php
// config.php - PERBAIKI SESSION START DAN PATH
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Error reporting untuk development
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'automarket');
define('DB_USER', 'root');
define('DB_PASS', '');

// Base URL - PERBAIKI SESUAI NAMA FOLDER 'Automarket'
define('BASE_URL', 'http://localhost/Automarket/');

// Upload directories - PERBAIKAN PATH dengan DIRECTORY_SEPARATOR
define('UPLOAD_CAR_DIR', __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'cars' . DIRECTORY_SEPARATOR);
define('UPLOAD_SIGNATURE_DIR', __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'signatures' . DIRECTORY_SEPARATOR);
define('UPLOAD_PROFILE_DIR', __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'profiles' . DIRECTORY_SEPARATOR);

// Create upload directories if they don't exist
$upload_dirs = [UPLOAD_CAR_DIR, UPLOAD_SIGNATURE_DIR, UPLOAD_PROFILE_DIR];
foreach ($upload_dirs as $dir) {
    if (!file_exists($dir)) {
        mkdir($dir, 0777, true);
    }
}

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Koneksi database gagal: " . $e->getMessage());
}

// Fungsi untuk mencegah SQL injection
function sanitize($data) {
    if (is_array($data)) {
        return array_map('sanitize', $data);
    }
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

// Fungsi untuk format harga
function format_rupiah($number) {
    return 'Rp ' . number_format($number, 0, ',', '.');
}

// Fungsi untuk redirect - PERBAIKAN
function redirect($url) {
    if (!preg_match("/^https?:\/\//", $url)) {
        // Jika URL relative, tambahkan base URL
        $url = BASE_URL . ltrim($url, '/');
    }
    header("Location: " . $url);
    exit();
}

// Fungsi untuk cek login
function check_login() {
    if (!isset($_SESSION['user_id'])) {
        $_SESSION['error'] = "Silakan login terlebih dahulu";
        redirect('login.php');
    }
}

// Fungsi untuk cek role
function check_role($allowed_roles) {
    if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], (array)$allowed_roles)) {
        $_SESSION['error'] = "Anda tidak memiliki akses ke halaman ini";
        redirect('index.php');
    }
}

// Include subscription_manager.php jika ada
$subscription_file = __DIR__ . DIRECTORY_SEPARATOR . 'subscription_manager.php';
if (file_exists($subscription_file)) {
    require_once $subscription_file;
    if (class_exists('SubscriptionManager')) {
        $subscriptionManager = new SubscriptionManager($pdo);
    } else {
        // Jika class tidak ada, buat object kosong untuk menghindari error
        $subscriptionManager = new class($pdo) {
            private $pdo;
            public function __construct($pdo) {
                $this->pdo = $pdo;
            }
            public function canUploadCar($seller_id) {
                return [
                    'can_upload' => true,
                    'current_count' => 0,
                    'max_allowed' => 999,
                    'remaining' => 999
                ];
            }
            public function getSubscriptionInfo($seller_id) {
                return ['max_mobil' => 999, 'nama_plan' => 'Free'];
            }
        };
    }
} else {
    // Jika file tidak ditemukan, buat object dummy untuk testing
    $subscriptionManager = new class($pdo) {
        private $pdo;
        public function __construct($pdo) {
            $this->pdo = $pdo;
        }
        public function canUploadCar($seller_id) {
            return [
                'can_upload' => true,
                'current_count' => 0,
                'max_allowed' => 999,
                'remaining' => 999
            ];
        }
        public function getSubscriptionInfo($seller_id) {
            return ['max_mobil' => 999, 'nama_plan' => 'Free'];
        }
    };
}

?>