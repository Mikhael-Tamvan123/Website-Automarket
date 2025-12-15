<?php
require_once __DIR__ . '/../config.php';

// Cek login dan role admin
check_login();
check_role(['admin']);

//security check
$referer = $_SERVER['HTTP_REFERER'] ?? '';
if (!strpos($referer, '/admin/') && basename($_SERVER['PHP_SELF']) != 'index.php') {
    // Redirect ke login admin jika bukan dari admin area
    redirect('admin/index.php');
}

// Set default date range (last 30 days)
$start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
$end_date = $_GET['end_date'] ?? date('Y-m-d');

// PERBAIKAN 1: Tambah CREATE TABLE untuk subscription_payments jika belum ada
$sql_check_table = "SHOW TABLES LIKE 'subscription_payments'";
$table_exists = $pdo->query($sql_check_table)->fetch();

if (!$table_exists) {
    // Buat tabel subscription_payments jika belum ada
    $sql_create_table = "CREATE TABLE subscription_payments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        seller_id INT NOT NULL,
        plan_id INT NOT NULL,
        payment_method VARCHAR(50) NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        status ENUM('pending', 'completed', 'rejected') DEFAULT 'pending',
        transfer_proof VARCHAR(255),
        rejection_reason TEXT,
        confirmed_by INT,
        payment_date DATE,
        confirmed_at DATETIME,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (seller_id) REFERENCES users(id),
        FOREIGN KEY (plan_id) REFERENCES subscription_plans(id)
    )";
    $pdo->exec($sql_create_table);
}

// PERBAIKAN 2: Handle payment confirmation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'confirm_payment' && isset($_POST['payment_id'])) {
        $payment_id = intval($_POST['payment_id']);
        
        // Get payment details
        $sql = "SELECT * FROM subscription_payments WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$payment_id]);
        $payment = $stmt->fetch();
        
        if ($payment) {
            // Update payment status
            $sql_update = "UPDATE subscription_payments SET 
                          status = 'completed', 
                          confirmed_by = ?,
                          confirmed_at = NOW()
                          WHERE id = ?";
            $stmt_update = $pdo->prepare($sql_update);
            $stmt_update->execute([$_SESSION['user_id'], $payment_id]);
            
            // Check if user already has an active subscription
            $sql_check = "SELECT * FROM seller_subscriptions 
                         WHERE seller_id = ? AND status = 'active' 
                         AND berakhir_tanggal >= CURDATE()";
            $stmt_check = $pdo->prepare($sql_check);
            $stmt_check->execute([$payment['seller_id']]);
            $active_sub = $stmt_check->fetch();
            
            if ($active_sub) {
                // Extend existing subscription
                $start_date_sub = new DateTime($active_sub['berakhir_tanggal']);
                $end_date_sub = clone $start_date_sub;
                $end_date_sub->modify('+1 month');
                
                $sql_update_sub = "UPDATE seller_subscriptions 
                                  SET plan_id = ?, berakhir_tanggal = ?, updated_at = NOW()
                                  WHERE id = ?";
                $stmt_update_sub = $pdo->prepare($sql_update_sub);
                $stmt_update_sub->execute([
                    $payment['plan_id'], 
                    $end_date_sub->format('Y-m-d'), 
                    $active_sub['id']
                ]);
            } else {
                // Create new subscription
                $start_date_sub = new DateTime();
                $end_date_sub = clone $start_date_sub;
                $end_date_sub->modify('+1 month');
                
                $sql_insert = "INSERT INTO seller_subscriptions 
                              (seller_id, plan_id, status, mulai_tanggal, berakhir_tanggal, created_at) 
                              VALUES (?, ?, 'active', ?, ?, NOW())";
                $stmt_insert = $pdo->prepare($sql_insert);
                $stmt_insert->execute([
                    $payment['seller_id'], 
                    $payment['plan_id'], 
                    $start_date_sub->format('Y-m-d'), 
                    $end_date_sub->format('Y-m-d')
                ]);
            }
            
            // PERBAIKAN 3: Update user's subscription_status (bukan max_mobil)
            $plan_sql = "SELECT nama_plan FROM subscription_plans WHERE id = ?";
            $plan_stmt = $pdo->prepare($plan_sql);
            $plan_stmt->execute([$payment['plan_id']]);
            $plan = $plan_stmt->fetch();
            
            if ($plan) {
                // Map plan names to subscription_status enum
                $plan_name = strtolower($plan['nama_plan']);
                $subscription_status = 'free'; // default
                
                if ($plan_name === 'basic') $subscription_status = 'basic';
                elseif ($plan_name === 'premium') $subscription_status = 'premium';
                elseif ($plan_name === 'business') $subscription_status = 'business';
                
                $sql_update_user = "UPDATE users SET subscription_status = ? WHERE id = ?";
                $stmt_update_user = $pdo->prepare($sql_update_user);
                $stmt_update_user->execute([$subscription_status, $payment['seller_id']]);
            }
            
            $_SESSION['success'] = "Pembayaran berhasil dikonfirmasi dan subscription diaktifkan";
        } else {
            $_SESSION['error'] = "Pembayaran tidak ditemukan";
        }
        
        header('Location: reports.php');
        exit();
    }
    
    if ($_POST['action'] === 'reject_payment' && isset($_POST['payment_id'])) {
        $payment_id = intval($_POST['payment_id']);
        $reason = sanitize($_POST['rejection_reason'] ?? '');
        
        $sql = "UPDATE subscription_payments SET 
                status = 'rejected', 
                rejection_reason = ?,
                confirmed_by = ?,
                confirmed_at = NOW()
                WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        if ($stmt->execute([$reason, $_SESSION['user_id'], $payment_id])) {
            $_SESSION['success'] = "Pembayaran berhasil ditolak";
        } else {
            $_SESSION['error'] = "Gagal menolak pembayaran";
        }
        
        header('Location: reports.php');
        exit();
    }
}

