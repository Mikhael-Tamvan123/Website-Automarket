<?php
require_once __DIR__ . '/../includes/db_config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'pembeli') {
    header('Location: ../login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Handle remove favorite
if (isset($_GET['remove_favorite'])) {
    $mobil_id = $_GET['remove_favorite'];
    
    $sql = "DELETE FROM favorit WHERE pembeli_id = ? AND mobil_id = ?";
    $stmt = $pdo->prepare($sql);
    if ($stmt->execute([$user_id, $mobil_id])) {
        $_SESSION['success'] = "Mobil berhasil dihapus dari favorit";
    } else {
        $_SESSION['error'] = "Gagal menghapus dari favorit";
    }
    header('Location: favorites.php');
    exit();
}

// Get favorite cars - QUERY DIPERBAIKI untuk mendapatkan foto dari tabel foto_mobil
$sql = "SELECT m.*, 
               u.nama_lengkap as penjual_nama,
               u.no_telepon as penjual_telepon,
               f.created_at as favorit_date,
               COALESCE(
                   (SELECT nama_file FROM foto_mobil WHERE mobil_id = m.id ORDER BY urutan LIMIT 1),
                   m.foto_mobil
               ) as foto_mobil_utama
        FROM favorit f
        JOIN mobil m ON f.mobil_id = m.id
        JOIN users u ON m.penjual_id = u.id
        WHERE f.pembeli_id = ?
        ORDER BY f.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id]);
