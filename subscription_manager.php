<?php
class SubscriptionManager {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Cek apakah penjual bisa upload mobil baru
     */
    public function canUploadCar($seller_id) {
        // Hitung jumlah mobil aktif penjual
        $sql = "SELECT COUNT(*) as total 
                FROM mobil 
                WHERE penjual_id = ? AND status IN ('tersedia', 'dipesan')";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$seller_id]);
        $current_count = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Get max upload limit dari subscription
        $sql = "SELECT u.max_mobil, sp.max_mobil as plan_max
                FROM users u
                LEFT JOIN seller_subscriptions ss ON u.id = ss.seller_id AND ss.status = 'active'
                LEFT JOIN subscription_plans sp ON ss.plan_id = sp.id
                WHERE u.id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$seller_id]);
        $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $max_upload = $user_data['plan_max'] ?? $user_data['max_mobil'] ?? 3;
        
        return [
            'can_upload' => $current_count < $max_upload,
            'current_count' => $current_count,
            'max_allowed' => $max_upload,
            'remaining' => max(0, $max_upload - $current_count)
        ];
    }
    
    /**
     * Get subscription info untuk penjual
     */
    public function getSubscriptionInfo($seller_id) {
        $sql = "SELECT u.*, sp.nama_plan, sp.harga_bulanan, sp.max_mobil, 
                       sp.fitur, ss.mulai_tanggal, ss.berakhir_tanggal, ss.status as sub_status
                FROM users u
                LEFT JOIN seller_subscriptions ss ON u.id = ss.seller_id 
                    AND (ss.status = 'active' OR ss.status IS NULL)
                LEFT JOIN subscription_plans sp ON ss.plan_id = sp.id
                WHERE u.id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$seller_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get semua subscription plans
     */
    public function getAllPlans() {
        $sql = "SELECT * FROM subscription_plans ORDER BY harga_bulanan ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Subscribe penjual ke plan tertentu
     */
    public function subscribeSeller($seller_id, $plan_id) {
        try {
            $this->pdo->beginTransaction();
            
            // Get plan details
            $sql = "SELECT * FROM subscription_plans WHERE id = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$plan_id]);
            $plan = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$plan) {
                throw new Exception("Plan tidak ditemukan");
            }
            
            // Nonaktifkan subscription lama
            $sql = "UPDATE seller_subscriptions 
                    SET status = 'inactive' 
                    WHERE seller_id = ? AND status = 'active'";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$seller_id]);
            
            // Tambah subscription baru
            $mulai_tanggal = date('Y-m-d');
            $berakhir_tanggal = date('Y-m-d', strtotime('+30 days'));
            
            $sql = "INSERT INTO seller_subscriptions 
                    (seller_id, plan_id, status, mulai_tanggal, berakhir_tanggal) 
                    VALUES (?, ?, 'active', ?, ?)";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$seller_id, $plan_id, $mulai_tanggal, $berakhir_tanggal]);
            
            // Update user max_mobil
            $sql = "UPDATE users SET max_mobil = ?, subscription_status = ? WHERE id = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$plan['max_mobil'], strtolower($plan['nama_plan']), $seller_id]);
            
            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log("Subscription error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check subscription expiry setiap hari
     */
    public function checkExpiredSubscriptions() {
        $sql = "UPDATE seller_subscriptions 
                SET status = 'expired' 
                WHERE status = 'active' AND berakhir_tanggal < CURDATE()";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute();
    }
}
?>