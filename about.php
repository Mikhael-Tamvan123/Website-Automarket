<?php
require_once 'config.php';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tentang Kami - Automarket</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #2c3e50;
            --secondary: #3498db;
            --accent: #e74c3c;
            --success: #27ae60;
            --light: #ecf0f1;
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

        .about-hero {
            background: var(--gradient), url('https://images.unsplash.com/photo-1486496572940-2bb2341fdbdf?ixlib=rb-4.0.3&auto=format&fit=crop&w=1950&q=80');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            color: white;
            padding: 120px 0;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .about-hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.4);
            z-index: 1;
        }

        .about-hero .container {
            position: relative;
            z-index: 2;
        }

        .about-hero h1 {
            font-size: 4rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.5);
        }

        .about-hero .lead {
            font-size: 1.4rem;
            font-weight: 300;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.5);
        }

        .section-title {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--primary);
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

        .section-title.center::after {
            left: 50%;
            transform: translateX(-50%);
        }

        .feature-icon {
            width: 100px;
            height: 100px;
            background: var(--gradient);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 25px;
            color: white;
            font-size: 2.5rem;
            transition: all 0.3s ease;
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
        }

        .feature-card {
            background: white;
            border-radius: 20px;
            padding: 40px 30px;
            text-align: center;
            height: 100%;
            transition: all 0.3s ease;
            border: none;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            position: relative;
            overflow: hidden;
        }

        .feature-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: var(--gradient);
        }

        .feature-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
        }

        .feature-card:hover .feature-icon {
            transform: scale(1.1) rotate(5deg);
        }

        .stats-container {
            background: var(--gradient);
            color: white;
            border-radius: 20px;
            padding: 60px 40px;
            text-align: center;
            margin: 50px 0;
            box-shadow: 0 20px 40px rgba(102, 126, 234, 0.3);
        }

        .stat-number {
            font-size: 3.5rem;
            font-weight: 700;
            margin-bottom: 10px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.2);
        }

        .stat-label {
            font-size: 1.2rem;
            opacity: 0.9;
            font-weight: 500;
        }

        .process-step {
            text-align: center;
            padding: 30px 20px;
            position: relative;
        }

        .step-number {
            width: 80px;
            height: 80px;
            background: var(--gradient);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 25px;
            color: white;
            font-size: 2rem;
            font-weight: 700;
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
            position: relative;
            z-index: 2;
        }

        .process-connector {
            position: absolute;
            top: 40px;
            left: 50%;
            width: 100%;
            height: 3px;
            background: var(--gradient);
            z-index: 1;
        }

        .mission-card {
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            border: none;
            transition: all 0.3s ease;
            height: 100%;
        }

        .mission-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
        }

        .mission-icon {
            width: 70px;
            height: 70px;
            background: var(--gradient);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.8rem;
            margin-bottom: 20px;
        }

        .floating-element {
            animation: float 6s ease-in-out infinite;
        }

        @keyframes float {
            0% { transform: translateY(0px); }
            50% { transform: translateY(-20px); }
            100% { transform: translateY(0px); }
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

        .value-badge {
            background: var(--gradient);
            color: white;
            padding: 10px 25px;
            border-radius: 25px;
            font-size: 1rem;
            margin: 5px;
            display: inline-block;
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }

        .timeline {
            position: relative;
            padding: 40px 0;
        }

        .timeline::before {
            content: '';
            position: absolute;
            top: 0;
            bottom: 0;
            left: 50%;
            width: 3px;
            background: var(--gradient);
            transform: translateX(-50%);
        }

        .timeline-item {
            margin-bottom: 50px;
            position: relative;
        }

        .timeline-content {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            position: relative;
            width: 45%;
        }

        .timeline-item:nth-child(odd) .timeline-content {
            margin-left: auto;
        }

        .timeline-item:nth-child(even) .timeline-content {
            margin-right: auto;
        }

        .timeline-content::after {
            content: '';
            position: absolute;
            top: 20px;
            width: 20px;
            height: 20px;
            background: var(--gradient);
            border-radius: 50%;
        }

        .timeline-item:nth-child(odd) .timeline-content::after {
            left: -10px;
        }

        .timeline-item:nth-child(even) .timeline-content::after {
            right: -10px;
        }

        .commitment-section {
            background: var(--gradient-secondary);
            color: white;
            border-radius: 20px;
            padding: 60px 40px;
            margin: 50px 0;
            text-align: center;
        }

        .commitment-icon {
            font-size: 3rem;
            margin-bottom: 20px;
            opacity: 0.9;
        }

        @media (max-width: 768px) {
            .timeline::before {
                left: 20px;
            }
            
            .timeline-content {
                width: calc(100% - 60px);
                margin-left: 60px !important;
            }
            
            .timeline-content::after {
                left: -10px !important;
                right: auto !important;
            }
            
            .about-hero h1 {
                font-size: 2.5rem;
            }
            
            .process-connector {
                display: none;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <!-- Hero Section -->
    <section class="about-hero">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <h1 class="display-3 fw-bold mb-4">Tentang Automarket</h1>
                    <p class="lead mb-4">Revolusi Jual Beli Mobil Bekas di Indonesia</p>
                    <div class="d-flex flex-wrap justify-content-center gap-3">
                        <span class="value-badge"><i class="fas fa-shield-alt me-2"></i>Aman & Terpercaya</span>
                        <span class="value-badge"><i class="fas fa-bolt me-2"></i>Proses Cepat</span>
                        <span class="value-badge"><i class="fas fa-hand-holding-usd me-2"></i>Harga Terbaik</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Stats Section -->
    <div class="container">
        <div class="stats-container animate-on-scroll">
            <div class="row">
                <div class="col-md-3 mb-4">
                    <div class="stat-number">100+</div>
                    <div class="stat-label">Mobil Terjual</div>
                </div>
                <div class="col-md-3 mb-4">
                    <div class="stat-number">100+</div>
                    <div class="stat-label">Pengguna Aktif</div>
                </div>
                <div class="col-md-3 mb-4">
                    <div class="stat-number">25+</div>
                    <div class="stat-label">Kota di Indonesia</div>
                </div>
                <div class="col-md-3 mb-4">
                    <div class="stat-number">98%</div>
                    <div class="stat-label">Kepuasan Pelanggan</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Mission & Vision -->
    <section class="py-5">
        <div class="container">
            <div class="row align-items-center mb-5">
                <div class="col-lg-6 mb-5 mb-lg-0">
                    <div class="mission-card animate-on-scroll">
                        <div class="mission-icon">
                            <i class="fas fa-bullseye"></i>
                        </div>
                        <h3 class="fw-bold mb-3">Misi Kami</h3>
                        <p class="lead mb-4">Membuat proses jual beli mobil menjadi lebih mudah, transparan, dan terpercaya bagi semua orang.</p>
                        <p class="mb-0">Automarket hadir sebagai solusi bagi masyarakat Indonesia yang ingin menjual atau membeli mobil bekas dengan proses yang aman dan terjamin. Kami menyediakan platform yang menghubungkan penjual dan pembeli secara langsung dengan sistem yang transparan.</p>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="mission-card animate-on-scroll">
                        <div class="mission-icon">
                            <i class="fas fa-eye"></i>
                        </div>
                        <h3 class="fw-bold mb-3">Visi Kami</h3>
                        <p class="lead mb-4">Menjadi platform jual beli mobil terdepan di Indonesia yang mengutamakan kepercayaan dan kepuasan pengguna.</p>
                        <p class="mb-0">Kami berkomitmen untuk terus berinovasi dalam menyediakan layanan terbaik, memastikan setiap transaksi berjalan lancar, dan membangun ekosistem jual beli mobil yang sehat dan menguntungkan bagi semua pihak.</p>
                    </div>
                </div>
            </div>

            <div class="row mt-5">
                <div class="col-lg-8 mx-auto">
                    <div class="text-center">
                        <h2 class="section-title center">Nilai-Nilai Kami</h2>
                        <p class="lead mb-5">Prinsip yang kami pegang teguh dalam setiap layanan</p>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-4">
                            <div class="d-flex align-items-start">
                                <div class="mission-icon me-4 flex-shrink-0">
                                    <i class="fas fa-handshake"></i>
                                </div>
                                <div>
                                    <h5>Integritas</h5>
                                    <p class="mb-0">Selalu jujur dan transparan dalam setiap transaksi</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-4">
                            <div class="d-flex align-items-start">
                                <div class="mission-icon me-4 flex-shrink-0">
                                    <i class="fas fa-users"></i>
                                </div>
                                <div>
                                    <h5>Kolaborasi</h5>
                                    <p class="mb-0">Bekerja sama untuk hasil yang terbaik</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-4">
                            <div class="d-flex align-items-start">
                                <div class="mission-icon me-4 flex-shrink-0">
                                    <i class="fas fa-rocket"></i>
                                </div>
                                <div>
                                    <h5>Inovasi</h5>
                                    <p class="mb-0">Terus berkembang dan berinovasi</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-4">
                            <div class="d-flex align-items-start">
                                <div class="mission-icon me-4 flex-shrink-0">
                                    <i class="fas fa-heart"></i>
                                </div>
                                <div>
                                    <h5>Kepuasan Pelanggan</h5>
                                    <p class="mb-0">Pelanggan adalah prioritas utama kami</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Commitment Section -->
    <section class="py-5">
        <div class="container">
            <div class="commitment-section animate-on-scroll">
                <div class="row justify-content-center">
                    <div class="col-lg-8 text-center">
                        <div class="commitment-icon">
                            <i class="fas fa-handshake"></i>
                        </div>
                        <h2 class="mb-4">Komitmen Kami Untuk Anda</h2>
                        <p class="lead mb-4">Kami berkomitmen untuk memberikan pengalaman jual beli mobil terbaik dengan layanan yang aman, transparan, dan terpercaya. Setiap transaksi di Automarket dilindungi oleh sistem keamanan yang canggih dan tim customer service yang siap membantu 24/7.</p>
                        <div class="d-flex flex-wrap justify-content-center gap-3 mt-4">
                            <span class="value-badge"><i class="fas fa-lock me-2"></i>Transaksi Aman</span>
                            <span class="value-badge"><i class="fas fa-clock me-2"></i>Proses Cepat</span>
                            <span class="value-badge"><i class="fas fa-headset me-2"></i>Support 24/7</span>
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
                <h2 class="section-title center">Keunggulan Automarket</h2>
                <p class="lead">Mengapa ribuan orang mempercayai platform kami?</p>
            </div>
            
            <div class="row">
                <div class="col-md-4 mb-4">
                    <div class="feature-card animate-on-scroll">
                        <div class="feature-icon">
                            <i class="fas fa-shield-alt"></i>
                        </div>
                        <h4 class="mb-3">Aman & Terpercaya</h4>
                        <p class="mb-0">Sistem verifikasi ketat untuk penjual dan pembeli, memastikan transaksi yang aman dan terjamin.</p>
                    </div>
                </div>
                
                <div class="col-md-4 mb-4">
                    <div class="feature-card animate-on-scroll">
                        <div class="feature-icon">
                            <i class="fas fa-hand-holding-usd"></i>
                        </div>
                        <h4 class="mb-3">Tanpa Biaya Tambahan</h4>
                        <p class="mb-0">Gratis pasang iklan, tanpa komisi penjualan. Hanya bayar booking fee ketika deal.</p>
                    </div>
                </div>
                
                <div class="col-md-4 mb-4">
                    <div class="feature-card animate-on-scroll">
                        <div class="feature-icon">
                            <i class="fas fa-comments"></i>
                        </div>
                        <h4 class="mb-3">Chat Langsung</h4>
                        <p class="mb-0">Komunikasi langsung dengan penjual, negosiasi harga lebih mudah dan transparan.</p>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-4 mb-4">
                    <div class="feature-card animate-on-scroll">
                        <div class="feature-icon">
                            <i class="fas fa-file-contract"></i>
                        </div>
                        <h4 class="mb-3">Faktur Resmi</h4>
                        <p class="mb-0">Dapatkan faktur booking resmi yang dilindungi sistem dan memiliki kekuatan hukum.</p>
                    </div>
                </div>
                
                <div class="col-md-4 mb-4">
                    <div class="feature-card animate-on-scroll">
                        <div class="feature-icon">
                            <i class="fas fa-mobile-alt"></i>
                        </div>
                        <h4 class="mb-3">Mudah Digunakan</h4>
                        <p class="mb-0">Interface yang user-friendly, mudah digunakan oleh siapapun dengan berbagai latar belakang.</p>
                    </div>
                </div>
                
                <div class="col-md-4 mb-4">
                    <div class="feature-card animate-on-scroll">
                        <div class="feature-icon">
                            <i class="fas fa-headset"></i>
                        </div>
                        <h4 class="mb-3">Customer Support</h4>
                        <p class="mb-0">Tim support siap membantu 7x24 jam untuk masalah transaksi dan keluhan pelanggan.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

            <!-- How It Works Section -->
    <section class="py-5">
        <div class="container">
            <div class="row text-center mb-5">
                <div class="col-12">
                    <h2 class="section-title center">Kerja Automarket</h2>
                    <p class="lead">mobil yang simpel, cepat, dan terpercaya</p>
                </div>
            </div>
            
            <div class="row justify-content-center">
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="process-step animate-on-scroll text-center">
                        <div class="step-number">1</div>
                        <h5 class="fw-bold mb-3">Pasangan</h5>
                        <p class="mb-0">Cari mobil sesuai keinginan atau gunakan filter yang tersedia</p>
                    </div>
                </div>
                
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="process-step animate-on-scroll text-center">
                        <div class="step-number">2</div>
                        <h5 class="fw-bold mb-3">Negosiasi & Booking</h5>
                        <p class="mb-0">Chat langsung dengan penjual/pembeli dan lakukan booking dengan DP</p>
                    </div>
                </div>
                
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="process-step animate-on-scroll text-center">
                        <div class="step-number">3</div>
                        <h5 class="fw-bold mb-3">Transaksi & Serah Terima</h5>
                        <p class="mb-0">Bayar pelunasan dan selesaikan transaksi dengan aman</p>
                    </div>
                </div>
            </div>
            
            <div class="text-center mt-5">
                <a href="register.php" class="btn btn-primary btn-lg me-3">
                    <i class="fas fa-user-plus me-2"></i> Daftar Sekarang
                </a>
                <a href="about.php" class="btn btn-outline-primary btn-lg">
                    <i class="fas fa-info-circle me-2"></i> Pelajari Lebih Lanjut
                </a>
            </div>
        </div>
    </section>

    <!-- Company Timeline -->
    <section class="py-5 bg-light">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="section-title center">Perjalanan Kami</h2>
                <p class="lead">Sejarah perkembangan Automarket dari awal hingga sekarang</p>
            </div>
            
            <div class="timeline">
                <div class="timeline-item animate-on-scroll">
                    <div class="timeline-content">
                        <h5 class="fw-bold text-primary">2020</h5>
                        <h6>Pendirian Automarket</h6>
                        <p class="mb-0">Automarket didirikan dengan visi merevolusi jual beli mobil bekas di Indonesia</p>
                    </div>
                </div>
                
                <div class="timeline-item animate-on-scroll">
                    <div class="timeline-content">
                        <h5 class="fw-bold text-primary">2021</h5>
                        <h6>Ekspansi ke 10 Kota</h6>
                        <p class="mb-0">Berhasil mengembangkan layanan ke 10 kota besar di Indonesia</p>
                    </div>
                </div>
                
                <div class="timeline-item animate-on-scroll">
                    <div class="timeline-content">
                        <h5 class="fw-bold text-primary">2022</h5>
                        <h6>10.000 Mobil Terjual</h6>
                        <p class="mb-0">Mencapai milestone 10.000 mobil berhasil terjual melalui platform</p>
                    </div>
                </div>
                
                <div class="timeline-item animate-on-scroll">
                    <div class="timeline-content">
                        <h5 class="fw-bold text-primary">2023</h5>
                        <h6>Pengguna Melebihi 50.000</h6>
                        <p class="mb-0">Jumlah pengguna aktif melebihi 50.000 orang di seluruh Indonesia</p>
                    </div>
                </div>
                
                <div class="timeline-item animate-on-scroll">
                    <div class="timeline-content">
                        <h5 class="fw-bold text-primary">2024</h5>
                        <h6>Inovasi Fitur Baru</h6>
                        <p class="mb-0">Meluncurkan fitur inspeksi mobil dan garansi terbatas untuk pembeli</p>
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
    </script>
</body>
</html>