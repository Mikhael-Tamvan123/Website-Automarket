<?php
require_once __DIR__ . '/../includes/auth.php';

// Cek login dan role
if (!$auth->isLoggedIn() || !$auth->checkRole('penjual')) {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$success = '';
$error = '';

// Get car ID from URL
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: my_cars.php");
    exit();
}

$car_id = $_GET['id'];

// Check if car belongs to current user
$sql_check = "SELECT * FROM mobil WHERE id = ? AND penjual_id = ?";
$stmt_check = $pdo->prepare($sql_check);
$stmt_check->execute([$car_id, $user_id]);
$car = $stmt_check->fetch(PDO::FETCH_ASSOC);

if (!$car) {
    header("Location: my_cars.php");
    exit();
}

// Process form submission
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
    $status = sanitize($_POST['status']);
    
    // Handle gambar upload - PERBAIKAN: gunakan field foto_mobil
    $foto_mobil = isset($car['foto_mobil']) ? $car['foto_mobil'] : ''; // Keep existing image
    
    if (isset($_FILES['foto_mobil']) && $_FILES['foto_mobil']['error'] == 0) {
        // PERBAIKAN PATH - folder upload
        $uploadDir = '../uploads/cars/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        // Validate file type
        $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
        $fileType = $_FILES['foto_mobil']['type'];
        
        if (in_array($fileType, $allowedTypes)) {
            // Delete old image if exists
            if (!empty($car['foto_mobil']) && file_exists('../uploads/cars/' . $car['foto_mobil'])) {
                unlink('../uploads/cars/' . $car['foto_mobil']);
            }
            
            $fileExtension = pathinfo($_FILES['foto_mobil']['name'], PATHINFO_EXTENSION);
            $fileName = uniqid() . '_' . $car_id . '.' . $fileExtension;
            
            if (move_uploaded_file($_FILES['foto_mobil']['tmp_name'], $uploadDir . $fileName)) {
                $foto_mobil = $fileName; // Simpan hanya nama file
            } else {
                $error = "Gagal mengupload gambar.";
            }
        } else {
            $error = "Format file tidak didukung. Gunakan JPG, PNG, GIF, atau WebP.";
        }
    }
    
    // Handle delete image
    if (isset($_POST['delete_image']) && $_POST['delete_image'] == '1') {
        if (!empty($car['foto_mobil']) && file_exists('../uploads/cars/' . $car['foto_mobil'])) {
            unlink('../uploads/cars/' . $car['foto_mobil']);
        }
        $foto_mobil = '';
    }
    
    if (!$error) {
        try {
            // Update car data dengan foto_mobil
            $sql = "UPDATE mobil SET 
                    merk = ?, model = ?, tahun = ?, warna = ?, plat_mobil = ?, 
                    no_mesin = ?, rangka_mesin = ?, slinder = ?, harga = ?, 
                    deskripsi = ?, kilometer = ?, bahan_bakar = ?, transmisi = ?, 
                    status = ?, foto_mobil = ?
                    WHERE id = ? AND penjual_id = ?";
            
            $params = [
                $merk, $model, $tahun, $warna, $plat_mobil, 
                $no_mesin, $rangka_mesin, $slinder, $harga, $deskripsi, 
                $kilometer, $bahan_bakar, $transmisi, $status, $foto_mobil,
                $car_id, $user_id
            ];
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            $success = "Data mobil berhasil diperbarui!";
            
            // Refresh car data
            $stmt_check->execute([$car_id, $user_id]);
            $car = $stmt_check->fetch(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            $error = "Terjadi kesalahan: " . $e->getMessage();
        }
    }
}

