<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/subscription_manager.php';

// Cek login dan role
if (!$auth->isLoggedIn() || !$auth->checkRole('penjual')) {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Cek subscription limit
$subscriptionManager = new SubscriptionManager($pdo);
$upload_check = $subscriptionManager->canUploadCar($user_id);

if (!$upload_check['can_upload'] && empty($_POST)) {
    $_SESSION['error'] = "Anda telah mencapai batas maksimum upload mobil (" . $upload_check['max_allowed'] . " mobil). 
                         <a href='subscription.php' class='alert-link'>Upgrade subscription</a> untuk upload lebih banyak.";
    header("Location: my_cars.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $merk = sanitize($_POST['merk']);
    $model = sanitize($_POST['model']);
    $tahun = sanitize($_POST['tahun']);
    $warna = sanitize($_POST['warna']);
    $plat_mobil = sanitize($_POST['plat_mobil']);
    $no_mesin = sanitize($_POST['no_mesin']);
    $rangka_mesin = sanitize($_POST['rangka_mesin']);
    $slinder = sanitize($_POST['slinder']);
    $harga = sanitize($_POST['harga']);
    $deskripsi = sanitize($_POST['deskripsi']);
    $kilometer = sanitize($_POST['kilometer']);
    $bahan_bakar = sanitize($_POST['bahan_bakar']);
    $transmisi = sanitize($_POST['transmisi']);
    
    // Handle gambar upload - GUNAKAN foto_mobil bukan gambar
    $foto_mobil = '';
    if (isset($_FILES['gambar']) && $_FILES['gambar']['error'] == 0) {
        $uploadDir = '../uploads/cars/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        // Validasi tipe file
        $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
        $fileType = $_FILES['gambar']['type'];
        
        if (in_array($fileType, $allowedTypes)) {
            $fileExtension = pathinfo($_FILES['gambar']['name'], PATHINFO_EXTENSION);
            $fileName = uniqid() . '.' . $fileExtension;
            $uploadFile = $uploadDir . $fileName;
            
            // Validasi ukuran file (maks 5MB)
            if ($_FILES['gambar']['size'] <= 5 * 1024 * 1024) {
                if (move_uploaded_file($_FILES['gambar']['tmp_name'], $uploadFile)) {
                    // Simpan hanya nama file
                    $foto_mobil = $fileName;
                } else {
                    $error = "Gagal mengupload gambar.";
                }
            } else {
                $error = "Ukuran gambar terlalu besar. Maksimal 5MB.";
            }
        } else {
            $error = "Format file tidak didukung. Gunakan JPG, PNG, atau GIF.";
        }
    }
    
    if (!$error) {
        try {
            // PERBAIKAN: Gunakan foto_mobil bukan gambar
            if ($foto_mobil) {
                $sql = "INSERT INTO mobil (penjual_id, merk, model, tahun, warna, plat_mobil, no_mesin, rangka_mesin, slinder, harga, deskripsi, kilometer, bahan_bakar, transmisi, foto_mobil) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $user_id, $merk, $model, $tahun, $warna, $plat_mobil, 
                    $no_mesin, $rangka_mesin, $slinder, $harga, $deskripsi, 
                    $kilometer, $bahan_bakar, $transmisi, $foto_mobil
                ]);
            } else {
                $sql = "INSERT INTO mobil (penjual_id, merk, model, tahun, warna, plat_mobil, no_mesin, rangka_mesin, slinder, harga, deskripsi, kilometer, bahan_bakar, transmisi) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $user_id, $merk, $model, $tahun, $warna, $plat_mobil, 
                    $no_mesin, $rangka_mesin, $slinder, $harga, $deskripsi, 
                    $kilometer, $bahan_bakar, $transmisi
                ]);
            }
            
            $success = "Mobil berhasil ditambahkan!";
            $_POST = array(); // Reset form
            
        } catch (PDOException $e) {
            $error = "Terjadi kesalahan: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Mobil - Automarket</title>
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
        .form-container {
            background: white;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
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
            <a class="nav-link" href="dashboard.php">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>
            <a class="nav-link active" href="add_car.php">
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
                <?php 
                // Count total unread messages
                $unread_sql = "SELECT COUNT(*) as total_unread FROM pesan WHERE penerima_id = ? AND dibaca = 0";
                $unread_stmt = $pdo->prepare($unread_sql);
                $unread_stmt->execute([$user_id]);
                $total_unread = $unread_stmt->fetch(PDO::FETCH_ASSOC)['total_unread'];
                ?>
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
            <h2 class="mb-4">Jual Mobil Baru</h2>

            <!-- Tambahkan Subscription Status seperti di dashboard -->
            <div class="alert alert-info mb-4">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <i class="fas fa-chart-line me-2"></i>
                        <strong>Paket:</strong> 
                        <span class="badge bg-<?php echo ($upload_check['plan_name'] ?? 'Free') == 'Free' ? 'secondary' : 'success'; ?>">
                            <?php echo $upload_check['plan_name'] ?? 'Free'; ?> Plan
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
                    <?php if (!$upload_check['can_upload']): ?>
                        <a href="subscription.php" class="btn btn-warning btn-sm">
                            <i class="fas fa-crown me-1"></i> Upgrade Plan
                        </a>
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
            
            <?php if($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <?php if($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>

            <div class="form-container">
                <form method="POST" action="" enctype="multipart/form-data">
                    <!-- Informasi Dasar -->
                    <h5 class="mb-3 text-primary">Informasi Dasar Mobil</h5>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Merk Mobil *</label>
                                <input type="text" name="merk" class="form-control" required value="<?php echo isset($_POST['merk']) ? $_POST['merk'] : ''; ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Model *</label>
                                <input type="text" name="model" class="form-control" required value="<?php echo isset($_POST['model']) ? $_POST['model'] : ''; ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Tahun *</label>
                                <input type="number" name="tahun" class="form-control" min="1990" max="<?php echo date('Y'); ?>" required value="<?php echo isset($_POST['tahun']) ? $_POST['tahun'] : ''; ?>">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Warna *</label>
                                <input type="text" name="warna" class="form-control" required value="<?php echo isset($_POST['warna']) ? $_POST['warna'] : ''; ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Plat Nomor *</label>
                                <input type="text" name="plat_mobil" class="form-control" required value="<?php echo isset($_POST['plat_mobil']) ? $_POST['plat_mobil'] : ''; ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Harga (Rp) *</label>
                                <input type="number" name="harga" class="form-control" required value="<?php echo isset($_POST['harga']) ? $_POST['harga'] : ''; ?>">
                            </div>
                        </div>
                    </div>

                    <!-- Informasi Teknis -->
                    <h5 class="mb-3 text-primary mt-4">Informasi Teknis</h5>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Nomor Mesin *</label>
                                <input type="text" name="no_mesin" class="form-control" required value="<?php echo isset($_POST['no_mesin']) ? $_POST['no_mesin'] : ''; ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Rangka Mesin *</label>
                                <input type="text" name="rangka_mesin" class="form-control" required value="<?php echo isset($_POST['rangka_mesin']) ? $_POST['rangka_mesin'] : ''; ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Jumlah Slinder *</label>
                                <input type="number" name="slinder" class="form-control" min="1" max="16" required value="<?php echo isset($_POST['slinder']) ? $_POST['slinder'] : ''; ?>">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Kilometer</label>
                                <input type="number" name="kilometer" class="form-control" value="<?php echo isset($_POST['kilometer']) ? $_POST['kilometer'] : '0'; ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Bahan Bakar</label>
                                <select name="bahan_bakar" class="form-control">
                                    <option value="bensin">Bensin</option>
                                    <option value="solar">Solar</option>
                                    <option value="listrik">Listrik</option>
                                    <option value="hybrid">Hybrid</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Transmisi</label>
                                <select name="transmisi" class="form-control">
                                    <option value="manual">Manual</option>
                                    <option value="matic">Matic</option>
                                    <option value="semi_automatic">Semi Automatic</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Gambar dan Deskripsi -->
                    <h5 class="mb-3 text-primary mt-4">Gambar & Deskripsi</h5>
                    <div class="mb-3">
                        <label class="form-label">Gambar Mobil</label>
                        <input type="file" name="gambar" class="form-control" accept="image/*">
                        <div class="form-text">
                            Format: JPG, PNG, GIF. Maksimal 5MB.
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Deskripsi Mobil *</label>
                        <textarea name="deskripsi" class="form-control" rows="4" required placeholder="Deskripsikan kondisi mobil, fitur, riwayat servis, dll..."><?php echo isset($_POST['deskripsi']) ? $_POST['deskripsi'] : ''; ?></textarea>
                    </div>

                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <a href="dashboard.php" class="btn btn-secondary me-md-2">Kembali</a>
                        <button type="submit" class="btn btn-success px-4">
                            <i class="fas fa-plus me-2"></i> Tambah Mobil
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>