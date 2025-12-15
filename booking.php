<?php
session_start();
require_once 'config.php';

// Cek apakah user sudah login sebagai pembeli
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'pembeli') {
    header("Location: login.php");
    exit();
}

// Cek apakah parameter car_id ada
if (!isset($_GET['car_id']) || empty($_GET['car_id'])) {
    header("Location: cars.php");
    exit();
}

$car_id = $_GET['car_id'];
$pembeli_id = $_SESSION['user_id'];

// Debug: Tampilkan car_id
echo "<!-- Debug: car_id = " . $car_id . " -->";

// Pastikan koneksi database berhasil
if (!$pdo) {
    die("Koneksi database gagal. Silakan coba lagi nanti.");
}

// Ambil informasi admin untuk rekening bank
$admin_info = null;
$admin_sql = "SELECT * FROM users WHERE role = 'admin' LIMIT 1";
$admin_stmt = $pdo->prepare($admin_sql);
$admin_stmt->execute();
$admin_info = $admin_stmt->fetch(PDO::FETCH_ASSOC);

// Debug query
try {
    $sql = "SELECT m.*, u.nama_lengkap as seller_name, u.email as seller_email, u.no_telepon as seller_phone 
            FROM mobil m 
            JOIN users u ON m.penjual_id = u.id 
            WHERE m.id = ? AND m.status = 'tersedia'";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$car_id]);
    $car = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$car) {
        // Cek apakah mobil sudah dibooking
        $check_booking_sql = "SELECT * FROM transaksi_booking WHERE mobil_id = ? AND status IN ('pending', 'dikonfirmasi') LIMIT 1";
        $check_booking_stmt = $pdo->prepare($check_booking_sql);
        $check_booking_stmt->execute([$car_id]);
        $existing_booking = $check_booking_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existing_booking) {
            $_SESSION['error'] = "Maaf, mobil ini sudah dibooking oleh pembeli lain.";
            header("Location: car_detail.php?id=" . $car_id);
            exit();
        } else {
            die("Mobil tidak tersedia atau tidak ditemukan!");
        }
    }
    
} catch (PDOException $e) {
    die("Error database: " . $e->getMessage());
}

$penjual_id = $car['penjual_id'];

// Hitung booking fee (misalnya 10% dari harga mobil, minimal 500000)
$booking_fee_percentage = 0.10; // 10%
$min_booking_fee = 500000;
$booking_fee = max($car['harga'] * $booking_fee_percentage, $min_booking_fee);
$total_harga = $car['harga'];

