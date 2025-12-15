<?php
require_once '../includes/db_config.php';
require_once '../config.php'; // Menggunakan config yang sama dengan admin

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'penjual') {
    header('Location: ../login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Handle subscription purchase
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        
        // Pilih plan dan masuk ke pembayaran
        if ($_POST['action'] === 'select_plan' && isset($_POST['plan_id'])) {
            $plan_id = intval($_POST['plan_id']);
            
            // Get plan details
            $sql = "SELECT * FROM subscription_plans WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$plan_id]);
            $plan = $stmt->fetch();
            
            if ($plan) {
                // Simpan plan yang dipilih di session
                $_SESSION['selected_plan'] = $plan;
                $_SESSION['selected_plan_id'] = $plan_id;
                
                // Redirect ke halaman pembayaran
                header('Location: subscription.php?step=payment');
                exit();
            } else {
                $_SESSION['error'] = "Plan tidak ditemukan";
            }
        }
        
        // Proses pembayaran
        if ($_POST['action'] === 'process_payment' && isset($_SESSION['selected_plan'])) {
            $plan_id = $_SESSION['selected_plan_id'];
            $plan = $_SESSION['selected_plan'];
            $payment_method = sanitize($_POST['payment_method']);
            $transfer_proof = '';
            
            // Handle upload bukti transfer
            if (isset($_FILES['transfer_proof']) && $_FILES['transfer_proof']['error'] == 0) {
                $uploadDir = '../uploads/payments/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                
                $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp', 'application/pdf'];
                $fileType = $_FILES['transfer_proof']['type'];
                
                if (in_array($fileType, $allowedTypes)) {
                    $fileExtension = pathinfo($_FILES['transfer_proof']['name'], PATHINFO_EXTENSION);
                    $fileName = 'PAYMENT_' . $user_id . '_' . time() . '.' . $fileExtension;
                    
                    if (move_uploaded_file($_FILES['transfer_proof']['tmp_name'], $uploadDir . $fileName)) {
                        $transfer_proof = $fileName;
                    }
                }
            }
            
            // Insert ke tabel subscription_payments dengan kolom yang benar
            $sql_insert = "INSERT INTO subscription_payments 
                          (seller_id, plan_id, payment_method, amount, status, 
                           transfer_proof, payment_date) 
                          VALUES (?, ?, ?, ?, 'pending', ?, CURDATE())";
            $stmt_insert = $pdo->prepare($sql_insert);
            $stmt_insert->execute([
                $user_id, 
                $plan_id, 
                $payment_method, 
                $plan['harga_bulanan'],
                $transfer_proof
            ]);
            
            $payment_id = $pdo->lastInsertId();
            
            // Hapus session plan
            unset($_SESSION['selected_plan']);
            unset($_SESSION['selected_plan_id']);
            
            $_SESSION['success'] = "Pembayaran berhasil diajukan. Menunggu konfirmasi admin.";
            $_SESSION['payment_id'] = $payment_id;
            
            header('Location: subscription.php?success=true');
            exit();
        }
        
        // Handle subscription cancellation request
        if ($_POST['action'] === 'cancel_request' && isset($_POST['subscription_id'])) {
            $subscription_id = intval($_POST['subscription_id']);
            
            $sql = "UPDATE seller_subscriptions 
                    SET status = 'inactive' 
                    WHERE id = ? AND seller_id = ?";
            $stmt = $pdo->prepare($sql);
            if ($stmt->execute([$subscription_id, $user_id])) {
                $_SESSION['success'] = "Permintaan pembatalan subscription telah dikirim";
            } else {
                $_SESSION['error'] = "Gagal mengirim permintaan pembatalan";
            }
            
            header('Location: subscription.php');
            exit();
        }
    }
}

// Get all available plans
$sql = "SELECT * FROM subscription_plans ORDER BY harga_bulanan ASC";
$stmt = $pdo->query($sql);
$plans = $stmt->fetchAll();

