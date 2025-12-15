<?php
session_start();

// Koneksi database
$host = 'localhost';
$dbname = 'automarket';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Koneksi database gagal: " . $e->getMessage());
}

// Check login
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header("Location: login.php");
    exit;
}

if ($_POST) {
    $pengirim_id = $_SESSION['user_id'];
    $penerima_id = $_POST['penerima_id'];
    $mobil_id = $_POST['mobil_id'];
    $pesan_text = $_POST['pesan']; // Diubah namanya agar tidak bentrok dengan table pesan
    
    try {
        // Insert ke tabel 'pesan' yang sudah ada
        $sql = "INSERT INTO pesan (pengirim_id, penerima_id, mobil_id, pesan) 
                VALUES (?, ?, ?, ?)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$pengirim_id, $penerima_id, $mobil_id, $pesan_text]);
        
        // Redirect back to messages
        header("Location: messages.php?to=$penerima_id&car=$mobil_id");
        exit;
        
    } catch(PDOException $e) {
        die("Error: " . $e->getMessage());
    }
} else {
    header("Location: messages.php");
    exit;
}
?>