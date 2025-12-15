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


// Get all subscription plans
$sql = "SELECT * FROM subscription_plans ORDER BY harga_bulanan ASC";
$stmt = $pdo->query($sql);
$plans = $stmt->fetchAll();

// Get all active subscriptions
$sql = "SELECT ss.*, u.username, u.nama_lengkap, u.email, sp.nama_plan, sp.harga_bulanan
        FROM seller_subscriptions ss
        JOIN users u ON ss.seller_id = u.id
        JOIN subscription_plans sp ON ss.plan_id = sp.id
        WHERE ss.status = 'active'
        ORDER BY ss.berakhir_tanggal ASC";
$stmt = $pdo->query($sql);
$active_subscriptions = $stmt->fetchAll();

// Get expired subscriptions
$sql = "SELECT ss.*, u.username, u.nama_lengkap, u.email, sp.nama_plan
        FROM seller_subscriptions ss
        JOIN users u ON ss.seller_id = u.id
        JOIN subscription_plans sp ON ss.plan_id = sp.id
        WHERE ss.status = 'expired'
        ORDER BY ss.berakhir_tanggal DESC
        LIMIT 20";
$stmt = $pdo->query($sql);
$expired_subscriptions = $stmt->fetchAll();

// Handle plan actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_plan':
                $nama_plan = sanitize($_POST['nama_plan']);
                $harga_bulanan = (float)$_POST['harga_bulanan'];
                $max_mobil = (int)$_POST['max_mobil'];
                $fitur = sanitize($_POST['fitur']);
                $is_popular = isset($_POST['is_popular']) ? 1 : 0;
                
                $sql = "INSERT INTO subscription_plans (nama_plan, harga_bulanan, max_mobil, fitur, is_popular) 
                        VALUES (?, ?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                if ($stmt->execute([$nama_plan, $harga_bulanan, $max_mobil, $fitur, $is_popular])) {
                    $_SESSION['success'] = "Plan berhasil ditambahkan";
                } else {
                    $_SESSION['error'] = "Gagal menambahkan plan";
                }
                break;
                
            case 'edit_plan':
                $plan_id = (int)$_POST['plan_id'];
                $nama_plan = sanitize($_POST['nama_plan']);
                $harga_bulanan = (float)$_POST['harga_bulanan'];
                $max_mobil = (int)$_POST['max_mobil'];
                $fitur = sanitize($_POST['fitur']);
                $is_popular = isset($_POST['is_popular']) ? 1 : 0;
                
                $sql = "UPDATE subscription_plans 
                        SET nama_plan = ?, harga_bulanan = ?, max_mobil = ?, fitur = ?, is_popular = ?
                        WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                if ($stmt->execute([$nama_plan, $harga_bulanan, $max_mobil, $fitur, $is_popular, $plan_id])) {
                    $_SESSION['success'] = "Plan berhasil diperbarui";
                } else {
                    $_SESSION['error'] = "Gagal memperbarui plan";
                }
                break;
                
            case 'delete_plan':
                $plan_id = (int)$_POST['plan_id'];
                
                // Cek apakah plan sedang digunakan
                $sql_check = "SELECT COUNT(*) as total FROM seller_subscriptions WHERE plan_id = ?";
                $stmt_check = $pdo->prepare($sql_check);
                $stmt_check->execute([$plan_id]);
                $in_use = $stmt_check->fetch()['total'];
                
                if ($in_use > 0) {
                    $_SESSION['error'] = "Plan tidak dapat dihapus karena sedang digunakan oleh $in_use seller";
                } else {
                    $sql = "DELETE FROM subscription_plans WHERE id = ?";
                    $stmt = $pdo->prepare($sql);
                    if ($stmt->execute([$plan_id])) {
                        $_SESSION['success'] = "Plan berhasil dihapus";
                    } else {
                        $_SESSION['error'] = "Gagal menghapus plan";
                    }
                }
                break;
                
            case 'extend_subscription':
                $subscription_id = (int)$_POST['subscription_id'];
                $months = (int)$_POST['months'];
                
                $sql = "UPDATE seller_subscriptions 
                        SET berakhir_tanggal = DATE_ADD(berakhir_tanggal, INTERVAL ? MONTH)
                        WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                if ($stmt->execute([$months, $subscription_id])) {
                    $_SESSION['success'] = "Subscription berhasil diperpanjang $months bulan";
                } else {
                    $_SESSION['error'] = "Gagal memperpanjang subscription";
                }
                break;
                
            case 'cancel_subscription':
                $subscription_id = (int)$_POST['subscription_id'];
                
                $sql = "UPDATE seller_subscriptions 
                        SET status = 'inactive' 
                        WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                if ($stmt->execute([$subscription_id])) {
                    $_SESSION['success'] = "Subscription berhasil dinonaktifkan";
                } else {
                    $_SESSION['error'] = "Gagal menonaktifkan subscription";
                }
                break;
        }
        redirect('admin/subscriptions.php');
    }
}