// Get current active subscription
$sql = "SELECT ss.*, sp.nama_plan, sp.harga_bulanan, sp.max_mobil, sp.fitur
        FROM seller_subscriptions ss
        JOIN subscription_plans sp ON ss.plan_id = sp.id
        WHERE ss.seller_id = ? AND ss.status = 'active' 
        AND ss.berakhir_tanggal >= CURDATE()
        ORDER BY ss.berakhir_tanggal DESC 
        LIMIT 1";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id]);
$current_subscription = $stmt->fetch();

// Get user's current upload count
$sql = "SELECT COUNT(*) as count FROM mobil WHERE penjual_id = ? AND status != 'terjual'";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id]);
$upload_count = $stmt->fetch()['count'];

// Get effective max upload dari subscription_status user
$sql_user = "SELECT subscription_status FROM users WHERE id = ?";
$stmt_user = $pdo->prepare($sql_user);
$stmt_user->execute([$user_id]);
$user_data = $stmt_user->fetch();

$effective_max_upload = 3; // Default free plan

// Ambil max_mobil dari subscription_plans berdasarkan subscription_status user
if ($user_data['subscription_status'] !== 'free') {
    $plan_name = ucfirst($user_data['subscription_status']);
    $sql_plan = "SELECT max_mobil FROM subscription_plans WHERE nama_plan = ?";
    $stmt_plan = $pdo->prepare($sql_plan);
    $stmt_plan->execute([$plan_name]);
    $plan_data = $stmt_plan->fetch();
    
    if ($plan_data) {
        $effective_max_upload = $plan_data['max_mobil'];
    }
}

// Get subscription history
$sql = "SELECT ss.*, sp.nama_plan, sp.harga_bulanan
        FROM seller_subscriptions ss
        JOIN subscription_plans sp ON ss.plan_id = sp.id
        WHERE ss.seller_id = ?
        ORDER BY ss.mulai_tanggal DESC
        LIMIT 10";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id]);
$subscription_history = $stmt->fetchAll();

// Get pending payments
$sql = "SELECT * FROM subscription_payments 
        WHERE seller_id = ? AND status = 'pending'
        ORDER BY created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id]);
$pending_payments = $stmt->fetchAll();

// PERBAIKAN: Get total unread messages untuk badge sidebar
$unread_sql = "SELECT COUNT(*) as total_unread FROM pesan WHERE penerima_id = ? AND dibaca = 0";
$unread_stmt = $pdo->prepare($unread_sql);
$unread_stmt->execute([$user_id]);
$total_unread = $unread_stmt->fetch(PDO::FETCH_ASSOC)['total_unread'];