// PERBAIKAN 4: Update query untuk pending payments dengan tabel yang benar
$sql_pending = "SELECT sp.*, u.nama_lengkap, u.email, pl.nama_plan
                FROM subscription_payments sp
                JOIN users u ON sp.seller_id = u.id
                JOIN subscription_plans pl ON sp.plan_id = pl.id
                WHERE sp.status = 'pending'
                ORDER BY sp.created_at DESC";
$pending_payments = $pdo->query($sql_pending)->fetchAll();

// Subscription Revenue Report
$sql = "SELECT 
            DATE(ss.mulai_tanggal) as tanggal,
            sp.nama_plan,
            sp.harga_bulanan,
            COUNT(ss.id) as jumlah,
            SUM(sp.harga_bulanan) as total
        FROM seller_subscriptions ss
        JOIN subscription_plans sp ON ss.plan_id = sp.id
        WHERE DATE(ss.mulai_tanggal) BETWEEN ? AND ?
        GROUP BY DATE(ss.mulai_tanggal), sp.id
        ORDER BY tanggal DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute([$start_date, $end_date]);
$subscription_report = $stmt->fetchAll();

// PERBAIKAN 5: Update Payment Report query
$sql_payments = "SELECT 
                    DATE(sp.created_at) as tanggal,
                    sp.payment_method,
                    sp.amount,
                    sp.status,
                    u.nama_lengkap,
                    pl.nama_plan,
                    COUNT(sp.id) as jumlah,
                    SUM(sp.amount) as total
                FROM subscription_payments sp
                JOIN users u ON sp.seller_id = u.id
                JOIN subscription_plans pl ON sp.plan_id = pl.id
                WHERE DATE(sp.created_at) BETWEEN ? AND ?
                GROUP BY DATE(sp.created_at), sp.payment_method, sp.status
                ORDER BY tanggal DESC";
$stmt_payments = $pdo->prepare($sql_payments);
$stmt_payments->execute([$start_date, $end_date]);
$payment_report = $stmt_payments->fetchAll();

// Total subscription revenue for period
$sql = "SELECT COALESCE(SUM(sp.harga_bulanan), 0) as total_revenue
        FROM seller_subscriptions ss
        JOIN subscription_plans sp ON ss.plan_id = sp.id
        WHERE DATE(ss.mulai_tanggal) BETWEEN ? AND ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$start_date, $end_date]);
$total_period_revenue = $stmt->fetch()['total_revenue'];

// Top selling plans
$sql = "SELECT 
            sp.nama_plan,
            COUNT(ss.id) as total_sales,
            SUM(sp.harga_bulanan) as total_revenue
        FROM seller_subscriptions ss
        JOIN subscription_plans sp ON ss.plan_id = sp.id
        WHERE DATE(ss.mulai_tanggal) BETWEEN ? AND ?
        GROUP BY sp.id
        ORDER BY total_revenue DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute([$start_date, $end_date]);
