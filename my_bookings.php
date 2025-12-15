<?php
require_once __DIR__ . '/../includes/db_config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'pembeli') {
    header('Location: ../login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Handle cancel booking
if (isset($_GET['cancel_booking'])) {
    $booking_id = $_GET['cancel_booking'];
    $sql = "UPDATE transaksi_booking SET status = 'dibatalkan' WHERE id = ? AND pembeli_id = ?";
    $stmt = $pdo->prepare($sql);
    if ($stmt->execute([$booking_id, $user_id])) {
        $_SESSION['success'] = "Booking berhasil dibatalkan";
    } else {
        $_SESSION['error'] = "Gagal membatalkan booking";
    }
    header('Location: my_bookings.php');
    exit();
}

// Get bookings - QUERY YANG DIPERBAIKI untuk mendapatkan foto
$sql = "SELECT tb.*, 
               m.merk, m.model, m.tahun, m.warna, m.harga, m.kilometer,
               u.nama_lengkap as penjual_nama,
               u.no_telepon as penjual_telepon,
               COALESCE(
                   (SELECT nama_file FROM foto_mobil WHERE mobil_id = m.id ORDER BY urutan LIMIT 1),
                   m.foto_mobil
               ) as foto_mobil_utama
        FROM transaksi_booking tb
        JOIN mobil m ON tb.mobil_id = m.id
        JOIN users u ON tb.penjual_id = u.id
        WHERE tb.pembeli_id = ?
        ORDER BY tb.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id]);
$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Count bookings by status - SESUAI DATABASE
$status_counts = [
    'pending' => 0,
    'dikonfirmasi' => 0,
    'selesai' => 0,
    'dibatalkan' => 0
];

// Hitung jumlah per status dari data booking yang sudah ada
foreach ($bookings as $booking) {
    $status = $booking['status'] ?? 'pending';
    if (isset($status_counts[$status])) {
        $status_counts[$status]++;
    }
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
        .booking-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            margin-bottom: 2rem;
            transition: all 0.3s ease;
        }
        .booking-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        .car-img {
            height: 150px;
            object-fit: cover;
            border-radius: 10px;
        }
        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-weight: 600;
        }
        .status-pending { 
            background: #fff3cd !important; 
            color: #856404 !important; 
        }
        .status-dikonfirmasi { 
            background: #d1ecf1 !important; 
            color: #0c5460 !important; 
        }
        .status-selesai { 
            background: #d4edda !important; 
            color: #155724 !important; 
        }
        .status-dibatalkan { 
            background: #f8d7da !important; 
            color: #721c24 !important; 
        }
        .stats-card {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            text-align: center;
            box-shadow: 0 3px 10px rgba(0,0,0,0.08);
            margin-bottom: 1.5rem;
        }
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: #6c757d;
        }
        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
            color: #dee2e6;
        }
        .booking-info {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        .navbar-brand {
            font-weight: 700;
            color: #2c3e50 !important;
        }
        .user-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #3498db;
        }
        .nav-link {
            transition: all 0.3s ease;
        }
        .nav-link:hover {
            transform: translateY(-2px);
            color: #3498db !important;
        }
        footer {
            margin-top: auto;
        }
    </style>
