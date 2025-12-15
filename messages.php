<?php
session_start();

// Cek file config yang tersedia
if (file_exists(__DIR__ . '/../config.php')) {
    require_once __DIR__ . '/../config.php';
} elseif (file_exists(__DIR__ . '/../includes/db_config.php')) {
    require_once __DIR__ . '/../includes/db_config.php';
} else {
    // Fallback: Buat koneksi manual
    $host = 'localhost';
    $dbname = 'automarket';
    $username = 'root';
    $password = '';
    
    try {
        $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch(PDOException $e) {
        die("Koneksi database gagal: " . $e->getMessage());
    }
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'penjual') {
    header('Location: ../login.php');
    exit();
}

$seller_id = $_SESSION['user_id'];

// AJAX: Kirim pesan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_send'])) {
    $response = ['success' => false, 'message' => ''];
    
    $penerima_id = $_POST['penerima_id'] ?? '';
    $pesan = trim($_POST['pesan'] ?? '');
    
    if (!empty($penerima_id) && !empty($pesan)) {
        try {
            $sql = "INSERT INTO pesan (pengirim_id, penerima_id, pesan, created_at) 
                    VALUES (?, ?, ?, NOW())";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$seller_id, $penerima_id, $pesan]);
            
            $response['success'] = true;
            $response['message'] = "Pesan berhasil dikirim!";
        } catch (PDOException $e) {
            $response['message'] = "Gagal mengirim pesan: " . $e->getMessage();
        }
    } else {
        $response['message'] = "Pesan tidak boleh kosong!";
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}

// AJAX: Ambil pesan baru
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['ajax_get_messages'])) {
    $other_user_id = $_GET['user_id'] ?? '';
    $last_message_id = $_GET['last_id'] ?? 0;
    
    if (!empty($other_user_id)) {
        $sql = "SELECT p.*, u.nama_lengkap as pengirim_nama
                FROM pesan p 
                JOIN users u ON p.pengirim_id = u.id 
                WHERE ((p.pengirim_id = ? AND p.penerima_id = ?) 
                    OR (p.pengirim_id = ? AND p.penerima_id = ?))
                    AND p.id > ?
                ORDER BY p.created_at ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$seller_id, $other_user_id, $other_user_id, $seller_id, $last_message_id]);
        $new_messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Tandai sebagai sudah dibaca
        if (!empty($new_messages)) {
            $read_sql = "UPDATE pesan SET dibaca = 1 WHERE penerima_id = ? AND pengirim_id = ? AND dibaca = 0";
            $read_stmt = $pdo->prepare($read_sql);
            $read_stmt->execute([$seller_id, $other_user_id]);
        }
        
        header('Content-Type: application/json');
        echo json_encode(['messages' => $new_messages]);
        exit();
    }
}

// Get conversations for seller
$sql = "SELECT DISTINCT 
               u.id as user_id,
               u.nama_lengkap as user_name,
               u.foto_profil,
               (SELECT pesan FROM pesan 
                WHERE (pengirim_id = u.id AND penerima_id = ?) 
                   OR (pengirim_id = ? AND penerima_id = u.id) 
                ORDER BY created_at DESC LIMIT 1) as last_message,
               (SELECT created_at FROM pesan 
                WHERE (pengirim_id = u.id AND penerima_id = ?) 
                   OR (pengirim_id = ? AND penerima_id = u.id) 
                ORDER BY created_at DESC LIMIT 1) as last_message_time,
               (SELECT COUNT(*) FROM pesan 
                WHERE penerima_id = ? AND pengirim_id = u.id AND dibaca = 0) as unread_count
        FROM users u
        JOIN pesan p ON (p.pengirim_id = u.id AND p.penerima_id = ?) 
                    OR (p.penerima_id = u.id AND p.pengirim_id = ?)
        WHERE u.id != ? AND u.role = 'pembeli'
        ORDER BY last_message_time DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute([$seller_id, $seller_id, $seller_id, $seller_id, $seller_id, $seller_id, $seller_id, $seller_id]);
$conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get messages for selected conversation
$selected_conversation = null;
$messages = [];
$last_message_id = 0;

