<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';

// Cek login menggunakan auth system yang sudah ada
if (!$auth->isLoggedIn()) {
    header("Location: ../login.php");
    exit();
}

// Cek role admin
if ($_SESSION['role'] != 'admin') {
    header("Location: ../index.php");
    exit();
}

// HAPUS bagian referer check karena tidak perlu
// $referer = $_SERVER['HTTP_REFERER'] ?? '';
// if (!strpos($referer, '/admin/') && basename($_SERVER['PHP_SELF']) != 'index.php') {
//     redirect('admin/index.php');
// }

// Hitung statistik
$stats = [];

// Total users
$sql = "SELECT COUNT(*) as total FROM users";
$stmt = $pdo->query($sql);
$stats['total_users'] = $stmt->fetch()['total'];

// Total penjual
$sql = "SELECT COUNT(*) as total FROM users WHERE role = 'penjual'";
$stmt = $pdo->query($sql);
$stats['total_sellers'] = $stmt->fetch()['total'];

// Total pembeli
$sql = "SELECT COUNT(*) as total FROM users WHERE role = 'pembeli'";
$stmt = $pdo->query($sql);
$stats['total_buyers'] = $stmt->fetch()['total'];

// Total mobil
$sql = "SELECT COUNT(*) as total FROM mobil";
$stmt = $pdo->query($sql);
$stats['total_cars'] = $stmt->fetch()['total'];

// Mobil tersedia
$sql = "SELECT COUNT(*) as total FROM mobil WHERE status = 'tersedia'";
$stmt = $pdo->query($sql);
$stats['available_cars'] = $stmt->fetch()['total'];

// Total booking
$sql = "SELECT COUNT(*) as total FROM transaksi_booking";
$stmt = $pdo->query($sql);
$stats['total_bookings'] = $stmt->fetch()['total'];

// Total booking hari ini
$sql = "SELECT COUNT(*) as total FROM transaksi_booking WHERE DATE(created_at) = CURDATE()";
$stmt = $pdo->query($sql);
$stats['today_bookings'] = $stmt->fetch()['total'];

// Total pendapatan dari subscriptions (bulan ini)
$sql = "SELECT COALESCE(SUM(harga_bulanan), 0) as total 
        FROM seller_subscriptions ss
        JOIN subscription_plans sp ON ss.plan_id = sp.id
        WHERE ss.status = 'active' 
        AND MONTH(ss.mulai_tanggal) = MONTH(CURDATE()) 
        AND YEAR(ss.mulai_tanggal) = YEAR(CURDATE())";
$stmt = $pdo->query($sql);
$stats['subscription_revenue'] = $stmt->fetch()['total'];

// Active subscriptions
$sql = "SELECT COUNT(*) as total FROM seller_subscriptions WHERE status = 'active'";
$stmt = $pdo->query($sql);
$stats['active_subscriptions'] = $stmt->fetch()['total'];

// Data chart - Pendaftaran user 7 hari terakhir
$sql = "SELECT DATE(created_at) as tanggal, COUNT(*) as jumlah
        FROM users 
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        GROUP BY DATE(created_at)
        ORDER BY tanggal ASC";
$stmt = $pdo->query($sql);
$user_registrations = $stmt->fetchAll();

// Data chart - Mobil upload 7 hari terakhir
$sql = "SELECT DATE(created_at) as tanggal, COUNT(*) as jumlah
        FROM mobil 
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        GROUP BY DATE(created_at)
        ORDER BY tanggal ASC";
$stmt = $pdo->query($sql);
$car_uploads = $stmt->fetchAll();

// Recent bookings
$sql = "SELECT tb.*, u1.nama_lengkap as nama_pembeli, u2.nama_lengkap as nama_penjual, m.merk, m.model
        FROM transaksi_booking tb
        JOIN users u1 ON tb.pembeli_id = u1.id
        JOIN users u2 ON tb.penjual_id = u2.id
        JOIN mobil m ON tb.mobil_id = m.id
        ORDER BY tb.created_at DESC
        LIMIT 10";
$stmt = $pdo->query($sql);
$recent_bookings = $stmt->fetchAll();

// Recent users
$sql = "SELECT * FROM users ORDER BY created_at DESC LIMIT 10";
$stmt = $pdo->query($sql);
$recent_users = $stmt->fetchAll();

