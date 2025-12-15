<?php
require_once 'config.php';

session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

$user_id = $_SESSION['user_id'];

try {
    // Hitung pesan belum dibaca
    $sql = "SELECT COUNT(*) as unread_count FROM messages WHERE penerima_id = ? AND dibaca = 0";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'unread_messages' => $result['unread_count'] ?? 0
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>