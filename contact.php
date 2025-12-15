<?php
require_once 'config.php';
require_once 'includes/notification_manager.php';

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nama = sanitize($_POST['nama']);
    $email = sanitize($_POST['email']);
    $subjek = sanitize($_POST['subjek']);
    $pesan = sanitize($_POST['pesan']);
    
    // Simpan ke database atau kirim email
    $sql = "INSERT INTO contact_messages (nama, email, subjek, pesan) VALUES (?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    
    if ($stmt->execute([$nama, $email, $subjek, $pesan])) {
        $success = "Pesan berhasil dikirim! Kami akan merespons dalam 1x24 jam.";
        
        // Kirim notifikasi ke admin
        $notificationManager->createNotification(1, 'Pesan Kontak Baru', 
            "Pesan baru dari $nama: $subjek");
    } else {
        $error = "Terjadi kesalahan saat mengirim pesan. Silakan coba lagi.";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kontak - Automarket</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #2c3e50;
            --secondary: #3498db;
            --accent: #e74c3c;
            --light: #ecf0f1;
            --dark: #2c3e50;
            --gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --gradient-secondary: linear-gradient(135deg, #3498db 0%, #2c3e50 100%);
        }

        * {
            font-family: 'Poppins', sans-serif;
        }

        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
        }

        .contact-hero {
            background: var(--gradient), url('https://images.unsplash.com/photo-1560518883-ce09059eeffa?ixlib=rb-4.0.3&auto=format&fit=crop&w=1950&q=80');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            color: white;
            padding: 100px 0;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .contact-hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.3);
            z-index: 1;
        }

        .contact-hero .container {
            position: relative;
            z-index: 2;
        }

        .contact-hero h1 {
            font-size: 3.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }

        .contact-hero .lead {
            font-size: 1.3rem;
            font-weight: 300;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.3);
        }

        .contact-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            border: none;
            overflow: hidden;
            transition: all 0.3s ease;
            margin-bottom: 30px;
        }

        .contact-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
        }

        .contact-card .card-body {
            padding: 40px;
        }

        .contact-info-card {
            background: var(--gradient-secondary);
            color: white;
            border-radius: 20px;
            padding: 40px 30px;
            text-align: center;
            height: 100%;
            transition: all 0.3s ease;
            border: none;
            box-shadow: 0 10px 30px rgba(52, 152, 219, 0.3);
        }

        .contact-info-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(52, 152, 219, 0.4);
        }

        .contact-icon {
            width: 80px;
            height: 80px;
            background: rgba(255,255,255,0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 25px;
            color: white;
            font-size: 2rem;
            backdrop-filter: blur(10px);
            border: 2px solid rgba(255,255,255,0.3);
        }

        .form-control {
            border-radius: 12px;
            border: 2px solid #e9ecef;
            padding: 15px 20px;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: var(--secondary);
            box-shadow: 0 0 0 0.2rem rgba(52, 152, 219, 0.25);
            transform: translateY(-2px);
        }

        .form-label {
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 10px;
        }

        .btn-primary {
            background: var(--gradient);
            border: none;
            border-radius: 12px;
            padding: 15px 40px;
            font-weight: 600;
            font-size: 1.1rem;
            transition: all 0.3s ease;
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.6);
        }

        .section-title {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 1rem;
            position: relative;
        }

        .section-title::after {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 0;
            width: 60px;
            height: 4px;
            background: var(--gradient);
            border-radius: 2px;
        }

        .map-container {
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            border: none;
        }

        .company-info {
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            margin-top: -50px;
            position: relative;
            z-index: 3;
        }

        .floating-element {
            animation: float 6s ease-in-out infinite;
        }

        @keyframes float {
            0% { transform: translateY(0px); }
            50% { transform: translateY(-20px); }
            100% { transform: translateY(0px); }
        }

        .feature-badge {
            background: var(--gradient);
            color: white;
            padding: 8px 20px;
            border-radius: 25px;
            font-size: 0.9rem;
            margin: 5px;
            display: inline-block;
        }

        .social-links {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-top: 20px;
        }

        .social-link {
            width: 50px;
            height: 50px;
            background: var(--gradient);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            text-decoration: none;
            transition: all 0.3s ease;
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        .social-link:hover {
            transform: translateY(-3px) scale(1.1);
            color: white;
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.6);
        }

        .stats-container {
            background: var(--gradient);
            color: white;
            border-radius: 20px;
            padding: 40px;
            text-align: center;
            margin: 50px 0;
        }

        .stat-number {
            font-size: 3rem;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .stat-label {
            font-size: 1.1rem;
            opacity: 0.9;
        }

        .feature-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 40px;
        }

        .feature-item {
            text-align: center;
            padding: 30px 20px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
        }

        .feature-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }

        .feature-item i {
            font-size: 2.5rem;
            background: var(--gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 15px;
        }

        .alert {
            border-radius: 15px;
            border: none;
            padding: 20px;
            font-weight: 500;
        }

        .animate-on-scroll {
            opacity: 0;
            transform: translateY(30px);
            transition: all 0.6s ease;
        }

        .animate-on-scroll.animated {
            opacity: 1;
            transform: translateY(0);
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <!-- Hero Section -->
    <section class="contact-hero">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <h1 class="display-3 fw-bold mb-4">Hubungi Kami</h1>
                    <p class="lead mb-4">Kami siap membantu Anda 12/6 dengan layanan terbaik</p>
                    <div class="d-flex flex-wrap justify-content-center gap-3">
                        <span class="feature-badge"><i class="fas fa-clock me-2"></i>Respon Cepat</span>
                        <span class="feature-badge"><i class="fas fa-headset me-2"></i>Support 12/6</span>
                        <span class="feature-badge"><i class="fas fa-shield-alt me-2"></i>Aman & Terpercaya</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Stats Section -->
    <div class="container">
        <div class="stats-container animate-on-scroll">
            <div class="row">
                <div class="col-md-3">
                    <div class="stat-number">50+</div>
                    <div class="stat-label">Pelanggan Puas</div>
                </div>
                <div class="col-md-3">
                    <div class="stat-number">12/6</div>
                    <div class="stat-label">Layanan Support</div>
                </div>
                <div class="col-md-3">
                    <div class="stat-number">15+</div>
                    <div class="stat-label">Kota Tersedia</div>
                </div>
                <div class="col-md-3">
                    <div class="stat-number">98%</div>
                    <div class="stat-label">Kepuasan Pelanggan</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Contact Form & Info Section -->
    <section class="py-5">
        <div class="container">
            <div class="row">
                <!-- Contact Form -->
                <div class="col-lg-8 mb-5">
                    <div class="contact-card animate-on-scroll">
                        <div class="card-body">
                            <h2 class="section-title">Kirim Pesan</h2>
                            <p class="text-muted mb-4">Isi form berikut dan tim kami akan segera merespons</p>
                            
                            <?php if($success): ?>
                                <div class="alert alert-success animate-on-scroll"><?php echo $success; ?></div>
                            <?php endif; ?>
                            
                            <?php if($error): ?>
                                <div class="alert alert-danger animate-on-scroll"><?php echo $error; ?></div>
                            <?php endif; ?>
                            
                            <form method="POST" action="">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-4">
                                            <label class="form-label">Nama Lengkap *</label>
                                            <input type="text" name="nama" class="form-control" required 
                                                   value="<?php echo isset($_POST['nama']) ? $_POST['nama'] : ''; ?>"
                                                   placeholder="Masukkan nama lengkap Anda">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-4">
                                            <label class="form-label">Email *</label>
                                            <input type="email" name="email" class="form-control" required 
                                                   value="<?php echo isset($_POST['email']) ? $_POST['email'] : ''; ?>"
                                                   placeholder="email@contoh.com">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-4">
                                    <label class="form-label">Subjek *</label>
                                    <input type="text" name="subjek" class="form-control" required 
                                           value="<?php echo isset($_POST['subjek']) ? $_POST['subjek'] : ''; ?>"
                                           placeholder="Subjek pesan Anda">
                                </div>
                                
                                <div class="mb-4">
                                    <label class="form-label">Pesan *</label>
                                    <textarea name="pesan" class="form-control" rows="6" required 
                                              placeholder="Tulis pesan Anda di sini..."><?php echo isset($_POST['pesan']) ? $_POST['pesan'] : ''; ?></textarea>
                                </div>
                                
                                <div class="text-center">
                                    <button type="submit" class="btn btn-primary px-5 py-3">
                                        <i class="fas fa-paper-plane me-2"></i> Kirim Pesan
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Contact Information -->
                <div class="col-lg-4">
                    <div class="contact-info-card floating-element animate-on-scroll">
                        <div class="contact-icon">
                            <i class="fas fa-map-marker-alt"></i>
                        </div>
                        <h4 class="mb-3">Alamat Kantor</h4>
                        <p class="mb-4">Jl. Sudirman No. 123<br>Jakarta Pusat 10220<br>Indonesia</p>
                        
                        <div class="contact-icon mt-4">
                            <i class="fas fa-phone"></i>
                        </div>
                        <h4 class="mb-3">Telepon</h4>
                        <p class="mb-4">+62 21 1234 5678<br>+62 812 3456 7890</p>
                        
                        <div class="contact-icon mt-4">
                            <i class="fas fa-envelope"></i>
                        </div>
                        <h4 class="mb-3">Email</h4>
                        <p class="mb-4">info@automarket.com<br>support@automarket.com</p>
                        
                        <div class="social-links">
                            <a href="#" class="social-link"><i class="fab fa-whatsapp"></i></a>
                            <a href="#" class="social-link"><i class="fab fa-instagram"></i></a>
                            <a href="#" class="social-link"><i class="fab fa-facebook-f"></i></a>
                            <a href="#" class="social-link"><i class="fab fa-twitter"></i></a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="py-5 bg-light">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="section-title d-inline-block">Mengapa Memilih Kami?</h2>
            </div>
            <div class="feature-grid">
                <div class="feature-item animate-on-scroll">
                    <i class="fas fa-clock"></i>
                    <h5>Respon Cepat</h5>
                    <p class="text-muted">Tim kami merespons dalam waktu 1 jam selama jam operasional</p>
                </div>
                <div class="feature-item animate-on-scroll">
                    <i class="fas fa-shield-alt"></i>
                    <h5>Aman & Terpercaya</h5>
                    <p class="text-muted">Transaksi aman dengan sistem terverifikasi</p>
                </div>
                <div class="feature-item animate-on-scroll">
                    <i class="fas fa-tags"></i>
                    <h5>Harga Terbaik</h5>
                    <p class="text-muted">Garansi harga terbaik dengan kualitas premium</p>
                </div>
                <div class="feature-item animate-on-scroll">
                    <i class="fas fa-headset"></i>
                    <h5>Support 24/7</h5>
                    <p class="text-muted">Layanan pelanggan siap membantu kapan saja</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Map & Company Info Section -->
    <section class="py-5">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6 mb-5 mb-lg-0">
                    <div class="map-container animate-on-scroll">
                        <div class="ratio ratio-16x9">
                            <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3966.521260322283!2d106.81956135068644!3d-6.194741395493371!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x2e69f5390917b759%3A0x6b45e67356080477!2sMonumen%20Nasional!5e0!3m2!1sid!2sid!4v1632973464816!5m2!1sid!2sid" 
                                    width="100%" height="450" style="border:0;" allowfullscreen="" loading="lazy"></iframe>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="company-info animate-on-scroll">
                        <h2 class="section-title mb-4">Lokasi Kami</h2>
                        <p class="lead mb-4">Kunjungi kantor kami untuk konsultasi langsung</p>
                        
                        <div class="mb-4">
                            <h5><i class="fas fa-building me-2 text-primary"></i>PT Kulkul Teknologi Internasional</h5>
                            <p class="mb-2">The City Tower, Jl. M.H. Thamrin No. 81</p>
                            <p class="mb-2">Level 12-14, Dukuh Atas, Menteng</p>
                            <p class="mb-2">Kec. Menteng, Kota Jakarta Pusat</p>
                            <p class="mb-0">Daerah Khusus Ibukota Jakarta 10310</p>
                        </div>
                        
                        <div class="row mt-4">
                            <div class="col-md-6">
                                <div class="d-flex align-items-center mb-3">
                                    <i class="fas fa-clock text-primary me-3 fs-5"></i>
                                    <div>
                                        <strong>Jam Operasional</strong>
                                        <p class="mb-0">Senin - Sabtu<br>12 Jam / 6 Hari</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="d-flex align-items-center mb-3">
                                    <i class="fas fa-parking text-primary me-3 fs-5"></i>
                                    <div>
                                        <strong>Parkir</strong>
                                        <p class="mb-0">Tersedia<br>Area Parkir Khusus</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <?php include 'includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Animation on scroll
        function animateOnScroll() {
            const elements = document.querySelectorAll('.animate-on-scroll');
            elements.forEach(element => {
                const elementTop = element.getBoundingClientRect().top;
                const elementVisible = 150;
                
                if (elementTop < window.innerHeight - elementVisible) {
                    element.classList.add('animated');
                }
            });
        }

        // Initial check
        animateOnScroll();

        // Check on scroll
        window.addEventListener('scroll', animateOnScroll);

        // Add loading animation to form
        document.querySelector('form')?.addEventListener('submit', function(e) {
            const btn = this.querySelector('button[type="submit"]');
            const originalText = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Mengirim...';
            btn.disabled = true;
            
            setTimeout(() => {
                btn.innerHTML = originalText;
                btn.disabled = false;
            }, 3000);
        });
    </script>
</body>
</html>