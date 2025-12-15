<?php
session_start();

// Cek session
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Koneksi database
$host = 'localhost';
$dbname = 'automarket';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed.");
}

$user_id = $_SESSION['user_id'];
$user = [];
$user_role = 'pembeli';

// Ambil data user
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        $user_role = $user['role'] ?? 'pembeli';
    }
} catch (PDOException $e) {
    // Silent error
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama_lengkap = $_POST['nama_lengkap'] ?? '';
    $email = $_POST['email'] ?? '';
    $no_telepon = $_POST['no_telepon'] ?? '';
    
    try {
        // Update query dengan kolom yang sesuai
        $update_sql = "UPDATE users SET nama_lengkap = ?, email = ?, no_telepon = ? WHERE id = ?";
        $update_stmt = $pdo->prepare($update_sql);
        $result = $update_stmt->execute([$nama_lengkap, $email, $no_telepon, $user_id]);
        
        if ($result && $update_stmt->rowCount() > 0) {
            $_SESSION['success'] = "Profil berhasil diperbarui!";
            header("Location: profile.php");
            exit();
        } else {
            $error = "Tidak ada perubahan data.";
        }
        
    } catch (PDOException $e) {
        $error = "Gagal update profil. Error: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - Automarket</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: Arial, sans-serif;
            background: #f8f9fa;
            line-height: 1.6;
            padding: 20px;
        }
        
        .container {
            max-width: 600px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        h1 {
            text-align: center;
            color: #333;
            margin-bottom: 30px;
        }
        
        .user-role {
            text-align: center;
            background: #007bff;
            color: white;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
            text-transform: capitalize;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #333;
        }
        
        input, textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
        }
        
        input:focus, textarea:focus {
            outline: none;
            border-color: #007bff;
        }
        
        input:disabled {
            background: #f8f9fa;
            color: #666;
        }
        
        .btn {
            background: #007bff;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            width: 100%;
        }
        
        .btn:hover {
            background: #0056b3;
        }
        
        .alert {
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .profile-pic {
            text-align: center;
            margin-bottom: 20px;
        }
        
        .profile-pic img {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #007bff;
        }
        
        .upload-btn {
            display: inline-block;
            margin-top: 10px;
            padding: 8px 15px;
            background: #28a745;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
        
        .file-input {
            display: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Profil Saya</h1>
        
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <?= $_SESSION['success'] ?>
                <?php unset($_SESSION['success']); ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-error">
                <?= $error ?>
            </div>
        <?php endif; ?>
        
        <div class="user-role">
            <?= htmlspecialchars(ucfirst($user_role)) ?>
        </div>
        
        <div class="profile-pic">
            <div style="width: 120px; height: 120px; border-radius: 50%; background: #e9eaec; display: flex; align-items: center; justify-content: center; margin: 0 auto; border: 3px solid #007bff;">
                <span style="color: #999; font-size: 14px;">No Photo</span>
            </div>
        </div>
        
        <form method="POST">
            <div class="form-group">
                <label>Username</label>
                <input type="text" value="<?= htmlspecialchars($user['username'] ?? '') ?>" disabled>
                <small style="color: #666; font-size: 12px;">Username tidak dapat diubah</small>
            </div>
            
            <div class="form-group">
                <label>Nama Lengkap *</label>
                <input type="text" name="nama_lengkap" value="<?= htmlspecialchars($user['nama_lengkap'] ?? '') ?>" required>
            </div>
            
            <div class="form-group">
                <label>Email *</label>
                <input type="email" name="email" value="<?= htmlspecialchars($user['email'] ?? '') ?>" required>
            </div>
            
            <div class="form-group">
                <label>Nomor Telepon</label>
                <input type="tel" name="no_telepon" value="<?= htmlspecialchars($user['no_telepon'] ?? '') ?>">
            </div>
            
            <button type="submit" class="btn">Simpan Perubahan</button>
        </form>
    </div>

    <script>
        // Simple form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const email = document.querySelector('input[name="email"]').value;
            const nama_lengkap = document.querySelector('input[name="nama_lengkap"]').value;
            
            if (!email.includes('@')) {
                alert('Email tidak valid!');
                e.preventDefault();
                return;
            }
            
            if (nama_lengkap.trim() === '') {
                alert('Nama lengkap harus diisi!');
                e.preventDefault();
                return;
            }
        });
    </script>
</body>
</html>