// Calculate subscription revenue
$sql = "SELECT 
            MONTH(ss.mulai_tanggal) as bulan,
            YEAR(ss.mulai_tanggal) as tahun,
            COUNT(ss.id) as total_subscriptions,
            SUM(sp.harga_bulanan) as total_revenue
        FROM seller_subscriptions ss
        JOIN subscription_plans sp ON ss.plan_id = sp.id
        WHERE ss.status = 'active'
        GROUP BY YEAR(ss.mulai_tanggal), MONTH(ss.mulai_tanggal)
        ORDER BY tahun DESC, bulan DESC
        LIMIT 6";
$stmt = $pdo->query($sql);
$revenue_stats = $stmt->fetchAll();

// Get total revenue
$sql = "SELECT COALESCE(SUM(sp.harga_bulanan), 0) as total_revenue
        FROM seller_subscriptions ss
        JOIN subscription_plans sp ON ss.plan_id = sp.id
        WHERE ss.status = 'active'";
$stmt = $pdo->query($sql);
$total_revenue = $stmt->fetch()['total_revenue'];
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Subscriptions - Admin Automarket</title>
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
            border-left: 4px solid var(--warning);
        }
        
        .plan-card {
            border-radius: 10px;
            transition: all 0.3s;
            border: 2px solid transparent;
            height: 100%;
        }
        .plan-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .plan-card.popular {
            border-color: #f39c12;
            position: relative;
        }
        .plan-card.popular::before {
            content: 'POPULAR';
            position: absolute;
            top: -10px;
            right: 20px;
            background: #f39c12;
            color: white;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: bold;
            z-index: 1;
        }
        .badge-subscription {
            padding: 4px 8px;
            border-radius: 20px;
            font-size: 0.75rem;
        }
        .table-actions {
            min-width: 100px;
        }
        .card-header {
            background-color: white;
            border-bottom: 1px solid rgba(0,0,0,.125);
            padding: 0.75rem 1.25rem;
        }
        .card-header h5 {
            margin: 0;
            font-size: 1rem;
        }
        .table th, .table td {
            padding: 0.75rem;
            vertical-align: middle;
        }
        .table th {
            font-weight: 600;
            font-size: 0.85rem;
            color: #495057;
        }
        .table td {
            font-size: 0.85rem;
        }
        .stats-card {
            height: 100%;
            border: none;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            border-left: 4px solid;
        }
        .stats-card h5 {
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
        }
        .stats-card h2 {
            font-size: 1.5rem;
            font-weight: 700;
        }
        .stats-card small {
            font-size: 0.8rem;
            opacity: 0.9;
        }
        .btn-group-sm > .btn {
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
        }
        
        .admin-header {
            background: white;
            border-bottom: 1px solid #e0e0e0;
            padding: 15px 0;
            margin-bottom: 30px;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
        }
        
        @media (max-width: 768px) {
            .plan-card {
                margin-bottom: 15px;
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
                    <a class="nav-link active" href="subscriptions.php">
                        <i class="fas fa-crown me-2"></i> Subscriptions
                    </a>
                    <a class="nav-link" href="reports.php">
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
            <div class="col-md-9 col-lg-10 p-0">
                <!-- Header -->
                <div class="admin-header">
                    <div class="container-fluid">
                        <div class="d-flex justify-content-between align-items-center">
                            <h2 class="mb-0">
                                <i class="fas fa-crown text-warning me-2"></i>
                                Subscription Management
                            </h2>
                            <div class="text-end">
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
                        <?php if(isset($_SESSION['success'])): ?>
                            <div class="alert alert-success alert-dismissible fade show py-2">
                                <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" style="padding: 0.75rem;"></button>
                            </div>
                        <?php endif; ?>

                        <?php if(isset($_SESSION['error'])): ?>
                            <div class="alert alert-danger alert-dismissible fade show py-2">
                                <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" style="padding: 0.75rem;"></button>
                            </div>
                        <?php endif; ?>

                        <!-- Revenue Stats -->
                        <div class="row mb-4">
                            <div class="col-md-4 mb-3">
                                <div class="card stats-card border-left-primary">
                                    <div class="card-body p-3">
                                        <h5><i class="fas fa-money-bill-wave me-2"></i>Total Revenue</h5>
                                        <h2><?php echo format_rupiah($total_revenue); ?></h2>
                                        <small>From Active Subscriptions</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <div class="card stats-card border-left-success">
                                    <div class="card-body p-3">
                                        <h5><i class="fas fa-users me-2"></i>Active Subscribers</h5>
                                        <h2><?php echo count($active_subscriptions); ?></h2>
                                        <small>Sellers with Active Plans</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <div class="card stats-card border-left-info">
                                    <div class="card-body p-3">
                                        <h5><i class="fas fa-crown me-2"></i>Total Plans</h5>
                                        <h2><?php echo count($plans); ?></h2>
                                        <small>Subscription Plans Available</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Subscription Plans -->
                        <div class="card mb-4 border-0 shadow-sm">
                            <div class="card-header d-flex justify-content-between align-items-center py-3">
                                <h5 class="mb-0"><i class="fas fa-box me-2"></i>Subscription Plans</h5>
                                <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#addPlanModal">
                                    <i class="fas fa-plus me-1"></i>Add New Plan
                                </button>
                            </div>
                            <div class="card-body p-3">
                                <div class="row">
                                    <?php foreach($plans as $plan): ?>
                                    <div class="col-md-3 mb-3">
                                        <div class="card plan-card h-100 <?php echo $plan['is_popular'] ? 'popular' : ''; ?>">
                                            <div class="card-body p-3 text-center">
                                                <h5 class="card-title mb-2"><?php echo $plan['nama_plan']; ?></h5>
                                                <h4 class="text-success mb-1"><?php echo format_rupiah($plan['harga_bulanan']); ?></h4>
                                                <small class="text-muted d-block mb-2">per month</small>
                                                <hr class="my-2">
                                                <p class="mb-2">
                                                    <i class="fas fa-car me-2 text-primary"></i>
                                                    <strong><?php echo $plan['max_mobil']; ?></strong> Cars Max
                                                </p>
                                                <p class="small text-muted mb-3"><?php echo $plan['fitur']; ?></p>
                                                
                                                <div class="btn-group w-100 mt-auto">
                                                    <button class="btn btn-outline-warning btn-sm" 
                                                            onclick="editPlan(<?php echo $plan['id']; ?>)"
                                                            data-bs-toggle="modal" data-bs-target="#editPlanModal">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <?php if($plan['nama_plan'] != 'Free'): ?>
                                                    <button class="btn btn-outline-danger btn-sm" 
                                                            onclick="deletePlan(<?php echo $plan['id']; ?>, '<?php echo $plan['nama_plan']; ?>')">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Active Subscriptions -->
                        <div class="card mb-4 border-0 shadow-sm">
                            <div class="card-header d-flex justify-content-between align-items-center py-3">
                                <h5 class="mb-0"><i class="fas fa-user-check me-2 text-success"></i>Active Subscriptions</h5>
                                <span class="badge bg-success"><?php echo count($active_subscriptions); ?> Active</span>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th width="20%">Seller</th>
                                                <th width="10%">Plan</th>
                                                <th width="12%">Start Date</th>
                                                <th width="12%">End Date</th>
                                                <th width="10%">Price</th>
                                                <th width="10%">Days Left</th>
                                                <th width="12%" class="text-center">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if(empty($active_subscriptions)): ?>
                                            <tr>
                                                <td colspan="7" class="text-center text-muted py-3">No active subscriptions found</td>
                                            </tr>
                                            <?php else: ?>
                                            <?php foreach($active_subscriptions as $sub): 
                                                $days_left = floor((strtotime($sub['berakhir_tanggal']) - time()) / (60 * 60 * 24));
                                            ?>
                                            <tr>
                                                <td>
                                                    <div class="fw-semibold"><?php echo htmlspecialchars($sub['nama_lengkap']); ?></div>
                                                    <small class="text-muted">@<?php echo htmlspecialchars($sub['username']); ?></small>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?php 
                                                        echo $sub['nama_plan'] == 'Free' ? 'secondary' : 
                                                             ($sub['nama_plan'] == 'Basic' ? 'primary' : 
                                                             ($sub['nama_plan'] == 'Premium' ? 'warning' : 'danger')); 
                                                    ?> badge-subscription">
                                                        <?php echo $sub['nama_plan']; ?>
                                                    </span>
                                                </td>
                                                <td><?php echo date('d M Y', strtotime($sub['mulai_tanggal'])); ?></td>
                                                <td>
                                                    <?php echo date('d M Y', strtotime($sub['berakhir_tanggal'])); ?>
                                                    <?php if($days_left < 7): ?>
                                                        <div class="small text-danger">
                                                            <i class="fas fa-exclamation-triangle"></i> Expires soon
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-success fw-bold"><?php echo format_rupiah($sub['harga_bulanan']); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php echo $days_left > 30 ? 'success' : ($days_left > 7 ? 'warning' : 'danger'); ?>">
                                                        <?php echo $days_left; ?> days
                                                    </span>
                                                </td>
                                                <td class="text-center">
                                                    <div class="btn-group btn-group-sm">
                                                        <button class="btn btn-outline-primary" 
                                                                data-bs-toggle="modal" 
                                                                data-bs-target="#extendModal"
                                                                onclick="setExtendData(<?php echo $sub['id']; ?>, '<?php echo $sub['nama_lengkap']; ?>')"
                                                                title="Extend">
                                                            <i class="fas fa-calendar-plus"></i>
                                                        </button>
                                                        <button class="btn btn-outline-danger"
                                                                onclick="cancelSubscription(<?php echo $sub['id']; ?>, '<?php echo $sub['nama_lengkap']; ?>')"
                                                                title="Cancel">
                                                            <i class="fas fa-ban"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- Expired Subscriptions -->
                        <?php if(!empty($expired_subscriptions)): ?>
                        <div class="card border-0 shadow-sm">
                            <div class="card-header d-flex justify-content-between align-items-center py-3">
                                <h5 class="mb-0"><i class="fas fa-history me-2 text-secondary"></i>Recently Expired Subscriptions</h5>
                                <span class="badge bg-secondary"><?php echo count($expired_subscriptions); ?> Expired</span>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-sm mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th width="30%">Seller</th>
                                                <th width="20%">Plan</th>
                                                <th width="25%">Ended Date</th>
                                                <th width="25%">Days Expired</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach($expired_subscriptions as $sub): 
                                                $days_expired = floor((time() - strtotime($sub['berakhir_tanggal'])) / (60 * 60 * 24));
                                            ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($sub['nama_lengkap']); ?></td>
                                                <td><span class="badge bg-secondary"><?php echo $sub['nama_plan']; ?></span></td>
                                                <td><?php echo date('d M Y', strtotime($sub['berakhir_tanggal'])); ?></td>
                                                <td>
                                                    <span class="badge bg-light text-dark"><?php echo $days_expired; ?> days ago</span>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

<!-- Add Plan Modal -->
<div class="modal fade" id="addPlanModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Subscription Plan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_plan">
                    <div class="mb-3">
                        <label class="form-label">Plan Name</label>
                        <input type="text" name="nama_plan" class="form-control form-control-sm" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Monthly Price</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text">Rp</span>
                            <input type="number" name="harga_bulanan" class="form-control" min="0" step="1000" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Max Cars</label>
                        <input type="number" name="max_mobil" class="form-control form-control-sm" min="1" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Features (comma separated)</label>
                        <textarea name="fitur" class="form-control form-control-sm" rows="3" required></textarea>
                        <small class="text-muted">Example: Dashboard, Email Notifications, Priority Listing</small>
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="is_popular" id="is_popular">
                        <label class="form-check-label" for="is_popular">
                            Mark as Popular Plan
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success btn-sm">Add Plan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Plan Modal -->
<div class="modal fade" id="editPlanModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Subscription Plan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit_plan">
                    <input type="hidden" name="plan_id" id="edit_plan_id">
                    <div class="mb-3">
                        <label class="form-label">Plan Name</label>
                        <input type="text" name="nama_plan" id="edit_nama_plan" class="form-control form-control-sm" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Monthly Price</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text">Rp</span>
                            <input type="number" name="harga_bulanan" id="edit_harga_bulanan" class="form-control" min="0" step="1000" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Max Cars</label>
                        <input type="number" name="max_mobil" id="edit_max_mobil" class="form-control form-control-sm" min="1" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Features</label>
                        <textarea name="fitur" id="edit_fitur" class="form-control form-control-sm" rows="3" required></textarea>
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="is_popular" id="edit_is_popular">
                        <label class="form-check-label" for="edit_is_popular">
                            Mark as Popular Plan
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning btn-sm">Update Plan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Extend Subscription Modal -->
<div class="modal fade" id="extendModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Extend Subscription</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="extend_subscription">
                    <input type="hidden" name="subscription_id" id="extend_subscription_id">
                    <p>Extend subscription for <strong id="extend_seller_name"></strong> by:</p>
                    <div class="mb-3">
                        <select name="months" class="form-select form-select-sm" required>
                            <option value="1">1 Month</option>
                            <option value="3">3 Months</option>
                            <option value="6">6 Months</option>
                            <option value="12">12 Months</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success btn-sm">Extend</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
<script>
// AJAX untuk mengambil data plan
function editPlan(planId) {
    fetch(`api/get_plan.php?id=${planId}`)
        .then(response => response.json())
        .then(data => {
            document.getElementById('edit_plan_id').value = data.id;
            document.getElementById('edit_nama_plan').value = data.nama_plan;
            document.getElementById('edit_harga_bulanan').value = data.harga_bulanan;
            document.getElementById('edit_max_mobil').value = data.max_mobil;
            document.getElementById('edit_fitur').value = data.fitur;
            document.getElementById('edit_is_popular').checked = data.is_popular == 1;
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Failed to load plan data');
        });
}

function deletePlan(planId, planName) {
    if (confirm(`Are you sure you want to delete the "${planName}" plan?`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.style.display = 'none';
        
        const action = document.createElement('input');
        action.name = 'action';
        action.value = 'delete_plan';
        form.appendChild(action);
        
        const planIdInput = document.createElement('input');
        planIdInput.name = 'plan_id';
        planIdInput.value = planId;
        form.appendChild(planIdInput);
        
        document.body.appendChild(form);
        form.submit();
    }
}

function setExtendData(subId, sellerName) {
    document.getElementById('extend_subscription_id').value = subId;
    document.getElementById('extend_seller_name').textContent = sellerName;
}

function cancelSubscription(subId, sellerName) {
    if (confirm(`Are you sure you want to cancel ${sellerName}'s subscription?`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.style.display = 'none';
        
        const action = document.createElement('input');
        action.name = 'action';
        action.value = 'cancel_subscription';
        form.appendChild(action);
        
        const subIdInput = document.createElement('input');
        subIdInput.name = 'subscription_id';
        subIdInput.value = subId;
        form.appendChild(subIdInput);
        
        document.body.appendChild(form);
        form.submit();
    }
}
</script>
</body>
</html>