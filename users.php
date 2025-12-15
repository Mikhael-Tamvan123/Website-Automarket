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
$role = $_GET['role'] ?? '';
$search = $_GET['search'] ?? '';
$sort = $_GET['sort'] ?? 'newest';

// Build query
$sql = "SELECT u.*, 
               (SELECT COUNT(*) FROM mobil WHERE penjual_id = u.id) as total_cars,
               (SELECT COUNT(*) FROM transaksi_booking WHERE pembeli_id = u.id) as total_bookings,
               ss.status as subscription_status,
               sp.nama_plan
        FROM users u
        LEFT JOIN seller_subscriptions ss ON u.id = ss.seller_id AND ss.status = 'active'
        LEFT JOIN subscription_plans sp ON ss.plan_id = sp.id
        WHERE 1=1";

$params = [];

if (!empty($role)) {
    $sql .= " AND u.role = ?";
    $params[] = $role;
}

if (!empty($search)) {
    $sql .= " AND (u.username LIKE ? OR u.nama_lengkap LIKE ? OR u.email LIKE ? OR u.no_telepon LIKE ?)";
    $search_term = "%$search%";
    $params = array_merge($params, [$search_term, $search_term, $search_term, $search_term]);
}

switch ($sort) {
    case 'oldest':
        $sql .= " ORDER BY u.created_at ASC";
        break;
    case 'name':
        $sql .= " ORDER BY u.nama_lengkap ASC";
        break;
    case 'cars':
        $sql .= " ORDER BY total_cars DESC";
        break;
    default:
        $sql .= " ORDER BY u.created_at DESC";
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

// User statistics
$sql_stats = "SELECT 
                role,
                COUNT(*) as total,
                SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) as today
              FROM users
              GROUP BY role";
$stmt_stats = $pdo->query($sql_stats);
$user_stats = $stmt_stats->fetchAll();