// Count total unread messages untuk badge
$unread_sql = "SELECT COUNT(*) as total_unread FROM pesan WHERE penerima_id = ? AND dibaca = 0";
$unread_stmt = $pdo->prepare($unread_sql);
$unread_stmt->execute([$user_id]);
$total_unread = $unread_stmt->fetch(PDO::FETCH_ASSOC)['total_unread'];
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Mobil - Automarket</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
            overflow-x: hidden;
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
            min-height: 100vh;
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
        .current-image {
            max-width: 100%;
            max-height: 200px;
            border-radius: 8px;
            border: 1px solid #dee2e6;
        }
        .image-preview {
            padding: 15px;
            border: 1px dashed #dee2e6;
            border-radius: 8px;
            background: #f8f9fa;
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
            .back-to-website {
                position: relative;
                bottom: auto;
                margin-top: 20px;
                margin-bottom: 20px;
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
            <a class="nav-link active" href="my_cars.php">
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

    <!-- Main Content -->
    <div class="main-content">
        <div class="p-4">
            <h2 class="mb-4">Edit Mobil</h2>
            
            <?php if($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <?php if($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>

            <div class="form-container">
                <form method="POST" action="" enctype="multipart/form-data">
                    <!-- Gambar Mobil -->
                    <h5 class="mb-3 text-primary">Gambar Mobil</h5>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Gambar Saat Ini</label>
                                <div class="image-preview text-center">
                                    <?php if(!empty($car['foto_mobil']) && file_exists('../uploads/cars/' . $car['foto_mobil'])): ?>
                                        <img src="../uploads/cars/<?php echo htmlspecialchars($car['foto_mobil']); ?>" 
                                             alt="Gambar Mobil" class="current-image">
                                        <div class="mt-2">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" 
                                                       name="delete_image" value="1" id="deleteImage">
                                                <label class="form-check-label text-danger" for="deleteImage">
                                                    Hapus gambar ini
                                                </label>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <div class="text-muted py-4">
                                            <i class="fas fa-car fa-2x mb-2"></i>
                                            <p>Belum ada gambar</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Upload Gambar Baru</label>
                                <input type="file" name="foto_mobil" class="form-control" accept="image/*">
                                <div class="form-text">
                                    Format: JPG, PNG, GIF, WebP. Maksimal 5MB.
                                    <?php if(!empty($car['foto_mobil'])): ?>
                                        <br><span class="text-warning">Upload gambar baru akan mengganti gambar saat ini.</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Informasi Dasar -->
                    <h5 class="mb-3 text-primary mt-4">Informasi Dasar Mobil</h5>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Merk Mobil *</label>
                                <input type="text" name="merk" class="form-control" required 
                                       value="<?php echo htmlspecialchars($car['merk']); ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Model *</label>
                                <input type="text" name="model" class="form-control" required 
                                       value="<?php echo htmlspecialchars($car['model']); ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Tahun *</label>
                                <input type="number" name="tahun" class="form-control" min="1990" max="<?php echo date('Y'); ?>" required 
                                       value="<?php echo htmlspecialchars($car['tahun']); ?>">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Warna *</label>
                                <input type="text" name="warna" class="form-control" required 
                                       value="<?php echo htmlspecialchars($car['warna']); ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Plat Nomor *</label>
                                <input type="text" name="plat_mobil" class="form-control" required 
                                       value="<?php echo htmlspecialchars($car['plat_mobil']); ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Harga (Rp) *</label>
                                <input type="number" name="harga" class="form-control" required 
                                       value="<?php echo htmlspecialchars($car['harga']); ?>">
                            </div>
                        </div>
                    </div>

                    <!-- Informasi Teknis -->
                    <h5 class="mb-3 text-primary mt-4">Informasi Teknis</h5>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Nomor Mesin *</label>
                                <input type="text" name="no_mesin" class="form-control" required 
                                       value="<?php echo htmlspecialchars($car['no_mesin']); ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Rangka Mesin *</label>
                                <input type="text" name="rangka_mesin" class="form-control" required 
                                       value="<?php echo htmlspecialchars($car['rangka_mesin']); ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Jumlah Slinder *</label>
                                <input type="number" name="slinder" class="form-control" min="1" max="16" required 
                                       value="<?php echo htmlspecialchars($car['slinder']); ?>">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Kilometer</label>
                                <input type="number" name="kilometer" class="form-control" 
                                       value="<?php echo htmlspecialchars($car['kilometer']); ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Bahan Bakar</label>
                                <select name="bahan_bakar" class="form-control">
                                    <option value="bensin" <?php echo $car['bahan_bakar'] == 'bensin' ? 'selected' : ''; ?>>Bensin</option>
                                    <option value="solar" <?php echo $car['bahan_bakar'] == 'solar' ? 'selected' : ''; ?>>Solar</option>
                                    <option value="listrik" <?php echo $car['bahan_bakar'] == 'listrik' ? 'selected' : ''; ?>>Listrik</option>
                                    <option value="hybrid" <?php echo $car['bahan_bakar'] == 'hybrid' ? 'selected' : ''; ?>>Hybrid</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Transmisi</label>
                                <select name="transmisi" class="form-control">
                                    <option value="manual" <?php echo $car['transmisi'] == 'manual' ? 'selected' : ''; ?>>Manual</option>
                                    <option value="matic" <?php echo $car['transmisi'] == 'matic' ? 'selected' : ''; ?>>Matic</option>
                                    <option value="semi_automatic" <?php echo $car['transmisi'] == 'semi_automatic' ? 'selected' : ''; ?>>Semi Automatic</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Status -->
                    <h5 class="mb-3 text-primary mt-4">Status Mobil</h5>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-control">
                                    <option value="tersedia" <?php echo $car['status'] == 'tersedia' ? 'selected' : ''; ?>>Tersedia</option>
                                    <option value="dipesan" <?php echo $car['status'] == 'dipesan' ? 'selected' : ''; ?>>Dipesan</option>
                                    <option value="terjual" <?php echo $car['status'] == 'terjual' ? 'selected' : ''; ?>>Terjual</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Deskripsi -->
                    <h5 class="mb-3 text-primary mt-4">Deskripsi</h5>
                    <div class="mb-3">
                        <label class="form-label">Deskripsi Mobil *</label>
                        <textarea name="deskripsi" class="form-control" rows="4" required 
                                  placeholder="Deskripsikan kondisi mobil, fitur, riwayat servis, dll..."><?php echo htmlspecialchars($car['deskripsi']); ?></textarea>
                    </div>

                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <a href="my_cars.php" class="btn btn-secondary me-md-2">Kembali</a>
                        <button type="submit" class="btn btn-warning px-4">
                            <i class="fas fa-save me-2"></i> Update Mobil
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>