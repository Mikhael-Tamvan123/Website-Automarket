<?php
require_once 'config.php';
require_once 'includes/car_manager.php';
require_once 'includes/favorite_manager.php';
require_once 'includes/message_manager.php';

if (!isset($_GET['id'])) {
    header("Location: cars.php");
    exit();
}

$car_id = intval($_GET['id']);
$car = $carManager->getCarById($car_id);

if (!$car) {
    header("Location: cars.php");
    exit();
}

// CEK STATUS BOOKING MOBIL
$is_booked = false;
$booking_status = '';
$booking_data = null;

// Query untuk mengecek apakah mobil sedang dibooking
$booking_check_sql = "SELECT status FROM transaksi_booking 
                      WHERE mobil_id = :mobil_id 
                      AND status IN ('pending', 'dikonfirmasi') 
                      LIMIT 1";
$booking_check_stmt = $pdo->prepare($booking_check_sql);
$booking_check_stmt->execute([':mobil_id' => $car_id]);
$booking_data = $booking_check_stmt->fetch();

if ($booking_data) {
    $is_booked = true;
    $booking_status = $booking_data['status'] == 'pending' ? 'Booking Pending' : 'Sudah di Booking';
}

// Get photos
$photos = $carManager->getCarPhotos($car_id);

// PERBAIKAN: Handle foto utama
$main_image = '';
if (!empty($car['foto_mobil'])) {
    $image_path = 'uploads/cars/' . $car['foto_mobil'];
    if (file_exists($image_path)) {
        $main_image = $image_path;
    }
}

// Jika tidak ada foto utama, coba ambil dari tabel foto_mobil
if (empty($main_image) && !empty($photos)) {
    $image_path = 'uploads/cars/' . $photos[0]['nama_file'];
    if (file_exists($image_path)) {
        $main_image = $image_path;
    }
}

// Check if favorite
$is_favorite = false;
if (isset($_SESSION['user_id']) && $_SESSION['role'] == 'pembeli') {
    $is_favorite = $favoriteManager->isFavorite($_SESSION['user_id'], $car_id);
}

// Handle booking
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['booking'])) {
    if (!isset($_SESSION['user_id'])) {
        $_SESSION['error'] = "Silakan login terlebih dahulu untuk booking";
        header("Location: login.php");
        exit();
    }
    
    if ($_SESSION['role'] != 'pembeli') {
        $_SESSION['error'] = "Hanya pembeli yang dapat melakukan booking";
        header("Location: car_detail.php?id=" . $car_id);
        exit();
    }
    
    // Cek apakah mobil sudah dibooking
    if ($is_booked) {
        $_SESSION['error'] = "Maaf, mobil ini sudah " . strtolower($booking_status) . " oleh pembeli lain";
        header("Location: car_detail.php?id=" . $car_id);
        exit();
    }
    
    // Redirect to booking page
    header("Location: booking.php?car_id=" . $car_id);
    exit();
}

