<?php
include "config/db.php";
header('Content-Type: application/json');

$kode_wilayah = $_GET['kode_wilayah'] ?? '';

if (empty($kode_wilayah)) {
    echo json_encode(['success' => false, 'message' => 'Kode wilayah tidak valid']);
    exit;
}

$query = $conn->query("SELECT * FROM wilayah WHERE kode_wilayah = '$kode_wilayah'");
if ($query->num_rows > 0) {
    $data = $query->fetch_assoc();
    
    // Cari data provinsi untuk wilayah ini
    $provinsi_kode = '';
    if ($data['level'] == 'provinsi') {
        $provinsi_kode = $data['kode_wilayah'];
    } else {
        // Untuk level lainnya, cari kode provinsi melalui parent
        $current = $data;
        while ($current && $current['level'] != 'provinsi') {
            $parent_query = $conn->query("SELECT * FROM wilayah WHERE kode_wilayah = '{$current['parent_kode']}'");
            if ($parent_query->num_rows > 0) {
                $current = $parent_query->fetch_assoc();
            } else {
                break;
            }
        }
        $provinsi_kode = $current['kode_wilayah'] ?? '';
    }
    
    // Cari nama parent jika ada
    $parent_nama = '';
    if (!empty($data['parent_kode'])) {
        $parent_query = $conn->query("SELECT nama FROM wilayah WHERE kode_wilayah = '{$data['parent_kode']}'");
        if ($parent_query->num_rows > 0) {
            $parent_data = $parent_query->fetch_assoc();
            $parent_nama = $parent_data['nama'];
        }
    }
    
    echo json_encode([
        'success' => true,
        'kode_wilayah' => $data['kode_wilayah'],
        'nama' => $data['nama'],
        'level' => $data['level'],
        'provinsi_kode' => $provinsi_kode,
        'parent_nama' => $parent_nama
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Data tidak ditemukan']);
}
?>