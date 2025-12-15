<?php
// my_cars.php - Path yang benar untuk config.php di root
require_once __DIR__ . '/../config.php'; // Kembali 1 level ke root Automarket

// Cek login dan role
check_login();
check_role(['penjual']);

$user_id = $_SESSION['user_id'];

// Cek subscription info dengan error handling
try {
    $upload_check = $subscriptionManager->canUploadCar($user_id);
    $subscription_info = $subscriptionManager->getSubscriptionInfo($user_id);
} catch (Exception $e) {
    // Fallback jika subscription manager error
    $upload_check = [
        'can_upload' => true,
        'current_count' => 0,
        'max_allowed' => 999,
        'remaining' => 999
    ];
    $subscription_info = ['nama_plan' => 'Free', 'max_mobil' => 999];
}

// Get all cars by this seller
$sql = "SELECT * FROM mobil WHERE penjual_id = ? ORDER BY created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id]);
$cars = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle delete car
if (isset($_GET['delete_id'])) {
    $delete_id = sanitize($_GET['delete_id']);
    
    // Verify ownership before delete
    $sql_check = "SELECT id, foto_mobil FROM mobil WHERE id = ? AND penjual_id = ?";
    $stmt_check = $pdo->prepare($sql_check);
    $stmt_check->execute([$delete_id, $user_id]);
    
    if ($car_to_delete = $stmt_check->fetch()) {
        // Hapus file gambar jika ada
        if (!empty($car_to_delete['foto_mobil'])) {
            $image_path = UPLOAD_CAR_DIR . $car_to_delete['foto_mobil'];
            if (file_exists($image_path)) {
                unlink($image_path);
            }
        }
        
        $sql_delete = "DELETE FROM mobil WHERE id = ?";
        $stmt_delete = $pdo->prepare($sql_delete);
        if ($stmt_delete->execute([$delete_id])) {
            $_SESSION['success'] = "Mobil berhasil dihapus";
            redirect('seller/my_cars.php');
        }
    }
    $_SESSION['error'] = "Gagal menghapus mobil";
    redirect('seller/my_cars.php');
}

