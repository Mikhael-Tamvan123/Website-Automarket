<?php
// upload.php
require_once 'config.php';

class FileUpload {
    private $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
    private $max_size = 5 * 1024 * 1024; // 5MB
    
    public function uploadCarPhoto($file, $car_id) {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'message' => 'Error uploading file'];
        }
        
        // Cek tipe file
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($file_extension, $this->allowed_types)) {
            return ['success' => false, 'message' => 'File type not allowed'];
        }
        
        // Cek ukuran file
        if ($file['size'] > $this->max_size) {
            return ['success' => false, 'message' => 'File too large'];
        }
        
        // Generate nama file unik
        $new_filename = 'car_' . $car_id . '_' . time() . '.' . $file_extension;
        $upload_path = 'uploads/cars/' . $new_filename;
        
        // Buat folder jika belum ada
        if (!is_dir('uploads/cars')) {
            mkdir('uploads/cars', 0777, true);
        }
        
        if (move_uploaded_file($file['tmp_name'], $upload_path)) {
            return ['success' => true, 'filename' => $new_filename];
        } else {
            return ['success' => false, 'message' => 'Failed to move uploaded file'];
        }
    }
    
    public function uploadSignature($file) {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'message' => 'Error uploading file'];
        }
        
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($file_extension, $this->allowed_types)) {
            return ['success' => false, 'message' => 'File type not allowed'];
        }
        
        if ($file['size'] > $this->max_size) {
            return ['success' => false, 'message' => 'File too large'];
        }
        
        $new_filename = 'signature_' . time() . '.' . $file_extension;
        $upload_path = 'uploads/signatures/' . $new_filename;
        
        if (!is_dir('uploads/signatures')) {
            mkdir('uploads/signatures', 0777, true);
        }
        
        if (move_uploaded_file($file['tmp_name'], $upload_path)) {
            return ['success' => true, 'filename' => $new_filename];
        } else {
            return ['success' => false, 'message' => 'Failed to move uploaded file'];
        }
    }
}

$fileUpload = new FileUpload();
?>