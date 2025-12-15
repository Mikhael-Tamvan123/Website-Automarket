<?php
// car_manager.php
require_once __DIR__ . '/../config.php';
require_once 'subscription_manager.php';

class CarManager {
    private $pdo;
    private $subscriptionManager;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->subscriptionManager = new SubscriptionManager($pdo);
    }
    
    // Get mobil terbaru - FIXED
    public function getRecentCars($limit = 6) {
        $sql = "SELECT m.*, u.nama_lengkap as penjual_nama, u.no_telepon as penjual_telepon, u.email as penjual_email
                FROM mobil m 
                JOIN users u ON m.penjual_id = u.id 
                WHERE m.status = 'tersedia' 
                ORDER BY m.created_at DESC 
                LIMIT " . (int)$limit;
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Method untuk dashboard penjual - GET mobil milik user dengan info subscription
    public function getUserCars($penjual_id, $limit = null) {
        $upload_info = $this->subscriptionManager->getUploadLimitInfo($penjual_id);
        
        $sql = "SELECT m.*, 
                       (SELECT COUNT(*) FROM foto_mobil WHERE mobil_id = m.id) as photo_count,
                       (SELECT COUNT(*) FROM transaksi_booking WHERE mobil_id = m.id AND status = 'dikonfirmasi') as booking_count
                FROM mobil m 
                WHERE m.penjual_id = ? 
                ORDER BY m.created_at DESC";
        
        if ($limit) {
            $sql .= " LIMIT " . intval($limit);
        }
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$penjual_id]);
        $cars = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'cars' => $cars,
            'upload_info' => $upload_info
        ];
    }
    
    // Method untuk dashboard penjual - GET statistik mobil
    public function getUserCarStats($penjual_id) {
        $sql = "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'tersedia' THEN 1 ELSE 0 END) as available,
                SUM(CASE WHEN status = 'terjual' THEN 1 ELSE 0 END) as sold,
                SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as draft
                FROM mobil 
                WHERE penjual_id = ?";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$penjual_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // Method untuk mendapatkan mobil dengan detail lengkap (untuk car_detail.php)
    public function getCar($id) {
        $sql = "SELECT m.*, 
                       u.nama_lengkap as penjual_nama, 
                       u.no_telepon as penjual_telepon, 
                       u.email as penjual_email,
                       u.created_at as penjual_created_at
                FROM mobil m 
                JOIN users u ON m.penjual_id = u.id 
                WHERE m.id = ?";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // Alias untuk getCar (untuk kompatibilitas)
    public function getCarById($id) {
        return $this->getCar($id);
    }
    
    // Add mobil baru dengan pengecekan subscription
    public function addCar($penjual_id, $plat_mobil, $no_mesin, $rangka_mesin, $slinder, $merk, $model, $tahun, $warna, $bahan_bakar, $transmisi, $kilometer, $harga, $deskripsi) {
        // Cek upload limit sebelum menambah mobil
        if (!$this->subscriptionManager->canUploadCar($penjual_id)) {
            throw new Exception("Batas upload mobil telah tercapai. Upgrade subscription untuk menambah lebih banyak mobil.");
        }

        $sql = "INSERT INTO mobil (penjual_id, plat_mobil, no_mesin, rangka_mesin, slinder, merk, model, tahun, warna, bahan_bakar, transmisi, kilometer, harga, deskripsi) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        try {
            $stmt = $this->pdo->prepare($sql);
            $result = $stmt->execute([
                $penjual_id, $plat_mobil, $no_mesin, $rangka_mesin, $slinder, 
                $merk, $model, $tahun, $warna, $bahan_bakar, $transmisi, 
                $kilometer, $harga, $deskripsi
            ]);

            if ($result) {
                // Update upload count jika berhasil
                $this->subscriptionManager->updateUploadCount($penjual_id, 1);
                return $this->pdo->lastInsertId();
            }
            return false;
        } catch(PDOException $e) {
            error_log("Error addCar: " . $e->getMessage());
            return false;
        }
    }

    // Add mobil baru dengan data array (alternatif method)
    public function addCarWithData($data) {
        return $this->addCar(
            $data['penjual_id'],
            $data['plat_mobil'],
            $data['no_mesin'] ?? '',
            $data['rangka_mesin'] ?? '',
            $data['slinder'] ?? 0,
            $data['merk'],
            $data['model'],
            $data['tahun'],
            $data['warna'],
            $data['bahan_bakar'],
            $data['transmisi'],
            $data['kilometer'],
            $data['harga'],
            $data['deskripsi'] ?? ''
        );
    }
    
    // Update mobil
    public function updateCar($id, $penjual_id, $data) {
        $allowed_fields = ['plat_mobil', 'no_mesin', 'rangka_mesin', 'slinder', 'merk', 'model', 'tahun', 'warna', 'bahan_bakar', 'transmisi', 'kilometer', 'harga', 'deskripsi', 'status'];
        $updates = [];
        $params = [];
        
        foreach ($data as $field => $value) {
            if (in_array($field, $allowed_fields)) {
                $updates[] = "$field = ?";
                $params[] = $value;
            }
        }
        
        if (empty($updates)) {
            return false;
        }
        
        $sql = "UPDATE mobil SET " . implode(', ', $updates) . " WHERE id = ? AND penjual_id = ?";
        $params[] = $id;
        $params[] = $penjual_id;
        
        try {
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute($params);
        } catch(PDOException $e) {
            error_log("Error updateCar: " . $e->getMessage());
            return false;
        }
    }
    
    // Get semua mobil
    public function getAllCars($limit = null) {
        $sql = "SELECT m.*, u.nama_lengkap as penjual_nama, u.no_telepon as penjual_telepon, u.email as penjual_email
                FROM mobil m 
                JOIN users u ON m.penjual_id = u.id 
                WHERE m.status = 'tersedia' 
                ORDER BY m.created_at DESC";
        
        if ($limit) {
            $sql .= " LIMIT " . intval($limit);
        }
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Get mobil by penjual
    public function getCarsBySeller($penjual_id) {
        $sql = "SELECT * FROM mobil WHERE penjual_id = ? ORDER BY created_at DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$penjual_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Update status mobil
    public function updateCarStatus($id, $status) {
        $sql = "UPDATE mobil SET status = ? WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$status, $id]);
    }
    
    // Hapus mobil dengan update count
    public function deleteCar($id, $penjual_id) {
        try {
            // Hapus foto terlebih dahulu
            $this->deleteCarPhotos($id);
            
            $sql = "DELETE FROM mobil WHERE id = ? AND penjual_id = ?";
            $stmt = $this->pdo->prepare($sql);
            $result = $stmt->execute([$id, $penjual_id]);
            
            if ($result) {
                // Update upload count (dikurangi 1)
                $this->subscriptionManager->updateUploadCount($penjual_id, -1);
            }
            
            return $result;
        } catch(PDOException $e) {
            error_log("Error deleteCar: " . $e->getMessage());
            return false;
        }
    }
    
    // Hapus foto mobil
    private function deleteCarPhotos($mobil_id) {
        $sql = "DELETE FROM foto_mobil WHERE mobil_id = ?";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$mobil_id]);
    }
    
    // Upload foto mobil
    public function addCarPhoto($mobil_id, $nama_file) {
        $sql = "INSERT INTO foto_mobil (mobil_id, nama_file) VALUES (?, ?)";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$mobil_id, $nama_file]);
    }
    
    // Get foto mobil
    public function getCarPhotos($mobil_id) {
        $sql = "SELECT * FROM foto_mobil WHERE mobil_id = ? ORDER BY urutan, id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$mobil_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Get mobil dengan filter
    public function getAllCarsFiltered($search = '', $merk = '', $tahun_min = '', $tahun_max = '', $harga_min = '', $harga_max = '', $bahan_bakar = '', $transmisi = '') {
        $sql = "SELECT m.*, u.nama_lengkap as penjual_nama, u.no_telepon as penjual_telepon, u.email as penjual_email
                FROM mobil m 
                JOIN users u ON m.penjual_id = u.id 
                WHERE m.status = 'tersedia'";
        
        $params = [];
        
        if (!empty($search)) {
            $sql .= " AND (m.merk LIKE ? OR m.model LIKE ? OR m.deskripsi LIKE ?)";
            $search_term = "%$search%";
            $params[] = $search_term;
            $params[] = $search_term;
            $params[] = $search_term;
        }
        
        if (!empty($merk)) {
            $sql .= " AND m.merk = ?";
            $params[] = $merk;
        }
        
        if (!empty($tahun_min)) {
            $sql .= " AND m.tahun >= ?";
            $params[] = $tahun_min;
        }
        
        if (!empty($tahun_max)) {
            $sql .= " AND m.tahun <= ?";
            $params[] = $tahun_max;
        }
        
        if (!empty($harga_min)) {
            $sql .= " AND m.harga >= ?";
            $params[] = $harga_min;
        }
        
        if (!empty($harga_max)) {
            $sql .= " AND m.harga <= ?";
            $params[] = $harga_max;
        }
        
        if (!empty($bahan_bakar)) {
            $sql .= " AND m.bahan_bakar = ?";
            $params[] = $bahan_bakar;
        }
        
        if (!empty($transmisi)) {
            $sql .= " AND m.transmisi = ?";
            $params[] = $transmisi;
        }
        
        $sql .= " ORDER BY m.created_at DESC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Get merk unik untuk filter
    public function getUniqueBrands() {
        $sql = "SELECT DISTINCT merk FROM mobil WHERE status = 'tersedia' ORDER BY merk";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    
    // Get statistik untuk homepage
    public function getHomepageStats() {
        $stats = [];
        
        // Total mobil tersedia
        $sql = "SELECT COUNT(*) as total_cars FROM mobil WHERE status = 'tersedia'";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        $stats['total_cars'] = $stmt->fetchColumn();
        
        // Total penjual
        $sql = "SELECT COUNT(DISTINCT penjual_id) as total_sellers FROM mobil WHERE status = 'tersedia'";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        $stats['total_sellers'] = $stmt->fetchColumn();
        
        return $stats;
    }

    // Cek apakah penjual bisa upload mobil (untuk validasi di form)
    public function canUploadMoreCars($penjual_id) {
        return $this->subscriptionManager->canUploadCar($penjual_id);
    }

    // Get upload limit info untuk penjual
    public function getUploadLimitInfo($penjual_id) {
        return $this->subscriptionManager->getUploadLimitInfo($penjual_id);
    }
}

$carManager = new CarManager($pdo);
?>