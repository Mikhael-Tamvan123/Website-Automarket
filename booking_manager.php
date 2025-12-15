<?php
// booking_manager.php
require_once __DIR__ . '/../config.php';

class BookingManager {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    // Hitung fee platform (5% dari uang booking)
    public function calculatePlatformFee($uang_booking, $fee_percentage = 5.0) {
        $fee_amount = ($uang_booking * $fee_percentage) / 100;
        $seller_receives = $uang_booking - $fee_amount;
        
        return [
            'fee_amount' => $fee_amount,
            'seller_receives' => $seller_receives,
            'fee_percentage' => $fee_percentage
        ];
    }
    
    // Buat booking baru dengan fee platform
    public function createBooking($penjual_id, $pembeli_id, $mobil_id, $harga_mobil, $uang_booking, $no_rekening_penjual, $nama_bank_penjual, $ketentuan_booking) {
        $kode_booking = 'BK' . date('Ymd') . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
        
        // Hitung fee platform
        $fee_calculation = $this->calculatePlatformFee($uang_booking);
        
        $sql = "INSERT INTO transaksi_booking 
                (kode_booking, penjual_id, pembeli_id, mobil_id, harga_mobil, uang_booking, 
                 no_rekening_penjual, nama_bank_penjual, ketentuan_booking, 
                 fee_platform, persentase_fee, status_pembayaran_fee) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')";
        
        try {
            $this->pdo->beginTransaction();
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $kode_booking, $penjual_id, $pembeli_id, $mobil_id, $harga_mobil, 
                $uang_booking, $no_rekening_penjual, $nama_bank_penjual, $ketentuan_booking,
                $fee_calculation['fee_amount'], $fee_calculation['fee_percentage']
            ]);
            
            // Update status mobil menjadi dipesan
            $update_sql = "UPDATE mobil SET status = 'dipesan' WHERE id = ?";
            $update_stmt = $this->pdo->prepare($update_sql);
            $update_stmt->execute([$mobil_id]);
            
