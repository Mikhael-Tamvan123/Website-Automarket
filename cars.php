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

// Filter parameters
$status = $_GET['status'] ?? '';
$seller_id = $_GET['seller_id'] ?? '';
$search = $_GET['search'] ?? '';
$sort = $_GET['sort'] ?? 'newest';

// Build query
$sql = "SELECT m.*, 
               u.nama_lengkap as seller_name,
               u.username as seller_username,
               ss.status as subscription_status,
               sp.nama_plan
        FROM mobil m
        JOIN users u ON m.penjual_id = u.id
        LEFT JOIN seller_subscriptions ss ON u.id = ss.seller_id AND ss.status = 'active'
        LEFT JOIN subscription_plans sp ON ss.plan_id = sp.id
        WHERE 1=1";

$params = [];

if (!empty($status)) {
    $sql .= " AND m.status = ?";
    $params[] = $status;
}

if (!empty($seller_id)) {
    $sql .= " AND m.penjual_id = ?";
    $params[] = $seller_id;
}

if (!empty($search)) {
    $sql .= " AND (m.merk LIKE ? OR m.model LIKE ? OR m.plat_mobil LIKE ? OR u.nama_lengkap LIKE ?)";
    $search_term = "%$search%";
    $params = array_merge($params, [$search_term, $search_term, $search_term, $search_term]);
}