// Proses form booking
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $metode_pembayaran = $_POST['metode_pembayaran'];
    $catatan = $_POST['catatan'];
    
    // Handle bukti pembayaran upload
    $bukti_pembayaran = '';
    if (isset($_FILES['bukti_pembayaran']) && $_FILES['bukti_pembayaran']['error'] == 0) {
        $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
        $max_size = 2 * 1024 * 1024; // 2MB
        $file_type = $_FILES['bukti_pembayaran']['type'];
        $file_size = $_FILES['bukti_pembayaran']['size'];
        
        if (!in_array($file_type, $allowed_types)) {
            $error = "File harus berupa gambar (JPG, JPEG, PNG, GIF)!";
        } elseif ($file_size > $max_size) {
            $error = "Ukuran file maksimal 2MB!";
        } else {
            // Create upload directory if not exists
            $upload_dir = 'uploads/bukti_pembayaran/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            // Generate unique filename
            $file_ext = pathinfo($_FILES['bukti_pembayaran']['name'], PATHINFO_EXTENSION);
            $file_name = 'bukti_' . time() . '_' . uniqid() . '.' . strtolower($file_ext);
            $upload_path = $upload_dir . $file_name;
            
            if (move_uploaded_file($_FILES['bukti_pembayaran']['tmp_name'], $upload_path)) {
                $bukti_pembayaran = $file_name;
            } else {
                $error = "Gagal mengupload bukti pembayaran!";
            }
        }
    } else {
        $error = "Bukti pembayaran wajib diunggah!";
    }
    
    if (empty($error)) {
        try {
            // Generate kode booking unik
            $kode_booking = 'BK' . date('Ymd') . strtoupper(substr(uniqid(), -6));
            
            // Get informasi pembeli
            $buyer_sql = "SELECT nama_lengkap, no_telepon FROM users WHERE id = ?";
            $buyer_stmt = $pdo->prepare($buyer_sql);
            $buyer_stmt->execute([$pembeli_id]);
            $buyer_info = $buyer_stmt->fetch(PDO::FETCH_ASSOC);
            
            // Insert ke database transaksi_booking dengan data lengkap
            $insert_sql = "INSERT INTO transaksi_booking (
                kode_booking, penjual_id, pembeli_id, mobil_id,
                tanggal_booking, catatan_pembeli, metode_pembayaran,
                jumlah_dp, jumlah_total, harga_mobil, uang_booking,
                status_pembayaran, bukti_pembayaran, tanggal_pembayaran,
                nama_pembeli, telepon_pembeli,
                nama_penjual, telepon_penjual,
                merk_mobil, model_mobil, tahun_mobil, foto_mobil_booking,
                status, created_at
            ) VALUES (?, ?, ?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())";
            
            $insert_stmt = $pdo->prepare($insert_sql);
            
            // Get mobil photo for backup
            $mobil_photo = !empty($car['foto_mobil']) ? $car['foto_mobil'] : '';
            
            $insert_stmt->execute([
                $kode_booking,
                $penjual_id,
                $pembeli_id,
                $car_id,
                $catatan,
                $metode_pembayaran,
                $booking_fee, // jumlah_dp
                $total_harga, // jumlah_total
                $total_harga, // harga_mobil
                $booking_fee, // uang_booking
                'menunggu_konfirmasi', // status_pembayaran
                $bukti_pembayaran,
                $buyer_info['nama_lengkap'], // nama_pembeli
                $buyer_info['no_telepon'], // telepon_pembeli
                $car['seller_name'], // nama_penjual
                $car['seller_phone'], // telepon_penjual
                $car['merk'], // merk_mobil
                $car['model'], // model_mobil
                $car['tahun'], // tahun_mobil
                $mobil_photo // foto_mobil_booking
            ]);
            
            // Update status mobil menjadi 'dipesan'
            $update_sql = "UPDATE mobil SET status = 'dipesan' WHERE id = ?";
            $update_stmt = $pdo->prepare($update_sql);
            $update_stmt->execute([$car_id]);
            
            $_SESSION['success'] = "Booking berhasil! Kode booking: <strong>" . $kode_booking . "</strong>. Silakan tunggu konfirmasi dari admin.";
            header("Location: buyer/my_bookings.php");
            exit();
            
        } catch (PDOException $e) {
            $error = "Gagal melakukan booking: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Mobil - Automarket</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .summary-box {
            background-color: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .summary-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px solid #dee2e6;
        }
        .summary-total {
            display: flex;
            justify-content: space-between;
            font-size: 1.2rem;
            font-weight: bold;
            color: #28a745;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 2px solid #28a745;
        }
        .payment-card {
            border: 2px solid #dee2e6;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        .payment-card:hover {
            border-color: #0d6efd;
            background-color: #f8f9fa;
        }
        .payment-card.selected {
            border-color: #0d6efd;
            background-color: #e7f1ff;
        }
        .bukti-upload-area {
            border: 2px dashed #dee2e6;
            border-radius: 10px;
            padding: 30px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            background: #f8f9fa;
        }
        .bukti-upload-area:hover {
            border-color: #0d6efd;
            background: #e9ecef;
        }
        .bukti-preview {
            max-width: 100%;
            max-height: 200px;
            margin-top: 15px;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            display: none;
        }
        .upload-icon {
            font-size: 2rem;
            color: #0d6efd;
            margin-bottom: 10px;
        }
        .admin-account {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
            padding: 20px;
            margin-top: 15px;
        }
        .car-info-box {
            background-color: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .seller-info {
            background-color: #e9ecef;
            border-radius: 8px;
            padding: 15px;
            margin-top: 15px;
        }
        .step-indicator {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
        }
        .step {
            flex: 1;
            text-align: center;
            padding: 10px;
            position: relative;
        }
        .step-number {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: #e9ecef;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 10px;
            font-weight: bold;
        }
        .step.active .step-number {
            background-color: #0d6efd;
            color: white;
        }
        .step::after {
            content: '';
            position: absolute;
            top: 20px;
            right: 0;
            width: 50%;
            height: 2px;
            background-color: #e9ecef;
        }
        .step:last-child::after {
            display: none;
        }
        .btn-booking {
            font-size: 1.1rem;
            font-weight: 600;
            padding: 15px 30px;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <?php 
    if (file_exists('includes/navbar.php')) {
        include 'includes/navbar.php'; 
    } elseif (file_exists('includes/header.php')) {
        include 'includes/header.php';
    }
    ?>

    <div class="container mt-4 mb-5">
        
        <h2 class="mb-4"><i class="fas fa-calendar-check me-2"></i>Booking Mobil</h2>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i><?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <div class="row">
            <!-- Kolom Kiri: Form Booking -->
            <div class="col-lg-8">
                <form method="POST" enctype="multipart/form-data" id="bookingForm">
                    <!-- Ringkasan Pembayaran -->
                    <div class="card shadow-sm mb-4">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><i class="fas fa-file-invoice-dollar me-2"></i>Ringkasan Pembayaran</h5>
                        </div>
                        <div class="card-body">
                            <div class="summary-box">
                                <div class="summary-item">
                                    <span>Harga Mobil:</span>
                                    <span>Rp <?php echo number_format($car['harga'], 0, ',', '.'); ?></span>
                                </div>
                                <div class="summary-item">
                                    <span>Biaya Booking (10%):</span>
                                    <span>Rp <?php echo number_format($booking_fee, 0, ',', '.'); ?></span>
                                </div>
                                <div class="summary-total">
                                    <span>Total yang harus dibayar:</span>
                                    <span class="text-success fw-bold">Rp <?php echo number_format($booking_fee, 0, ',', '.'); ?></span>
                                </div>
                                <div class="mt-3 text-muted">
                                    <small><i class="fas fa-info-circle me-1"></i> 
                                        Biaya booking akan dipotong dari total harga saat pelunasan.
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Metode Pembayaran -->
                    <div class="card shadow-sm mb-4">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0"><i class="fas fa-credit-card me-2"></i>Pilih Metode Pembayaran</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <div class="payment-card text-center" onclick="selectPaymentMethod('transfer_bank')">
                                        <i class="fas fa-university fa-3x mb-3 text-primary"></i>
                                        <h5>Transfer Bank</h5>
                                        <p class="text-muted small">Transfer ke rekening admin</p>
                                        <input type="radio" class="btn-check" name="metode_pembayaran" id="transfer_bank" value="transfer_bank" required>
                                        <label class="btn btn-outline-primary w-100 mt-2" for="transfer_bank">
                                            Pilih
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <div class="payment-card text-center" onclick="selectPaymentMethod('qris')">
                                        <i class="fas fa-qrcode fa-3x mb-3 text-success"></i>
                                        <h5>QRIS</h5>
                                        <p class="text-muted small">Scan QR Code</p>
                                        <input type="radio" class="btn-check" name="metode_pembayaran" id="qris" value="qris" required>
                                        <label class="btn btn-outline-success w-100 mt-2" for="qris">
                                            Pilih
                                        </label>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Informasi Rekening Admin (muncul jika transfer bank dipilih) -->
                            <div id="bankInfo" style="display: none;">
                                <div class="admin-account mt-3">
                                    <div class="text-center">
                                        <i class="fas fa-university fa-2x mb-2"></i>
                                        <h5>BCA - Bank Central Asia</h5>
                                        <h3 class="my-3">123 456 7890</h3>
                                        <p class="mb-0">a.n. PT. AUTOMARKET INDONESIA</p>
                                    </div>
                                </div>
                                <div class="alert alert-info mt-3">
                                    <i class="fas fa-info-circle me-2"></i>
                                    Transfer sesuai dengan nominal booking fee ke rekening di atas
                                </div>
                            </div>
                            
                            <!-- Informasi QRIS (muncul jika QRIS dipilih) -->
                            <div id="qrisInfo" style="display: none;">
                                <div class="text-center mt-3">
                                    <div class="alert alert-success">
                                        <i class="fas fa-qrcode fa-2x mb-2"></i>
                                        <h5>QRIS akan dikirim via WhatsApp/Email</h5>
                                        <p class="mb-0">Setelah booking, QRIS akan dikirim ke kontak Anda</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Upload Bukti Pembayaran -->
                    <div class="card shadow-sm mb-4">
                        <div class="card-header bg-warning text-dark">
                            <h5 class="mb-0"><i class="fas fa-receipt me-2"></i>Upload Bukti Pembayaran</h5>
                        </div>
                        <div class="card-body">
                            <div class="bukti-upload-area" id="buktiUploadArea" onclick="document.getElementById('buktiFile').click()">
                                <div class="upload-icon">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                </div>
                                <h5>Upload Bukti Pembayaran</h5>
                                <p class="text-muted">Klik atau drag & drop file di sini</p>
                                <p class="file-info">Format: JPG, JPEG, PNG, GIF | Maksimal: 2MB</p>
                                
                                <input type="file" name="bukti_pembayaran" id="buktiFile" accept="image/*" required 
                                       style="display: none;" onchange="previewBukti(event)">
                                <div id="buktiFileName" class="mt-2 text-success fw-bold" style="display: none;"></div>
                                <img id="buktiPreview" class="bukti-preview" alt="Preview Bukti Pembayaran">
                            </div>
                            <div class="alert alert-warning mt-3">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <strong>Wajib upload bukti pembayaran booking fee Rp <?php echo number_format($booking_fee, 0, ',', '.'); ?></strong>
                                <br>
                                <small>Bukti pembayaran harus jelas menunjukkan nominal dan nama pengirim</small>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Catatan -->
                    <div class="card shadow-sm mb-4">
                        <div class="card-header bg-secondary text-white">
                            <h5 class="mb-0"><i class="fas fa-sticky-note me-2"></i>Catatan Tambahan</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label for="catatan" class="form-label">
                                    <strong>Catatan untuk Penjual/Admin</strong>
                                </label>
                                <textarea class="form-control" id="catatan" name="catatan" rows="4" 
                                          placeholder="Contoh: 
• Saya ingin meninjau mobil di alamat...
• Jadwal yang memungkinkan...
• Pertanyaan khusus tentang mobil..."></textarea>
                                <div class="form-text mt-2">
                                    <i class="fas fa-info-circle me-1"></i> 
                                    Tambahkan catatan khusus untuk penjual atau admin jika diperlukan
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Tombol Aksi -->
                    <div class="d-grid gap-2 mb-4">
                        <button type="submit" class="btn btn-success btn-booking">
                            <i class="fas fa-check-circle me-2"></i> Konfirmasi Booking & Bayar
                        </button>
                        <a href="car_detail.php?id=<?php echo $car_id; ?>" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-2"></i> Kembali ke Detail Mobil
                        </a>
                    </div>
                </form>
            </div>
            
            <!-- Kolom Kanan: Informasi Mobil -->
            <div class="col-lg-4">
                <!-- Informasi Mobil -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0"><i class="fas fa-car me-2"></i>Mobil yang Dipesan</h5>
                    </div>
                    <div class="card-body">
                        <div class="car-info-box">
                            <h4 class="text-primary mb-3"><?php echo htmlspecialchars($car['merk'] . ' ' . $car['model']); ?></h4>
                            
                            <div class="mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="fw-bold">Harga:</span>
                                    <span class="price-tag">Rp <?php echo number_format($car['harga'], 0, ',', '.'); ?></span>
                                </div>
                            </div>
                            
                            <div class="car-details">
                                <p class="mb-2">
                                    <i class="fas fa-calendar-alt me-2"></i>
                                    <strong>Tahun:</strong> 
                                    <span class="badge bg-secondary"><?php echo htmlspecialchars($car['tahun']); ?></span>
                                </p>
                                <p class="mb-2">
                                    <i class="fas fa-plate me-2"></i>
                                    <strong>Plat:</strong> 
                                    <?php echo htmlspecialchars($car['plat_mobil']); ?>
                                </p>
                                <p class="mb-2">
                                    <i class="fas fa-palette me-2"></i>
                                    <strong>Warna:</strong> 
                                    <?php echo htmlspecialchars($car['warna']); ?>
                                </p>
                                <p class="mb-2">
                                    <i class="fas fa-gas-pump me-2"></i>
                                    <strong>Bahan Bakar:</strong> 
                                    <?php echo htmlspecialchars($car['bahan_bakar']); ?>
                                </p>
                                <p class="mb-0">
                                    <i class="fas fa-tachometer-alt me-2"></i>
                                    <strong>Kilometer:</strong> 
                                    <?php echo number_format($car['kilometer'], 0, ',', '.'); ?> km
                                </p>
                            </div>
                        </div>
                        
                        <!-- Informasi Penjual -->
                        <div class="seller-info">
                            <h6><i class="fas fa-user-tie me-2"></i>Penjual:</h6>
                            <p class="mb-1">
                                <strong>Nama:</strong> 
                                <?php echo htmlspecialchars($car['seller_name']); ?>
                            </p>
                            <p class="mb-1">
                                <strong>Email:</strong> 
                                <?php echo htmlspecialchars($car['seller_email']); ?>
                            </p>
                            <?php if (!empty($car['seller_phone'])): ?>
                                <p class="mb-0">
                                    <strong>Telepon:</strong> 
                                    <?php echo htmlspecialchars($car['seller_phone']); ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Informasi Booking -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Informasi Booking</h5>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info">
                            <p class="mb-2"><strong>Tanggal Booking:</strong></p>
                            <p class="fw-bold"><?php echo date('d F Y H:i'); ?></p>
                            
                            <p class="mb-2"><strong>Kode Booking:</strong></p>
                            <p class="fw-bold text-primary">Akan digenerate otomatis</p>
                            
                            <p class="mb-2"><strong>Status:</strong></p>
                            <span class="badge bg-warning">Menunggu konfirmasi</span>
                        </div>
                    </div>
                </div>
                
                <!-- Syarat & Ketentuan -->
                <div class="card shadow-sm">
                    <div class="card-header bg-danger text-white">
                        <h5 class="mb-0"><i class="fas fa-exclamation-triangle me-2"></i>Syarat & Ketentuan</h5>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-danger">
                            <small>
                                <ul class="mb-0">
                                    <li class="mb-2">Booking fee tidak dapat dikembalikan jika pembatalan dilakukan oleh pembeli</li>
                                    <li class="mb-2">Pembayaran booking harus dilakukan dalam waktu 24 jam</li>
                                    <li class="mb-2">Booking otomatis dibatalkan jika tidak ada bukti pembayaran dalam 24 jam</li>
                                    <li>Setelah booking dikonfirmasi, penjual akan menghubungi untuk penjadwalan</li>
                                </ul>
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Select payment method
        function selectPaymentMethod(method) {
            // Reset all cards
            document.querySelectorAll('.payment-card').forEach(card => {
                card.classList.remove('selected');
            });
            
            // Select clicked card
            event.currentTarget.classList.add('selected');
            
            // Show/hide payment info
            const bankInfo = document.getElementById('bankInfo');
            const qrisInfo = document.getElementById('qrisInfo');
            
            if (method === 'transfer_bank') {
                bankInfo.style.display = 'block';
                qrisInfo.style.display = 'none';
            } else if (method === 'qris') {
                bankInfo.style.display = 'none';
                qrisInfo.style.display = 'block';
            }
        }
        
        // Preview bukti pembayaran
        function previewBukti(event) {
            const input = event.target;
            const uploadArea = document.getElementById('buktiUploadArea');
            const fileName = document.getElementById('buktiFileName');
            const preview = document.getElementById('buktiPreview');
            
            if (input.files && input.files[0]) {
                const file = input.files[0];
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                    fileName.textContent = file.name;
                    fileName.style.display = 'block';
                }
                
                reader.readAsDataURL(file);
            }
        }
        
        // Drag and drop untuk bukti pembayaran
        const buktiUploadArea = document.getElementById('buktiUploadArea');
        
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            buktiUploadArea.addEventListener(eventName, preventDefaults, false);
        });
        
        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }
        
        ['dragenter', 'dragover'].forEach(eventName => {
            buktiUploadArea.addEventListener(eventName, highlight, false);
        });
        
        ['dragleave', 'drop'].forEach(eventName => {
            buktiUploadArea.addEventListener(eventName, unhighlight, false);
        });
        
        function highlight(e) {
            buktiUploadArea.style.borderColor = '#0d6efd';
            buktiUploadArea.style.backgroundColor = '#e9ecef';
        }
        
        function unhighlight(e) {
            buktiUploadArea.style.borderColor = '#dee2e6';
            buktiUploadArea.style.backgroundColor = '#f8f9fa';
        }
        
        buktiUploadArea.addEventListener('drop', handleDrop, false);
        
        function handleDrop(e) {
            const dt = e.dataTransfer;
            const files = dt.files;
            const input = document.getElementById('buktiFile');
            
            input.files = files;
            previewBukti({target: input});
        }
        
        // Form validation
        document.getElementById('bookingForm').addEventListener('submit', function(e) {
            const metodePembayaran = document.querySelector('input[name="metode_pembayaran"]:checked');
            const buktiFile = document.getElementById('buktiFile').files[0];
            
            if (!metodePembayaran) {
                e.preventDefault();
                alert('Silakan pilih metode pembayaran!');
                return false;
            }
            
            if (!buktiFile) {
                e.preventDefault();
                alert('Harap upload bukti pembayaran booking fee!');
                return false;
            }
            
            // Show loading
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Memproses...';
            submitBtn.disabled = true;
            
            // Confirm booking
            if (!confirm('Apakah Anda yakin ingin melakukan booking? Booking fee tidak dapat dikembalikan jika dibatalkan.')) {
                submitBtn.innerHTML = '<i class="fas fa-check-circle me-2"></i> Konfirmasi Booking & Bayar';
                submitBtn.disabled = false;
                return false;
            }
            
            return true;
        });
        
        // Auto-select first payment method on load
        document.addEventListener('DOMContentLoaded', function() {
            const firstPaymentCard = document.querySelector('.payment-card');
            if (firstPaymentCard) {
                firstPaymentCard.click();
            }
        });
    </script>
</body>
</html>