$step = $_GET['step'] ?? 'plans';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subscription Plans - Automarket</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary: #2c3e50;
            --secondary: #34495e;
            --success: #27ae60;
            --info: #3498db;
            --warning: #f39c12;
            --danger: #e74c3c;
        }
        
        body {
            background-color: #f8f9fa;
        }
        
        /* Sidebar Styles - Sama seperti dashboard.php */
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
        
        /* Subscription Page Specific Styles */
        .plan-card {
            border: 2px solid #e9ecef;
            border-radius: 15px;
            transition: all 0.3s ease;
            height: 100%;
            position: relative;
        }
        .plan-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        .plan-card.popular {
            border-color: var(--warning);
            position: relative;
        }
        .plan-card.popular::before {
            content: 'POPULAR';
            position: absolute;
            top: -10px;
            right: 20px;
            background: var(--warning);
            color: white;
            padding: 3px 15px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: bold;
            z-index: 1;
        }
        .price {
            font-size: 2.2rem;
            font-weight: bold;
            color: var(--primary);
        }
        .feature-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .feature-list li {
            padding: 8px 0;
            border-bottom: 1px solid #f1f1f1;
        }
        .feature-list li:last-child {
            border-bottom: none;
        }
        .feature-list li i {
            color: var(--success);
            margin-right: 10px;
            width: 20px;
        }
        .upload-progress {
            height: 12px;
            border-radius: 6px;
        }
        .current-plan-badge {
            position: absolute;
            top: 10px;
            left: 10px;
            background: var(--success);
            color: white;
            padding: 3px 10px;
            border-radius: 15px;
            font-size: 0.7rem;
            font-weight: bold;
        }
        .btn-subscribe {
            background: linear-gradient(45deg, var(--primary), var(--secondary));
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s;
        }
        .btn-subscribe:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(44, 62, 80, 0.3);
        }
        .stats-card {
            border-left: 4px solid;
            height: 100%;
        }
        .stats-card.border-primary { border-color: var(--primary); }
        .stats-card.border-success { border-color: var(--success); }
        .stats-card.border-warning { border-color: var(--warning); }
        .stats-card.border-info { border-color: var(--info); }
        
        .payment-method {
            border: 2px solid #dee2e6;
            border-radius: 10px;
            padding: 15px;
            cursor: pointer;
            transition: all 0.3s;
        }
        .payment-method:hover {
            border-color: var(--info);
            background: #f8f9fa;
        }
        .payment-method.selected {
            border-color: var(--success);
            background: #e8f4f1;
        }
        .payment-method input[type="radio"] {
            display: none;
        }
        
        /* Stepper */
        .stepper {
            display: flex;
            justify-content: center;
            margin-bottom: 40px;
        }
        .step {
            display: flex;
            align-items: center;
        }
        .step-number {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #e9ecef;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-right: 10px;
        }
        .step.active .step-number {
            background: var(--info);
            color: white;
        }
        .step.completed .step-number {
            background: var(--success);
            color: white;
        }
        .step-title {
            font-weight: 500;
        }
        .step-divider {
            width: 80px;
            height: 2px;
            background: #e9ecef;
            margin: 0 15px;
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
            .stepper {
                flex-direction: column;
                align-items: center;
                gap: 20px;
            }
            .step-divider {
                width: 2px;
                height: 40px;
                margin: 10px 0;
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
            <a class="nav-link" href="bookings.php">
                <i class="fas fa-calendar-check"></i> Booking Saya
            </a>
            <a class="nav-link active" href="subscription.php">
                <i class="fas fa-crown"></i> Subscription
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
        <div class="container py-4">
            <!-- Notifications -->
            <?php if(isset($_SESSION['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show py-2">
                    <i class="fas fa-check-circle me-2"></i>
                    <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" style="padding: 0.75rem;"></button>
                </div>
            <?php endif; ?>
            
            <?php if(isset($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show py-2">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" style="padding: 0.75rem;"></button>
                </div>
            <?php endif; ?>

            <!-- Stepper -->
            <div class="stepper mb-5">
                <div class="step <?php echo $step == 'plans' ? 'active' : ($step == 'payment' ? 'completed' : 'completed'); ?>">
                    <div class="step-number">1</div>
                    <div class="step-title">Pilih Plan</div>
                </div>
                <div class="step-divider"></div>
                <div class="step <?php echo $step == 'payment' ? 'active' : ''; ?>">
                    <div class="step-number">2</div>
                    <div class="step-title">Pembayaran</div>
                </div>
                <div class="step-divider"></div>
                <div class="step <?php echo isset($_GET['success']) ? 'active' : ''; ?>">
                    <div class="step-number">3</div>
                    <div class="step-title">Selesai</div>
                </div>
            </div>

            <!-- Step 1: Pilih Plan -->
            <?php if($step == 'plans'): ?>
            <div class="row mb-4">
                <div class="col-12">
                    <h1 class="text-center mb-1">
                        <i class="fas fa-crown text-warning me-2"></i>
                        Pilih Plan Terbaik untuk Bisnis Anda
                    </h1>
                    <p class="text-center text-muted mb-4">Upgrade untuk lebih banyak fitur dan meningkatkan penjualan mobil</p>
                </div>
            </div>

            <!-- Current Subscription Status -->
            <div class="row mb-4">
                <div class="col-md-6 mb-3">
                    <div class="card stats-card border-primary">
                        <div class="card-body p-3">
                            <h5 class="card-title mb-2">
                                <i class="fas fa-chart-line me-2"></i>Status Upload
                            </h5>
                            <div class="progress upload-progress mb-2">
                                <?php 
                                $percentage = ($upload_count / $effective_max_upload) * 100;
                                $progress_class = $percentage >= 90 ? 'bg-danger' : 
                                                ($percentage >= 70 ? 'bg-warning' : 'bg-success');
                                ?>
                                <div class="progress-bar <?php echo $progress_class; ?>" 
                                     style="width: <?php echo min($percentage, 100); ?>%">
                                </div>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span class="text-muted">
                                    <?php echo $upload_count; ?>/<?php echo $effective_max_upload; ?> mobil
                                </span>
                                <span class="fw-bold"><?php echo round($percentage, 1); ?>%</span>
                            </div>
                            <?php if ($upload_count >= $effective_max_upload): ?>
                                <div class="alert alert-danger mt-2 py-1 mb-0">
                                    <i class="fas fa-exclamation-triangle me-1"></i>
                                    Limit upload penuh! Upgrade untuk upload lebih banyak mobil.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6 mb-3">
                    <div class="card stats-card border-success">
                        <div class="card-body p-3">
                            <h5 class="card-title mb-2">
                                <i class="fas fa-crown me-2"></i>Plan Saat Ini
                            </h5>
                            <?php if ($current_subscription): ?>
                                <h4 class="text-success mb-1"><?php echo $current_subscription['nama_plan']; ?></h4>
                                <p class="mb-1">
                                    <i class="fas fa-calendar-check me-2"></i>
                                    Berlaku hingga: <?php echo date('d M Y', strtotime($current_subscription['berakhir_tanggal'])); ?>
                                </p>
                                <p class="mb-0">
                                    <i class="fas fa-car me-2"></i>
                                    Limit: <?php echo $current_subscription['max_mobil']; ?> mobil
                                </p>
                            <?php else: ?>
                                <h4 class="text-secondary mb-1">Free Plan</h4>
                                <p class="mb-1">
                                    <i class="fas fa-info-circle me-2"></i>
                                    Default plan untuk semua penjual baru
                                </p>
                                <p class="mb-0">
                                    <i class="fas fa-car me-2"></i>
                                    Limit: 3 mobil
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Subscription Plans -->
            <div class="row mb-5">
                <?php foreach ($plans as $plan): 
                    $is_current = $current_subscription && $current_subscription['plan_id'] == $plan['id'];
                ?>
                <div class="col-md-3 mb-4">
                    <div class="card plan-card h-100 <?php echo $plan['is_popular'] ? 'popular' : ''; ?>">
                        <?php if ($is_current): ?>
                            <div class="current-plan-badge">
                                <i class="fas fa-check me-1"></i>AKTIF
                            </div>
                        <?php endif; ?>
                        
                        <div class="card-body p-3">
                            <h4 class="card-title text-center mb-3"><?php echo $plan['nama_plan']; ?></h4>
                            
                            <div class="price text-center mb-3">
                                <?php if($plan['harga_bulanan'] > 0): ?>
                                    Rp <?php echo number_format($plan['harga_bulanan'], 0, ',', '.'); ?>
                                    <small class="text-muted d-block">/bulan</small>
                                <?php else: ?>
                                    <span class="text-success">GRATIS</span>
                                <?php endif; ?>
                            </div>
                            
                            <ul class="feature-list mb-4">
                                <li>
                                    <i class="fas fa-car"></i>
                                    <strong><?php echo $plan['max_mobil']; ?></strong> Mobil Aktif
                                </li>
                                <?php 
                                $features = explode(',', $plan['fitur']);
                                foreach ($features as $feature): 
                                    $feature = trim($feature);
                                    if (!empty($feature)):
                                ?>
                                    <li>
                                        <i class="fas fa-check"></i>
                                        <?php echo $feature; ?>
                                    </li>
                                <?php 
                                    endif;
                                endforeach; 
                                ?>
                            </ul>

                            <form method="POST" class="mt-auto">
                                <input type="hidden" name="action" value="select_plan">
                                <input type="hidden" name="plan_id" value="<?php echo $plan['id']; ?>">
                                <button type="submit" 
                                        class="btn w-100 <?php echo $is_current ? 'btn-success' : 'btn-subscribe'; ?>"
                                        <?php echo $is_current ? 'disabled' : ''; ?>>
                                    <?php if ($is_current): ?>
                                        <i class="fas fa-check-circle me-2"></i>Plan Aktif
                                    <?php elseif($plan['harga_bulanan'] == 0): ?>
                                        <i class="fas fa-rocket me-2"></i>Pilih Gratis
                                    <?php else: ?>
                                        <i class="fas fa-shopping-cart me-2"></i>Berlangganan
                                    <?php endif; ?>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Step 2: Pembayaran -->
            <?php if($step == 'payment' && isset($_SESSION['selected_plan'])): 
                $plan = $_SESSION['selected_plan'];
            ?>
            <div class="row justify-content-center">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header bg-white">
                            <h4 class="mb-0">
                                <i class="fas fa-credit-card me-2 text-primary"></i>
                                Pembayaran Subscription
                            </h4>
                        </div>
                        <div class="card-body">
                            <!-- Order Summary -->
                            <div class="card mb-4 border-primary">
                                <div class="card-body">
                                    <h5 class="card-title text-primary">
                                        <i class="fas fa-receipt me-2"></i>
                                        Ringkasan Pesanan
                                    </h5>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <p class="mb-1"><strong>Plan:</strong> <?php echo $plan['nama_plan']; ?></p>
                                            <p class="mb-1"><strong>Durasi:</strong> 1 Bulan</p>
                                            <p class="mb-0"><strong>Limit Mobil:</strong> <?php echo $plan['max_mobil']; ?> mobil</p>
                                        </div>
                                        <div class="col-md-6 text-end">
                                            <h3 class="text-success mb-0">
                                                Rp <?php echo number_format($plan['harga_bulanan'], 0, ',', '.'); ?>
                                            </h3>
                                            <small class="text-muted">Total Pembayaran</small>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Payment Form -->
                            <form method="POST" action="" enctype="multipart/form-data" id="paymentForm">
                                <input type="hidden" name="action" value="process_payment">
                                
                                <!-- Payment Method -->
                                <h5 class="mb-3">Pilih Metode Pembayaran</h5>
                                <div class="row mb-4">
                                    <div class="col-md-6 mb-3">
                                        <label class="payment-method">
                                            <input type="radio" name="payment_method" value="bca" required>
                                            <div class="d-flex align-items-center">
                                                <div class="me-3">
                                                    <i class="fas fa-university fa-2x text-primary"></i>
                                                </div>
                                                <div>
                                                    <h6 class="mb-1">Transfer Bank BCA</h6>
                                                    <p class="mb-0 text-muted small">No. Rek: 1234567890</p>
                                                    <p class="mb-0 text-muted small">a.n. Automarket Indonesia</p>
                                                </div>
                                            </div>
                                        </label>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="payment-method">
                                            <input type="radio" name="payment_method" value="mandiri" required>
                                            <div class="d-flex align-items-center">
                                                <div class="me-3">
                                                    <i class="fas fa-university fa-2x text-danger"></i>
                                                </div>
                                                <div>
                                                    <h6 class="mb-1">Transfer Bank Mandiri</h6>
                                                    <p class="mb-0 text-muted small">No. Rek: 0987654321</p>
                                                    <p class="mb-0 text-muted small">a.n. Automarket Indonesia</p>
                                                </div>
                                            </div>
                                        </label>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="payment-method">
                                            <input type="radio" name="payment_method" value="bni" required>
                                            <div class="d-flex align-items-center">
                                                <div class="me-3">
                                                    <i class="fas fa-university fa-2x text-success"></i>
                                                </div>
                                                <div>
                                                    <h6 class="mb-1">Transfer Bank BNI</h6>
                                                    <p class="mb-0 text-muted small">No. Rek: 1122334455</p>
                                                    <p class="mb-0 text-muted small">a.n. Automarket Indonesia</p>
                                                </div>
                                            </div>
                                        </label>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="payment-method">
                                            <input type="radio" name="payment_method" value="bri" required>
                                            <div class="d-flex align-items-center">
                                                <div class="me-3">
                                                    <i class="fas fa-university fa-2x text-warning"></i>
                                                </div>
                                                <div>
                                                    <h6 class="mb-1">Transfer Bank BRI</h6>
                                                    <p class="mb-0 text-muted small">No. Rek: 5566778899</p>
                                                    <p class="mb-0 text-muted small">a.n. Automarket Indonesia</p>
                                                </div>
                                            </div>
                                        </label>
                                    </div>
                                </div>

                                <!-- Upload Bukti Transfer -->
                                <div class="mb-4">
                                    <h5 class="mb-3">Upload Bukti Transfer</h5>
                                    <div class="mb-3">
                                        <label class="form-label">File Bukti Transfer (JPG, PNG, PDF)</label>
                                        <input type="file" name="transfer_proof" class="form-control" accept="image/*,.pdf" required>
                                        <small class="text-muted">Upload screenshot atau foto bukti transfer</small>
                                    </div>
                                </div>

                                <!-- Instructions -->
                                <div class="alert alert-info mb-4">
                                    <h6><i class="fas fa-info-circle me-2"></i>Instruksi Pembayaran:</h6>
                                    <ol class="mb-0">
                                        <li>Transfer ke rekening yang dipilih</li>
                                        <li>Jumlah transfer harus sesuai dengan total</li>
                                        <li>Upload bukti transfer</li>
                                        <li>Admin akan memverifikasi dalam 1x24 jam</li>
                                        <li>Subscription akan aktif setelah pembayaran dikonfirmasi</li>
                                    </ol>
                                </div>

                                <div class="d-flex justify-content-between">
                                    <a href="subscription.php" class="btn btn-secondary">
                                        <i class="fas fa-arrow-left me-2"></i>Kembali
                                    </a>
                                    <button type="submit" class="btn btn-success px-4">
                                        <i class="fas fa-paper-plane me-2"></i>Kirim Pembayaran
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Step 3: Konfirmasi -->
            <?php if(isset($_GET['success'])): ?>
            <div class="row justify-content-center">
                <div class="col-md-6">
                    <div class="card text-center border-success">
                        <div class="card-body py-5">
                            <div class="mb-4">
                                <i class="fas fa-check-circle fa-4x text-success"></i>
                            </div>
                            <h3 class="card-title mb-3">Pembayaran Berhasil Diajukan!</h3>
                            <p class="card-text text-muted mb-4">
                                Pembayaran Anda sedang menunggu verifikasi admin. 
                                Kami akan mengirimkan notifikasi melalui email setelah pembayaran dikonfirmasi.
                            </p>
                            
                            <?php if(isset($_SESSION['payment_id'])): ?>
                            <div class="alert alert-info text-start mb-4">
                                <h6><i class="fas fa-receipt me-2"></i>Detail Transaksi:</h6>
                                <p class="mb-1"><strong>ID Transaksi:</strong> PAY<?php echo str_pad($_SESSION['payment_id'], 6, '0', STR_PAD_LEFT); ?></p>
                                <p class="mb-1"><strong>Status:</strong> <span class="badge bg-warning">Menunggu Konfirmasi</span></p>
                                <p class="mb-0"><strong>Estimasi Konfirmasi:</strong> 1x24 jam</p>
                            </div>
                            <?php unset($_SESSION['payment_id']); ?>
                            <?php endif; ?>
                            
                            <div class="d-flex justify-content-center gap-3">
                                <a href="subscription.php" class="btn btn-primary">
                                    <i class="fas fa-history me-2"></i>Cek Status
                                </a>
                                <a href="../index.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-home me-2"></i>Ke Beranda
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Pending Payments -->
            <?php if(!empty($pending_payments) && $step == 'plans'): ?>
            <div class="card mb-4">
                <div class="card-header bg-white">
                    <h5 class="mb-0">
                        <i class="fas fa-clock me-2 text-warning"></i>
                        Pembayaran Menunggu Konfirmasi
                    </h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>ID Transaksi</th>
                                    <th>Plan</th>
                                    <th>Metode</th>
                                    <th>Jumlah</th>
                                    <th>Tanggal</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pending_payments as $payment): 
                                    $plan_sql = "SELECT nama_plan FROM subscription_plans WHERE id = ?";
                                    $plan_stmt = $pdo->prepare($plan_sql);
                                    $plan_stmt->execute([$payment['plan_id']]);
                                    $plan_name = $plan_stmt->fetch()['nama_plan'];
                                ?>
                                <tr>
                                    <td>PAY<?php echo str_pad($payment['id'], 6, '0', STR_PAD_LEFT); ?></td>
                                    <td><?php echo $plan_name; ?></td>
                                    <td><?php echo strtoupper($payment['payment_method']); ?></td>
                                    <td class="text-success fw-bold">Rp <?php echo number_format($payment['amount'], 0, ',', '.'); ?></td>
                                    <td><?php echo date('d M Y', strtotime($payment['created_at'])); ?></td>
                                    <td>
                                        <span class="badge bg-warning">
                                            <i class="fas fa-clock me-1"></i>Menunggu
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Subscription History -->
            <div class="card mb-4">
                <div class="card-header bg-white">
                    <h5 class="mb-0">
                        <i class="fas fa-history me-2"></i>
                        Riwayat Subscription
                    </h5>
                </div>
                <div class="card-body p-0">
                    <?php if (!empty($subscription_history)): ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Plan</th>
                                        <th>Mulai</th>
                                        <th>Berakhir</th>
                                        <th>Status</th>
                                        <th>Harga/Bulan</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($subscription_history as $history): 
                                        $is_active = ($history['status'] == 'active' && strtotime($history['berakhir_tanggal']) >= time());
                                    ?>
                                    <tr>
                                        <td>
                                            <span class="fw-semibold"><?php echo $history['nama_plan']; ?></span>
                                        </td>
                                        <td><?php echo date('d M Y', strtotime($history['mulai_tanggal'])); ?></td>
                                        <td><?php echo date('d M Y', strtotime($history['berakhir_tanggal'])); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $is_active ? 'success' : 'secondary'; ?>">
                                                <?php echo $is_active ? 'AKTIF' : strtoupper($history['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if($history['harga_bulanan'] > 0): ?>
                                                Rp <?php echo number_format($history['harga_bulanan'], 0, ',', '.'); ?>
                                            <?php else: ?>
                                                <span class="text-success">Gratis</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($is_active): ?>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="cancel_request">
                                                <input type="hidden" name="subscription_id" value="<?php echo $history['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger"
                                                        onclick="return confirm('Batalkan subscription ini?')">
                                                    <i class="fas fa-ban me-1"></i>Batalkan
                                                </button>
                                            </form>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-history fa-3x text-muted mb-3"></i>
                            <p class="text-muted">Belum ada riwayat subscription.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            var alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                var bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
        
        // Payment method selection
        document.addEventListener('DOMContentLoaded', function() {
            const paymentMethods = document.querySelectorAll('.payment-method');
            paymentMethods.forEach(method => {
                method.addEventListener('click', function() {
                    // Remove selected class from all
                    paymentMethods.forEach(m => m.classList.remove('selected'));
                    // Add selected class to clicked
                    this.classList.add('selected');
                    // Check the radio button
                    const radio = this.querySelector('input[type="radio"]');
                    radio.checked = true;
                });
            });
            
            // Validate payment form
            const paymentForm = document.getElementById('paymentForm');
            if (paymentForm) {
                paymentForm.addEventListener('submit', function(e) {
                    const paymentMethod = document.querySelector('input[name="payment_method"]:checked');
                    const transferProof = document.querySelector('input[name="transfer_proof"]');
                    
                    if (!paymentMethod) {
                        e.preventDefault();
                        alert('Pilih metode pembayaran terlebih dahulu');
                        return false;
                    }
                    
                    if (!transferProof.value) {
                        e.preventDefault();
                        alert('Upload bukti transfer terlebih dahulu');
                        return false;
                    }
                    
                    return true;
                });
            }
        });
    </script>
</body>
</html>