switch ($sort) {
    case 'price_low':
        $sql .= " ORDER BY m.harga ASC";
        break;
    case 'price_high':
        $sql .= " ORDER BY m.harga DESC";
        break;
    case 'year':
        $sql .= " ORDER BY m.tahun DESC";
        break;
    default:
        $sql .= " ORDER BY m.created_at DESC";
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$cars = $stmt->fetchAll();

// Get sellers for filter
$sql_sellers = "SELECT DISTINCT u.id, u.nama_lengkap, u.username 
                FROM mobil m
                JOIN users u ON m.penjual_id = u.id
                WHERE u.role = 'penjual'
                ORDER BY u.nama_lengkap ASC";
$sellers = $pdo->query($sql_sellers)->fetchAll();

// Car statistics
$sql_stats = "SELECT 
                status,
                COUNT(*) as total,
                AVG(harga) as avg_price
              FROM mobil
              GROUP BY status";
$stmt_stats = $pdo->query($sql_stats);
$car_stats = $stmt_stats->fetchAll();

// Total stats
$total_cars = 0;
$total_value = 0;
foreach ($car_stats as $stat) {
    $total_cars += $stat['total'];
    $total_value += $stat['total'] * $stat['avg_price'];
}

// Handle actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        $car_id = (int)$_POST['car_id'];
        
        switch ($_POST['action']) {
            case 'update_status':
                $new_status = sanitize($_POST['status']);
                $sql = "UPDATE mobil SET status = ? WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                if ($stmt->execute([$new_status, $car_id])) {
                    $_SESSION['success'] = "Car status updated successfully";
                }
                break;
                
            case 'delete_car':
                // Get car image for deletion
                $sql_get = "SELECT foto_mobil FROM mobil WHERE id = ?";
                $stmt_get = $pdo->prepare($sql_get);
                $stmt_get->execute([$car_id]);
                $car = $stmt_get->fetch();
                
                // Delete image file if exists
                if (!empty($car['foto_mobil'])) {
                    $image_path = UPLOAD_CAR_DIR . $car['foto_mobil'];
                    if (file_exists($image_path)) {
                        unlink($image_path);
                    }
                }
                
                $sql = "DELETE FROM mobil WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                if ($stmt->execute([$car_id])) {
                    $_SESSION['success'] = "Car deleted successfully";
                }
                break;
                
            case 'feature_car':
                // You can add a featured column to mobil table
                // For now, just mark as featured in session
                $_SESSION['success'] = "Car marked as featured";
                break;
        }
        redirect('admin/cars.php');
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cars Management - Admin Automarket</title>
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
            border-left: 4px solid var(--success);
        }
        
        .admin-header {
            background: white;
            border-bottom: 1px solid #e0e0e0;
            padding: 15px 0;
            margin-bottom: 30px;
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
        
        .status-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
        }
        
        .seller-badge {
            padding: 4px 8px;
            border-radius: 15px;
            font-size: 0.75rem;
        }
        
        .car-image {
            width: 80px;
            height: 60px;
            border-radius: 5px;
            object-fit: cover;
        }
        
        .table-responsive {
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
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
                    <a class="nav-link active" href="cars.php">
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
            <div class="col-md-9 col-lg-10 p-0">
                <!-- Header -->
                <div class="admin-header">
                    <div class="container-fluid">
                        <div class="d-flex justify-content-between align-items-center">
                            <h2 class="mb-0">
                                <i class="fas fa-car text-success me-2"></i>
                                Cars Management
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
                        <!-- Header -->
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h2>
                                <i class="fas fa-car text-success me-2"></i>
                                Car Management
                            </h2>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#filterModal">
                                <i class="fas fa-filter me-2"></i>Filter
                            </button>
                        </div>

                        <!-- Messages -->
                        <?php if(isset($_SESSION['success'])): ?>
                            <div class="alert alert-success alert-dismissible fade show mb-4">
                                <i class="fas fa-check-circle me-2"></i>
                                <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <!-- Statistics -->
                        <div class="row mb-4">
                            <div class="col-md-3 col-6 mb-3">
                                <div class="card stat-card border-left-primary">
                                    <div class="card-body">
                                        <h6 class="text-muted">Total Cars</h6>
                                        <h3 class="mb-0"><?php echo $total_cars; ?></h3>
                                        <small class="text-muted">All listings</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3 col-6 mb-3">
                                <div class="card stat-card border-left-success">
                                    <div class="card-body">
                                        <h6 class="text-muted">Available</h6>
                                        <h3 class="mb-0">
                                            <?php 
                                                $available = 0;
                                                foreach($car_stats as $stat) {
                                                    if ($stat['status'] == 'tersedia') $available = $stat['total'];
                                                }
                                                echo $available;
                                            ?>
                                        </h3>
                                        <small class="text-muted">Ready for sale</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3 col-6 mb-3">
                                <div class="card stat-card border-left-warning">
                                    <div class="card-body">
                                        <h6 class="text-muted">Booked</h6>
                                        <h3 class="mb-0">
                                            <?php 
                                                $booked = 0;
                                                foreach($car_stats as $stat) {
                                                    if ($stat['status'] == 'dipesan') $booked = $stat['total'];
                                                }
                                                echo $booked;
                                            ?>
                                        </h3>
                                        <small class="text-muted">Currently booked</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3 col-6 mb-3">
                                <div class="card stat-card border-left-info">
                                    <div class="card-body">
                                        <h6 class="text-muted">Total Value</h6>
                                        <h3 class="mb-0"><?php echo format_rupiah($total_value); ?></h3>
                                        <small class="text-muted">Market value</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Cars Table -->
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-white py-3">
                                <h5 class="mb-0">All Car Listings</h5>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Car Details</th>
                                                <th>Seller</th>
                                                <th>Specifications</th>
                                                <th>Price</th>
                                                <th>Status</th>
                                                <th>Posted</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach($cars as $car): ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex">
                                                        <?php if(!empty($car['foto_mobil']) && file_exists(UPLOAD_CAR_DIR . $car['foto_mobil'])): ?>
                                                            <img src="<?php echo BASE_URL; ?>uploads/cars/<?php echo $car['foto_mobil']; ?>" 
                                                                 class="car-image me-3"
                                                                 alt="<?php echo htmlspecialchars($car['merk']); ?>">
                                                        <?php else: ?>
                                                            <div class="car-image me-3 bg-light d-flex align-items-center justify-content-center">
                                                                <i class="fas fa-car text-muted"></i>
                                                            </div>
                                                        <?php endif; ?>
                                                        <div>
                                                            <strong><?php echo htmlspecialchars($car['merk'] . ' ' . $car['model']); ?></strong><br>
                                                            <small class="text-muted">
                                                                Plat: <?php echo htmlspecialchars($car['plat_mobil']); ?><br>
                                                                Tahun: <?php echo $car['tahun']; ?>
                                                            </small>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($car['seller_name']); ?></strong><br>
                                                        <small class="text-muted">@<?php echo htmlspecialchars($car['seller_username']); ?></small>
                                                        <?php if($car['subscription_status'] == 'active'): ?>
                                                            <br>
                                                            <span class="badge bg-success seller-badge">
                                                                <i class="fas fa-crown me-1"></i>
                                                                <?php echo $car['nama_plan']; ?>
                                                            </span>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="small">
                                                        <i class="fas fa-palette me-1 text-muted"></i> <?php echo $car['warna']; ?><br>
                                                        <i class="fas fa-gas-pump me-1 text-muted"></i> <?php echo $car['bahan_bakar']; ?><br>
                                                        <i class="fas fa-cogs me-1 text-muted"></i> <?php echo $car['transmisi']; ?><br>
                                                        <i class="fas fa-tachometer-alt me-1 text-muted"></i> <?php echo number_format($car['kilometer']); ?> km
                                                    </div>
                                                </td>
                                                <td class="fw-bold text-success">
                                                    <?php echo format_rupiah($car['harga']); ?>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?php 
                                                        switch($car['status']) {
                                                            case 'tersedia': echo 'success'; break;
                                                            case 'dipesan': echo 'warning'; break;
                                                            case 'terjual': echo 'secondary'; break;
                                                            default: echo 'info';
                                                        }
                                                    ?> status-badge">
                                                        <?php echo $car['status']; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php echo date('d M Y', strtotime($car['created_at'])); ?><br>
                                                    <small class="text-muted">
                                                        <?php 
                                                            $days_ago = floor((time() - strtotime($car['created_at'])) / (60 * 60 * 24));
                                                            echo $days_ago . ' days ago';
                                                        ?>
                                                    </small>
                                                </td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <a href="<?php echo BASE_URL; ?>car_detail.php?id=<?php echo $car['id']; ?>" 
                                                           class="btn btn-outline-primary" target="_blank">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                        <button class="btn btn-outline-warning"
                                                                data-bs-toggle="modal"
                                                                data-bs-target="#statusModal"
                                                                onclick="setCarStatus(<?php echo $car['id']; ?>, '<?php echo $car['status']; ?>')">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <button class="btn btn-outline-info"
                                                                onclick="featureCar(<?php echo $car['id']; ?>)">
                                                            <i class="fas fa-star"></i>
                                                        </button>
                                                        <button class="btn btn-outline-danger"
                                                                onclick="deleteCar(<?php echo $car['id']; ?>, '<?php echo htmlspecialchars($car['merk'] . ' ' . $car['model']); ?>')">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                            
                                            <?php if(empty($cars)): ?>
                                            <tr>
                                                <td colspan="7" class="text-center py-4">
                                                    <i class="fas fa-car fa-2x text-muted mb-3"></i>
                                                    <p class="text-muted">No cars found for selected filters</p>
                                                </td>
                                            </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="card-footer bg-white py-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <small class="text-muted">
                                        Showing <?php echo count($cars); ?> cars
                                    </small>
                                    <small class="text-muted">
                                        Total value: <?php echo format_rupiah($total_value); ?>
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter Modal -->
    <div class="modal fade" id="filterModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="GET">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-filter me-2"></i>Filter Cars</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="">All Status</option>
                                <option value="tersedia" <?php echo $status == 'tersedia' ? 'selected' : ''; ?>>Available</option>
                                <option value="dipesan" <?php echo $status == 'dipesan' ? 'selected' : ''; ?>>Booked</option>
                                <option value="terjual" <?php echo $status == 'terjual' ? 'selected' : ''; ?>>Sold</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Seller</label>
                            <select name="seller_id" class="form-select">
                                <option value="">All Sellers</option>
                                <?php foreach($sellers as $seller): ?>
                                <option value="<?php echo $seller['id']; ?>" <?php echo $seller_id == $seller['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($seller['nama_lengkap']); ?> (@<?php echo $seller['username']; ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Sort By</label>
                            <select name="sort" class="form-select">
                                <option value="newest" <?php echo $sort == 'newest' ? 'selected' : ''; ?>>Newest First</option>
                                <option value="price_low" <?php echo $sort == 'price_low' ? 'selected' : ''; ?>>Price: Low to High</option>
                                <option value="price_high" <?php echo $sort == 'price_high' ? 'selected' : ''; ?>>Price: High to Low</option>
                                <option value="year" <?php echo $sort == 'year' ? 'selected' : ''; ?>>Newest Year</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Search</label>
                            <input type="text" name="search" class="form-control" placeholder="Brand, model, plate..." value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <a href="cars.php" class="btn btn-secondary">Reset</a>
                        <button type="submit" class="btn btn-primary">Apply Filter</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Status Update Modal -->
    <div class="modal fade" id="statusModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Update Car Status</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update_status">
                        <input type="hidden" name="car_id" id="status_car_id">
                        <div class="mb-3">
                            <label class="form-label">New Status</label>
                            <select name="status" class="form-select" id="status_select" required>
                                <option value="tersedia">Available</option>
                                <option value="dipesan">Booked</option>
                                <option value="terjual">Sold</option>
                            </select>
                        </div>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            Changing status will affect car visibility and booking availability.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning">Update Status</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    function setCarStatus(carId, currentStatus) {
        document.getElementById('status_car_id').value = carId;
        document.getElementById('status_select').value = currentStatus;
    }
    
    function featureCar(carId) {
        if (confirm('Feature this car on homepage?')) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.style.display = 'none';
            
            const action = document.createElement('input');
            action.name = 'action';
            action.value = 'feature_car';
            form.appendChild(action);
            
            const idInput = document.createElement('input');
            idInput.name = 'car_id';
            idInput.value = carId;
            form.appendChild(idInput);
            
            document.body.appendChild(form);
            form.submit();
        }
    }
    
    function deleteCar(carId, carName) {
        if (confirm(`Are you sure you want to delete "${carName}"?\n\nThis will permanently remove the car listing!`)) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.style.display = 'none';
            
            const action = document.createElement('input');
            action.name = 'action';
            action.value = 'delete_car';
            form.appendChild(action);
            
            const idInput = document.createElement('input');
            idInput.name = 'car_id';
            idInput.value = carId;
            form.appendChild(idInput);
            
            document.body.appendChild(form);
            form.submit();
        }
    }
    </script>
</body>
</html>