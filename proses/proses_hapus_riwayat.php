<?php
include "../config/db.php";
session_start();

header('Content-Type: application/json');

// Cek koneksi database
if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Koneksi database gagal']);
    exit;
}

// Cek method request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method tidak diizinkan']);
    exit;
}

// Ambil data dari POST
$tipe = $_POST['tipe'] ?? '';
$kode = $_POST['kode'] ?? '';
$jenis = $_POST['jenis'] ?? '';
$id = $_POST['id'] ?? 0;

try {
    if ($tipe === 'lokasi') {
        // Hapus riwayat lokasi dari tabel riwayat_aktivitas
        $sql = "DELETE FROM riwayat_aktivitas 
                WHERE jenis_aktivitas IN ('TAMBAH_LOKASI', 'EDIT_LOKASI', 'HAPUS_LOKASI') 
                AND kode_data = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $kode);
        
    } elseif ($tipe === 'laporan') {
        // Hapus riwayat laporan dari tabel riwayat_laporan
        $sql = "DELETE FROM riwayat_laporan WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);
        
    } elseif ($tipe === 'wilayah') {
        // Hapus riwayat wilayah dari tabel riwayat_aktivitas
        $sql = "DELETE FROM riwayat_aktivitas 
                WHERE jenis_aktivitas IN ('TAMBAH_WILAYAH', 'EDIT_WILAYAH', 'HAPUS_WILAYAH') 
                AND kode_data = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $kode);
        
    } else {
        echo json_encode(['success' => false, 'message' => 'Tipe riwayat tidak valid']);
        exit;
    }
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Riwayat berhasil dihapus']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal menghapus riwayat: ' . $stmt->error]);
    }
    
    $stmt->close();
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

$conn->close();
?>