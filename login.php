<?php
require_once 'config.php';
require_once 'includes/auth.php';

if ($auth->isLoggedIn()) {
    header("Location: index.php");
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = sanitize($_POST['username']);
    $password = $_POST['password'];
    
    if ($auth->login($username, $password)) {
        $_SESSION['success'] = "Login berhasil! Selamat datang " . $_SESSION['nama_lengkap'];
        
        // Redirect berdasarkan role
        if ($_SESSION['role'] == 'admin') {
            header("Location: admin/dashboard.php");
        } elseif ($_SESSION['role'] == 'penjual') {
            header("Location: seller/dashboard.php");
        } else {
            header("Location: index.php");
        }
        exit();
    } else {
        $error = "Username atau password salah!";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Automarket</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .login-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .login-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .login-header {
            background: #2c3e50;
            color: white;
            padding: 30px;
            text-align: center;
        }
        .login-body {
            padding: 40px;
        }
        .btn-login {
            background: #3498db;
            border: none;
            padding: 12px;
            font-weight: 600;
        }
        .btn-login:hover {
            background: #2980b9;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-5">
                    <div class="login-card">
                        <div class="login-header">
                            <h2><i class="fas fa-car"></i> Automarket</h2>
                            <p class="mb-0">Masuk ke akun Anda</p>
                        </div>
                        
                        <div class="login-body">
                            <?php if(isset($_SESSION['success'])): ?>
                                <div class="alert alert-success">
                                    <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                                </div>
                            <?php endif; ?>
                            
                            <?php if(isset($_SESSION['error'])): ?>
                                <div class="alert alert-danger">
                                    <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                                </div>
                            <?php endif; ?>
                            
                            <?php if($error): ?>
                                <div class="alert alert-danger"><?php echo $error; ?></div>
                            <?php endif; ?>
                            
                            <form method="POST" action="">
                                <div class="mb-3">
                                    <label class="form-label">Username atau Email</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-user"></i></span>
                                        <input type="text" name="username" class="form-control" required value="<?php echo isset($_POST['username']) ? $_POST['username'] : ''; ?>">
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Password</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                        <input type="password" name="password" class="form-control" required>
                                    </div>
                                </div>
                                
                                <div class="mb-3 form-check">
                                    <input type="checkbox" class="form-check-input" id="remember">
                                    <label class="form-check-label" for="remember">Ingat saya</label>
                                </div>
                                
                                <button type="submit" class="btn btn-login w-100">Masuk</button>
                            </form>
                            
                            <div class="text-center mt-4">
                                <p>Belum punya akun? <a href="register.php">Daftar di sini</a></p>
                                <p><a href="forgot_password.php">Lupa password?</a></p>
                            </div>
                            
                            <hr>
                            
                            <div class="text-center">
                                <small class="text-muted">
                                    Dengan login, Anda menyetujui <a href="#">Syarat & Ketentuan</a> kami
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>