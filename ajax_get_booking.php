<?php
// file: ajax_get_booking.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';

// Cek login
if (!$auth->isLoggedIn() || $_SESSION['role'] != 'admin') {
    http_response_code(403);
    die('Access denied');
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    http_response_code(400);
    die('Invalid booking ID');
}

$booking_id = (int)$_GET['id'];

// Query untuk mendapatkan detail booking
$sql = "SELECT tb.*, 
               u1.nama_lengkap as nama_pembeli, u1.email as email_pembeli, u1.no_telepon as telepon_pembeli,
               u2.nama_lengkap as nama_penjual, u2.email as email_penjual, u2.no_telepon as telepon_penjual,
               m.merk, m.model, m.tahun, m.plat_mobil, m.warna, m.kilometer, m.transmisi, m.bahan_bakar
        FROM transaksi_booking tb
        JOIN users u1 ON tb.pembeli_id = u1.id
        JOIN users u2 ON tb.penjual_id = u2.id
        JOIN mobil m ON tb.mobil_id = m.id
        WHERE tb.id = ?";

$stmt = $pdo->prepare($sql);
$stmt->execute([$booking_id]);
$booking = $stmt->fetch();

if (!$booking) {
    http_response_code(404);
    die('Booking not found');
}

// Fungsi helper
function formatRupiah($amount) {
    return 'Rp ' . number_format($amount, 0, ',', '.');
}

function formatTanggal($date) {
    if (!$date) return '-';
    return date('d M Y', strtotime($date));
}

function formatTanggalWaktu($datetime) {
    if (!$datetime) return '-';
    return date('d M Y H:i', strtotime($datetime));
}
?>

<div class="row">
    <div class="col-md-8">
        <!-- Ringkasan Booking -->
        <div class="card mb-3">
            <div class="card-header bg-light">
                <h6 class="mb-0"><i class="fas fa-receipt me-2"></i>Booking #<?php echo htmlspecialchars($booking['kode_booking']); ?></h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Tanggal Booking:</strong> <?php echo formatTanggal($booking['tanggal_booking']); ?></p>
                        <p><strong>Jam Booking:</strong> <?php echo $booking['jam_booking'] ?: '-'; ?></p>
                        <p><strong>Status Booking:</strong> <span class="badge bg-<?php echo getStatusColor($booking['status']); ?>"><?php echo ucfirst($booking['status']); ?></span></p>
                        <p><strong>Pembeli:</strong> <?php echo htmlspecialchars($booking['nama_pembeli']); ?></p>
                        <p><strong>Email:</strong> <?php echo htmlspecialchars($booking['email_pembeli']); ?></p>
                        <p><strong>Telepon:</strong> <?php echo htmlspecialchars($booking['telepon_pembeli']); ?></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Penjual:</strong> <?php echo htmlspecialchars($booking['nama_penjual']); ?></p>
                        <p><strong>Email Penjual:</strong> <?php echo htmlspecialchars($booking['email_penjual']); ?></p>
                        <p><strong>Telepon Penjual:</strong> <?php echo htmlspecialchars($booking['telepon_penjual']); ?></p>
                        <p><strong>Mobil:</strong> <?php echo htmlspecialchars($booking['merk'] . ' ' . $booking['model'] . ' (' . $booking['tahun'] . ')'); ?></p>
                        <p><strong>Plat:</strong> <?php echo htmlspecialchars($booking['plat_mobil']); ?></p>
                        <p><strong>Warna:</strong> <?php echo htmlspecialchars($booking['warna']); ?></p>
                    </div>
                </div>
                
                <hr>
                
                <div class="row">
                    <div class="col-md-6">
                        <h6>Informasi Pembayaran</h6>
                        <p><strong>Metode Pembayaran:</strong> <?php echo formatMetodePembayaran($booking['metode_pembayaran']); ?></p>
                        <p><strong>Status Pembayaran:</strong> <span class="badge bg-<?php echo getStatusPembayaranColor($booking['status_pembayaran']); ?>"><?php echo ucfirst(str_replace('_', ' ', $booking['status_pembayaran'])); ?></span></p>
                        <p><strong>Harga Mobil:</strong> <?php echo formatRupiah($booking['harga_mobil']); ?></p>
                        <p><strong>Uang Booking:</strong> <?php echo formatRupiah($booking['uang_booking']); ?></p>
                        <p><strong>Jumlah Total:</strong> <span class="fw-bold text-success"><?php echo formatRupiah($booking['jumlah_total']); ?></span></p>
                    </div>
                    <div class="col-md-6">
                        <h6>Informasi Mobil</h6>
                        <p><strong>Kilometer:</strong> <?php echo number_format($booking['kilometer'], 0, ',', '.'); ?> km</p>
                        <p><strong>Transmisi:</strong> <?php echo ucfirst(str_replace('_', ' ', $booking['transmisi'])); ?></p>
                        <p><strong>Bahan Bakar:</strong> <?php echo ucfirst($booking['bahan_bakar']); ?></p>
                        <p><strong>Lokasi Pertemuan:</strong> <?php echo htmlspecialchars($booking['lokasi_pertemuan'] ?: '-'); ?></p>
                        <?php if ($booking['catatan']): ?>
                        <p><strong>Catatan:</strong> <?php echo htmlspecialchars($booking['catatan']); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <!-- Aksi Dokument -->
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h6 class="mb-0"><i class="fas fa-download me-2"></i>Aksi Dokument</h6>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <!-- 1. Print langsung -->
                    <button class="btn btn-primary" onclick="printBooking()">
                        <i class="fas fa-print me-2"></i>Print Dokumen
                    </button>
                    
                    <!-- 2. Download PDF via AJAX -->
                    <button class="btn btn-success" onclick="downloadPDF(<?php echo $booking_id; ?>)">
                        <i class="fas fa-file-pdf me-2"></i>Download PDF
                    </button>
                    
                    <!-- 3. Download Excel via AJAX -->
                    <button class="btn btn-warning" onclick="downloadExcel(<?php echo $booking_id; ?>)">
                        <i class="fas fa-file-excel me-2"></i>Download Excel
                    </button>
                    
                    <!-- 4. Kirim Email -->
                    <button class="btn btn-info" onclick="sendEmail()">
                        <i class="fas fa-envelope me-2"></i>Kirim Email
                    </button>
                </div>
                
                <hr>
                
                <div class="text-center">
                    <small class="text-muted">PDF dan Excel akan di-generate oleh server</small>
                </div>
            </div>
        </div>
        
        <!-- Status Timeline -->
        <div class="card mt-3">
            <div class="card-header bg-light">
                <h6 class="mb-0"><i class="fas fa-history me-2"></i>Timeline</h6>
            </div>
            <div class="card-body">
                <ul class="list-unstyled timeline">
                    <li class="mb-2">
                        <i class="fas fa-calendar-check text-success me-2"></i>
                        <span>Dibuat:</span>
                        <small class="text-muted"><?php echo formatTanggalWaktu($booking['created_at']); ?></small>
                    </li>
                    <?php if ($booking['dikonfirmasi_at']): ?>
                    <li class="mb-2">
                        <i class="fas fa-check-circle text-primary me-2"></i>
                        <span>Dikonfirmasi:</span>
                        <small class="text-muted"><?php echo formatTanggalWaktu($booking['dikonfirmasi_at']); ?></small>
                    </li>
                    <?php endif; ?>
                    <?php if ($booking['selesai_at']): ?>
                    <li class="mb-2">
                        <i class="fas fa-flag-checkered text-success me-2"></i>
                        <span>Selesai:</span>
                        <small class="text-muted"><?php echo formatTanggalWaktu($booking['selesai_at']); ?></small>
                    </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>