// Count total unread messages untuk badge
$unread_sql = "SELECT COUNT(*) as total_unread FROM pesan WHERE penerima_id = ? AND dibaca = 0";
$unread_stmt = $pdo->prepare($unread_sql);
$unread_stmt->execute([$user_id]);
$total_unread = $unread_stmt->fetch(PDO::FETCH_ASSOC)['total_unread'];
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mobil Saya - Automarket</title>
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
        .car-image-thumb {
            width: 60px;
            height: 40px;
            object-fit: cover;
            border-radius: 4px;
        }
        .status-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
        }
        .stats-badge {
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            font-size: 0.9rem;
        }
        .subscription-alert {
            background: #e8f4fc;
            border: 1px solid #b6d4fe;
        }
        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
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
            <a class="nav-link active" href="my_cars.php">
                <i class="fas fa-car"></i> Mobil Saya
            </a>
            <a class="nav-link" href="bookings.php">
                <i class="fas fa-calendar-check"></i> Booking Saya
            </a>
            <a class="nav-link" href="messages.php">
                <i class="fas fa-comments"></i> Pesan
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
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="mb-1">
                    <i class="fas fa-car text-success me-2"></i>
                    Mobil Saya
                </h2>
                <p class="text-muted mb-0">Kelola semua mobil yang Anda jual</p>
            </div>
            <a href="add_car.php" class="btn btn-success <?php echo !$upload_check['can_upload'] ? 'disabled' : ''; ?>">
                <i class="fas fa-plus me-2"></i> Tambah Mobil
            </a>
        </div>

        <!-- Subscription Status -->
        <div class="alert alert-info subscription-alert mb-4">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <i class="fas fa-chart-line me-2"></i>
                    <strong>Paket:</strong> 
                    <span class="badge bg-<?php echo ($subscription_info['nama_plan'] ?? 'Free') == 'Free' ? 'secondary' : 'success'; ?>">
                        <?php echo $subscription_info['nama_plan'] ?? 'Free'; ?> Plan
                    </span>
                    | 
                    <strong>Upload:</strong> 
                    <span class="fw-bold <?php echo $upload_check['current_count'] >= $upload_check['max_allowed'] ? 'text-danger' : 'text-success'; ?>">
                        <?php echo $upload_check['current_count']; ?>/<?php echo $upload_check['max_allowed']; ?> mobil
                    </span>
                    |
                    <strong>Sisa Kuota:</strong> 
                    <span class="badge bg-<?php echo $upload_check['remaining'] > 0 ? 'success' : 'danger'; ?>">
                        <i class="fas fa-<?php echo $upload_check['remaining'] > 0 ? 'check' : 'times'; ?> me-1"></i>
                        <?php echo $upload_check['remaining']; ?> slot tersedia
                    </span>
                </div>
                <?php if (($subscription_info['nama_plan'] ?? 'Free') == 'Free'): ?>
                    <a href="subscription.php" class="btn btn-warning btn-sm">
                        <i class="fas fa-crown me-1"></i> Upgrade Plan
                    </a>
                <?php elseif (isset($subscription_info['berakhir_tanggal'])): ?>
                    <small class="text-muted">
                        Berlaku hingga: <?php echo date('d M Y', strtotime($subscription_info['berakhir_tanggal'])); ?>
                    </small>
                <?php endif; ?>
            </div>
            <?php if (!$upload_check['can_upload']): ?>
                <div class="mt-2 alert alert-warning p-2 mb-0">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Kuota penuh!</strong> Anda telah mencapai batas maksimal upload. 
                    <a href="subscription.php" class="alert-link">Upgrade subscription</a> untuk menambah kuota.
                </div>
            <?php endif; ?>
        </div>

        <!-- Messages -->
        <?php if(isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show mb-4">
                <i class="fas fa-check-circle me-2"></i>
                <?php echo $_SESSION['success']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                <?php unset($_SESSION['success']); ?>
            </div>
        <?php endif; ?>

        <?php if(isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show mb-4">
                <i class="fas fa-exclamation-circle me-2"></i>
                <?php echo $_SESSION['error']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                <?php unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>

        <!-- Car List -->
        <?php if(empty($cars)): ?>
            <div class="card text-center py-5 border-0 shadow-sm">
                <div class="card-body py-5">
                    <i class="fas fa-car fa-4x text-muted mb-4 opacity-50"></i>
                    <h4 class="card-title mb-3">Belum ada mobil yang dijual</h4>
                    <p class="card-text text-muted mb-4">Mulai jual mobil pertama Anda dan raih keuntungan!</p>
                    <a href="add_car.php" class="btn btn-success btn-lg px-4">
                        <i class="fas fa-plus me-2"></i> Jual Mobil Pertama
                    </a>
                </div>
            </div>
        <?php else: ?>
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="fas fa-list me-2 text-success"></i>
                            Daftar Mobil Anda
                        </h5>
                        <span class="badge bg-light text-dark">
                            Total: <?php echo count($cars); ?> mobil
                        </span>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th width="50" class="text-center">#</th>
                                    <th>Mobil</th>
                                    <th width="120">Plat</th>
                                    <th width="80">Tahun</th>
                                    <th width="150">Harga</th>
                                    <th width="120">Status</th>
                                    <th width="130">Tanggal</th>
                                    <th width="150" class="text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($cars as $index => $car): ?>
                                <tr>
                                    <td class="text-center"><?php echo $index + 1; ?></td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <?php if(!empty($car['foto_mobil']) && file_exists(UPLOAD_CAR_DIR . $car['foto_mobil'])): ?>
                                                <img src="<?php echo BASE_URL; ?>uploads/cars/<?php echo $car['foto_mobil']; ?>" 
                                                     class="car-image-thumb me-3" 
                                                     alt="<?php echo htmlspecialchars($car['merk']); ?>"
                                                     onerror="this.src='https://via.placeholder.com/60x40?text=No+Image'">
                                            <?php else: ?>
                                                <div class="car-image-thumb me-3 bg-light d-flex align-items-center justify-content-center">
                                                    <i class="fas fa-car text-muted"></i>
                                                </div>
                                            <?php endif; ?>
                                            <div>
                                                <strong class="d-block"><?php echo htmlspecialchars($car['merk'] . ' ' . $car['model']); ?></strong>
                                                <small class="text-muted"><?php echo htmlspecialchars($car['warna'] . ' • ' . ucfirst($car['transmisi'])); ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td><code><?php echo htmlspecialchars($car['plat_mobil']); ?></code></td>
                                    <td><?php echo htmlspecialchars($car['tahun']); ?></td>
                                    <td class="fw-bold text-success"><?php echo format_rupiah($car['harga']); ?></td>
                                    <td>
                                        <span class="badge bg-<?php 
                                            switch($car['status']) {
                                                case 'tersedia': echo 'success'; break;
                                                case 'dipesan': echo 'warning'; break;
                                                case 'terjual': echo 'secondary'; break;
                                                default: echo 'info';
                                            }
                                        ?> status-badge">
                                            <i class="fas fa-<?php 
                                                switch($car['status']) {
                                                    case 'tersedia': echo 'check-circle'; break;
                                                    case 'dipesan': echo 'clock'; break;
                                                    case 'terjual': echo 'check'; break;
                                                    default: echo 'info-circle';
                                                }
                                            ?> me-1"></i>
                                            <?php echo ucfirst($car['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('d/m/Y', strtotime($car['created_at'])); ?></td>
                                    <td class="text-center">
                                        <div class="btn-group btn-group-sm" role="group">
                                            <a href="<?php echo BASE_URL; ?>car_detail.php?id=<?php echo $car['id']; ?>" 
                                               class="btn btn-outline-primary" 
                                               title="Lihat Detail" target="_blank" data-bs-toggle="tooltip">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="edit_car.php?id=<?php echo $car['id']; ?>" 
                                               class="btn btn-outline-warning"
                                               title="Edit" data-bs-toggle="tooltip">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="my_cars.php?delete_id=<?php echo $car['id']; ?>" 
                                               class="btn btn-outline-danger" 
                                               onclick="return confirm('Yakin ingin menghapus mobil <?php echo addslashes($car['merk'] . ' ' . $car['model']); ?>?')"
                                               title="Hapus" data-bs-toggle="tooltip">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Statistics -->
            <div class="mt-4 p-4 bg-light rounded shadow-sm">
                <h6 class="mb-3"><i class="fas fa-chart-pie me-2 text-success"></i>Statistik Mobil Anda</h6>
                <div class="row">
                    <div class="col-md-8">
                        <div class="d-flex flex-wrap gap-3">
                            <span class="stats-badge bg-success text-white">
                                <i class="fas fa-check-circle me-2"></i>
                                <strong><?php echo count(array_filter($cars, fn($car) => $car['status'] == 'tersedia')); ?></strong> Tersedia
                            </span>
                            <span class="stats-badge bg-warning text-dark">
                                <i class="fas fa-clock me-2"></i>
                                <strong><?php echo count(array_filter($cars, fn($car) => $car['status'] == 'dipesan')); ?></strong> Dipesan
                            </span>
                            <span class="stats-badge bg-secondary text-white">
                                <i class="fas fa-check me-2"></i>
                                <strong><?php echo count(array_filter($cars, fn($car) => $car['status'] == 'terjual')); ?></strong> Terjual
                            </span>
                            <span class="stats-badge bg-info text-white">
                                <i class="fas fa-car me-2"></i>
                                <strong><?php echo count($cars); ?></strong> Total
                            </span>
                        </div>
                    </div>
                    <div class="col-md-4 text-end">
                        <p class="mb-1">
                            <small class="text-muted">
                                <i class="fas fa-sync-alt me-1"></i>
                                Update terakhir: <?php echo date('H:i'); ?>
                            </small>
                        </p>
                        <small class="text-muted">
                            <?php echo date('d M Y'); ?>
                        </small>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto dismiss alerts after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize tooltips
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
            
            // Auto dismiss alerts
            setTimeout(function() {
                var alerts = document.querySelectorAll('.alert:not(.alert-info)');
                alerts.forEach(function(alert) {
                    var bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                });
            }, 5000);
        });
        
        // Confirm before deleting
        function confirmDelete(carName) {
            return confirm('Yakin ingin menghapus mobil "' + carName + '"? Tindakan ini tidak dapat dibatalkan!');
        }
    </script>
</body>
</html>