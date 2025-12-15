-- Buat database
CREATE DATABASE IF NOT EXISTS automarket CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE automarket;

-- Table: users
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    nama_lengkap VARCHAR(100) NOT NULL,
    no_ktp VARCHAR(20) NOT NULL,
    no_telepon VARCHAR(15) NOT NULL,
    role ENUM('admin', 'penjual', 'pembeli') NOT NULL,
    foto_profil VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Table: alamat
CREATE TABLE alamat (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    jalan TEXT NOT NULL,
    kota VARCHAR(100) NOT NULL,
    provinsi VARCHAR(100) NOT NULL,
    kode_pos VARCHAR(10) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Table: mobil
CREATE TABLE mobil (
    id INT AUTO_INCREMENT PRIMARY KEY,
    penjual_id INT NOT NULL,
    plat_mobil VARCHAR(15) UNIQUE NOT NULL,
    no_mesin VARCHAR(50) UNIQUE NOT NULL,
    rangka_mesin VARCHAR(50) UNIQUE NOT NULL,
    slinder INT NOT NULL,
    merk VARCHAR(50) NOT NULL,
    model VARCHAR(50) NOT NULL,
    tahun INT NOT NULL,
    warna VARCHAR(30) NOT NULL,
    harga DECIMAL(15,2) NOT NULL,
    deskripsi TEXT,
    kilometer BIGINT DEFAULT 0,
    bahan_bakar ENUM('bensin', 'solar', 'listrik', 'hybrid') DEFAULT 'bensin',
    transmisi ENUM('manual', 'matic', 'semi_automatic') DEFAULT 'manual',
    foto_mobil VARCHAR(255), -- Foto utama
    status ENUM('tersedia', 'dipesan', 'terjual') DEFAULT 'tersedia',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (penjual_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Table: foto_mobil
CREATE TABLE foto_mobil (
    id INT AUTO_INCREMENT PRIMARY KEY,
    mobil_id INT NOT NULL,
    nama_file VARCHAR(255) NOT NULL,
    urutan INT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (mobil_id) REFERENCES mobil(id) ON DELETE CASCADE
);

-- Table: favorit
CREATE TABLE favorit (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pembeli_id INT NOT NULL,
    mobil_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (pembeli_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (mobil_id) REFERENCES mobil(id) ON DELETE CASCADE,
    UNIQUE KEY unique_favorit (pembeli_id, mobil_id)
);

-- Table: pesan
CREATE TABLE pesan (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pengirim_id INT NOT NULL,
    penerima_id INT NOT NULL,
    mobil_id INT,
    pesan TEXT NOT NULL,
    dibaca BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (pengirim_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (penerima_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (mobil_id) REFERENCES mobil(id) ON DELETE SET NULL
);

-- Table: transaksi_booking 
CREATE TABLE transaksi_booking (
    id INT AUTO_INCREMENT PRIMARY KEY,
    kode_booking VARCHAR(20) UNIQUE NOT NULL,
    penjual_id INT NOT NULL,
    pembeli_id INT NOT NULL,
    mobil_id INT NOT NULL,
    
    -- Informasi booking
    tanggal_booking DATE NOT NULL,
    jam_booking TIME,
    lokasi_pertemuan VARCHAR(255),
    catatan TEXT,
    catatan_pembeli TEXT,
    catatan_penjual TEXT,
    
    -- Informasi pembayaran (METODE PEMBAYARAN DITAMBAHKAN)
    metode_pembayaran ENUM('transfer_bank', 'cash', 'credit_card', 'e_wallet', 'belum_dipilih') DEFAULT 'belum_dipilih',
    bank_tujuan VARCHAR(50),
    no_rek_tujuan VARCHAR(50),
    nama_pemilik_rek VARCHAR(100),
    jumlah_dp DECIMAL(15,2) DEFAULT 0,
    jumlah_total DECIMAL(15,2) DEFAULT 0,
    status_pembayaran ENUM('belum_bayar', 'menunggu_konfirmasi', 'lunas', 'gagal') DEFAULT 'belum_bayar',
    bukti_pembayaran VARCHAR(255),
    tanggal_pembayaran DATETIME,
    catatan_pembayaran TEXT,
    
    -- Informasi harga & uang muka
    harga_mobil DECIMAL(15,2) NOT NULL,
    uang_booking DECIMAL(15,2) NOT NULL,
    no_rekening_penjual VARCHAR(50) NOT NULL,
    nama_bank_penjual VARCHAR(50) NOT NULL,
    
    -- Status booking
    status ENUM('pending', 'dikonfirmasi', 'dibatalkan', 'selesai') DEFAULT 'pending',
    alasan_pembatalan TEXT,
    
    -- Dokumen
    ketentuan_booking TEXT,
    tanda_tangan_pembeli VARCHAR(255),
    stempel_admin VARCHAR(255),
    
    -- Backup data (jika mobil berubah/terhapus)
    merk_mobil VARCHAR(50),
    model_mobil VARCHAR(50),
    tahun_mobil INT,
    foto_mobil_booking VARCHAR(255),
    nama_penjual VARCHAR(100),
    telepon_penjual VARCHAR(15),
    nama_pembeli VARCHAR(100),
    telepon_pembeli VARCHAR(15),
    
    -- Timestamps
    dikonfirmasi_at DATETIME,
    dibatalkan_at DATETIME,
    selesai_at DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Foreign keys
    FOREIGN KEY (penjual_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (pembeli_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (mobil_id) REFERENCES mobil(id) ON DELETE CASCADE,
    
    -- Indexes
    INDEX idx_kode_booking (kode_booking),
    INDEX idx_pembeli_status (pembeli_id, status),
    INDEX idx_penjual_status (penjual_id, status),
    INDEX idx_status_pembayaran (status_pembayaran),
    INDEX idx_metode_pembayaran (metode_pembayaran)
);

-- Table: notifikasi
CREATE TABLE notifikasi (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    judul VARCHAR(255) NOT NULL,
    pesan TEXT NOT NULL,
    dibaca BOOLEAN DEFAULT FALSE,
    link VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Table: bank_accounts (untuk rekening penjual)
CREATE TABLE bank_accounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    nama_bank VARCHAR(50) NOT NULL,
    no_rekening VARCHAR(50) NOT NULL,
    nama_pemilik VARCHAR(100) NOT NULL,
    is_primary BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_bank_account (user_id, nama_bank, no_rekening)
);

-- Table: contact_messages (untuk form kontak)
CREATE TABLE contact_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    subjek VARCHAR(255) NOT NULL,
    pesan TEXT NOT NULL,
    dibaca BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Table: messages (untuk sistem pesan internal) - OPSIONAL, bisa dihapus karena sudah ada tabel pesan
CREATE TABLE IF NOT EXISTS messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pengirim_id INT NOT NULL,
    pengirim_role ENUM('buyer','seller','admin') NOT NULL,
    penerima_id INT NOT NULL,
    penerima_role ENUM('buyer','seller','admin') NOT NULL,
    car_id INT NULL,
    judul VARCHAR(255) NOT NULL,
    pesan TEXT NOT NULL,
    dibaca TINYINT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tambahan table untuk subscription
CREATE TABLE subscription_plans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama_plan VARCHAR(100) NOT NULL,
    harga_bulanan DECIMAL(10,2) NOT NULL,
    max_mobil INT NOT NULL,
    fitur TEXT,
    is_popular BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE seller_subscriptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    seller_id INT NOT NULL,
    plan_id INT NOT NULL,
    status ENUM('active', 'inactive', 'expired') DEFAULT 'active',
    mulai_tanggal DATE,
    berakhir_tanggal DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (seller_id) REFERENCES users(id),
    FOREIGN KEY (plan_id) REFERENCES subscription_plans(id)
);

-- Tambah kolom max_mobil di tabel users untuk free tier
ALTER TABLE users 
ADD COLUMN max_mobil INT DEFAULT 3,
ADD COLUMN subscription_expiry DATE NULL;

-- Tambah kolom subscription_status jika belum ada
ALTER TABLE users 
ADD COLUMN subscription_status ENUM('free', 'basic', 'premium', 'business') DEFAULT 'free';

-- Insert default subscription plans
INSERT INTO subscription_plans (nama_plan, harga_bulanan, max_mobil, fitur, is_popular) VALUES
('Free', 0, 3, 'Dashboard Dasar,Notifikasi Email,3 Mobil Aktif', FALSE),
('Basic', 50000, 10, 'Semua Fitur Free,Prioritas Listing,10 Mobil Aktif,Support Email', TRUE),
('Premium', 100000, 30, 'Semua Fitur Basic,30 Mobil Aktif,Analytics Dashboard,Support Prioritas', FALSE),
('Business', 250000, 100, 'Semua Fitur Premium,100 Mobil Aktif,API Access,Support 24/7,White Label', FALSE);