// Handle send message
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['send_message'])) {
    if (!isset($_SESSION['user_id'])) {
        $_SESSION['error'] = "Silakan login terlebih dahulu untuk mengirim pesan";
        header("Location: login.php");
        exit();
    }
    
    $pesan = sanitize($_POST['pesan']);
    if (!empty($pesan)) {
        $messageManager->sendMessage($_SESSION['user_id'], $car['penjual_id'], $car_id, $pesan);
        $_SESSION['success'] = "Pesan berhasil dikirim!";
        header("Location: car_detail.php?id=" . $car_id);
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $car['merk'] . ' ' . $car['model']; ?> - Automarket</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #2c3e50;
            --secondary: #3498db;
            --accent: #e74c3c;
            --warning: #f39c12;
            --success: #27ae60;
            --light-bg: #f8f9fa;
            --dark-text: #2c3e50;
            --shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            --shadow-hover: 0 8px 15px rgba(0, 0, 0, 0.2);
        }

        * {
            font-family: 'Poppins', sans-serif;
        }

        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
        }

        .navbar-brand {
            font-weight: 700;
            color: var(--primary) !important;
        }

        .car-image {
            height: 500px;
            object-fit: cover;
            border-radius: 15px;
            transition: transform 0.3s ease;
            box-shadow: var(--shadow);
        }

        .car-image:hover {
            transform: scale(1.02);
        }

        .thumbnail {
            height: 80px;
            width: 80px;
            object-fit: cover;
            cursor: pointer;
            border: 3px solid transparent;
            border-radius: 10px;
            transition: all 0.3s ease;
            box-shadow: var(--shadow);
        }

        .thumbnail:hover {
            transform: translateY(-2px);
            border-color: var(--secondary);
        }

        .thumbnail.active {
            border-color: var(--accent);
            transform: scale(1.1);
        }

        .spec-item {
            padding: 12px 0;
            border-bottom: 1px solid #e9ecef;
            transition: background-color 0.3s ease;
        }

        .spec-item:hover {
            background-color: var(--light-bg);
            padding-left: 10px;
        }

        .price-tag {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--accent);
            text-shadow: 2px 2px 4px rgba(0,0,0,0.1);
        }

        .card {
            border: none;
            border-radius: 15px;
            box-shadow: var(--shadow);
            transition: all 0.3s ease;
            overflow: hidden;
        }

        .card:hover {
            box-shadow: var(--shadow-hover);
            transform: translateY(-5px);
        }

        .card-header {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            border-bottom: none;
            padding: 15px 20px;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--secondary), #2980b9);
            border: none;
            border-radius: 10px;
            padding: 12px 30px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-hover);
        }

        .btn-secondary {
            background: linear-gradient(135deg, #95a5a6, #7f8c8d);
            border: none;
            border-radius: 10px;
            padding: 12px 30px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-outline-danger {
            border-radius: 10px;
            padding: 12px 30px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .favorite-btn:hover {
            transform: translateY(-2px);
        }

        .breadcrumb {
            background: rgba(255,255,255,0.9);
            border-radius: 10px;
            padding: 15px;
            box-shadow: var(--shadow);
        }

        .alert {
            border-radius: 10px;
            border: none;
            box-shadow: var(--shadow);
        }

        .seller-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
        }

        .feature-icon {
            width: 50px;
            height: 50px;
            background: var(--secondary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            color: white;
            font-size: 1.2rem;
        }

        .spec-badge {
            background: linear-gradient(135deg, var(--secondary), #2980b9);
            color: white;
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 0.9rem;
            margin: 5px;
        }

        .message-box {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 15px;
            color: white;
        }

        .message-box .form-control {
            border-radius: 10px;
            border: none;
            padding: 12px;
        }

        .floating-action {
            position: fixed;
            bottom: 30px;
            right: 30px;
            z-index: 1000;
        }

        .floating-action .btn {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            box-shadow: var(--shadow-hover);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .fade-in {
            animation: fadeIn 0.6s ease-in-out;
        }

        .sticky-sidebar {
            position: sticky;
            top: 20px;
            height: fit-content;
            max-height: calc(100vh - 40px);
            overflow-y: auto;
        }

        /* TAMBAHAN STYLE UNTUK BOOKING STATUS */
        .booking-status-badge {
            position: absolute;
            top: 20px;
            right: 20px;
            background: linear-gradient(135deg, var(--warning), #e67e22);
            color: white;
            padding: 10px 20px;
            border-radius: 25px;
            font-weight: 600;
            font-size: 0.9rem;
            z-index: 100;
            box-shadow: 0 5px 15px rgba(243, 156, 18, 0.3);
            transform: rotate(5deg);
        }

        .booking-status-badge.pending {
            background: linear-gradient(135deg, var(--warning), #e67e22);
        }

        .booking-status-badge.confirmed {
            background: linear-gradient(135deg, var(--success), #27ae60);
        }

        .booked-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(44, 62, 80, 0.7);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 2;
            border-radius: 15px 15px 0 0;
            color: white;
            font-size: 1.8rem;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 3px;
        }

        .btn-disabled {
            opacity: 0.6;
            cursor: not-allowed !important;
        }

        .btn-disabled:hover {
            transform: none !important;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="container mt-4">
        <?php if(isset($_SESSION['success'])): ?>
            <div class="alert alert-success fade-in"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
        <?php endif; ?>
        
        <?php if(isset($_SESSION['error'])): ?>
            <div class="alert alert-danger fade-in"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
        <?php endif; ?>

        <!-- NOTIFIKASI STATUS BOOKING -->
        <?php if($is_booked): ?>
            <div class="alert alert-warning fade-in">
                <div class="d-flex align-items-center">
                    <i class="fas fa-exclamation-triangle fa-2x me-3"></i>
                    <div>
                        <h5 class="mb-1">Mobil ini <?php echo strtolower($booking_status); ?></h5>
                        <p class="mb-0">Mobil sedang dalam proses booking oleh pembeli lain. Anda masih bisa melihat detailnya, tetapi tidak dapat melakukan booking.</p>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <nav aria-label="breadcrumb" class="mb-4 fade-in">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none"><i class="fas fa-home me-1"></i>Beranda</a></li>
                <li class="breadcrumb-item"><a href="cars.php" class="text-decoration-none"><i class="fas fa-car me-1"></i>Mobil</a></li>
                <li class="breadcrumb-item active text-primary"><i class="fas fa-info-circle me-1"></i><?php echo $car['merk'] . ' ' . $car['model']; ?></li>
            </ol>
        </nav>

        <div class="row fade-in">
            <!-- Car Images & Specifications - KOLOM KIRI -->
            <div class="col-lg-8 col-md-7">
                <!-- Galeri Mobil -->
                <div class="card mb-4 position-relative">
                    <!-- BOOKING STATUS BADGE -->
                    <?php if($is_booked): ?>
                        <div class="booking-status-badge <?php echo $booking_data['status']; ?>">
                            <i class="fas fa-calendar-check me-2"></i><?php echo $booking_status; ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-images me-2"></i>Galeri Mobil</h5>
                    </div>
                    <div class="card-body position-relative">
                        <?php if(!empty($main_image)): ?>
                            <div class="position-relative">
                                <!-- OVERLAY UNTUK MOBIL YANG DIBOOKING -->
                                <?php if($is_booked): ?>
                                    <div class="booked-overlay">
                                        <div class="text-center">
                                            <i class="fas fa-calendar-alt mb-3" style="font-size: 3rem;"></i>
                                            <div><?php echo $booking_status; ?></div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <img id="mainImage" src="<?php echo $main_image; ?>" class="car-image w-100 mb-3" alt="<?php echo $car['merk'] . ' ' . $car['model']; ?>">
                            </div>
                            
                            <div class="d-flex gap-2 justify-content-center flex-wrap">
                                <img src="<?php echo $main_image; ?>" 
                                     class="thumbnail active" 
                                     onclick="changeImage(this)" 
                                     alt="Thumbnail">
                                <!-- Add more thumbnails from foto_mobil table -->
                                <?php
                                if (!empty($photos)) {
                                    foreach ($photos as $index => $photo) {
                                        if ($index > 0) { // Skip the first one if it's the same as main_image
                                            $photo_path = 'uploads/cars/' . $photo['nama_file'];
                                            if (file_exists($photo_path) && $photo_path != $main_image) {
                                                echo '<img src="' . $photo_path . '" 
                                                     class="thumbnail" 
                                                     onclick="changeImage(this)" 
                                                     alt="Thumbnail">';
                                            }
                                        }
                                    }
                                }
                                ?>
                            </div>
                        <?php else: ?>
                            <div class="car-image w-100 mb-3 bg-light d-flex align-items-center justify-content-center rounded position-relative">
                                <?php if($is_booked): ?>
                                    <div class="booked-overlay">
                                        <div class="text-center">
                                            <i class="fas fa-calendar-alt mb-3" style="font-size: 3rem;"></i>
                                            <div><?php echo $booking_status; ?></div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="text-center text-muted">
                                    <i class="fas fa-car fa-5x mb-3"></i>
                                    <p>Gambar tidak tersedia</p>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Car Specifications -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-list-alt me-2"></i>Spesifikasi Detail</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="spec-item">
                                    <div class="d-flex align-items-center">
                                        <div class="feature-icon">
                                            <i class="fas fa-car"></i>
                                        </div>
                                        <div>
                                            <strong>Merk/Model</strong><br>
                                            <?php echo $car['merk'] . ' ' . $car['model']; ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="spec-item">
                                    <div class="d-flex align-items-center">
                                        <div class="feature-icon">
                                            <i class="fas fa-calendar-alt"></i>
                                        </div>
                                        <div>
                                            <strong>Tahun</strong><br>
                                            <?php echo $car['tahun']; ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="spec-item">
                                    <div class="d-flex align-items-center">
                                        <div class="feature-icon">
                                            <i class="fas fa-palette"></i>
                                        </div>
                                        <div>
                                            <strong>Warna</strong><br>
                                            <?php echo $car['warna']; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="spec-item">
                                    <div class="d-flex align-items-center">
                                        <div class="feature-icon">
                                            <i class="fas fa-tachometer-alt"></i>
                                        </div>
                                        <div>
                                            <strong>Kilometer</strong><br>
                                            <?php echo number_format($car['kilometer']); ?> km
                                        </div>
                                    </div>
                                </div>
                                <div class="spec-item">
                                    <div class="d-flex align-items-center">
                                        <div class="feature-icon">
                                            <i class="fas fa-gas-pump"></i>
                                        </div>
                                        <div>
                                            <strong>Bahan Bakar</strong><br>
                                            <?php echo ucfirst($car['bahan_bakar']); ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="spec-item">
                                    <div class="d-flex align-items-center">
                                        <div class="feature-icon">
                                            <i class="fas fa-cog"></i>
                                        </div>
                                        <div>
                                            <strong>Transmisi</strong><br>
                                            <?php echo ucfirst(str_replace('_', ' ', $car['transmisi'])); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Additional Spec Badges -->
                        <div class="mt-4">
                            <span class="spec-badge"><i class="fas fa-engine me-1"></i> <?php echo number_format($car['slinder']); ?> cc</span>
                            <span class="spec-badge"><i class="fas fa-plate me-1"></i> <?php echo $car['plat_mobil']; ?></span>
                            <span class="spec-badge"><i class="fas fa-engine me-1"></i> No. Mesin: <?php echo $car['no_mesin']; ?></span>
                            
                            <!-- BADGE STATUS BOOKING -->
                            <?php if($is_booked): ?>
                                <span class="spec-badge" style="background: linear-gradient(135deg, var(--warning), #e67e22);">
                                    <i class="fas fa-calendar-check me-1"></i> <?php echo $booking_status; ?>
                                </span>
                            <?php endif; ?>
                        </div>

                        <?php if(!empty($car['deskripsi'])): ?>
                        <div class="mt-4">
                            <h6><i class="fas fa-file-alt me-2"></i>Deskripsi</h6>
                            <div class="p-3 bg-light rounded">
                                <p class="mb-0"><?php echo nl2br($car['deskripsi']); ?></p>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div> <!-- TUTUP col-lg-8 col-md-7 -->

            <!-- Car Details Sidebar - KOLOM KANAN -->
            <div class="col-lg-4 col-md-5">
                <div class="sticky-sidebar">
                    <!-- Price & Action Card -->
                    <div class="card mb-4">
                        <div class="card-body text-center">
                            <h2 class="card-title text-primary"><?php echo $car['merk'] . ' ' . $car['model']; ?></h2>
                            <p class="text-muted mb-3">
                                <i class="fas fa-calendar-alt me-1"></i>Tahun <?php echo $car['tahun']; ?> 
                                • <i class="fas fa-road me-1"></i><?php echo number_format($car['kilometer']); ?> km
                            </p>
                            
                            <div class="price-tag mb-4">
                                Rp <?php echo number_format($car['harga'], 0, ',', '.'); ?>
                            </div>
                            
                            <!-- NOTIFIKASI STATUS DI DALAM CARD -->
                            <?php if($is_booked): ?>
                                <div class="alert alert-warning mb-4 text-center py-2">
                                    <i class="fas fa-exclamation-circle me-2"></i>
                                    <strong>Mobil <?php echo strtolower($booking_status); ?></strong>
                                </div>
                            <?php endif; ?>
                            
                            <div class="d-grid gap-2 mb-4">
                                <?php if(isset($_SESSION['user_id']) && $_SESSION['role'] == 'pembeli'): ?>
                                    <?php if($is_booked): ?>
                                        <!-- TOMBOL BOOKING DINONAKTIFKAN -->
                                        <button class="btn btn-secondary btn-lg w-100 py-3" disabled>
                                            <i class="fas fa-calendar-times me-2"></i> Sudah di Booking
                                        </button>
                                        
                                        <!-- TOMBOL FAVORIT MASIH AKTIF TAPI TAMBAHKAN DISABLED STATE -->
                                        <button class="favorite-btn btn <?php echo $is_favorite ? 'btn-danger' : 'btn-outline-danger'; ?> w-100 py-3 btn-disabled" 
                                                data-car-id="<?php echo $car_id; ?>" 
                                                title="Mobil sudah dibooking">
                                            <i class="<?php echo $is_favorite ? 'fas' : 'far'; ?> fa-heart me-2"></i>
                                            <span><?php echo $is_favorite ? 'Hapus Favorit' : 'Tambah Favorit'; ?></span>
                                        </button>
                                    <?php else: ?>
                                        <!-- TOMBOL BOOKING NORMAL -->
                                        <form method="POST" class="d-inline">
                                            <button type="submit" name="booking" class="btn btn-primary btn-lg w-100 py-3">
                                                <i class="fas fa-calendar-check me-2"></i> Booking Sekarang
                                            </button>
                                        </form>
                                        
                                        <!-- TOMBOL FAVORIT NORMAL -->
                                        <button class="favorite-btn btn <?php echo $is_favorite ? 'btn-danger' : 'btn-outline-danger'; ?> w-100 py-3" data-car-id="<?php echo $car_id; ?>">
                                            <i class="<?php echo $is_favorite ? 'fas' : 'far'; ?> fa-heart me-2"></i>
                                            <span><?php echo $is_favorite ? 'Hapus Favorit' : 'Tambah Favorit'; ?></span>
                                        </button>
                                    <?php endif; ?>
                                <?php elseif(!isset($_SESSION['user_id'])): ?>
                                    <?php if($is_booked): ?>
                                        <button class="btn btn-secondary btn-lg w-100 py-3" disabled>
                                            <i class="fas fa-calendar-times me-2"></i> Sudah di Booking
                                        </button>
                                    <?php else: ?>
                                        <a href="login.php" class="btn btn-primary btn-lg w-100 py-3">
                                            <i class="fas fa-calendar-check me-2"></i> Login untuk Booking
                                        </a>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Seller Info -->
                    <div class="card seller-card mb-4">
                        <div class="card-body">
                            <h5 class="card-title mb-3"><i class="fas fa-user-tie me-2"></i>Informasi Penjual</h5>
                            <div class="d-flex align-items-center mb-3">
                                <div class="bg-white rounded-circle p-2 me-3">
                                    <i class="fas fa-user text-primary"></i>
                                </div>
                                <div>
                                    <strong><?php echo $car['penjual_nama']; ?></strong><br>
                                    <small>Terdaftar sejak <?php echo date('M Y', strtotime($car['created_at'])); ?></small>
                                </div>
                            </div>
                            <div class="d-flex align-items-center mb-2">
                                <i class="fas fa-phone me-2"></i>
                                <span><?php echo $car['penjual_telepon']; ?></span>
                            </div>
                            <div class="d-flex align-items-center">
                                <i class="fas fa-envelope me-2"></i>
                                <span><?php echo isset($car['email']) ? $car['email'] : 'Email tidak tersedia'; ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Message -->
                    <?php if(isset($_SESSION['user_id']) && $_SESSION['user_id'] != $car['penjual_id']): ?>
                    <div class="card message-box">
                        <div class="card-body">
                            <h5 class="card-title mb-3"><i class="fas fa-comments me-2"></i>Kirim Pesan ke Penjual</h5>
                            <form method="POST">
                                <div class="mb-3">
                                    <textarea name="pesan" class="form-control" rows="3" 
                                              placeholder="Halo, saya tertarik dengan mobil ini. Bisa beri informasi lebih lanjut?" 
                                              <?php echo $is_booked ? 'placeholder="Mobil sudah dibooking, tetapi Anda bisa menanyakan informasi lain."' : ''; ?>
                                              required></textarea>
                                </div>
                                <button type="submit" name="send_message" class="btn btn-light w-100">
                                    <i class="fas fa-paper-plane me-2"></i> Kirim Pesan
                                </button>
                            </form>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div> <!-- TUTUP col-lg-4 col-md-5 -->
        </div> <!-- TUTUP row -->
    </div>

    <!-- Floating Action Button -->
    <div class="floating-action">
        <button class="btn btn-primary" onclick="scrollToTop()">
            <i class="fas fa-arrow-up"></i>
        </button>
    </div>

    <?php include 'includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function changeImage(element) {
            document.getElementById('mainImage').src = element.src;
            document.querySelectorAll('.thumbnail').forEach(thumb => {
                thumb.classList.remove('active');
            });
            element.classList.add('active');
        }

        // Favorite functionality - ONLY FOR NON-BOOKED CARS
        const favoriteBtn = document.querySelector('.favorite-btn:not(.btn-disabled)');
        if (favoriteBtn) {
            favoriteBtn.addEventListener('click', function() {
                const carId = this.getAttribute('data-car-id');
                const icon = this.querySelector('i');
                const text = this.querySelector('span');
                
                fetch('includes/favorite_action.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'car_id=' + carId + '&action=toggle'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        if (data.is_favorite) {
                            icon.classList.remove('far');
                            icon.classList.add('fas');
                            text.textContent = 'Hapus Favorit';
                            this.classList.add('btn-danger');
                            this.classList.remove('btn-outline-danger');
                        } else {
                            icon.classList.remove('fas');
                            icon.classList.add('far');
                            text.textContent = 'Tambah Favorit';
                            this.classList.remove('btn-danger');
                            this.classList.add('btn-outline-danger');
                        }
                    }
                });
            });
        }

        function scrollToTop() {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        // Add scroll effect to floating button
        window.addEventListener('scroll', function() {
            const floatingBtn = document.querySelector('.floating-action');
            if (window.scrollY > 300) {
                floatingBtn.style.opacity = '1';
                floatingBtn.style.transform = 'translateY(0)';
            } else {
                floatingBtn.style.opacity = '0';
                floatingBtn.style.transform = 'translateY(20px)';
            }
        });

        // Initialize floating button
        document.querySelector('.floating-action').style.opacity = '0';
        document.querySelector('.floating-action').style.transition = 'all 0.3s ease';
    </script>
</body>
</html>