</div>

<script>
// 1. FUNGSI PRINT
function printBooking() {
    // Ambil HTML dari konten booking
    const printContent = document.querySelector('.col-md-8').innerHTML;
    
    // Buat window untuk print
    const printWindow = window.open('', '_blank', 'width=800,height=600');
    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>Booking Receipt - <?php echo $booking['kode_booking']; ?></title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
            <style>
                body { padding: 20px; font-family: Arial, sans-serif; }
                .card { border: 1px solid #ddd; }
                .card-header { background-color: #f8f9fa !important; color: #000 !important; }
                @media print {
                    .btn, .timeline { display: none !important; }
                    .card { border: none !important; box-shadow: none !important; }
                }
            </style>
        </head>
        <body>
            <div class="container">
                <h2 class="text-center mb-4">Booking #<?php echo $booking['kode_booking']; ?></h2>
                <div class="row">
                    <div class="col-12">
                        ${printContent}
                    </div>
                </div>
                <div class="text-center mt-4 text-muted">
                    <p>Dicetak pada: ${new Date().toLocaleString()}</p>
                    <p>Automarket - www.automarket.com</p>
                </div>
            </div>
        </body>
        </html>
    `);
    
    printWindow.document.close();
    printWindow.focus();
    
    // Tunggu sebentar untuk memastikan konten terload
    setTimeout(() => {
        printWindow.print();
    }, 500);
}

// 2. FUNGSI DOWNLOAD PDF (via AJAX)
function downloadPDF(bookingId) {
    const btn = event.target;
    const originalText = btn.innerHTML;
    
    // Tampilkan loading
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Generating PDF...';
    btn.disabled = true;
    
    // Panggil API untuk generate PDF
    fetch(`api/generate_pdf.php?id=${bookingId}`)
        .then(response => {
            if (response.ok) return response.blob();
            throw new Error('Failed to generate PDF');
        })
        .then(blob => {
            // Buat link untuk download
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `booking_<?php echo $booking['kode_booking']; ?>_${new Date().getTime()}.pdf`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
            
            // Reset button
            btn.innerHTML = originalText;
            btn.disabled = false;
            
            // Tampilkan notifikasi
            showNotification('PDF berhasil diunduh', 'success');
        })
        .catch(error => {
            console.error('Error:', error);
            btn.innerHTML = originalText;
            btn.disabled = false;
            showNotification('Gagal mengunduh PDF: ' + error.message, 'danger');
            
            // Fallback: buka PDF generator online
            const data = {
                kode: "<?php echo $booking['kode_booking']; ?>",
                tanggal: "<?php echo date('d M Y', strtotime($booking['tanggal_booking'])); ?>",
                pembeli: "<?php echo addslashes($booking['nama_pembeli']); ?>",
                mobil: "<?php echo addslashes($booking['merk'] . ' ' . $booking['model'] . ' (' . $booking['tahun'] . ')'); ?>",
                total: "<?php echo formatRupiah($booking['jumlah_total']); ?>"
            };
            
            // Alternatif: gunakan jsPDF jika tersedia
            if (typeof jsPDF !== 'undefined') {
                generatePDFWithJSPDF(data);
            }
        });
}

// 3. FUNGSI DOWNLOAD EXCEL (via AJAX)
function downloadExcel(bookingId) {
    const btn = event.target;
    const originalText = btn.innerHTML;
    
    // Tampilkan loading
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Generating Excel...';
    btn.disabled = true;
    
    // Panggil API untuk generate Excel
    fetch(`api/generate_excel.php?id=${bookingId}`)
        .then(response => {
            if (response.ok) return response.blob();
            throw new Error('Failed to generate Excel');
        })
        .then(blob => {
            // Buat link untuk download
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `booking_<?php echo $booking['kode_booking']; ?>_${new Date().getTime()}.xlsx`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
            
            // Reset button
            btn.innerHTML = originalText;
            btn.disabled = false;
            
            // Tampilkan notifikasi
            showNotification('Excel berhasil diunduh', 'success');
        })
        .catch(error => {
            console.error('Error:', error);
            btn.innerHTML = originalText;
            btn.disabled = false;
            showNotification('Gagal mengunduh Excel: ' + error.message, 'danger');
            
            // Fallback: generate CSV
            generateCSV();
        });
}

// 4. FUNGSI KIRIM EMAIL
function sendEmail() {
    const subject = `Konfirmasi Booking - <?php echo $booking['kode_booking']; ?>`;
    const body = `Yth. <?php echo $booking['nama_pembeli']; ?>,

Berikut detail booking Anda:

Kode Booking: <?php echo $booking['kode_booking']; ?>
Tanggal Booking: <?php echo date('d M Y', strtotime($booking['tanggal_booking'])); ?>
Mobil: <?php echo $booking['merk'] . ' ' . $booking['model'] . ' (' . $booking['tahun'] . ')'; ?>
Plat: <?php echo $booking['plat_mobil']; ?>
Harga Mobil: <?php echo formatRupiah($booking['harga_mobil']); ?>
Uang Booking: <?php echo formatRupiah($booking['uang_booking']); ?>
Total: <?php echo formatRupiah($booking['jumlah_total']); ?>

Status: <?php echo ucfirst($booking['status']); ?>

Terima kasih telah menggunakan Automarket.

Salam,
Admin Automarket`;

    // Buka aplikasi email default
    window.location.href = `mailto:<?php echo $booking['email_pembeli']; ?>?subject=${encodeURIComponent(subject)}&body=${encodeURIComponent(body)}`;
}

// Helper functions
function generateCSV() {
    const csvContent = `"KODE BOOKING","<?php echo $booking['kode_booking']; ?>"
"TANGGAL","<?php echo date('d M Y', strtotime($booking['tanggal_booking'])); ?>"
"PEMBELI","<?php echo addslashes($booking['nama_pembeli']); ?>"
"EMAIL PEMBELI","<?php echo $booking['email_pembeli']; ?>"
"TELEPON PEMBELI","<?php echo $booking['telepon_pembeli']; ?>"
"PENJUAL","<?php echo addslashes($booking['nama_penjual']); ?>"
"EMAIL PENJUAL","<?php echo $booking['email_penjual']; ?>"
"TELEPON PENJUAL","<?php echo $booking['telepon_penjual']; ?>"
"MOBIL","<?php echo addslashes($booking['merk'] . ' ' . $booking['model']); ?>"
"TAHUN","<?php echo $booking['tahun']; ?>"
"PLAT","<?php echo $booking['plat_mobil']; ?>"
"WARNA","<?php echo $booking['warna']; ?>"
"KILOMETER","<?php echo number_format($booking['kilometer'], 0, ',', '.'); ?> km"
"TRANSMISI","<?php echo ucfirst(str_replace('_', ' ', $booking['transmisi'])); ?>"
"BAHAN BAKAR","<?php echo ucfirst($booking['bahan_bakar']); ?>"
"HARGA MOBIL","Rp <?php echo number_format($booking['harga_mobil'], 0, ',', '.'); ?>"
"UANG BOOKING","Rp <?php echo number_format($booking['uang_booking'], 0, ',', '.'); ?>"
"TOTAL","Rp <?php echo number_format($booking['jumlah_total'], 0, ',', '.'); ?>"
"STATUS BOOKING","<?php echo ucfirst($booking['status']); ?>"
"STATUS PEMBAYARAN","<?php echo ucfirst(str_replace('_', ' ', $booking['status_pembayaran'])); ?>"
"METODE PEMBAYARAN","<?php echo formatMetodePembayaran($booking['metode_pembayaran']); ?>"
"LOKASI PERTEMUAN","<?php echo addslashes($booking['lokasi_pertemuan'] ?: '-'); ?>"
"CATATAN","<?php echo addslashes($booking['catatan'] ?: '-'); ?>"
"DICETAK","${new Date().toLocaleString()}"`;
    
    // Create blob dan download
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `booking_<?php echo $booking['kode_booking']; ?>.csv`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
}

function generatePDFWithJSPDF(data) {
    try {
        const doc = new jsPDF();
        
        // Header
        doc.setFontSize(20);
        doc.text('AUTOMARKET', 105, 15, null, null, 'center');
        doc.setFontSize(16);
        doc.text('BOOKING RECEIPT', 105, 25, null, null, 'center');
        doc.setFontSize(12);
        doc.text(`Kode: ${data.kode}`, 105, 35, null, null, 'center');
        
        // Content
        doc.setFontSize(11);
        let y = 50;
        doc.text(`Tanggal: ${data.tanggal}`, 20, y);
        y += 10;
        doc.text(`Pembeli: ${data.pembeli}`, 20, y);
        y += 10;
        doc.text(`Mobil: ${data.mobil}`, 20, y);
        y += 10;
        doc.text(`Total: ${data.total}`, 20, y);
        y += 20;
        
        // Footer
        doc.setFontSize(10);
        doc.text(`Dicetak pada: ${new Date().toLocaleString()}`, 20, 280);
        doc.text('www.automarket.com', 105, 280, null, null, 'center');
        
        // Save
        doc.save(`booking_${data.kode}.pdf`);
        
        showNotification('PDF berhasil dibuat dengan jsPDF', 'success');
    } catch (error) {
        console.error('Error generating PDF:', error);
        showNotification('Gagal membuat PDF. Silakan coba lagi.', 'danger');
    }
}

function showNotification(message, type = 'info') {
    // Buat notifikasi sederhana
    const alert = document.createElement('div');
    alert.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
    alert.style.cssText = 'top: 20px; right: 20px; z-index: 9999; max-width: 300px;';
    alert.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    document.body.appendChild(alert);
    
    // Hapus otomatis setelah 5 detik
    setTimeout(() => {
        alert.remove();
    }, 5000);
}
</script>

<style>
.timeline li {
    position: relative;
    padding-left: 1.5rem;
}

.timeline li:before {
    content: '';
    position: absolute;
    left: 0;
    top: 0.5rem;
    width: 8px;
    height: 8px;
    background-color: #6c757d;
    border-radius: 50%;
}

@media print {
    .col-md-4, .btn, .timeline {
        display: none !important;
    }
    
    .card {
        break-inside: avoid;
        border: 1px solid #ddd !important;
    }
}
</style>

<?php
// Helper functions untuk PHP
function getStatusColor($status) {
    switch ($status) {
        case 'pending': return 'warning';
        case 'dikonfirmasi': return 'primary';
        case 'selesai': return 'success';
        case 'dibatalkan': return 'danger';
        default: return 'secondary';
    }
}

function getStatusPembayaranColor($status) {
    switch ($status) {
        case 'belum_bayar': return 'warning';
        case 'menunggu_konfirmasi': return 'info';
        case 'lunas': return 'success';
        case 'gagal': return 'danger';
        default: return 'secondary';
    }
}

function formatMetodePembayaran($metode) {
    $map = [
        'transfer_bank' => 'Transfer Bank',
        'cash' => 'Cash',
        'credit_card' => 'Kartu Kredit',
        'e_wallet' => 'E-Wallet',
        'belum_dipilih' => 'Belum Dipilih'
    ];
    return $map[$metode] ?? ucfirst(str_replace('_', ' ', $metode));
}
?>