<?php
require_once __DIR__ . '/../includes/auth.php';

// Cek login dan role
if (!$auth->isLoggedIn() || !$auth->checkRole('penjual')) {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Handle action: terima/tolak booking
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && isset($_POST['booking_id'])) {
        $booking_id = $_POST['booking_id'];
        $action = $_POST['action'];
        
        // Validasi kepemilikan booking
        $check_sql = "SELECT b.id 
                     FROM transaksi_booking b
                     JOIN mobil m ON b.mobil_id = m.id
                     WHERE b.id = ? AND m.penjual_id = ?";
        $check_stmt = $pdo->prepare($check_sql);
        $check_stmt->execute([$booking_id, $user_id]);
        
        if ($check_stmt->fetch()) {
            // Update status booking
            if ($action === 'terima') {
                $status = 'dikonfirmasi';
            } elseif ($action === 'tolak') {
                $status = 'dibatalkan';
            }
            
            $update_sql = "UPDATE transaksi_booking 
                          SET status = ?, 
                              dikonfirmasi_at = NOW(),
                              catatan_penjual = ?
                          WHERE id = ?";
            $update_stmt = $pdo->prepare($update_sql);
            $update_stmt->execute([
                $status,
                $_POST['catatan'] ?? '', // Tambahkan catatan jika ada
                $booking_id
            ]);
            
            // Redirect untuk refresh halaman
            header("Location: bookings.php?success=" . urlencode("Booking berhasil di$action"));
            exit();
        }
    }
}

// Get bookings for seller's cars dengan informasi pembayaran yang benar
$sql = "SELECT b.*, 
               m.merk, m.model, m.plat_mobil, 
               u.nama_lengkap as pembeli_nama, 
               u.no_telepon as pembeli_telepon,
               -- Hitung uang masuk yang benar:
               (CASE 
                   WHEN b.status_pembayaran = 'lunas' THEN b.uang_booking  -- Jika lunas, uang booking masuk
                   WHEN b.status_pembayaran = 'menunggu_konfirmasi' THEN b.uang_booking  -- DP juga masuk
                   ELSE 0  -- Belum bayar atau gagal = 0
               END) as uang_masuk,
               -- Hitung sisa yang harus dibayar:
               (CASE 
                   WHEN b.status_pembayaran = 'lunas' THEN 0  -- Sudah lunas, sisa 0
                   WHEN b.status_pembayaran = 'menunggu_konfirmasi' THEN b.harga_mobil - b.uang_booking  -- Kurangi dengan DP
                   ELSE b.harga_mobil  -- Belum bayar = harga mobil penuh
               END) as sisa_bayar
        FROM transaksi_booking b
        JOIN mobil m ON b.mobil_id = m.id
        JOIN users u ON b.pembeli_id = u.id
        WHERE m.penjual_id = ?
        ORDER BY b.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id]);
$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Hitung statistik keuangan yang benar
$total_uang_masuk = 0;
$total_uang_pending = 0;
$total_booking = 0;

foreach ($bookings as $booking) {
    $total_booking++;
    if ($booking['status_pembayaran'] == 'lunas') {
        $total_uang_masuk += $booking['uang_booking']; // Hanya uang booking yang masuk
    } elseif ($booking['status_pembayaran'] == 'menunggu_konfirmasi') {
        $total_uang_pending += $booking['uang_booking']; // DP yang menunggu konfirmasi
    }
}

