<?php
// favorite_manager.php
require_once __DIR__ . '/../config.php';

class FavoriteManager {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    // Tambah ke favorit
    public function addFavorite($pembeli_id, $mobil_id) {
        $sql = "INSERT INTO favorit (pembeli_id, mobil_id) VALUES (?, ?)";
        
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$pembeli_id, $mobil_id]);
            return true;
        } catch(PDOException $e) {
            return false;
        }
    }
    
    // Hapus dari favorit
    public function removeFavorite($pembeli_id, $mobil_id) {
        $sql = "DELETE FROM favorit WHERE pembeli_id = ? AND mobil_id = ?";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$pembeli_id, $mobil_id]);
    }
    
    // Cek apakah sudah difavoritkan
    public function isFavorite($pembeli_id, $mobil_id) {
        $sql = "SELECT id FROM favorit WHERE pembeli_id = ? AND mobil_id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$pembeli_id, $mobil_id]);
        return $stmt->fetch() !== false;
    }
    
    // Get semua favorit user
    public function getUserFavorites($pembeli_id) {
        $sql = "SELECT f.*, m.*, u.nama_lengkap as penjual_nama
                FROM favorit f
                JOIN mobil m ON f.mobil_id = m.id
                JOIN users u ON m.penjual_id = u.id
                WHERE f.pembeli_id = ?
                ORDER BY f.created_at DESC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$pembeli_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

$favoriteManager = new FavoriteManager($pdo);
?>