// Subscription stats
$sql = "SELECT sp.nama_plan, COUNT(ss.id) as total
        FROM subscription_plans sp
        LEFT JOIN seller_subscriptions ss ON sp.id = ss.plan_id AND ss.status = 'active'
        GROUP BY sp.id
        ORDER BY sp.harga_bulanan ASC";
$stmt = $pdo->query($sql);
$subscription_stats = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Automarket</title>
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
            border-left: 4px solid var(--success);
        }
        
        .stat-card {
            border-radius: 10px;
            border: none;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            transition: transform 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-icon {
            font-size: 2.5rem;
            opacity: 0.8;
        }
        
        .chart-container {
            background: white;
            border-radius: 10px;
            padding: 15px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            height: 100%;
        }
        
        /* PERBAIKAN: Chart height lebih compact */
        .chart-wrapper {
            height: 180px; /* Dikurangi dari 200px */
            position: relative;
            margin-top: 10px;
        }
        
        .chart-title {
            font-size: 0.95rem;
            margin-bottom: 10px;
            color: #2c3e50;
            font-weight: 600;
        }
        
        .table-responsive {
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
        }
        
        .badge-admin {
            font-size: 0.8rem;
            padding: 5px 10px;
            border-radius: 20px;
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
                    <a class="nav-link active" href="dashboard.php">
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
            <div class="col-md-9 col-lg-10">
                <!-- Header -->
                <div class="admin-header">
                    <div class="container-fluid">
                        <div class="d-flex justify-content-between align-items-center">
                            <h2 class="mb-0">
                                <i class="fas fa-tachometer-alt text-success me-2"></i>
                                Admin Dashboard
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
                    <!-- Statistics Cards -->
                    <div class="row mb-4">
                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="card stat-card border-left-primary">
                                <div class="card-body">
                                    <div class="row align-items-center">
                                        <div class="col-8">
                                            <h6 class="text-uppercase text-muted mb-1">Total Users</h6>
                                            <h2 class="mb-0"><?php echo $stats['total_users']; ?></h2>
                                        </div>
                                        <div class="col-4 text-end">
                                            <i class="fas fa-users stat-icon text-primary"></i>
                                        </div>
                                    </div>
                                    <div class="mt-3">
                                        <small class="text-muted">
                                            <i class="fas fa-male me-1"></i> <?php echo $stats['total_sellers']; ?> Sellers
                                            | 
                                            <i class="fas fa-shopping-cart me-1"></i> <?php echo $stats['total_buyers']; ?> Buyers
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="card stat-card border-left-success">
                                <div class="card-body">
                                    <div class="row align-items-center">
                                        <div class="col-8">
                                            <h6 class="text-uppercase text-muted mb-1">Total Cars</h6>
                                            <h2 class="mb-0"><?php echo $stats['total_cars']; ?></h2>
                                        </div>
                                        <div class="col-4 text-end">
                                            <i class="fas fa-car stat-icon text-success"></i>
                                        </div>
                                    </div>
                                    <div class="mt-3">
                                        <small class="text-muted">
                                            <i class="fas fa-check-circle me-1 text-success"></i> 
                                            <?php echo $stats['available_cars']; ?> Available
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="card stat-card border-left-warning">
                                <div class="card-body">
                                    <div class="row align-items-center">
                                        <div class="col-8">
                                            <h6 class="text-uppercase text-muted mb-1">Bookings</h6>
                                            <h2 class="mb-0"><?php echo $stats['total_bookings']; ?></h2>
                                        </div>
                                        <div class="col-4 text-end">
                                            <i class="fas fa-calendar-check stat-icon text-warning"></i>
                                        </div>
                                    </div>
                                    <div class="mt-3">
                                        <small class="text-muted">
                                            <i class="fas fa-calendar-day me-1"></i> 
                                            <?php echo $stats['today_bookings']; ?> Today
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="card stat-card border-left-info">
                                <div class="card-body">
                                    <div class="row align-items-center">
                                        <div class="col-8">
                                            <h6 class="text-uppercase text-muted mb-1">Subscription Revenue</h6>
                                            <h2 class="mb-0"><?php echo format_rupiah($stats['subscription_revenue']); ?></h2>
                                        </div>
                                        <div class="col-4 text-end">
                                            <i class="fas fa-money-bill-wave stat-icon text-info"></i>
                                        </div>
                                    </div>
                                    <div class="mt-3">
                                        <small class="text-muted">
                                            <i class="fas fa-crown me-1"></i> 
                                            <?php echo $stats['active_subscriptions']; ?> Active
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Charts Section - DIUBAH -->
                    <div class="row mb-4">
                        <!-- User Registration Chart -->
                        <div class="col-lg-6 mb-4">
                            <div class="chart-container">
                                <div class="d-flex align-items-center mb-2">
                                    <i class="fas fa-chart-line text-primary me-2"></i>
                                    <h6 class="chart-title mb-0">User Registrations (7 Days)</h6>
                                </div>
                                <div class="chart-wrapper">
                                    <canvas id="userChart"></canvas>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Car Upload Chart -->
                        <div class="col-lg-6 mb-4">
                            <div class="chart-container">
                                <div class="d-flex align-items-center mb-2">
                                    <i class="fas fa-chart-bar text-success me-2"></i>
                                    <h6 class="chart-title mb-0">Car Uploads (7 Days)</h6>
                                </div>
                                <div class="chart-wrapper">
                                    <canvas id="carChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Subscription Stats -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="chart-container">
                                <div class="d-flex align-items-center mb-3">
                                    <i class="fas fa-crown text-warning me-2"></i>
                                    <h6 class="chart-title mb-0">Subscription Plans Distribution</h6>
                                </div>
                                <div class="row mt-2">
                                    <?php foreach($subscription_stats as $plan): ?>
                                    <div class="col-md-3 col-6 mb-3">
                                        <div class="card border-0 shadow-sm">
                                            <div class="card-body text-center py-3">
                                                <h3 class="text-<?php 
                                                    echo $plan['nama_plan'] == 'Free' ? 'secondary' : 
                                                         ($plan['nama_plan'] == 'Basic' ? 'primary' : 
                                                         ($plan['nama_plan'] == 'Premium' ? 'warning' : 'danger')); 
                                                ?>">
                                                    <?php echo $plan['total']; ?>
                                                </h3>
                                                <h6 class="mb-2" style="font-size: 0.9rem;"><?php echo $plan['nama_plan']; ?></h6>
                                                <small class="text-muted">
                                                    <?php 
                                                        $percentage = $stats['total_sellers'] > 0 ? 
                                                            round(($plan['total'] / $stats['total_sellers']) * 100, 1) : 0;
                                                        echo $percentage; ?>%
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Activities -->
                    <div class="row">
                        <!-- Recent Bookings -->
                        <div class="col-lg-6 mb-4">
                            <div class="card border-0 shadow-sm">
                                <div class="card-header bg-white py-3">
                                    <div class="d-flex align-items-center">
                                        <i class="fas fa-history text-primary me-2"></i>
                                        <h6 class="mb-0">Recent Bookings</h6>
                                    </div>
                                </div>
                                <div class="card-body p-0">
                                    <div class="table-responsive">
                                        <table class="table table-hover mb-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Code</th>
                                                    <th>Car</th>
                                                    <th>Status</th>
                                                    <th>Date</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach($recent_bookings as $booking): ?>
                                                <tr>
                                                    <td><code><?php echo $booking['kode_booking']; ?></code></td>
                                                    <td>
                                                        <small><?php echo $booking['merk'] . ' ' . $booking['model']; ?></small>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-<?php 
                                                            switch($booking['status']) {
                                                                case 'pending': echo 'warning'; break;
                                                                case 'dikonfirmasi': echo 'success'; break;
                                                                case 'dibatalkan': echo 'danger'; break;
                                                                default: echo 'secondary';
                                                            }
                                                        ?> badge-admin">
                                                            <?php echo $booking['status']; ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <small><?php echo date('d/m', strtotime($booking['created_at'])); ?></small>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Recent Users -->
                        <div class="col-lg-6 mb-4">
                            <div class="card border-0 shadow-sm">
                                <div class="card-header bg-white py-3">
                                    <div class="d-flex align-items-center">
                                        <i class="fas fa-user-plus text-primary me-2"></i>
                                        <h6 class="mb-0">Recent Users</h6>
                                    </div>
                                </div>
                                <div class="card-body p-0">
                                    <div class="table-responsive">
                                        <table class="table table-hover mb-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>User</th>
                                                    <th>Email</th>
                                                    <th>Role</th>
                                                    <th>Joined</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach($recent_users as $user): ?>
                                                <tr>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <?php if(!empty($user['foto_profil'])): ?>
                                                                <img src="<?php echo BASE_URL; ?>uploads/profiles/<?php echo $user['foto_profil']; ?>" 
                                                                     class="user-avatar me-2">
                                                            <?php else: ?>
                                                                <div class="user-avatar me-2 bg-light d-flex align-items-center justify-content-center">
                                                                    <i class="fas fa-user text-muted"></i>
                                                                </div>
                                                            <?php endif; ?>
                                                            <div>
                                                                <div class="fw-bold" style="font-size: 0.9rem;"><?php echo htmlspecialchars($user['nama_lengkap']); ?></div>
                                                                <small class="text-muted">@<?php echo htmlspecialchars($user['username']); ?></small>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td><small><?php echo htmlspecialchars($user['email']); ?></small></td>
                                                    <td>
                                                        <span class="badge bg-<?php 
                                                            switch($user['role']) {
                                                                case 'admin': echo 'danger'; break;
                                                                case 'penjual': echo 'success'; break;
                                                                case 'pembeli': echo 'primary'; break;
                                                                default: echo 'secondary';
                                                            }
                                                        ?> badge-admin">
                                                            <?php echo $user['role']; ?>
                                                        </span>
                                                    </td>
                                                    <td><small><?php echo date('d/m', strtotime($user['created_at'])); ?></small></td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Stats Footer -->
                    <div class="row mt-4">
                        <div class="col-12">
                            <div class="card border-0 bg-light">
                                <div class="card-body py-3">
                                    <div class="row text-center">
                                        <div class="col-md-3 col-6 border-end">
                                            <h4 class="text-success mb-1"><?php echo $stats['total_sellers']; ?></h4>
                                            <small class="text-muted">Sellers</small>
                                        </div>
                                        <div class="col-md-3 col-6 border-end">
                                            <h4 class="text-primary mb-1"><?php echo $stats['total_buyers']; ?></h4>
                                            <small class="text-muted">Buyers</small>
                                        </div>
                                        <div class="col-md-3 col-6 border-end">
                                            <h4 class="text-warning mb-1"><?php echo $stats['available_cars']; ?></h4>
                                            <small class="text-muted">Available Cars</small>
                                        </div>
                                        <div class="col-md-3 col-6">
                                            <h4 class="text-info mb-1"><?php echo $stats['active_subscriptions']; ?></h4>
                                            <small class="text-muted">Active Subs</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // User Registration Chart - DIUBAH
        const userCtx = document.getElementById('userChart').getContext('2d');
        const userChart = new Chart(userCtx, {
            type: 'line',
            data: {
                labels: [<?php 
                    $dates = [];
                    foreach($user_registrations as $reg) {
                        $dates[] = "'" . date('d M', strtotime($reg['tanggal'])) . "'";
                    }
                    echo implode(',', $dates);
                ?>],
                datasets: [{
                    label: 'User Registrations',
                    data: [<?php 
                        $counts = [];
                        foreach($user_registrations as $reg) {
                            $counts[] = $reg['jumlah'];
                        }
                        echo implode(',', $counts);
                    ?>],
                    borderColor: '#3498db',
                    backgroundColor: 'rgba(52, 152, 219, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    x: {
                        ticks: {
                            maxRotation: 0,
                            padding: 5
                        },
                        grid: {
                            display: false
                        }
                    },
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1,
                            padding: 5
                        },
                        grid: {
                            color: 'rgba(0,0,0,0.05)'
                        }
                    }
                }
            }
        });

        // Car Upload Chart - DIUBAH
        const carCtx = document.getElementById('carChart').getContext('2d');
        const carChart = new Chart(carCtx, {
            type: 'bar',
            data: {
                labels: [<?php 
                    $dates = [];
                    foreach($car_uploads as $car) {
                        $dates[] = "'" . date('d M', strtotime($car['tanggal'])) . "'";
                    }
                    echo implode(',', $dates);
                ?>],
                datasets: [{
                    label: 'Car Uploads',
                    data: [<?php 
                        $counts = [];
                        foreach($car_uploads as $car) {
                            $counts[] = $car['jumlah'];
                        }
                        echo implode(',', $counts);
                    ?>],
                    backgroundColor: '#27ae60',
                    borderColor: '#219653',
                    borderWidth: 1,
                    borderRadius: 3
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    x: {
                        ticks: {
                            maxRotation: 0,
                            padding: 5
                        },
                        grid: {
                            display: false
                        }
                    },
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1,
                            padding: 5
                        },
                        grid: {
                            color: 'rgba(0,0,0,0.05)'
                        }
                    }
                }
            }
        });
    </script>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>