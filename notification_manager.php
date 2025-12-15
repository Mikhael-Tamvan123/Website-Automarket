<?php
class NotificationManager {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    public function createNotification($user_id, $title, $message, $type = 'info') {
        try {
            $sql = "INSERT INTO notifications (user_id, title, message, type, is_read, created_at) 
                    VALUES (?, ?, ?, ?, 0, NOW())";
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute([$user_id, $title, $message, $type]);
        } catch (PDOException $e) {
            error_log("Notification Error: " . $e->getMessage());
            return false;
        }
    }
    
    public static function addNotification($message, $type = 'info') {
        // Fallback method for simple notifications
        error_log("Notification: [$type] $message");
        return true;
    }
}

// Initialize notification manager
$notificationManager = new NotificationManager($pdo);
?>