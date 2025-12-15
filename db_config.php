<?php
// includes/db_config.php
session_start();

$host = 'localhost';
$dbname = 'automarket';
$username = 'root';
$password = ''; // Sesuaikan dengan password MySQL Anda

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Koneksi database gagal: " . $e->getMessage());
}

// Include dan inisialisasi semua managers
require_once 'car_manager.php';
require_once 'favorite_manager.php';
require_once 'booking_manager.php';
require_once 'message_manager.php';
require_once 'subscription_manager.php';
require_once 'notification_manager.php';

// Inisialisasi managers
$carManager = new CarManager($pdo);
$favoriteManager = new FavoriteManager($pdo);
$bookingManager = new BookingManager($pdo);
$messageManager = new MessageManager($pdo);
$subscriptionManager = new SubscriptionManager($pdo);
$notificationManager = new NotificationManager($pdo);

require_once __DIR__ . '/subscription_manager.php';
$subscriptionManager = new SubscriptionManager($pdo);
?>