if (isset($_GET['chat_with'])) {
    $other_user_id = $_GET['chat_with'];
    
    // Get other user info
    $user_sql = "SELECT id, nama_lengkap, foto_profil FROM users WHERE id = ?";
    $user_stmt = $pdo->prepare($user_sql);
    $user_stmt->execute([$other_user_id]);
    $selected_conversation = $user_stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($selected_conversation) {
        // Get messages
        $msg_sql = "SELECT p.*, u.nama_lengkap as pengirim_nama
                    FROM pesan p 
                    JOIN users u ON p.pengirim_id = u.id 
                    WHERE (p.pengirim_id = ? AND p.penerima_id = ?) 
                       OR (p.pengirim_id = ? AND p.penerima_id = ?)
                    ORDER BY p.created_at ASC";
        $msg_stmt = $pdo->prepare($msg_sql);
        $msg_stmt->execute([$seller_id, $other_user_id, $other_user_id, $seller_id]);
        $messages = $msg_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get last message ID
        if (!empty($messages)) {
            $last_message = end($messages);
            $last_message_id = $last_message['id'];
        }
        
        // Mark messages as read
        $read_sql = "UPDATE pesan SET dibaca = 1 WHERE penerima_id = ? AND pengirim_id = ? AND dibaca = 0";
        $read_stmt = $pdo->prepare($read_sql);
        $read_stmt->execute([$seller_id, $other_user_id]);
    }
}

