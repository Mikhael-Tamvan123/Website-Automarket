<?php
// includes/header.php
if (!isset($_SESSION)) {
    session_start();
}
?>
<nav class="navbar navbar-expand-lg navbar-light bg-light shadow-sm">
    <div class="container">
        <a class="navbar-brand" href="index.php">
            <i class="fas fa-car"></i> Automarket
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>" href="index.php">Beranda</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'cars.php' ? 'active' : ''; ?>" href="cars.php">Mobil</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'about.php' ? 'active' : ''; ?>" href="about.php">Tentang Kami</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'contact.php' ? 'active' : ''; ?>" href="contact.php">Kontak</a>
                </li>
            </ul>
            <div class="d-flex">
                <?php if(isset($_SESSION['user_id'])): ?>
                    <div class="dropdown">
                        <button class="btn btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user"></i> <?php echo $_SESSION['nama_lengkap']; ?>
                        </button>
                        <ul class="dropdown-menu">
                            <?php if($_SESSION['role'] == 'admin'): ?>
                                <li><a class="dropdown-item" href="admin/dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard Admin</a></li>
                            <?php elseif($_SESSION['role'] == 'penjual'): ?>
                                <li><a class="dropdown-item" href="seller/dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard Penjual</a></li>
                                <li><a class="dropdown-item" href="seller/add_car.php"><i class="fas fa-plus"></i> Jual Mobil</a></li>
                            <?php else: ?>
                                <li><a class="dropdown-item" href="buyer/dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard Pembeli</a></li>
                                <li><a class="dropdown-item" href="buyer/favorites.php"><i class="fas fa-heart"></i> Favorit Saya</a></li>
                            <?php endif; ?>
                            <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user-edit"></i> Profil</a></li>
                            <li><a class="dropdown-item" href="messages.php"><i class="fas fa-envelope"></i> Pesan</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt"></i> Keluar</a></li>
                        </ul>
                    </div>
                <?php else: ?>
                    <a href="login.php" class="btn btn-outline-primary me-2">
                        <i class="fas fa-sign-in-alt"></i> Masuk
                    </a>
                    <a href="register.php" class="btn btn-primary">
                        <i class="fas fa-user-plus"></i> Daftar
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>