<?php
include "../config/db.php";
session_start();

header('Content-Type: application/json');

// Log untuk debug
error_log("=== DEBUG PROCESS EDIT WILAYAH ===");
error_log("POST Data: " . print_r($_POST, true));

// Fungsi untuk mencatat riwayat aktivitas
function catatRiwayatWilayah($conn, $jenis_aktivitas, $kode_data, $nama_data, $deskripsi, $data_sebelum = null, $data_sesudah = null) {
    $username = $_SESSION['username'] ?? 'system';
    $created_at = date('Y-m-d H:i:s');
    
    // Escape data untuk keamanan
    $jenis_aktivitas = $conn->real_escape_string($jenis_aktivitas);
    $kode_data = $conn->real_escape_string($kode_data);
    $nama_data = $conn->real_escape_string($nama_data);
    $deskripsi = $conn->real_escape_string($deskripsi);
    $data_sebelum = $data_sebelum ? "'" . $conn->real_escape_string(json_encode($data_sebelum, JSON_UNESCAPED_UNICODE)) . "'" : 'NULL';
    $data_sesudah = $data_sesudah ? "'" . $conn->real_escape_string(json_encode($data_sesudah, JSON_UNESCAPED_UNICODE)) . "'" : 'NULL';
    $dibuat_oleh = $conn->real_escape_string($username);
    
    $query = "INSERT INTO riwayat_aktivitas 
              (jenis_aktivitas, kode_data, nama_data, deskripsi, data_sebelum, data_sesudah, dibuat_oleh, created_at) 
              VALUES ('$jenis_aktivitas', '$kode_data', '$nama_data', '$deskripsi', $data_sebelum, $data_sesudah, '$dibuat_oleh', '$created_at')";
    
    error_log("Query Riwayat: $query");
    return $conn->query($query);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $kode_lama = $_POST['kode_lama'] ?? '';
    $kode_baru = $_POST['kode_baru'] ?? '';
    $nama = trim($_POST['nama'] ?? '');
    $provinsi = $_POST['provinsi'] ?? '';

    error_log("Kode Lama: $kode_lama");
    error_log("Kode Baru: $kode_baru"); 
    error_log("Nama: $nama");
    error_log("Provinsi: $provinsi");

    // Validasi sederhana
    if (empty($kode_lama) || empty($kode_baru) || empty($nama)) {
        error_log("VALIDASI GAGAL: Data tidak lengkap");
        echo json_encode(['success' => false, 'message' => 'Data tidak lengkap!']);
        exit;
    }

    // Cek data lama
    $check_query = $conn->query("SELECT * FROM wilayah WHERE kode_wilayah = '$kode_lama'");
    if ($check_query->num_rows === 0) {
        error_log("DATA TIDAK DITEMUKAN: $kode_lama");
        echo json_encode(['success' => false, 'message' => 'Data wilayah tidak ditemukan!']);
        exit;
    }

    $data_lama = $check_query->fetch_assoc();
    error_log("Data Lama: " . print_r($data_lama, true));

    // Update data
    $update_query = "UPDATE wilayah SET kode_wilayah = '$kode_baru', nama = '$nama' WHERE kode_wilayah = '$kode_lama'";
    error_log("Query Update: $update_query");

    if ($conn->query($update_query)) {
        error_log("UPDATE BERHASIL");
        
        // Data sesudah edit
        $data_sesudah = [
            'kode_wilayah' => $kode_baru,
            'nama' => $nama,
            'level' => $data_lama['level'],
            'parent_kode' => $data_lama['parent_kode']
        ];
        
        // Catat riwayat edit wilayah
        $deskripsi = "Edit data wilayah " . $data_lama['level'] . ": " . $data_lama['nama'] . " → " . $nama;
        
        $riwayat_success = catatRiwayatWilayah(
            $conn, 
            'EDIT_WILAYAH', 
            $kode_lama, 
            $nama, 
            $deskripsi, 
            $data_lama, 
            $data_sesudah
        );
        
        if ($riwayat_success) {
            error_log("RIWAYAT BERHASIL DICATAT");
        } else {
            error_log("GAGAL MENCATAT RIWAYAT: " . $conn->error);
        }
        
        echo json_encode([
            'success' => true, 
            'message' => 'Data wilayah berhasil diperbarui!'
        ]);
    } else {
        $error = $conn->error;
        error_log("UPDATE GAGAL: $error");
        echo json_encode([
            'success' => false, 
            'message' => 'Gagal memperbarui data: ' . $error
        ]);
    }

} else {
    error_log("METHOD TIDAK DIIZINKAN");
    echo json_encode(['success' => false, 'message' => 'Method tidak diizinkan!']);
}

$conn->close();
?>