[file name]: admin_sidebar.php
[file content begin]
<?php
// Sidebar untuk admin panel dengan desain yang konsisten
?>
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
        <?php 
        $current_page = basename($_SERVER['PHP_SELF']);
        ?>
        <a class="nav-link <?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>" href="dashboard.php">
            <i class="fas fa-tachometer-alt me-2"></i> Dashboard
        </a>
        <a class="nav-link <?php echo $current_page == 'users.php' ? 'active' : ''; ?>" href="users.php">
            <i class="fas fa-users me-2"></i> Users
        </a>
        <a class="nav-link <?php echo $current_page == 'cars.php' ? 'active' : ''; ?>" href="cars.php">
            <i class="fas fa-car me-2"></i> Cars
        </a>
        <a class="nav-link <?php echo $current_page == 'bookings.php' ? 'active' : ''; ?>" href="bookings.php">
            <i class="fas fa-calendar-check me-2"></i> Bookings
        </a>
        <a class="nav-link <?php echo $current_page == 'subscriptions.php' ? 'active' : ''; ?>" href="subscriptions.php">
            <i class="fas fa-crown me-2"></i> Subscriptions
        </a>
        <a class="nav-link <?php echo $current_page == 'reports.php' ? 'active' : ''; ?>" href="reports.php">
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
[file content end]