            $this->pdo->commit();
            return [
                'kode_booking' => $kode_booking,
                'fee_info' => $fee_calculation
            ];
        } catch(PDOException $e) {
            $this->pdo->rollBack();
            error_log("Booking error: " . $e->getMessage());
            return false;
        }
    }

    // Buat booking dengan data array (alternatif method)
    public function createBookingWithData($data) {
        return $this->createBooking(
            $data['penjual_id'],
            $data['pembeli_id'],
            $data['mobil_id'],
            $data['harga_mobil'],
            $data['uang_booking'],
            $data['no_rekening_penjual'],
            $data['nama_bank_penjual'],
            $data['ketentuan_booking'] ?? ''
        );
    }
    
    // Get semua booking
    public function getAllBookings() {
        $sql = "SELECT tb.*, 
                pj.nama_lengkap as penjual_nama, 
                pb.nama_lengkap as pembeli_nama,
                m.merk, m.model, m.plat_mobil
                FROM transaksi_booking tb
                JOIN users pj ON tb.penjual_id = pj.id
                JOIN users pb ON tb.pembeli_id = pb.id
                JOIN mobil m ON tb.mobil_id = m.id
                ORDER BY tb.created_at DESC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Get booking by ID
    public function getBookingById($id) {
        $sql = "SELECT tb.*, 
                pj.nama_lengkap as penjual_nama, pj.no_ktp as penjual_ktp, pj.no_telepon as penjual_telepon,
                pb.nama_lengkap as pembeli_nama, pb.no_ktp as pembeli_ktp, pb.no_telepon as pembeli_telepon,
                m.merk, m.model, m.plat_mobil, m.tahun, m.warna, m.no_mesin, m.rangka_mesin, m.slinder
                FROM transaksi_booking tb
                JOIN users pj ON tb.penjual_id = pj.id
                JOIN users pb ON tb.pembeli_id = pb.id
                JOIN mobil m ON tb.mobil_id = m.id
                WHERE tb.id = ?";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // Get booking by user
    public function getBookingsByUser($user_id) {
        $sql = "SELECT tb.*, 
                pj.nama_lengkap as penjual_nama, 
                pb.nama_lengkap as pembeli_nama,
                m.merk, m.model, m.plat_mobil
                FROM transaksi_booking tb
                JOIN users pj ON tb.penjual_id = pj.id
                JOIN users pb ON tb.pembeli_id = pb.id
                JOIN mobil m ON tb.mobil_id = m.id
                WHERE tb.penjual_id = ? OR tb.pembeli_id = ?
                ORDER BY tb.created_at DESC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$user_id, $user_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Get booking stats untuk dashboard
    public function getUserBookingStats($user_id, $role = 'pembeli') {
        if ($role === 'pembeli') {
            $sql = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN status = 'dikonfirmasi' THEN 1 ELSE 0 END) as confirmed,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                    SUM(CASE WHEN status = 'dibatalkan' THEN 1 ELSE 0 END) as cancelled
                    FROM transaksi_booking 
                    WHERE pembeli_id = ?";
        } else {
            $sql = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN status = 'dikonfirmasi' THEN 1 ELSE 0 END) as confirmed,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                    SUM(CASE WHEN status = 'dibatalkan' THEN 1 ELSE 0 END) as cancelled
                    FROM transaksi_booking 
                    WHERE penjual_id = ?";
        }
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$user_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Get booking aktif untuk dashboard
    public function getUserActiveBookings($user_id, $role = 'pembeli', $limit = 5) {
        if ($role === 'pembeli') {
            $sql = "SELECT tb.*, m.merk, m.model, m.tahun
                    FROM transaksi_booking tb
                    JOIN mobil m ON tb.mobil_id = m.id
                    WHERE tb.pembeli_id = ? AND tb.status IN ('pending', 'dikonfirmasi')
                    ORDER BY tb.created_at DESC 
                    LIMIT ?";
        } else {
            $sql = "SELECT tb.*, m.merk, m.model, m.tahun
                    FROM transaksi_booking tb
                    JOIN mobil m ON tb.mobil_id = m.id
                    WHERE tb.penjual_id = ? AND tb.status IN ('pending', 'dikonfirmasi')
                    ORDER BY tb.created_at DESC 
                    LIMIT ?";
        }
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$user_id, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Update status booking
    public function updateBookingStatus($id, $status) {
        $sql = "UPDATE transaksi_booking SET status = ? WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$status, $id]);
    }

    // Update status pembayaran fee
    public function updateFeePaymentStatus($id, $status) {
        $sql = "UPDATE transaksi_booking SET status_pembayaran_fee = ? WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$status, $id]);
    }

    // Get revenue stats untuk admin
    public function getRevenueStats() {
        $stats = [];

        // Total fee revenue
        $sql = "SELECT SUM(fee_platform) as total_fee_revenue 
                FROM transaksi_booking 
                WHERE status_pembayaran_fee = 'paid'";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        $stats['total_fee_revenue'] = $stmt->fetchColumn() ?? 0;

        // Total fee pending
        $sql = "SELECT SUM(fee_platform) as pending_fee_revenue 
                FROM transaksi_booking 
                WHERE status_pembayaran_fee = 'pending'";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        $stats['pending_fee_revenue'] = $stmt->fetchColumn() ?? 0;

        // Total booking value
        $sql = "SELECT SUM(uang_booking) as total_booking_value 
                FROM transaksi_booking 
                WHERE status IN ('dikonfirmasi', 'completed')";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        $stats['total_booking_value'] = $stmt->fetchColumn() ?? 0;

        // Booking stats per bulan
        $sql = "SELECT 
                DATE_FORMAT(created_at, '%Y-%m') as month,
                COUNT(*) as booking_count,
                SUM(uang_booking) as total_booking_amount,
                SUM(fee_platform) as total_fee_amount
                FROM transaksi_booking 
                WHERE status IN ('dikonfirmasi', 'completed')
                GROUP BY DATE_FORMAT(created_at, '%Y-%m')
                ORDER BY month DESC
                LIMIT 6";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        $stats['monthly_stats'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $stats;
    }
    
    // Tambah tanda tangan dan stempel
    public function addSignature($id, $tanda_tangan_pembeli, $stempel_admin) {
        $sql = "UPDATE transaksi_booking SET tanda_tangan_pembeli = ?, stempel_admin = ? WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$tanda_tangan_pembeli, $stempel_admin, $id]);
    }

    // Get booking untuk invoice/receipt
    public function getBookingForInvoice($booking_id) {
        $sql = "SELECT tb.*,
                pj.nama_lengkap as penjual_nama, pj.no_telepon as penjual_telepon, pj.email as penjual_email,
                pb.nama_lengkap as pembeli_nama, pb.no_telepon as pembeli_telepon, pb.email as pembeli_email,
                m.merk, m.model, m.tahun, m.warna, m.plat_mobil,
                (tb.uang_booking - tb.fee_platform) as penjual_receives
                FROM transaksi_booking tb
                JOIN users pj ON tb.penjual_id = pj.id
                JOIN users pb ON tb.pembeli_id = pb.id
                JOIN mobil m ON tb.mobil_id = m.id
                WHERE tb.id = ?";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$booking_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

$bookingManager = new BookingManager($pdo);
?>