// Count total unread messages
$unread_sql = "SELECT COUNT(*) as total_unread FROM pesan WHERE penerima_id = ? AND dibaca = 0";
$unread_stmt = $pdo->prepare($unread_sql);
$unread_stmt->execute([$seller_id]);
$total_unread = $unread_stmt->fetch(PDO::FETCH_ASSOC)['total_unread'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pesan - Automarket Seller</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
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
        .chat-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .conversations-sidebar {
            border-right: 1px solid #dee2e6;
            max-height: 70vh;
            overflow-y: auto;
        }
        .chat-area {
            height: 70vh;
            display: flex;
            flex-direction: column;
        }
        .messages-container {
            flex: 1;
            overflow-y: auto;
            padding: 20px;
            background: #f8f9fa;
        }
        .message {
            max-width: 70%;
            padding: 10px 15px;
            border-radius: 15px;
            margin-bottom: 10px;
            position: relative;
            animation: fadeIn 0.3s ease-in;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .message.sent {
            background: #28a745;
            color: white;
            margin-left: auto;
            border-bottom-right-radius: 5px;
        }
        .message.received {
            background: white;
            color: #333;
            margin-right: auto;
            border-bottom-left-radius: 5px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .conversation-item {
            padding: 15px;
            border-bottom: 1px solid #eee;
            cursor: pointer;
            transition: all 0.3s;
        }
        .conversation-item:hover {
            background: #f8f9fa;
        }
        .conversation-item.active {
            background: #e8f5e9;
            border-left: 4px solid #28a745;
        }
        .conversation-item.unread {
            background: #e8f5e9;
            border-left: 4px solid #ffc107;
        }
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
        }
        .message-time {
            font-size: 0.7rem;
            opacity: 0.8;
            margin-top: 5px;
        }
        .chat-header {
            background: white;
            border-bottom: 1px solid #dee2e6;
            padding: 15px 20px;
        }
        .message-input {
            border-radius: 25px;
            padding-right: 50px;
            border: 1px solid #ddd;
        }
        .message-input:focus {
            border-color: #28a745;
            box-shadow: 0 0 0 0.2rem rgba(40, 167, 69, 0.25);
        }
        .send-btn {
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-left: 10px;
            background: #28a745;
            border: none;
        }
        .send-btn:hover {
            background: #218838;
        }
        .send-btn:disabled {
            background: #6c757d;
        }
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #6c757d;
        }
        .badge-unread {
            font-size: 0.7rem;
            padding: 3px 8px;
            background: #ffc107;
            color: #212529;
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
            <a class="nav-link" href="add_car.php">
                <i class="fas fa-plus-circle"></i> +Jual Mobil
            </a>
            <a class="nav-link" href="my_cars.php">
                <i class="fas fa-car"></i> Mobil Saya
            </a>
            <a class="nav-link" href="my_bookings.php">
                <i class="fas fa-calendar-check"></i> Booking Saya
            </a>
            <a class="nav-link active" href="messages.php">
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

    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="fas fa-comments text-success me-2"></i>Pesan</h2>
            <div>
                <?php if ($total_unread > 0): ?>
                    <span class="badge bg-success">
                        <i class="fas fa-bell me-1"></i>
                        <?= $total_unread ?> Pesan Baru
                    </span>
                <?php endif; ?>
            </div>
        </div>

        <div class="chat-container">
            <div class="row">
                <!-- Daftar Percakapan -->
                <div class="col-md-4 p-0">
                    <div class="conversations-sidebar">
                        <div class="p-3 bg-light border-bottom">
                            <h5 class="mb-0">Percakapan</h5>
                        </div>
                        <?php if (!empty($conversations)): ?>
                            <?php foreach ($conversations as $conv): ?>
                                <div class="conversation-item <?= ($conv['unread_count'] > 0) ? 'unread' : '' ?> <?= ($selected_conversation && $selected_conversation['id'] == $conv['user_id']) ? 'active' : '' ?>"
                                     onclick="window.location.href='?chat_with=<?= $conv['user_id'] ?>'">
                                    <div class="d-flex align-items-center">
                                        <div class="flex-shrink-0">
                                            <?php if (!empty($conv['foto_profil'])): ?>
                                                <img src="../uploads/profiles/<?= htmlspecialchars($conv['foto_profil']) ?>" 
                                                     class="user-avatar" 
                                                     alt="<?= htmlspecialchars($conv['user_name']) ?>">
                                            <?php else: ?>
                                                <div class="user-avatar bg-secondary d-flex align-items-center justify-content-center">
                                                    <i class="fas fa-user text-white"></i>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="flex-grow-1 ms-3">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <h6 class="mb-0"><?= htmlspecialchars($conv['user_name']) ?></h6>
                                                <?php if ($conv['unread_count'] > 0): ?>
                                                    <span class="badge bg-warning badge-unread"><?= $conv['unread_count'] ?></span>
                                                <?php endif; ?>
                                            </div>
                                            <p class="text-muted small mb-0">
                                                <?= htmlspecialchars(substr($conv['last_message'] ?? 'Belum ada pesan', 0, 30)) ?>...
                                            </p>
                                            <small class="text-muted">
                                                <?= $conv['last_message_time'] ? date('H:i', strtotime($conv['last_message_time'])) : '' ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-comments fa-2x mb-3"></i>
                                <p>Belum ada percakapan</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Area Chat -->
                <div class="col-md-8 p-0">
                    <div class="chat-area">
                        <?php if ($selected_conversation): ?>
                            <!-- Header Chat -->
                            <div class="chat-header">
                                <div class="d-flex align-items-center">
                                    <?php if (!empty($selected_conversation['foto_profil'])): ?>
                                        <img src="../uploads/profiles/<?= htmlspecialchars($selected_conversation['foto_profil']) ?>" 
                                             class="user-avatar me-3" 
                                             alt="<?= htmlspecialchars($selected_conversation['nama_lengkap']) ?>">
                                    <?php else: ?>
                                        <div class="user-avatar bg-secondary me-3 d-flex align-items-center justify-content-center">
                                            <i class="fas fa-user text-white"></i>
                                        </div>
                                    <?php endif; ?>
                                    <div>
                                        <h5 class="mb-0"><?= htmlspecialchars($selected_conversation['nama_lengkap']) ?></h5>
                                        <small class="text-muted">Pembeli</small>
                                    </div>
                                </div>
                            </div>

                            <!-- Daftar Pesan -->
                            <div class="messages-container" id="messagesContainer">
                                <?php if (!empty($messages)): ?>
                                    <?php foreach ($messages as $msg): ?>
                                        <div class="message <?= ($msg['pengirim_id'] == $seller_id) ? 'sent' : 'received' ?>">
                                            <?php if ($msg['pengirim_id'] != $seller_id): ?>
                                                <small class="fw-bold d-block mb-1">
                                                    <?= htmlspecialchars($msg['pengirim_nama']) ?>
                                                </small>
                                            <?php endif; ?>
                                            <div class="message-content">
                                                <?= nl2br(htmlspecialchars($msg['pesan'])) ?>
                                            </div>
                                            <div class="message-time">
                                                <?= date('H:i', strtotime($msg['created_at'])) ?>
                                                <?php if ($msg['pengirim_id'] == $seller_id): ?>
                                                    <?= $msg['dibaca'] ? '✓✓' : '✓' ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="empty-state">
                                        <i class="fas fa-comment-slash fa-2x mb-3"></i>
                                        <p>Belum ada pesan</p>
                                        <small>Mulai percakapan dengan mengirim pesan</small>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Form Kirim Pesan -->
                            <div class="p-3 border-top bg-white">
                                <form method="POST" id="messageForm" onsubmit="sendMessage(event)">
                                    <input type="hidden" name="penerima_id" id="receiverId" value="<?= $selected_conversation['id'] ?>">
                                    <div class="input-group">
                                        <input type="text" name="pesan" id="messageInput" class="form-control message-input" 
                                               placeholder="Ketik pesan..." 
                                               autocomplete="off">
                                        <button type="submit" class="btn btn-success send-btn" id="sendBtn">
                                            <i class="fas fa-paper-plane"></i>
                                        </button>
                                    </div>
                                </form>
                            </div>
                        <?php else: ?>
                            <!-- Empty State ketika belum pilih percakapan -->
                            <div class="empty-state h-100 d-flex flex-column justify-content-center">
                                <i class="fas fa-comments fa-3x mb-3 text-muted"></i>
                                <h4>Pilih Percakapan</h4>
                                <p class="text-muted">Pilih percakapan dari daftar untuk mulai mengobrol</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        let lastMessageId = <?= $last_message_id ?>;
        let currentChatUserId = <?= $selected_conversation ? $selected_conversation['id'] : 0 ?>;
        let checkMessagesInterval;

        // Scroll ke pesan terbaru
        function scrollToBottom() {
            const container = document.getElementById('messagesContainer');
            if (container) {
                container.scrollTop = container.scrollHeight;
            }
        }

        // Kirim pesan dengan AJAX
        function sendMessage(e) {
            e.preventDefault();
            
            const messageInput = document.getElementById('messageInput');
            const receiverId = document.getElementById('receiverId');
            const message = messageInput.value.trim();
            
            if (!message || !receiverId.value) return;
            
            const sendBtn = document.getElementById('sendBtn');
            sendBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            sendBtn.disabled = true;
            
            // Kirim ke server
            $.ajax({
                url: 'messages.php',
                type: 'POST',
                data: {
                    ajax_send: 1,
                    penerima_id: receiverId.value,
                    pesan: message
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        // Tambah pesan ke UI
                        addMessageToUI({
                            id: 'temp_' + Date.now(),
                            pengirim_id: <?= $seller_id ?>,
                            penerima_id: receiverId.value,
                            pesan: message,
                            created_at: new Date().toISOString(),
                            pengirim_nama: 'Anda',
                            dibaca: 0
                        }, true);
                        
                        // Clear input
                        messageInput.value = '';
                        messageInput.focus();
                    } else {
                        alert('Gagal mengirim pesan: ' + response.message);
                    }
                    
                    sendBtn.innerHTML = '<i class="fas fa-paper-plane"></i>';
                    sendBtn.disabled = false;
                },
                error: function() {
                    alert('Terjadi kesalahan saat mengirim pesan');
                    sendBtn.innerHTML = '<i class="fas fa-paper-plane"></i>';
                    sendBtn.disabled = false;
                }
            });
        }

        // Tambah pesan ke UI
        function addMessageToUI(message, isSentByMe = false) {
            const container = document.getElementById('messagesContainer');
            const emptyState = container.querySelector('.empty-state');
            
            // Hapus empty state jika ada
            if (emptyState) {
                emptyState.remove();
            }
            
            const isSender = message.pengirim_id == <?= $seller_id ?>;
            const messageTime = new Date(message.created_at);
            
            const messageDiv = document.createElement('div');
            messageDiv.className = `message ${isSender ? 'sent' : 'received'}`;
            
            let content = '';
            if (!isSender) {
                content += `<small class="fw-bold d-block mb-1">${escapeHtml(message.pengirim_nama)}</small>`;
            }
            
            content += `
                <div class="message-content">${escapeHtml(message.pesan).replace(/\n/g, '<br>')}</div>
                <div class="message-time">
                    ${messageTime.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}
                    ${isSender ? (message.dibaca ? '✓✓' : '✓') : ''}
                </div>
            `;
            
            messageDiv.innerHTML = content;
            container.appendChild(messageDiv);
            
            // Scroll ke bawah
            scrollToBottom();
        }

        // Cek pesan baru secara berkala
        function checkNewMessages() {
            if (!currentChatUserId) return;
            
            $.ajax({
                url: 'messages.php',
                type: 'GET',
                data: {
                    ajax_get_messages: 1,
                    user_id: currentChatUserId,
                    last_id: lastMessageId
                },
                dataType: 'json',
                success: function(response) {
                    if (response.messages && response.messages.length > 0) {
                        response.messages.forEach(message => {
                            addMessageToUI(message);
                            lastMessageId = Math.max(lastMessageId, message.id);
                        });
                    }
                }
            });
        }

        // Escape HTML untuk keamanan
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Inisialisasi
        $(document).ready(function() {
            // Scroll ke bawah saat halaman dimuat
            scrollToBottom();
            
            // Cek pesan baru setiap 3 detik jika ada percakapan aktif
            if (currentChatUserId) {
                checkMessagesInterval = setInterval(checkNewMessages, 3000);
            }
            
            // Auto focus ke input pesan
            const messageInput = document.getElementById('messageInput');
            if (messageInput) {
                messageInput.focus();
                
                // Enable/disable send button
                messageInput.addEventListener('input', function() {
                    const sendBtn = document.getElementById('sendBtn');
                    sendBtn.disabled = this.value.trim() === '';
                });
                
                // Kirim dengan Enter
                messageInput.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter' && !e.shiftKey) {
                        e.preventDefault();
                        sendMessage(e);
                    }
                });
            }
        });

        // Cleanup interval ketika pindah halaman
        window.addEventListener('beforeunload', function() {
            if (checkMessagesInterval) {
                clearInterval(checkMessagesInterval);
            }
        });
    </script>
</body>
</html>