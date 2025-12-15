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
    $email = sanitize($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $nama_lengkap = sanitize($_POST['nama_lengkap']);
    $no_ktp = sanitize($_POST['no_ktp']);
    $no_telepon = sanitize($_POST['no_telepon']);
    $role = sanitize($_POST['role']);
    
    // Validasi
    if ($password !== $confirm_password) {
        $error = "Password tidak cocok!";
    } elseif (strlen($password) < 6) {
        $error = "Password minimal 6 karakter!";
    } else {
        // Handle foto KTP upload
        $foto_ktp = '';
        if (isset($_FILES['foto_ktp']) && $_FILES['foto_ktp']['error'] == 0) {
            $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
            $max_size = 2 * 1024 * 1024; // 2MB
            $file_type = $_FILES['foto_ktp']['type'];
            $file_size = $_FILES['foto_ktp']['size'];
            
            if (!in_array($file_type, $allowed_types)) {
                $error = "File harus berupa gambar (JPG, JPEG, PNG, GIF)!";
            } elseif ($file_size > $max_size) {
                $error = "Ukuran file maksimal 2MB!";
            } else {
                // Create upload directory if not exists
                $upload_dir = 'uploads/ktp/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                // Generate unique filename
                $file_ext = pathinfo($_FILES['foto_ktp']['name'], PATHINFO_EXTENSION);
                $file_name = 'ktp_' . time() . '_' . uniqid() . '.' . strtolower($file_ext);
                $upload_path = $upload_dir . $file_name;
                
                if (move_uploaded_file($_FILES['foto_ktp']['tmp_name'], $upload_path)) {
                    $foto_ktp = $file_name;
                } else {
                    $error = "Gagal mengupload foto KTP!";
                }
            }
        } else {
            $error = "Foto KTP wajib diunggah!";
        }
        
        if (empty($error)) {
            // Register user dengan foto KTP
            $user_id = $auth->register($username, $password, $email, $nama_lengkap, $no_ktp, $no_telepon, $role, $foto_ktp);
            
            if ($user_id) {
                $_SESSION['success'] = "Registrasi berhasil! Silakan login.";
                header("Location: login.php");
                exit();
            } else {
                $error = "Username atau email sudah digunakan!";
                // Hapus file yang sudah diupload jika gagal register
                if (!empty($foto_ktp) && file_exists($upload_dir . $foto_ktp)) {
                    unlink($upload_dir . $foto_ktp);
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar - Automarket</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .register-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px 0;
        }
        .register-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
        }
        .register-header {
            background: #2c3e50;
            color: white;
            padding: 30px;
            text-align: center;
            border-radius: 15px 15px 0 0;
        }
        .register-body {
            padding: 40px;
        }
        .ktp-upload-area {
            border: 2px dashed #dee2e6;
            border-radius: 10px;
            padding: 30px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            background: #f8f9fa;
        }
        .ktp-upload-area:hover {
            border-color: #667eea;
            background: #e9ecef;
        }
        .ktp-upload-area.active {
            border-color: #28a745;
            background: #d4edda;
        }
        .ktp-preview {
            max-width: 100%;
            max-height: 200px;
            margin-top: 15px;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            display: none;
        }
        .upload-icon {
            font-size: 3rem;
            color: #667eea;
            margin-bottom: 15px;
        }
        .file-info {
            font-size: 0.9rem;
            color: #6c757d;
            margin-top: 10px;
        }
        .required-field::after {
            content: " *";
            color: #dc3545;
        }
        .form-label {
            font-weight: 600;
            margin-bottom: 8px;
        }
        .password-strength {
            height: 5px;
            margin-top: 5px;
            border-radius: 3px;
            transition: all 0.3s ease;
        }
        .strength-weak { background-color: #dc3545; width: 25%; }
        .strength-medium { background-color: #ffc107; width: 50%; }
        .strength-strong { background-color: #28a745; width: 100%; }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-10 col-lg-8">
                    <div class="register-card">
                        <div class="register-header">
                            <h2><i class="fas fa-car"></i> Automarket</h2>
                            <p class="mb-0">Daftar akun baru - Verifikasi dengan Foto KTP</p>
                        </div>
                        
                        <div class="register-body">
                            <?php if($error): ?>
                                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                    <i class="fas fa-exclamation-triangle me-2"></i><?php echo $error; ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>
                            <?php endif; ?>
                            
                            <form method="POST" action="" enctype="multipart/form-data" id="registerForm">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label required-field">Username</label>
                                            <input type="text" name="username" class="form-control" required 
                                                   value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>"
                                                   minlength="3" maxlength="50">
                                            <div class="form-text">Minimal 3 karakter, huruf dan angka saja</div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label required-field">Email</label>
                                            <input type="email" name="email" class="form-control" required 
                                                   value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                                            <div class="form-text">Email aktif untuk verifikasi</div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label required-field">Password</label>
                                            <input type="password" name="password" id="password" class="form-control" required 
                                                   minlength="6" onkeyup="checkPasswordStrength()">
                                            <div id="password-strength" class="password-strength"></div>
                                            <div class="form-text">Minimal 6 karakter</div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label required-field">Konfirmasi Password</label>
                                            <input type="password" name="confirm_password" id="confirm_password" class="form-control" required>
                                            <div id="password-match" class="form-text"></div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label required-field">Nama Lengkap</label>
                                    <input type="text" name="nama_lengkap" class="form-control" required 
                                           value="<?php echo isset($_POST['nama_lengkap']) ? htmlspecialchars($_POST['nama_lengkap']) : ''; ?>"
                                           minlength="3" maxlength="100">
                                    <div class="form-text">Nama lengkap sesuai KTP</div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label required-field">No. KTP</label>
                                            <input type="text" name="no_ktp" class="form-control" required 
                                                   value="<?php echo isset($_POST['no_ktp']) ? htmlspecialchars($_POST['no_ktp']) : ''; ?>"
                                                   pattern="[0-9]{16}" maxlength="16" 
                                                   oninput="this.value = this.value.replace(/[^0-9]/g, '')">
                                            <div class="form-text">16 digit angka tanpa spasi</div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label required-field">No. Telepon</label>
                                            <input type="tel" name="no_telepon" class="form-control" required 
                                                   value="<?php echo isset($_POST['no_telepon']) ? htmlspecialchars($_POST['no_telepon']) : ''; ?>"
                                                   pattern="[0-9]{10,13}" 
                                                   oninput="this.value = this.value.replace(/[^0-9]/g, '')">
                                            <div class="form-text">Contoh: 081234567890</div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Foto KTP Upload Section -->
                                <div class="mb-4">
                                    <label class="form-label required-field">Foto KTP</label>
                                    <div class="ktp-upload-area" id="ktpUploadArea" onclick="document.getElementById('ktpFile').click()">
                                        <div class="upload-icon">
                                            <i class="fas fa-camera"></i>
                                        </div>
                                        <h5>Upload Foto KTP</h5>
                                        <p class="text-muted">Klik atau drag & drop file di sini</p>
                                        <p class="file-info">Format: JPG, JPEG, PNG, GIF | Maksimal: 2MB</p>
                                        
                                        <input type="file" name="foto_ktp" id="ktpFile" accept="image/*" required 
                                               style="display: none;" onchange="previewKTP(event)">
                                        <div id="ktpFileName" class="mt-2 text-success fw-bold" style="display: none;"></div>
                                        <img id="ktpPreview" class="ktp-preview" alt="Preview KTP">
                                    </div>
                                    <div class="form-text">
                                        <i class="fas fa-info-circle me-1"></i> Foto KTP wajib diunggah untuk verifikasi keamanan
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label required-field">Daftar sebagai</label>
                                    <select name="role" class="form-select" required>
                                        <option value="">Pilih peran</option>
                                        <option value="pembeli" <?php echo (isset($_POST['role']) && $_POST['role'] == 'pembeli') ? 'selected' : ''; ?>>Pembeli</option>
                                        <option value="penjual" <?php echo (isset($_POST['role']) && $_POST['role'] == 'penjual') ? 'selected' : ''; ?>>Penjual</option>
                                    </select>
                                    <div class="form-text">
                                        <i class="fas fa-user me-1"></i> Pembeli: Untuk membeli mobil<br>
                                        <i class="fas fa-store me-1"></i> Penjual: Untuk menjual mobil
                                    </div>
                                </div>
                                
                                <div class="mb-3 form-check">
                                    <input type="checkbox" class="form-check-input" id="agree" required>
                                    <label class="form-check-label" for="agree">
                                        Saya menyetujui <a href="#" data-bs-toggle="modal" data-bs-target="#termsModal">Syarat & Ketentuan</a> 
                                        dan <a href="#" data-bs-toggle="modal" data-bs-target="#privacyModal">Kebijakan Privasi</a>
                                    </label>
                                </div>
                                
                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-primary btn-lg py-3">
                                        <i class="fas fa-user-plus me-2"></i> Daftar Sekarang
                                    </button>
                                </div>
                            </form>
                            
                            <div class="text-center mt-4">
                                <p>Sudah punya akun? <a href="login.php" class="text-primary fw-bold">Login di sini</a></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Terms & Conditions Modal -->
    <div class="modal fade" id="termsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Syarat & Ketentuan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <h6>1. Umum</h6>
                    <p>Dengan mendaftar di Automarket, Anda menyetujui semua syarat dan ketentuan yang berlaku.</p>
                    
                    <h6>2. Akun Pengguna</h6>
                    <p>Anda bertanggung jawab penuh atas kerahasiaan akun dan password Anda.</p>
                    
                    <h6>3. Data KTP</h6>
                    <p>Data KTP yang diunggah hanya digunakan untuk verifikasi keamanan transaksi.</p>
                    
                    <h6>4. Larangan</h6>
                    <p>Dilarang keras menggunakan data atau identitas orang lain untuk registrasi.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Privacy Policy Modal -->
    <div class="modal fade" id="privacyModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Kebijakan Privasi</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <h6>1. Pengumpulan Data</h6>
                    <p>Kami mengumpulkan data pribadi Anda untuk keperluan verifikasi dan transaksi.</p>
                    
                    <h6>2. Penggunaan Data</h6>
                    <p>Data Anda hanya digunakan untuk keperluan platform Automarket dan tidak akan dijual kepada pihak ketiga.</p>
                    
                    <h6>3. Keamanan Data</h6>
                    <p>Kami menggunakan enkripsi untuk melindungi data sensitif seperti foto KTP.</p>
                    
                    <h6>4. Penyimpanan Data</h6>
                    <p>Data KTP disimpan secara aman dan hanya dapat diakses oleh admin yang berwenang.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Preview KTP
        function previewKTP(event) {
            const input = event.target;
            const uploadArea = document.getElementById('ktpUploadArea');
            const fileName = document.getElementById('ktpFileName');
            const preview = document.getElementById('ktpPreview');
            
            if (input.files && input.files[0]) {
                const file = input.files[0];
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                    fileName.textContent = file.name;
                    fileName.style.display = 'block';
                    uploadArea.classList.add('active');
                }
                
                reader.readAsDataURL(file);
            }
        }

        // Drag and drop functionality
        const ktpUploadArea = document.getElementById('ktpUploadArea');
        
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            ktpUploadArea.addEventListener(eventName, preventDefaults, false);
        });

        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }

        ['dragenter', 'dragover'].forEach(eventName => {
            ktpUploadArea.addEventListener(eventName, highlight, false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            ktpUploadArea.addEventListener(eventName, unhighlight, false);
        });

        function highlight(e) {
            ktpUploadArea.classList.add('active');
        }

        function unhighlight(e) {
            ktpUploadArea.classList.remove('active');
        }

        ktpUploadArea.addEventListener('drop', handleDrop, false);

        function handleDrop(e) {
            const dt = e.dataTransfer;
            const files = dt.files;
            const input = document.getElementById('ktpFile');
            
            input.files = files;
            previewKTP({target: input});
        }

        // Password strength checker
        function checkPasswordStrength() {
            const password = document.getElementById('password').value;
            const strengthBar = document.getElementById('password-strength');
            const confirmInput = document.getElementById('confirm_password');
            const matchText = document.getElementById('password-match');
            
            // Reset
            strengthBar.className = 'password-strength';
            
            if (password.length === 0) {
                return;
            }
            
            let strength = 0;
            
            // Length check
            if (password.length >= 8) strength++;
            if (password.length >= 12) strength++;
            
            // Complexity checks
            if (/[a-z]/.test(password)) strength++;
            if (/[A-Z]/.test(password)) strength++;
            if (/[0-9]/.test(password)) strength++;
            if (/[^A-Za-z0-9]/.test(password)) strength++;
            
            // Update strength bar
            if (strength <= 2) {
                strengthBar.classList.add('strength-weak');
            } else if (strength <= 4) {
                strengthBar.classList.add('strength-medium');
            } else {
                strengthBar.classList.add('strength-strong');
            }
            
            // Password match checker
            if (confirmInput.value.length > 0) {
                if (password === confirmInput.value) {
                    matchText.textContent = "✓ Password cocok";
                    matchText.style.color = "#28a745";
                } else {
                    matchText.textContent = "✗ Password tidak cocok";
                    matchText.style.color = "#dc3545";
                }
            } else {
                matchText.textContent = "";
            }
        }

        // Confirm password checker
        document.getElementById('confirm_password').addEventListener('keyup', checkPasswordStrength);

        // Form validation
        document.getElementById('registerForm').addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const ktpFile = document.getElementById('ktpFile').files[0];
            const agreeCheckbox = document.getElementById('agree');
            
            if (password !== confirmPassword) {
                e.preventDefault();
                alert('Password dan konfirmasi password tidak cocok!');
                return false;
            }
            
            if (!ktpFile) {
                e.preventDefault();
                alert('Harap unggah foto KTP!');
                return false;
            }
            
            if (!agreeCheckbox.checked) {
                e.preventDefault();
                alert('Anda harus menyetujui Syarat & Ketentuan dan Kebijakan Privasi!');
                return false;
            }
            
            // Show loading
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Memproses...';
            submitBtn.disabled = true;
            
            return true;
        });

        // Real-time KTP number validation
        document.querySelector('input[name="no_ktp"]').addEventListener('input', function(e) {
            if (this.value.length === 16) {
                this.classList.remove('is-invalid');
                this.classList.add('is-valid');
            } else if (this.value.length > 0) {
                this.classList.remove('is-valid');
                this.classList.add('is-invalid');
            } else {
                this.classList.remove('is-valid', 'is-invalid');
            }
        });

        // Real-time phone number validation
        document.querySelector('input[name="no_telepon"]').addEventListener('input', function(e) {
            if (this.value.length >= 10 && this.value.length <= 13) {
                this.classList.remove('is-invalid');
                this.classList.add('is-valid');
            } else if (this.value.length > 0) {
                this.classList.remove('is-valid');
                this.classList.add('is-invalid');
            } else {
                this.classList.remove('is-valid', 'is-invalid');
            }
        });
    </script>
</body>
</html>