// Handle actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        $user_id = (int)$_POST['user_id'];
        
        switch ($_POST['action']) {
            case 'update_role':
                $new_role = sanitize($_POST['role']);
                $sql = "UPDATE users SET role = ? WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                if ($stmt->execute([$new_role, $user_id])) {
                    $_SESSION['success'] = "User role updated successfully";
                }
                break;
                
            case 'delete_user':
                // Check if user has active listings or bookings
                $sql_check = "SELECT 
                                (SELECT COUNT(*) FROM mobil WHERE penjual_id = ?) as car_count,
                                (SELECT COUNT(*) FROM transaksi_booking WHERE penjual_id = ? OR pembeli_id = ?) as booking_count";
                $stmt_check = $pdo->prepare($sql_check);
                $stmt_check->execute([$user_id, $user_id, $user_id]);
                $check = $stmt_check->fetch();
                
                if ($check['car_count'] > 0 || $check['booking_count'] > 0) {
                    $_SESSION['error'] = "Cannot delete user with active listings or bookings";
                } else {
                    $sql = "DELETE FROM users WHERE id = ?";
                    $stmt = $pdo->prepare($sql);
                    if ($stmt->execute([$user_id])) {
                        $_SESSION['success'] = "User deleted successfully";
                    }
                }
                break;
                
            case 'ban_user':
                $reason = sanitize($_POST['reason'] ?? '');
                // You can add a banned flag to users table
                // For now, we'll just update the role
                $sql = "UPDATE users SET role = 'banned' WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                if ($stmt->execute([$user_id])) {
                    $_SESSION['success'] = "User has been banned";
                }
                break;
        }
        redirect('admin/users.php');
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - Admin Automarket</title>
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
        
        .user-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #dee2e6;
        }
        
        .role-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
        }
        
        .stats-card {
            border-radius: 10px;
            border: none;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .user-actions {
            min-width: 120px;
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
                    <a class="nav-link active" href="users.php">
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
            <div class="col-md-9 col-lg-10 p-0">
                <!-- Header -->
                <div class="admin-header">
                    <div class="container-fluid">
                        <div class="d-flex justify-content-between align-items-center">
                            <h2 class="mb-0">
                                <i class="fas fa-users text-primary me-2"></i>
                                Users Management
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
                                <i class="fas fa-users text-primary me-2"></i>
                                User Management
                            </h2>
                            <div>
                                <a href="users.php?export=csv" class="btn btn-success">
                                    <i class="fas fa-file-export me-2"></i>Export
                                </a>
                            </div>
                        </div>

                        <!-- Messages -->
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

                        <!-- User Statistics -->
                        <div class="row mb-4">
                            <?php foreach($user_stats as $stat): ?>
                            <div class="col-md-3 col-6 mb-3">
                                <div class="card stat-card border-left-<?php 
                                    echo $stat['role'] == 'admin' ? 'danger' : 
                                         ($stat['role'] == 'penjual' ? 'success' : 'primary');
                                ?>">
                                    <div class="card-body">
                                        <h6 class="text-muted text-uppercase"><?php echo ucfirst($stat['role']); ?>s</h6>
                                        <h3 class="mb-1"><?php echo $stat['total']; ?></h3>
                                        <small class="text-muted">
                                            <i class="fas fa-user-plus me-1"></i>
                                            <?php echo $stat['today']; ?> today
                                        </small>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            <div class="col-md-3 col-6 mb-3">
                                <div class="card stat-card border-left-info">
                                    <div class="card-body">
                                        <h6 class="text-muted text-uppercase">Total Users</h6>
                                        <h3 class="mb-1"><?php echo count($users); ?></h3>
                                        <small class="text-muted">
                                            <i class="fas fa-database me-1"></i>
                                            All accounts
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Filter Section -->
                        <div class="card mb-4 border-0 shadow-sm">
                            <div class="card-body">
                                <form method="GET" class="row g-3">
                                    <div class="col-md-4">
                                        <label class="form-label">Role</label>
                                        <select name="role" class="form-select">
                                            <option value="">All Roles</option>
                                            <option value="admin" <?php echo $role == 'admin' ? 'selected' : ''; ?>>Admin</option>
                                            <option value="penjual" <?php echo $role == 'penjual' ? 'selected' : ''; ?>>Seller</option>
                                            <option value="pembeli" <?php echo $role == 'pembeli' ? 'selected' : ''; ?>>Buyer</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Sort By</label>
                                        <select name="sort" class="form-select">
                                            <option value="newest" <?php echo $sort == 'newest' ? 'selected' : ''; ?>>Newest First</option>
                                            <option value="oldest" <?php echo $sort == 'oldest' ? 'selected' : ''; ?>>Oldest First</option>
                                            <option value="name" <?php echo $sort == 'name' ? 'selected' : ''; ?>>Name A-Z</option>
                                            <option value="cars" <?php echo $sort == 'cars' ? 'selected' : ''; ?>>Most Cars</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Search</label>
                                        <div class="input-group">
                                            <input type="text" name="search" class="form-control" placeholder="Name, email, phone..." value="<?php echo htmlspecialchars($search); ?>">
                                            <button class="btn btn-primary" type="submit">
                                                <i class="fas fa-search"></i>
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <!-- Users Table -->
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-white py-3">
                                <h5 class="mb-0">All Users</h5>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>User</th>
                                                <th>Contact</th>
                                                <th>Role</th>
                                                <th>Subscription</th>
                                                <th>Stats</th>
                                                <th>Joined</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach($users as $user): ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <?php if(!empty($user['foto_profil'])): ?>
                                                            <img src="<?php echo BASE_URL; ?>uploads/profiles/<?php echo $user['foto_profil']; ?>" 
                                                                 class="user-avatar me-3">
                                                        <?php else: ?>
                                                            <div class="user-avatar me-3 bg-light d-flex align-items-center justify-content-center">
                                                                <i class="fas fa-user text-muted"></i>
                                                            </div>
                                                        <?php endif; ?>
                                                        <div>
                                                            <strong><?php echo htmlspecialchars($user['nama_lengkap']); ?></strong><br>
                                                            <small class="text-muted">@<?php echo htmlspecialchars($user['username']); ?></small>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="small">
                                                        <i class="fas fa-envelope me-1 text-muted"></i> <?php echo htmlspecialchars($user['email']); ?><br>
                                                        <i class="fas fa-phone me-1 text-muted"></i> <?php echo htmlspecialchars($user['no_telepon']); ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?php 
                                                        switch($user['role']) {
                                                            case 'admin': echo 'danger'; break;
                                                            case 'penjual': echo 'success'; break;
                                                            case 'pembeli': echo 'primary'; break;
                                                            default: echo 'secondary';
                                                        }
                                                    ?> role-badge">
                                                        <?php echo $user['role']; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if($user['role'] == 'penjual'): ?>
                                                        <?php if($user['subscription_status'] == 'active'): ?>
                                                            <span class="badge bg-success">
                                                                <i class="fas fa-crown me-1"></i>
                                                                <?php echo $user['nama_plan'] ?? 'Free'; ?>
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="badge bg-secondary">Free</span>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="small">
                                                        <?php if($user['role'] == 'penjual'): ?>
                                                            <i class="fas fa-car text-success me-1"></i> <?php echo $user['total_cars']; ?> cars<br>
                                                        <?php endif; ?>
                                                        <i class="fas fa-calendar-check text-info me-1"></i> <?php echo $user['total_bookings']; ?> bookings
                                                    </div>
                                                </td>
                                                <td>
                                                    <?php echo date('d M Y', strtotime($user['created_at'])); ?><br>
                                                    <small class="text-muted">
                                                        <?php 
                                                            $days_ago = floor((time() - strtotime($user['created_at'])) / (60 * 60 * 24));
                                                            echo $days_ago == 0 ? 'Today' : $days_ago . ' days ago';
                                                        ?>
                                                    </small>
                                                </td>
                                                <td class="user-actions">
                                                    <div class="btn-group btn-group-sm">
                                                        <button class="btn btn-outline-primary"
                                                                data-bs-toggle="modal"
                                                                data-bs-target="#viewUserModal"
                                                                onclick="viewUser(<?php echo $user['id']; ?>)">
                                                            <i class="fas fa-eye"></i>
                                                        </button>
                                                        <button class="btn btn-outline-warning"
                                                                data-bs-toggle="modal"
                                                                data-bs-target="#editRoleModal"
                                                                onclick="setEditUser(<?php echo $user['id']; ?>, '<?php echo $user['role']; ?>', '<?php echo htmlspecialchars($user['nama_lengkap']); ?>')">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <?php if($user['role'] != 'admin'): ?>
                                                        <button class="btn btn-outline-danger"
                                                                onclick="deleteUser(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['nama_lengkap']); ?>')">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="card-footer bg-white py-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <small class="text-muted">
                                        Total: <?php echo count($users); ?> users
                                    </small>
                                    <small class="text-muted">
                                        Showing all users
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- View User Modal -->
    <div class="modal fade" id="viewUserModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">User Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="viewUserBody">
                    <!-- Content loaded via AJAX -->
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Role Modal -->
    <div class="modal fade" id="editRoleModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit User Role</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update_role">
                        <input type="hidden" name="user_id" id="edit_user_id">
                        <p>Update role for <strong id="edit_user_name"></strong>:</p>
                        <div class="mb-3">
                            <select name="role" class="form-select" id="edit_user_role" required>
                                <option value="admin">Admin</option>
                                <option value="penjual">Seller</option>
                                <option value="pembeli">Buyer</option>
                            </select>
                        </div>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            Changing user role may affect their access and permissions.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning">Update Role</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    function viewUser(userId) {
        // Load user details via AJAX
        document.getElementById('viewUserBody').innerHTML = `
            <div class="text-center py-4">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-2">Loading user details...</p>
            </div>
        `;
        
        // In real app, use AJAX to fetch user details
        // For now, show basic info
        setTimeout(() => {
            document.getElementById('viewUserBody').innerHTML = `
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    User details would load here via AJAX in a real implementation.
                </div>
            `;
        }, 500);
    }
    
    function setEditUser(userId, currentRole, userName) {
        document.getElementById('edit_user_id').value = userId;
        document.getElementById('edit_user_name').textContent = userName;
        document.getElementById('edit_user_role').value = currentRole;
    }
    
    function deleteUser(userId, userName) {
        if (confirm(`Are you sure you want to delete user "${userName}"?\n\nThis action cannot be undone!`)) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.style.display = 'none';
            
            const action = document.createElement('input');
            action.name = 'action';
            action.value = 'delete_user';
            form.appendChild(action);
            
            const idInput = document.createElement('input');
            idInput.name = 'user_id';
            idInput.value = userId;
            form.appendChild(idInput);
            
            document.body.appendChild(form);
            form.submit();
        }
    }
    </script>
</body>
</html>