</head>
<body>
    <!-- Custom Header untuk My Bookings -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
        <div class="container">
            <a class="navbar-brand" href="../index.php">
                <i class="fas fa-car me-2 text-primary"></i>
                <span class="fw-bold">Automarket</span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="../index.php">
                            <i class="fas fa-home me-1"></i>Beranda
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../cars.php">
                            <i class="fas fa-search me-1"></i>Cari Mobil
                        </a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle me-1"></i><?php echo htmlspecialchars($_SESSION['nama_lengkap']); ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="dashboard.php"><i class="fas fa-tachometer-alt me-2"></i>Dashboard</a></li>
                            <li><a class="dropdown-item" href="favorites.php"><i class="fas fa-heart me-2"></i>Favorit</a></li>
                            <li><a class="dropdown-item active" href="my_bookings.php"><i class="fas fa-calendar-check me-2"></i>Booking Saya</a></li>
                            <li><a class="dropdown-item" href="messages.php"><i class="fas fa-comments me-2"></i>Pesan</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="../profile.php"><i class="fas fa-cog me-2"></i>Pengaturan Profil</a></li>
                            <li><a class="dropdown-item text-danger" href="../logout.php"><i class="fas fa-sign-out-alt me-2"></i>Keluar</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid py-4">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 mb-4">
                <div class="card">
                    <div class="card-body text-center">
                        <div class="mb-3">
                            <?php if (!empty($_SESSION['foto_profil'])): ?>
                                <img src="../uploads/profiles/<?php echo $_SESSION['foto_profil']; ?>" class="user-avatar" alt="Profile">
                            <?php else: ?>
                                <div class="user-avatar mx-auto bg-light d-flex align-items-center justify-content-center">
                                    <i class="fas fa-user fa-2x text-muted"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                        <h5 class="fw-bold mb-1"><?php echo htmlspecialchars($_SESSION['nama_lengkap']); ?></h5>
                        <p class="text-muted mb-3">Pembeli</p>
                        
                        <div class="list-group list-group-flush">
                            <a href="dashboard.php" class="list-group-item list-group-item-action">
                                <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                            </a>
                            <a href="favorites.php" class="list-group-item list-group-item-action">
                                <i class="fas fa-heart me-2"></i>Favorit Saya
                            </a>
                            <a href="my_bookings.php" class="list-group-item list-group-item-action active">
                                <i class="fas fa-calendar-check me-2"></i>Booking Saya
                            </a>
                            <a href="messages.php" class="list-group-item list-group-item-action">
                                <i class="fas fa-comments me-2"></i>Pesan
                            </a>
                            <a href="../profile.php" class="list-group-item list-group-item-action">
                                <i class="fas fa-cog me-2"></i>Pengaturan Profil
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-md-9">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="h3 mb-0"><i class="fas fa-calendar-check me-2 text-success"></i>Booking Saya</h1>
                    <span class="badge bg-primary fs-6"><?php echo count($bookings); ?> Booking</span>
                </div>

                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Booking Stats - DIPERBAIKI -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="stats-card">
                            <div class="text-warning fs-4 fw-bold"><?php echo $status_counts['pending']; ?></div>
                            <div class="text-muted">Pending</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card">
                            <div class="text-info fs-4 fw-bold"><?php echo $status_counts['dikonfirmasi']; ?></div>
                            <div class="text-muted">Confirmed</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card">
                            <div class="text-success fs-4 fw-bold"><?php echo $status_counts['selesai']; ?></div>
                            <div class="text-muted">Completed</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card">
                            <div class="text-danger fs-4 fw-bold"><?php echo $status_counts['dibatalkan']; ?></div>
                            <div class="text-muted">Cancelled</div>
                        </div>
                    </div>
                </div>

                <?php if (!empty($bookings)): ?>
                    <div class="row">
                        <?php foreach ($bookings as $booking): ?>
                            <div class="col-12 mb-4">
                                <div class="card booking-card">
                                    <div class="card-body">
                                        <div class="row align-items-center">
                                            <div class="col-md-3">
                                                <?php
                                                // MENDAPATKAN FOTO MOBIL
                                                $foto_url = '';
                                                if (!empty($booking['foto_mobil_utama'])) {
                                                    $foto_path = '../uploads/cars/' . $booking['foto_mobil_utama'];
                                                    if (file_exists($foto_path)) {
                                                        $foto_url = $foto_path;
                                                    }
                                                }
                                                
                                                if (empty($foto_url)) {
                                                    $foto_url = 'https://via.placeholder.com/200x150?text=No+Image';
                                                }
                                                ?>
                                                
                                                <img src="<?php echo $foto_url; ?>" 
                                                     class="car-img w-100" alt="<?php echo $booking['merk'] . ' ' . $booking['model']; ?>">
                                            </div>
                                            <div class="col-md-6">
                                                <h5 class="card-title"><?php echo $booking['merk'] . ' ' . $booking['model']; ?></h5>
                                                
                                                <div class="booking-info">
                                                    <div class="row">
                                                        <div class="col-6">
                                                            <small class="text-muted">Tahun</small>
                                                            <div><strong><?php echo $booking['tahun']; ?></strong></div>
                                                        </div>
                                                        <div class="col-6">
                                                            <small class="text-muted">Warna</small>
                                                            <div><strong><?php echo $booking['warna']; ?></strong></div>
                                                        </div>
                                                    </div>
                                                    <div class="row mt-2">
                                                        <div class="col-6">
                                                            <small class="text-muted">Kilometer</small>
                                                            <div><strong><?php echo number_format($booking['kilometer']); ?> km</strong></div>
                                                        </div>
                                                        <div class="col-6">
                                                            <small class="text-muted">Penjual</small>
                                                            <div><strong><?php echo $booking['penjual_nama']; ?></strong></div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="car-price h5 text-success mb-0 mt-2">
                                                    Rp <?php echo number_format($booking['harga'], 0, ',', '.'); ?>
                                                </div>
                                            </div>
                                            <div class="col-md-3 text-end">
                                                <div class="mb-3">
                                                    <span class="status-badge status-<?php echo $booking['status']; ?>">
                                                        <?php 
                                                        // Konversi status ke bahasa Inggris untuk tampilan
                                                        $status_display = [
                                                            'pending' => 'Pending',
                                                            'dikonfirmasi' => 'Confirmed',
                                                            'selesai' => 'Completed',
                                                            'dibatalkan' => 'Cancelled'
                                                        ];
                                                        echo $status_display[$booking['status']] ?? ucfirst($booking['status']); 
                                                        ?>
                                                    </span>
                                                </div>
                                                
                                                <?php if (!empty($booking['kode_booking'])): ?>
                                                <div class="mb-2">
                                                    <small class="text-muted">Kode Booking:</small><br>
                                                    <strong><?php echo $booking['kode_booking']; ?></strong>
                                                </div>
                                                <?php endif; ?>
                                                
                                                <div class="mb-3">
                                                    <small class="text-muted">Tanggal Booking:</small><br>
                                                    <strong><?php echo date('d M Y H:i', strtotime($booking['created_at'])); ?></strong>
                                                </div>
                                                
                                                <div class="btn-group-vertical w-100">
                                                    <a href="../car_detail.php?id=<?php echo $booking['mobil_id']; ?>" class="btn btn-sm btn-primary mb-2">
                                                        <i class="fas fa-eye me-1"></i>Lihat Mobil
                                                    </a>
                                                    
                                                    <?php if ($booking['status'] == 'pending' || $booking['status'] == 'dikonfirmasi'): ?>
                                                        <a href="my_bookings.php?cancel_booking=<?php echo $booking['id']; ?>" 
                                                           class="btn btn-sm btn-danger"
                                                           onclick="return confirm('Batalkan booking ini?')">
                                                            <i class="fas fa-times me-1"></i>Batalkan
                                                        </a>
                                                    <?php endif; ?>
                                                    
                                                    <?php if ($booking['penjual_telepon']): ?>
                                                        <a href="https://wa.me/62<?php echo ltrim($booking['penjual_telepon'], '0'); ?>?text=Halo%20<?php echo urlencode($booking['penjual_nama']); ?>%2C%20saya%20ingin%20bertanya%20tentang%20mobil%20<?php echo urlencode($booking['merk'] . ' ' . $booking['model']); ?>%20yang%20saya%20booking" 
                                                           target="_blank" 
                                                           class="btn btn-sm btn-success mt-2">
                                                            <i class="fab fa-whatsapp me-1"></i>Hubungi Penjual
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-calendar-check"></i>
                        <h3>Belum Ada Booking</h3>
                        <p class="mb-4">Anda belum melakukan booking mobil</p>
                        <a href="../cars.php" class="btn btn-primary btn-lg">
                            <i class="fas fa-search me-2"></i>Cari Mobil
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-dark text-white py-4 mt-5">
        <div class="container">
            <div class="row">
                <div class="col-md-4">
                    <h5><i class="fas fa-car me-2"></i>Automarket</h5>
                    <p class="text-muted">Platform jual beli mobil terpercaya di Indonesia.</p>
                </div>
                <div class="col-md-4">
                    <h5>Quick Links</h5>
                    <ul class="list-unstyled">
                        <li><a href="../index.php" class="text-white text-decoration-none">Beranda</a></li>
                        <li><a href="../cars.php" class="text-white text-decoration-none">Cari Mobil</a></li>
                        <li><a href="../about.php" class="text-white text-decoration-none">Tentang Kami</a></li>
                        <li><a href="../contact.php" class="text-white text-decoration-none">Kontak</a></li>
                    </ul>
                </div>
                <div class="col-md-4">
                    <h5>Hubungi Kami</h5>
                    <p class="mb-1"><i class="fas fa-envelope me-2"></i> support@automarket.com</p>
                    <p class="mb-1"><i class="fas fa-phone me-2"></i> (021) 1234-5678</p>
                </div>
            </div>
            <hr class="bg-light">
            <div class="text-center">
                <p class="mb-0">&copy; <?php echo date('Y'); ?> Automarket. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto refresh untuk update status booking
        setTimeout(function() {
            window.location.reload();
        }, 30000); // Refresh setiap 30 detik
    </script>
</body>
</html>