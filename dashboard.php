<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/subscription_manager.php';

// Cek login dan role
if (!$auth->isLoggedIn()) {
    header("Location: ../login.php");
    exit();
}

if (!$auth->checkRole('penjual')) {
    header("Location: ../index.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Cek subscription info
$subscriptionManager = new SubscriptionManager($pdo);
$upload_check = $subscriptionManager->canUploadCar($user_id);
$subscription_info = $subscriptionManager->getSubscriptionInfo($user_id);

// Count total unread messages untuk badge
$unread_sql = "SELECT COUNT(*) as total_unread FROM pesan WHERE penerima_id = ? AND dibaca = 0";
$unread_stmt = $pdo->prepare($unread_sql);
$unread_stmt->execute([$user_id]);
$total_unread = $unread_stmt->fetch(PDO::FETCH_ASSOC)['total_unread'];

// Get statistics for seller
$sql_my_cars = "SELECT COUNT(*) as total FROM mobil WHERE penjual_id = ?";
$sql_sold_cars = "SELECT COUNT(*) as total FROM mobil WHERE penjual_id = ? AND status = 'terjual'";
$sql_active_cars = "SELECT COUNT(*) as total FROM mobil WHERE penjual_id = ? AND status = 'tersedia'";
$sql_my_bookings = "SELECT COUNT(*) as total FROM transaksi_booking WHERE penjual_id = ?";

$total_my_cars = $pdo->prepare($sql_my_cars);
$total_my_cars->execute([$user_id]);
$total_my_cars = $total_my_cars->fetchColumn();

$sold_cars = $pdo->prepare($sql_sold_cars);
$sold_cars->execute([$user_id]);
$sold_cars = $sold_cars->fetchColumn();

$active_cars = $pdo->prepare($sql_active_cars);
$active_cars->execute([$user_id]);
$active_cars = $active_cars->fetchColumn();

$my_bookings = $pdo->prepare($sql_my_bookings);
$my_bookings->execute([$user_id]);
$my_bookings = $my_bookings->fetchColumn();

// Get my cars (5 terbaru)
$sql_my_cars_list = "SELECT * FROM mobil WHERE penjual_id = ? ORDER BY created_at DESC LIMIT 5";
$stmt_my_cars = $pdo->prepare($sql_my_cars_list);
$stmt_my_cars->execute([$user_id]);
$my_cars = $stmt_my_cars->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Penjual - Automarket</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
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
        }
        .stat-card {
            border-radius: 10px;
            padding: 20px;
            color: white;
            margin-bottom: 20px;
            transition: transform 0.3s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .stat-card.primary { background: #3498db; }
        .stat-card.success { background: #27ae60; }
        .stat-card.warning { background: #f39c12; }
        .stat-card.info { background: #17a2b8; }
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
        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
            }
            .main-content {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-brand">
            <h3>Automarket</h3>
            <small>Seller Panel</small>
        </div>
        
        <nav class="nav flex-column">
            <a class="nav-link active" href="dashboard.php">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>
            <a class="nav-link" href="add_car.php">
                <i class="fas fa-plus-circle"></i> +Jual Mobil
            </a>
            <a class="nav-link" href="my_cars.php">
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

    <div class="main-content">
        <div class="p-4">
            <h2 class="mb-4">Dashboard Penjual</h2>
            <p class="text-muted">Selamat datang, <?php echo $_SESSION['nama_lengkap']; ?>!</p>
            
            <!-- Statistics Cards -->
            <div class="row">
                <div class="col-md-3">
                    <div class="stat-card primary">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h3><?php echo $total_my_cars; ?></h3>
                                <p>Total Mobil</p>
                            </div>
                            <i class="fas fa-car fa-2x"></i>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card success">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h3><?php echo $active_cars; ?></h3>
                                <p>Mobil Aktif</p>
                            </div>
                            <i class="fas fa-eye fa-2x"></i>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card warning">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h3><?php echo $sold_cars; ?></h3>
                                <p>Terjual</p>
                            </div>
                            <i class="fas fa-check-circle fa-2x"></i>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card info">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h3><?php echo $my_bookings; ?></h3>
                                <p>Total Booking</p>
                            </div>
                            <i class="fas fa-calendar-check fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Subscription Status -->
            <div class="row mt-3">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-crown me-2 text-warning"></i>
                                    Status Subscription
                                </h5>
                                <a href="subscription.php" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-info-circle me-1"></i> Detail Plan
                                </a>
                            </div>
                            
                            <div class="row align-items-center">
                                <div class="col-md-3">
                                    <div class="text-center">
                                        <div class="display-6 fw-bold text-primary">
                                            <?php echo $upload_check['current_count']; ?>/<?php echo $upload_check['max_allowed']; ?>
                                        </div>
                                        <p class="text-muted mb-0">Mobil Diupload</p>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-2">
                                        <span class="badge bg-<?php 
                                            echo ($subscription_info['nama_plan'] ?? 'Free') == 'Free' ? 'secondary' : 
                                            (($subscription_info['nama_plan'] == 'Basic') ? 'primary' : 
                                            (($subscription_info['nama_plan'] == 'Premium') ? 'warning' : 'danger'));
                                        ?> fs-6">
                                            <?php echo $subscription_info['nama_plan'] ?? 'Free'; ?> Plan
                                        </span>
                                        <?php if (isset($subscription_info['berakhir_tanggal']) && $subscription_info['berakhir_tanggal']): ?>
                                            <span class="ms-2 text-muted">
                                                <i class="fas fa-calendar me-1"></i>
                                                Berakhir: <?php echo date('d M Y', strtotime($subscription_info['berakhir_tanggal'])); ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="progress" style="height: 15px; border-radius: 8px;">
                                        <?php 
                                        $percentage = ($upload_check['current_count'] / $upload_check['max_allowed']) * 100;
                                        $progress_class = $percentage >= 90 ? 'bg-danger' : 
                                                         ($percentage >= 70 ? 'bg-warning' : 
                                                         ($percentage >= 50 ? 'bg-info' : 'bg-success'));
                                        ?>
                                        <div class="progress-bar <?php echo $progress_class; ?>" 
                                             style="width: <?php echo min($percentage, 100); ?>%">
                                            <span class="fw-bold"><?php echo number_format($percentage, 0); ?>%</span>
                                        </div>
                                    </div>
                                    
                                    <div class="mt-2">
                                        <?php if ($upload_check['remaining'] > 0): ?>
                                            <span class="text-success">
                                                <i class="fas fa-check-circle me-1"></i>
                                                Anda masih bisa upload <strong><?php echo $upload_check['remaining']; ?> mobil</strong> lagi
                                            </span>
                                        <?php else: ?>
                                            <span class="text-danger">
                                                <i class="fas fa-exclamation-circle me-1"></i>
                                                Limit upload telah tercapai!
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="col-md-3 text-end">
                                    <?php if ($upload_check['remaining'] <= 2 || ($subscription_info['nama_plan'] ?? 'Free') == 'Free'): ?>
                                        <a href="subscription.php" class="btn btn-warning">
                                            <i class="fas fa-crown me-2"></i> Upgrade Plan
                                        </a>
                                    <?php else: ?>
                                        <a href="add_car.php" class="btn btn-success">
                                            <i class="fas fa-plus me-2"></i> Tambah Mobil
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>