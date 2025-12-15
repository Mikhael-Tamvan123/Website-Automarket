<?php
// message_manager.php
require_once __DIR__ . '/../config.php';

class MessageManager {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    // Kirim pesan
    public function sendMessage($pengirim_id, $penerima_id, $mobil_id, $pesan) {
        $sql = "INSERT INTO pesan (pengirim_id, penerima_id, mobil_id, pesan) VALUES (?, ?, ?, ?)";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$pengirim_id, $penerima_id, $mobil_id, $pesan]);
    }
    
    // Get percakapan antara dua user
    public function getConversation($user1_id, $user2_id, $mobil_id = null) {
        $sql = "SELECT p.*, u.nama_lengkap as pengirim_nama
                FROM pesan p
                JOIN users u ON p.pengirim_id = u.id
                WHERE ((pengirim_id = ? AND penerima_id = ?) OR (pengirim_id = ? AND penerima_id = ?))";
        
        $params = [$user1_id, $user2_id, $user2_id, $user1_id];
        
        if ($mobil_id) {
            $sql .= " AND (p.mobil_id = ? OR p.mobil_id IS NULL)";
            $params[] = $mobil_id;
        }
        
        $sql .= " ORDER BY p.created_at ASC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Get daftar percakapan user
    public function getUserConversations($user_id) {
        $sql = "SELECT DISTINCT 
                CASE 
                    WHEN p.pengirim_id = ? THEN p.penerima_id 
                    ELSE p.pengirim_id 
                END as other_user_id,
                u.nama_lengkap as other_user_name,
                m.merk, m.model,
                (SELECT pesan FROM pesan WHERE (pengirim_id = ? AND penerima_id = other_user_id) OR (pengirim_id = other_user_id AND penerima_id = ?) ORDER BY created_at DESC LIMIT 1) as last_message,
                (SELECT created_at FROM pesan WHERE (pengirim_id = ? AND penerima_id = other_user_id) OR (pengirim_id = other_user_id AND penerima_id = ?) ORDER BY created_at DESC LIMIT 1) as last_message_time
                FROM pesan p
                JOIN users u ON u.id = CASE WHEN p.pengirim_id = ? THEN p.penerima_id ELSE p.pengirim_id END
                LEFT JOIN mobil m ON p.mobil_id = m.id
                WHERE p.pengirim_id = ? OR p.penerima_id = ?
                ORDER BY last_message_time DESC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Tandai pesan sebagai dibaca
    public function markAsRead($pesan_ids) {
        if (empty($pesan_ids)) return false;
        
        $placeholders = str_repeat('?,', count($pesan_ids) - 1) . '?';
        $sql = "UPDATE pesan SET dibaca = 1 WHERE id IN ($placeholders)";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($pesan_ids);
    }
}

$messageManager = new MessageManager($pdo);
?>