$favorites = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Favorit Saya - Automarket</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .car-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            margin-bottom: 2rem;
            height: 100%;
        }
        .car-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }
        .car-img-container {
            height: 200px;
            overflow: hidden;
            border-radius: 15px 15px 0 0;
            position: relative;
            background: #f8f9fa;
        }
        .car-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }
        .car-card:hover .car-img {
            transform: scale(1.05);
        }
        .car-price {
            font-size: 1.3rem;
            font-weight: 700;
            color: #e74c3c;
        }
        .car-specs {
            color: #6c757d;
            font-size: 0.9rem;
        }
        .favorite-btn {
            position: absolute;
            top: 15px;
            right: 15px;
            background: rgba(255,255,255,0.9);
            border: none;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #e74c3c;
            font-size: 1.2rem;
            transition: all 0.3s ease;
            text-decoration: none;
            z-index: 10;
        }
        .favorite-btn:hover {
            background: #e74c3c;
            color: white;
            transform: scale(1.1);
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
        .status-badge {
            position: absolute;
            bottom: 10px;
            left: 10px;
            z-index: 5;
        }
        .card-body {
            display: flex;
            flex-direction: column;
            height: 100%;
        }
        .card-content {
            flex-grow: 1;
        }
        .card-actions {
            margin-top: auto;
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
    </style>
</head>
<body>
    <!-- Custom Header untuk Favorites -->
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
                            <li><a class="dropdown-item active" href="favorites.php"><i class="fas fa-heart me-2"></i>Favorit</a></li>
                            <li><a class="dropdown-item" href="my_bookings.php"><i class="fas fa-calendar-check me-2"></i>Booking Saya</a></li>
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
                                <img src="../uploads/profiles/<?php echo $_SESSION['foto_profil']; ?>" 
                                     class="user-avatar" alt="Profile">
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
                            <a href="favorites.php" class="list-group-item list-group-item-action active">
                                <i class="fas fa-heart me-2"></i>Favorit Saya
                            </a>
                            <a href="my_bookings.php" class="list-group-item list-group-item-action">
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
                    <h1 class="h3 mb-0"><i class="fas fa-heart me-2 text-danger"></i>Mobil Favorit Saya</h1>
                    <span class="badge bg-primary fs-6"><?php echo count($favorites); ?> Mobil</span>
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

                <?php if (!empty($favorites)): ?>
                    <div class="row">
                        <?php foreach ($favorites as $car): ?>
                            <div class="col-md-6 col-lg-4 mb-4">
                                <div class="card car-card">
                                    <div class="car-img-container">
                                        <?php
                                        // MENDAPATKAN FOTO MOBIL DENGAN BENAR
                                        $foto_url = '';
                                        if (!empty($car['foto_mobil_utama'])) {
                                            $foto_path = '../uploads/cars/' . $car['foto_mobil_utama'];
                                            if (file_exists($foto_path)) {
                                                $foto_url = $foto_path;
                                            }
                                        }
                                        
                                        if (empty($foto_url)) {
                                            $foto_url = 'https://via.placeholder.com/300x200/ffffff/666666?text=No+Image';
                                        }
                                        ?>
                                        
                                        <img src="<?php echo $foto_url; ?>" 
                                             class="car-img" 
                                             alt="<?php echo htmlspecialchars($car['merk'] . ' ' . $car['model']); ?>"
                                             onerror="this.src='https://via.placeholder.com/300x200/ffffff/666666?text=No+Image'">
                                        
                                        <a href="favorites.php?remove_favorite=<?php echo $car['id']; ?>" 
                                           class="favorite-btn"
                                           onclick="return confirm('Hapus dari favorit?')"
                                           title="Hapus dari Favorit">
                                            <i class="fas fa-heart"></i>
                                        </a>
                                        
                                        <div class="status-badge">
                                            <span class="badge bg-<?php echo ($car['status'] == 'tersedia') ? 'success' : 'secondary'; ?>">
                                                <?php echo ucfirst($car['status'] ?? 'tersedia'); ?>
                                            </span>
                                        </div>
                                    </div>
                                    
                                    <div class="card-body">
                                        <div class="card-content">
                                            <h5 class="card-title"><?php echo htmlspecialchars($car['merk'] . ' ' . $car['model']); ?></h5>
                                            
                                            <p class="car-specs mb-2">
                                                <i class="fas fa-calendar-alt me-1"></i>
                                                <?php echo htmlspecialchars($car['tahun']); ?> • 
                                                <i class="fas fa-tachometer-alt me-1"></i>
                                                <?php echo number_format($car['kilometer'] ?? 0); ?> km
                                            </p>
                                            
                                            <p class="car-specs mb-2">
                                                <i class="fas fa-gas-pump me-1"></i>
                                                <?php echo ucfirst($car['bahan_bakar'] ?? 'Bensin'); ?> • 
                                                <i class="fas fa-cog me-1"></i>
                                                <?php echo ucfirst($car['transmisi'] ?? 'Manual'); ?>
                                            </p>
                                            
                                            <p class="car-specs mb-3">
                                                <i class="fas fa-user me-1"></i>
                                                <?php echo htmlspecialchars($car['penjual_nama']); ?>
                                            </p>
                                            
                                            <?php if (!empty($car['deskripsi'])): ?>
                                                <p class="car-specs mb-3 small">
                                                    <?php echo htmlspecialchars(substr($car['deskripsi'], 0, 100)); ?>
                                                    <?php echo strlen($car['deskripsi']) > 100 ? '...' : ''; ?>
                                                </p>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="card-actions">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <div class="car-price">
                                                    Rp <?php echo number_format($car['harga'], 0, ',', '.'); ?>
                                                </div>
                                            </div>
                                            
                                            <div class="btn-group w-100">
                                                <a href="../car_detail.php?id=<?php echo $car['id']; ?>" 
                                                   class="btn btn-sm btn-primary flex-fill">
                                                    <i class="fas fa-eye me-1"></i>Detail
                                                </a>
                                                <a href="../booking.php?car_id=<?php echo $car['id']; ?>" 
                                                   class="btn btn-sm btn-success flex-fill">
                                                    <i class="fas fa-calendar-check me-1"></i>Booking
                                                </a>
                                            </div>
                                            
                                            <small class="text-muted d-block mt-2">
                                                <i class="fas fa-clock me-1"></i>
                                                Ditambahkan: <?php echo date('d M Y', strtotime($car['favorit_date'])); ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-heart text-muted"></i>
                        <h3 class="text-muted">Belum Ada Mobil Favorit</h3>
                        <p class="mb-4">Tambahkan mobil ke favorit untuk melihatnya di sini</p>
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
        // Konfirmasi sebelum menghapus favorit
        document.addEventListener('DOMContentLoaded', function() {
            const favoriteButtons = document.querySelectorAll('.favorite-btn');
            favoriteButtons.forEach(button => {
                button.addEventListener('click', function(e) {
                    if (!confirm('Yakin ingin menghapus mobil ini dari favorit?')) {
                        e.preventDefault();
                    }
                });
            });
        });
    </script>
</body>
</html>