// Format Rupiah helper function
function formatRupiah($angka) {
    return 'Rp ' . number_format($angka, 0, ',', '.');
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Saya - Automarket</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
            overflow-x: hidden;
        }
        .sidebar {
            background: linear-gradient(180deg, #28a745 0%, #20c997 100%);
            color: white;
            height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            width: 250px;
            padding-top: 20px;
        }
        .sidebar-brand {
            padding: 0 20px 30px;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            margin-bottom: 20px;
        }
        .sidebar-brand h3 {
            font-weight: bold;
            margin-bottom: 5px;
        }
        .sidebar-brand small {
            opacity: 0.8;
        }
        .nav-link {
            color: rgba(255,255,255,0.9);
            padding: 12px 20px;
            margin: 5px 15px;
            border-radius: 8px;
            transition: all 0.3s;
        }
        .nav-link:hover {
            background: rgba(255,255,255,0.15);
            color: white;
            transform: translateX(5px);
        }
        .nav-link.active {
            background: rgba(255,255,255,0.2);
            color: white;
            font-weight: 500;
            border-left: 4px solid white;
        }
        .nav-link i {
            width: 25px;
        }
        .main-content {
            margin-left: 250px;
            padding: 20px;
            min-height: 100vh;
        }
        .back-to-website {
            position: absolute;
            bottom: 20px;
            left: 0;
            right: 0;
            padding: 0 20px;
        }
        .btn-outline-light:hover {
            color: #28a745;
        }
        .badge-unread {
            font-size: 0.7rem;
            padding: 3px 8px;
            background: #ffc107;
            color: #212529;
        }
        .booking-card {
            margin-bottom: 20px;
            border: 1px solid #dee2e6;
            border-radius: 10px;
            transition: transform 0.2s;
            background: white;
        }
        .booking-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .booking-info p {
            margin-bottom: 5px;
            font-size: 0.9rem;
        }
        .uang-info {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 10px;
            margin: 10px 0;
        }
        .uang-label {
            font-size: 0.8rem;
            color: #6c757d;
            margin-bottom: 2px;
        }
        .uang-nominal {
            font-weight: bold;
            font-size: 1.1rem;
        }
        .uang-masuk {
            color: #28a745;
        }
        .uang-pending {
            color: #ffc107;
        }
        .uang-booking {
            color: #17a2b8;
        }
        .uang-sisa {
            color: #dc3545;
        }
        .stat-card {
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
            color: white;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .stat-card.total {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .stat-card.masuk {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
        }
        .stat-card.pending {
            background: linear-gradient(135deg, #ffc107 0%, #ff8c00 100%);
        }
        
        /* Modal Styles */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1050;
            align-items: center;
            justify-content: center;
        }
        .modal-dialog-small {
            max-width: 500px;
            width: 90%;
        }
        .modal-content {
            background: white;
            border-radius: 10px;
            overflow: hidden;
        }
        
        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
                height: auto;
            }
            .main-content {
                margin-left: 0;
            }
            .back-to-website {
                position: relative;
                bottom: auto;
                margin-top: 20px;
                margin-bottom: 20px;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-brand">
            <h3>Automarket</h3>
            <small>Seller Panel</small>
        </div>
        
        <nav class="nav flex-column">
            <a class="nav-link" href="dashboard.php">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>
            <a class="nav-link" href="add_car.php">
                <i class="fas fa-plus-circle"></i> +Jual Mobil
            </a>
            <a class="nav-link" href="my_cars.php">
                <i class="fas fa-car"></i> Mobil Saya
            </a>
            <a class="nav-link active" href="bookings.php">
                <i class="fas fa-calendar-check"></i> Booking Saya
            </a>
            <a class="nav-link" href="messages.php">
                <i class="fas fa-comments"></i> Pesan
                <?php 
                // Count total unread messages
                $unread_sql = "SELECT COUNT(*) as total_unread FROM pesan WHERE penerima_id = ? AND dibaca = 0";
                $unread_stmt = $pdo->prepare($unread_sql);
                $unread_stmt->execute([$user_id]);
                $total_unread = $unread_stmt->fetch(PDO::FETCH_ASSOC)['total_unread'];
                ?>
                <?php if ($total_unread > 0): ?>
                    <span class="badge bg-warning badge-unread float-end"><?= $total_unread ?></span>
                <?php endif; ?>
            </a>
            
            <div class="back-to-website">
                <a href="../index.php" class="btn btn-outline-light w-100">
                    <i class="fas fa-arrow-left"></i> Kembali ke Website
                </a>
            </div>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="p-4">
            <h2 class="mb-4">Booking Mobil</h2>
            
            <!-- Statistik Keuangan -->
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="stat-card total">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-1">Total Booking</h6>
                                <h3 class="mb-0"><?php echo $total_booking; ?></h3>
                            </div>
                            <i class="fas fa-calendar-check fa-2x opacity-50"></i>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-card masuk">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-1">Uang Masuk</h6>
                                <h3 class="mb-0"><?php echo formatRupiah($total_uang_masuk); ?></h3>
                                <small>Uang Booking Diterima</small>
                            </div>
                            <i class="fas fa-money-bill-wave fa-2x opacity-50"></i>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-card pending">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-1">Menunggu Konfirmasi</h6>
                                <h3 class="mb-0"><?php echo formatRupiah($total_uang_pending); ?></h3>
                                <small>DP Belum Dikonfirmasi</small>
                            </div>
                            <i class="fas fa-clock fa-2x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Success Message -->
            <?php if(isset($_GET['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($_GET['success']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <?php if(empty($bookings)): ?>
                <div class="card text-center py-5">
                    <div class="card-body">
                        <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                        <h5 class="card-title">Belum ada booking</h5>
                        <p class="card-text">Booking dari pembeli akan muncul di sini</p>
                    </div>
                </div>
            <?php else: ?>
                <div class="row">
                    <?php foreach($bookings as $booking): 
                        // Tentukan warna badge berdasarkan status pembayaran
                        $badge_color = 'secondary';
                        if ($booking['status_pembayaran'] == 'lunas') {
                            $badge_color = 'success';
                        } elseif ($booking['status_pembayaran'] == 'menunggu_konfirmasi') {
                            $badge_color = 'warning';
                        } elseif ($booking['status_pembayaran'] == 'gagal') {
                            $badge_color = 'danger';
                        }
                    ?>
                    <div class="col-md-6">
                        <div class="card booking-card <?php echo $booking['status'] ?? 'pending'; ?>">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <h5 class="card-title mb-0">
                                        <?php echo htmlspecialchars($booking['merk'] . ' ' . $booking['model']); ?>
                                    </h5>
                                    <div class="text-end">
                                        <span class="badge bg-<?php 
                                            echo ($booking['status'] ?? 'pending') == 'dikonfirmasi' ? 'success' : 
                                                 (($booking['status'] ?? 'pending') == 'dibatalkan' ? 'danger' : 'warning'); 
                                        ?>">
                                            <?php 
                                                $status_map = [
                                                    'pending' => 'Menunggu',
                                                    'dikonfirmasi' => 'Dikonfirmasi',
                                                    'dibatalkan' => 'Dibatalkan',
                                                    'selesai' => 'Selesai'
                                                ];
                                                echo $status_map[$booking['status']] ?? ucfirst($booking['status']);
                                            ?>
                                        </span>
                                        <br>
                                        <small class="badge bg-<?php echo $badge_color; ?> mt-1">
                                            <?php 
                                                $status_pembayaran_map = [
                                                    'belum_bayar' => 'Belum Bayar',
                                                    'menunggu_konfirmasi' => 'DP Menunggu',
                                                    'lunas' => 'Lunas',
                                                    'gagal' => 'Gagal'
                                                ];
                                                echo $status_pembayaran_map[$booking['status_pembayaran']] ?? ucfirst(str_replace('_', ' ', $booking['status_pembayaran']));
                                            ?>
                                        </small>
                                    </div>
                                </div>
                                
                                <p class="text-muted mb-2">
                                    Plat: <strong><?php echo htmlspecialchars($booking['plat_mobil']); ?></strong>
                                </p>
                                
                                <!-- Informasi Uang yang DIPERBAIKI -->
                                <div class="uang-info">
                                    <div class="row">
                                        <div class="col-md-6 mb-2">
                                            <div class="uang-label">Harga Mobil</div>
                                            <div class="uang-nominal"><?php echo formatRupiah($booking['harga_mobil']); ?></div>
                                        </div>
                                        <div class="col-md-6 mb-2">
                                            <div class="uang-label">Uang Booking (DP)</div>
                                            <div class="uang-nominal uang-booking"><?php echo formatRupiah($booking['uang_booking']); ?></div>
                                        </div>
                                        <div class="col-md-6 mb-2">
                                            <div class="uang-label">Uang Masuk</div>
                                            <div class="uang-nominal <?php echo $booking['status_pembayaran'] == 'lunas' ? 'uang-masuk' : ($booking['status_pembayaran'] == 'menunggu_konfirmasi' ? 'uang-pending' : 'text-muted'); ?>">
                                                <?php echo formatRupiah($booking['uang_masuk']); ?>
                                            </div>
                                            <small class="text-muted">
                                                <?php 
                                                    if ($booking['status_pembayaran'] == 'lunas') {
                                                        echo 'Uang Booking Diterima';
                                                    } elseif ($booking['status_pembayaran'] == 'menunggu_konfirmasi') {
                                                        echo 'DP Menunggu Konfirmasi';
                                                    } else {
                                                        echo 'Belum Ada Pembayaran';
                                                    }
                                                ?>
                                            </small>
                                        </div>
                                        <div class="col-md-6 mb-2">
                                            <div class="uang-label">Sisa Bayar</div>
                                            <div class="uang-nominal uang-sisa"><?php echo formatRupiah($booking['sisa_bayar']); ?></div>
                                            <small class="text-muted">
                                                <?php 
                                                    if ($booking['status_pembayaran'] == 'lunas') {
                                                        echo 'Sudah Lunas';
                                                    } elseif ($booking['status_pembayaran'] == 'menunggu_konfirmasi') {
                                                        echo 'Setelah DP';
                                                    } else {
                                                        echo 'Belum Ada DP';
                                                    }
                                                ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="booking-info">
                                    <p class="mb-1">
                                        <i class="fas fa-user me-2"></i>
                                        <?php echo htmlspecialchars($booking['pembeli_nama']); ?>
                                    </p>
                                    <p class="mb-1">
                                        <i class="fas fa-phone me-2"></i>
                                        <?php echo htmlspecialchars($booking['pembeli_telepon']); ?>
                                    </p>
                                    <p class="mb-1">
                                        <i class="fas fa-calendar me-2"></i>
                                        <?php echo date('d M Y H:i', strtotime($booking['created_at'])); ?>
                                    </p>
                                    <?php if(isset($booking['tanggal_booking'])): ?>
                                    <p class="mb-1">
                                        <i class="fas fa-clock me-2"></i>
                                        Booking untuk: <?php echo date('d M Y', strtotime($booking['tanggal_booking'])); ?>
                                    </p>
                                    <?php endif; ?>
                                    
                                    <?php if($booking['metode_pembayaran'] && $booking['metode_pembayaran'] != 'belum_dipilih'): ?>
                                    <p class="mb-1">
                                        <i class="fas fa-credit-card me-2"></i>
                                        Metode: 
                                        <?php 
                                            $metode_map = [
                                                'transfer_bank' => 'Transfer Bank',
                                                'cash' => 'Cash',
                                                'credit_card' => 'Kartu Kredit',
                                                'e_wallet' => 'E-Wallet'
                                            ];
                                            echo $metode_map[$booking['metode_pembayaran']] ?? ucfirst(str_replace('_', ' ', $booking['metode_pembayaran']));
                                        ?>
                                    </p>
                                    <?php endif; ?>
                                </div>

                                <?php if($booking['catatan_pembeli']): ?>
                                <div class="mt-2">
                                    <small class="text-muted">Catatan Pembeli:</small>
                                    <p class="mb-0"><?php echo htmlspecialchars($booking['catatan_pembeli']); ?></p>
                                </div>
                                <?php endif; ?>

                                <div class="mt-3">
                                    <?php if(($booking['status'] ?? 'pending') == 'pending'): ?>
                                        <button class="btn btn-success btn-sm me-1 btn-terima" 
                                                data-booking-id="<?php echo $booking['id']; ?>">
                                            <i class="fas fa-check me-1"></i> Terima
                                        </button>
                                        <button class="btn btn-danger btn-sm btn-tolak" 
                                                data-booking-id="<?php echo $booking['id']; ?>">
                                            <i class="fas fa-times me-1"></i> Tolak
                                        </button>
                                    <?php elseif(($booking['status'] ?? 'pending') == 'dikonfirmasi'): ?>
                                        <button class="btn btn-primary btn-sm btn-hubungi" 
                                                data-telepon="<?php echo htmlspecialchars($booking['pembeli_telepon']); ?>">
                                            <i class="fas fa-phone me-1"></i> Hubungi Pembeli
                                        </button>
                                        
                                        <?php if($booking['status_pembayaran'] == 'menunggu_konfirmasi'): ?>
                                        <a href="?konfirmasi_pembayaran=<?php echo $booking['id']; ?>" 
                                           class="btn btn-warning btn-sm ms-1"
                                           onclick="return confirm('Konfirmasi bahwa uang booking sudah diterima?')">
                                            <i class="fas fa-check-circle me-1"></i> Konfirmasi Pembayaran
                                        </a>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    
                                    <!-- Tombol detail untuk melihat info lengkap -->
                                    <button class="btn btn-info btn-sm ms-1 btn-detail" 
                                            data-booking-id="<?php echo $booking['id']; ?>">
                                        <i class="fas fa-info-circle me-1"></i> Detail
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div class="mt-3">
                    <p class="text-muted">
                        Total: <strong><?php echo count($bookings); ?></strong> booking | 
                        Uang Masuk: <strong class="text-success"><?php echo formatRupiah($total_uang_masuk); ?></strong> | 
                        Menunggu Konfirmasi: <strong class="text-warning"><?php echo formatRupiah($total_uang_pending); ?></strong>
                    </p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal untuk catatan -->
    <div class="modal-overlay" id="modalOverlay">
        <div class="modal-dialog modal-dialog-small">
            <div class="modal-content">
                <form method="POST" action="bookings.php" id="actionForm">
                    <div class="modal-header">
                        <h5 class="modal-title" id="modalTitle">Konfirmasi Booking</h5>
                        <button type="button" class="btn-close" onclick="closeModal()"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" id="actionInput">
                        <input type="hidden" name="booking_id" id="bookingIdInput">
                        
                        <div class="mb-3">
                            <label for="catatan" class="form-label">Catatan (opsional):</label>
                            <textarea class="form-control" id="catatan" name="catatan" rows="3" 
                                      placeholder="Berikan catatan untuk pembeli..."></textarea>
                        </div>
                        
                        <p id="confirmMessage">Apakah Anda yakin?</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" onclick="closeModal()">Batal</button>
                        <button type="submit" class="btn" id="submitBtn">Ya, Konfirmasi</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal untuk detail booking -->
    <div class="modal fade" id="detailModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Detail Booking</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="detailModalBody">
                    Loading...
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Modal handling
        function showModal(action, bookingId) {
            const modal = document.getElementById('modalOverlay');
            const title = document.getElementById('modalTitle');
            const actionInput = document.getElementById('actionInput');
            const bookingIdInput = document.getElementById('bookingIdInput');
            const message = document.getElementById('confirmMessage');
            const submitBtn = document.getElementById('submitBtn');
            
            actionInput.value = action;
            bookingIdInput.value = bookingId;
            
            if (action === 'terima') {
                title.textContent = 'Terima Booking';
                message.textContent = 'Apakah Anda yakin ingin menerima booking ini?';
                submitBtn.className = 'btn btn-success';
                submitBtn.textContent = 'Ya, Terima';
            } else if (action === 'tolak') {
                title.textContent = 'Tolak Booking';
                message.textContent = 'Apakah Anda yakin ingin menolak booking ini?';
                submitBtn.className = 'btn btn-danger';
                submitBtn.textContent = 'Ya, Tolak';
            }
            
            modal.style.display = 'flex';
        }
        
        function closeModal() {
            document.getElementById('modalOverlay').style.display = 'none';
            document.getElementById('catatan').value = '';
        }
        
        // Fungsi untuk membuka modal detail
        function showDetail(bookingId) {
            fetch(`ajax_get_booking_detail.php?id=${bookingId}`)
                .then(response => response.text())
                .then(html => {
                    document.getElementById('detailModalBody').innerHTML = html;
                    const modal = new bootstrap.Modal(document.getElementById('detailModal'));
                    modal.show();
                })
                .catch(error => {
                    document.getElementById('detailModalBody').innerHTML = 
                        '<div class="alert alert-danger">Gagal memuat detail booking</div>';
                });
        }
        
        // Event listeners untuk tombol
        document.addEventListener('DOMContentLoaded', function() {
            // Tombol terima
            document.querySelectorAll('.btn-terima').forEach(btn => {
                btn.addEventListener('click', function() {
                    const bookingId = this.getAttribute('data-booking-id');
                    showModal('terima', bookingId);
                });
            });
            
            // Tombol tolak
            document.querySelectorAll('.btn-tolak').forEach(btn => {
                btn.addEventListener('click', function() {
                    const bookingId = this.getAttribute('data-booking-id');
                    showModal('tolak', bookingId);
                });
            });
            
            // Tombol hubungi
            document.querySelectorAll('.btn-hubungi').forEach(btn => {
                btn.addEventListener('click', function() {
                    const telepon = this.getAttribute('data-telepon');
                    window.location.href = 'tel:' + telepon;
                });
            });
            
            // Tombol detail
            document.querySelectorAll('.btn-detail').forEach(btn => {
                btn.addEventListener('click', function() {
                    const bookingId = this.getAttribute('data-booking-id');
                    showDetail(bookingId);
                });
            });
            
            // Tutup modal saat klik di luar
            document.getElementById('modalOverlay').addEventListener('click', function(e) {
                if (e.target === this) {
                    closeModal();
                }
            });
        });
    </script>
</body>
</html>