$top_plans = $stmt->fetchAll();

// Monthly revenue summary
$sql = "SELECT 
            DATE_FORMAT(ss.mulai_tanggal, '%Y-%m') as bulan,
            COUNT(ss.id) as total_subscriptions,
            SUM(sp.harga_bulanan) as monthly_revenue
        FROM seller_subscriptions ss
        JOIN subscription_plans sp ON ss.plan_id = sp.id
        WHERE DATE(ss.mulai_tanggal) BETWEEN ? AND ?
        GROUP BY DATE_FORMAT(ss.mulai_tanggal, '%Y-%m')
        ORDER BY bulan DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute([$start_date, $end_date]);
$monthly_summary = $stmt->fetchAll();

// Active subscriptions count by plan
$sql = "SELECT 
            sp.nama_plan,
            COUNT(ss.id) as active_count
        FROM seller_subscriptions ss
        JOIN subscription_plans sp ON ss.plan_id = sp.id
        WHERE ss.status = 'active' AND ss.berakhir_tanggal >= CURDATE()
        GROUP BY sp.id
        ORDER BY sp.harga_bulanan ASC";
$stmt = $pdo->query($sql);
$active_by_plan = $stmt->fetchAll();

// Export functionality
if (isset($_GET['export']) && $_GET['export'] == 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="subscription_report_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Date', 'Plan', 'Price', 'Quantity', 'Total']);
    
    foreach($subscription_report as $row) {
        fputcsv($output, [
            $row['tanggal'],
            $row['nama_plan'],
            $row['harga_bulanan'],
            $row['jumlah'],
            $row['total']
        ]);
    }
    
    fclose($output);
    exit;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subscription Reports - Admin Automarket</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
            font-size: 0.9rem;
        }
        
        .admin-sidebar {
            background: var(--primary);
            color: white;
            min-height: 100vh;
            padding: 0;
            box-shadow: 3px 0 15px rgba(0,0,0,0.1);
        }
        
        .admin-sidebar .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 12px 20px;
            border-bottom: 1px solid rgba(255,255,255,0.05);
            transition: all 0.3s;
        }
        
        .admin-sidebar .nav-link:hover, .admin-sidebar .nav-link.active {
            color: white;
            background: rgba(255,255,255,0.1);
            border-left: 4px solid var(--info);
        }
        
        .report-card {
            border-radius: 10px;
            border: none;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .stat-badge {
            font-size: 0.9rem;
            padding: 8px 15px;
            border-radius: 20px;
        }
        
        .date-filter {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
        }
        
        .export-btn {
            border-radius: 20px;
            padding: 8px 20px;
        }
        
        .admin-header {
            background: white;
            border-bottom: 1px solid #e0e0e0;
            padding: 15px 0;
            margin-bottom: 30px;
        }
        
        .chart-container {
            height: 250px;
            position: relative;
        }
        
        .payment-proof-img {
            max-width: 200px;
            max-height: 200px;
            cursor: pointer;
            border: 1px solid #dee2e6;
            border-radius: 5px;
        }
        
        .modal-preview {
            max-width: 80%;
            max-height: 80vh;
        }
        
        @media (max-width: 768px) {
            .chart-container {
                height: 200px;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 admin-sidebar p-0">
                <div class="p-3 text-center" style="background: rgba(0,0,0,0.2);">
                    <h4><i class="fas fa-crown me-2"></i>Automarket</h4>
                    <p class="small mb-0">Admin Panel</p>
                    <small class="text-light opacity-75">
                        <i class="fas fa-user-shield me-1"></i>
                        <?php echo $_SESSION['nama_lengkap'] ?? $_SESSION['username']; ?>
                    </small>
                </div>
                
                <nav class="nav flex-column mt-3">
                    <a class="nav-link" href="dashboard.php">
                        <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                    </a>
                    <a class="nav-link" href="users.php">
                        <i class="fas fa-users me-2"></i> Users
                    </a>
                    <a class="nav-link" href="cars.php">
                        <i class="fas fa-car me-2"></i> Cars
                    </a>
                    <a class="nav-link" href="bookings.php">
                        <i class="fas fa-calendar-check me-2"></i> Bookings
                    </a>
                    <a class="nav-link" href="subscriptions.php">
                        <i class="fas fa-crown me-2"></i> Subscriptions
                    </a>
                    <a class="nav-link active" href="reports.php">
                        <i class="fas fa-chart-bar me-2"></i> Reports
                    </a>
                    <div class="mt-4"></div>
                    <a class="nav-link" href="<?php echo BASE_URL; ?>">
                        <i class="fas fa-home me-2"></i> Main Site
                    </a>
                    <a class="nav-link" href="logout.php">
                        <i class="fas fa-sign-out-alt me-2"></i> Logout
                    </a>
                </nav>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10">
                <!-- Header -->
                <div class="admin-header">
                    <div class="container-fluid">
                        <div class="d-flex justify-content-between align-items-center">
                            <h2 class="mb-0">
                                <i class="fas fa-chart-bar text-info me-2"></i>
                                Subscription Reports
                            </h2>
                            <div class="text-end">
                                <!-- Notification Badge for Pending Payments -->
                                <?php if(count($pending_payments) > 0): ?>
                                <span class="badge bg-danger me-2">
                                    <i class="fas fa-bell me-1"></i>
                                    <?php echo count($pending_payments); ?> pembayaran menunggu
                                </span>
                                <?php endif; ?>
                                <small class="text-muted me-3">
                                    <i class="fas fa-clock me-1"></i>
                                    <?php echo date('l, d F Y'); ?>
                                </small>
                                <span class="badge bg-success">Online</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="container-fluid">
                    <div class="p-4">
                        <!-- Notifications -->
                        <?php if(isset($_SESSION['success'])): ?>
                            <div class="alert alert-success alert-dismissible fade show mb-4">
                                <i class="fas fa-check-circle me-2"></i>
                                <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <?php if(isset($_SESSION['error'])): ?>
                            <div class="alert alert-danger alert-dismissible fade show mb-4">
                                <i class="fas fa-exclamation-circle me-2"></i>
                                <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <!-- Pending Payments Section -->
                        <?php if(count($pending_payments) > 0): ?>
                        <div class="card report-card mb-4 border-warning">
                            <div class="card-header bg-warning text-white">
                                <h5 class="mb-0">
                                    <i class="fas fa-clock me-2"></i>
                                    Pembayaran Menunggu Konfirmasi
                                    <span class="badge bg-danger ms-2"><?php echo count($pending_payments); ?> baru</span>
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Penjual</th>
                                                <th>Plan</th>
                                                <th>Metode</th>
                                                <th>Jumlah</th>
                                                <th>Bukti Transfer</th>
                                                <th>Tanggal</th>
                                                <th>Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach($pending_payments as $payment): ?>
                                            <tr>
                                                <td>
                                                    <strong>PAY<?php echo str_pad($payment['id'], 6, '0', STR_PAD_LEFT); ?></strong>
                                                </td>
                                                <td>
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($payment['nama_lengkap']); ?></strong><br>
                                                        <small class="text-muted"><?php echo htmlspecialchars($payment['email']); ?></small>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="badge bg-primary"><?php echo $payment['nama_plan']; ?></span>
                                                </td>
                                                <td><?php echo strtoupper($payment['payment_method']); ?></td>
                                                <td class="fw-bold text-success">Rp <?php echo number_format($payment['amount'], 0, ',', '.'); ?></td>
                                                <td>
                                                    <?php if(!empty($payment['transfer_proof'])): ?>
                                                    <img src="../uploads/payments/<?php echo htmlspecialchars($payment['transfer_proof']); ?>" 
                                                         class="payment-proof-img" 
                                                         data-bs-toggle="modal" 
                                                         data-bs-target="#proofModal"
                                                         data-proof-src="../uploads/payments/<?php echo htmlspecialchars($payment['transfer_proof']); ?>"
                                                         alt="Bukti Transfer">
                                                    <?php else: ?>
                                                        <span class="text-muted">Tidak ada bukti</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo date('d M Y H:i', strtotime($payment['created_at'])); ?></td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <form method="POST" style="display: inline;">
                                                            <input type="hidden" name="action" value="confirm_payment">
                                                            <input type="hidden" name="payment_id" value="<?php echo $payment['id']; ?>">
                                                            <button type="submit" class="btn btn-success btn-sm" 
                                                                    onclick="return confirm('Konfirmasi pembayaran ini?')">
                                                                <i class="fas fa-check me-1"></i>Konfirmasi
                                                            </button>
                                                        </form>
                                                        <button type="button" class="btn btn-danger btn-sm" 
                                                                data-bs-toggle="modal" 
                                                                data-bs-target="#rejectModal"
                                                                data-payment-id="<?php echo $payment['id']; ?>">
                                                            <i class="fas fa-times me-1"></i>Tolak
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Date Filter -->
                        <div class="card date-filter mb-4">
                            <form method="GET" class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label">Start Date</label>
                                    <input type="date" name="start_date" class="form-control" value="<?php echo $start_date; ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">End Date</label>
                                    <input type="date" name="end_date" class="form-control" value="<?php echo $end_date; ?>">
                                </div>
                                <div class="col-md-4 d-flex align-items-end">
                                    <button type="submit" class="btn btn-primary w-100 me-2">
                                        <i class="fas fa-filter me-2"></i>Filter
                                    </button>
                                    <a href="reports.php?export=csv&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" 
                                       class="btn btn-success w-100">
                                        <i class="fas fa-file-export me-2"></i>Export CSV
                                    </a>
                                </div>
                            </form>
                        </div>

                        <!-- Summary Stats -->
                        <div class="row mb-4">
                            <div class="col-md-4 mb-3">
                                <div class="card report-card bg-primary text-white">
                                    <div class="card-body">
                                        <h5><i class="fas fa-money-bill-wave me-2"></i>Period Revenue</h5>
                                        <h2><?php echo format_rupiah($total_period_revenue); ?></h2>
                                        <small><?php echo date('d M Y', strtotime($start_date)); ?> - <?php echo date('d M Y', strtotime($end_date)); ?></small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <div class="card report-card bg-success text-white">
                                    <div class="card-body">
                                        <h5><i class="fas fa-shopping-cart me-2"></i>Total Sales</h5>
                                        <h2><?php echo count($subscription_report); ?></h2>
                                        <small>Subscription Transactions</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <div class="card report-card bg-info text-white">
                                    <div class="card-body">
                                        <h5><i class="fas fa-crown me-2"></i>Active Subscribers</h5>
                                        <h2>
                                            <?php 
                                                $total_active = 0;
                                                foreach($active_by_plan as $plan) {
                                                    $total_active += $plan['active_count'];
                                                }
                                                echo $total_active;
                                            ?>
                                        </h2>
                                        <small>Currently Active</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Charts -->
                        <div class="row mb-4">
                            <div class="col-lg-6 mb-4">
                                <div class="card report-card">
                                    <div class="card-header">
                                        <h5 class="mb-0"><i class="fas fa-chart-pie me-2"></i>Active Subscriptions by Plan</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="chart-container">
                                            <canvas id="planChart"></canvas>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-6 mb-4">
                                <div class="card report-card">
                                    <div class="card-header">
                                        <h5 class="mb-0"><i class="fas fa-chart-line me-2"></i>Monthly Revenue</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="chart-container">
                                            <canvas id="revenueChart"></canvas>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Top Plans -->
                        <div class="card report-card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-trophy me-2 text-warning"></i>Top Selling Plans</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Plan</th>
                                                <th>Sales Count</th>
                                                <th>Total Revenue</th>
                                                <th>Average Monthly</th>
                                                <th>Market Share</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            $total_sales_all = 0;
                                            $total_revenue_all = 0;
                                            foreach($top_plans as $plan) {
                                                $total_sales_all += $plan['total_sales'];
                                                $total_revenue_all += $plan['total_revenue'];
                                            }
                                            
                                            foreach($top_plans as $plan): 
                                                $market_share = $total_revenue_all > 0 ? ($plan['total_revenue'] / $total_revenue_all) * 100 : 0;
                                            ?>
                                            <tr>
                                                <td>
                                                    <span class="badge bg-<?php 
                                                        echo $plan['nama_plan'] == 'Free' ? 'secondary' : 
                                                             ($plan['nama_plan'] == 'Basic' ? 'primary' : 
                                                             ($plan['nama_plan'] == 'Premium' ? 'warning' : 'danger')); 
                                                    ?> stat-badge">
                                                        <?php echo $plan['nama_plan']; ?>
                                                    </span>
                                                </td>
                                                <td><strong><?php echo $plan['total_sales']; ?></strong></td>
                                                <td class="text-success fw-bold"><?php echo format_rupiah($plan['total_revenue']); ?></td>
                                                <td>
                                                    <?php 
                                                        $avg = $plan['total_sales'] > 0 ? $plan['total_revenue'] / $plan['total_sales'] : 0;
                                                        echo format_rupiah($avg);
                                                    ?>
                                                </td>
                                                <td>
                                                    <div class="progress" style="height: 20px;">
                                                        <div class="progress-bar bg-<?php 
                                                            echo $plan['nama_plan'] == 'Free' ? 'secondary' : 
                                                                 ($plan['nama_plan'] == 'Basic' ? 'primary' : 
                                                                 ($plan['nama_plan'] == 'Premium' ? 'warning' : 'danger')); 
                                                        ?>" 
                                                             style="width: <?php echo $market_share; ?>%">
                                                            <?php echo round($market_share, 1); ?>%
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Preview Bukti Transfer -->
    <div class="modal fade" id="proofModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Bukti Transfer</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <img id="proofImage" src="" alt="Bukti Transfer" class="img-fluid modal-preview">
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Tolak Pembayaran -->
    <div class="modal fade" id="rejectModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" id="rejectForm">
                    <div class="modal-header">
                        <h5 class="modal-title">Tolak Pembayaran</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="reject_payment">
                        <input type="hidden" name="payment_id" id="rejectPaymentId">
                        
                        <div class="mb-3">
                            <label class="form-label">Alasan Penolakan</label>
                            <textarea name="rejection_reason" class="form-control" rows="4" 
                                      placeholder="Berikan alasan penolakan pembayaran..." required></textarea>
                        </div>
                        <p class="text-muted small">
                            <i class="fas fa-info-circle me-1"></i>
                            Alasan akan dikirim ke email penjual
                        </p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-danger">Tolak Pembayaran</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

<script>
// Active Subscriptions by Plan Chart
const planCtx = document.getElementById('planChart').getContext('2d');
const planChart = new Chart(planCtx, {
    type: 'doughnut',
    data: {
        labels: [<?php 
            $labels = [];
            foreach($active_by_plan as $plan) {
                $labels[] = "'" . $plan['nama_plan'] . "'";
            }
            echo implode(',', $labels);
        ?>],
        datasets: [{
            data: [<?php 
                $data = [];
                foreach($active_by_plan as $plan) {
                    $data[] = $plan['active_count'];
                }
                echo implode(',', $data);
            ?>],
            backgroundColor: [
                '#6c757d', // Free - gray
                '#3498db', // Basic - blue
                '#f39c12', // Premium - orange
                '#e74c3c'  // Business - red
            ],
            borderWidth: 2
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom'
            }
        }
    }
});

// Monthly Revenue Chart
const revenueCtx = document.getElementById('revenueChart').getContext('2d');
const revenueChart = new Chart(revenueCtx, {
    type: 'bar',
    data: {
        labels: [<?php 
            $months = [];
            foreach($monthly_summary as $month) {
                $months[] = "'" . date('M Y', strtotime($month['bulan'] . '-01')) . "'";
            }
            echo implode(',', array_reverse($months));
        ?>],
        datasets: [{
            label: 'Monthly Revenue',
            data: [<?php 
                $revenues = [];
                foreach(array_reverse($monthly_summary) as $month) {
                    $revenues[] = $month['monthly_revenue'];
                }
                echo implode(',', $revenues);
            ?>],
            backgroundColor: '#27ae60',
            borderColor: '#219653',
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return 'Rp ' + value.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
                    }
                }
            }
        },
        plugins: {
            legend: {
                display: false
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return 'Revenue: Rp ' + context.raw.toLocaleString('id-ID');
                    }
                }
            }
        }
    }
});

// Modal untuk preview bukti transfer
document.addEventListener('DOMContentLoaded', function() {
    const proofModal = document.getElementById('proofModal');
    const proofImage = document.getElementById('proofImage');
    
    proofModal.addEventListener('show.bs.modal', function(event) {
        const button = event.relatedTarget;
        const proofSrc = button.getAttribute('data-proof-src');
        proofImage.src = proofSrc;
    });
    
    // Modal untuk tolak pembayaran
    const rejectModal = document.getElementById('rejectModal');
    rejectModal.addEventListener('show.bs.modal', function(event) {
        const button = event.relatedTarget;
        const paymentId = button.getAttribute('data-payment-id');
        document.getElementById('rejectPaymentId').value = paymentId;
    });
    
    // Auto dismiss alerts
    setTimeout(function() {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(function(alert) {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        });
    }, 5000);
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>