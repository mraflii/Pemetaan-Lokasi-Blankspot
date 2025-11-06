<?php
include "config/db.php";
session_start();

// Set timezone
date_default_timezone_set('Asia/Jakarta');

// Cek koneksi database
if (!$conn) {
    die(json_encode(['success' => false, 'error' => 'Koneksi database gagal']));
}

// Ambil data dari POST
$input = json_decode(file_get_contents('php://input'), true);

if ($input && isset($input['filters'])) {
    $filters = $input['filters'];
    $jenis = $input['jenis'] ?? 'pdf';
    $nama_laporan = $input['nama_laporan'] ?? 'Cetak PDF Laporan ' . date('d/m/Y H:i');
    $dibuat_oleh = $_SESSION['username'] ?? 'System';
    
    // HANYA catat untuk PDF dan Excel, TIDAK untuk view
    if (!in_array($jenis, ['pdf', 'excel'])) {
        echo json_encode([
            'success' => false, 
            'error' => 'Jenis laporan tidak valid. Hanya PDF dan Excel yang dicatat.'
        ]);
        exit;
    }
    
    // Generate nama file
    $timestamp = date('Y-m-d_H-i-s');
    $path_file = 'reports/' . $jenis . '_' . $timestamp . '.' . $jenis;
    
    $filters_json = json_encode($filters, JSON_UNESCAPED_UNICODE);
    
    // Simpan ke database
    $stmt = $conn->prepare("INSERT INTO riwayat_laporan (nama_laporan, jenis_laporan, filter, dibuat_oleh, path_file) VALUES (?, ?, ?, ?, ?)");
    
    if ($stmt) {
        $stmt->bind_param("sssss", $nama_laporan, $jenis, $filters_json, $dibuat_oleh, $path_file);
        
        if ($stmt->execute()) {
            echo json_encode([
                'success' => true, 
                'message' => 'Aktivitas cetak tercatat',
                'id' => $stmt->insert_id
            ]);
        } else {
            error_log("Error executing statement: " . $stmt->error);
            echo json_encode([
                'success' => false, 
                'error' => 'Gagal menyimpan ke database: ' . $stmt->error
            ]);
        }
        $stmt->close();
    } else {
        error_log("Error preparing statement: " . $conn->error);
        echo json_encode([
            'success' => false, 
            'error' => 'Gagal mempersiapkan query: ' . $conn->error
        ]);
    }
} else {
    echo json_encode([
        'success' => false, 
        'error' => 'Data tidak valid atau filter tidak tersedia'
    ]);
}

$conn->close();
?>