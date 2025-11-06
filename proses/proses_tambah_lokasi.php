<?php
include "../config/db.php";

// --- Ambil data dari form ---
$provinsi            = $_POST['provinsi'] ?? '';
$kota                = $_POST['kota'] ?? '';
$kecamatan           = $_POST['kecamatan'] ?? '';
$desa                = $_POST['desa'] ?? '';
$kode_lokasi         = $_POST['kode_lokasi'] ?? '';
$nama_tempat         = $_POST['nama_tempat'] ?? '';
$koordinat           = $_POST['koordinat'] ?? '';
$keterangan          = $_POST['keterangan'] ?? '';
$ketersediaan_sinyal = $_POST['ketersediaan_sinyal'] ?? 'No';
$kecepatan_sinyal    = $_POST['kecepatan_sinyal'] ?? 0;

// --- Validasi data wajib ---
if (!$provinsi || !$kota || !$kecamatan || !$desa || !$kode_lokasi || !$nama_tempat || !$koordinat) {
    die("<script>alert('❌ Data tidak lengkap!'); window.history.back();</script>");
}

// --- 1. Cek duplikasi kode lokasi ---
$stmt_check = $conn->prepare("SELECT 1 FROM lokasi WHERE kode_lokasi = ?");
$stmt_check->bind_param("s", $kode_lokasi);
$stmt_check->execute();
$result_check = $stmt_check->get_result();
if ($result_check->num_rows > 0) {
    echo "<script>alert('❌ Kode lokasi \"$kode_lokasi\" sudah ada!'); window.location.href = '../create_lokasi.php';</script>";
    exit;
}

// --- 2. Cek duplikasi nama tempat di desa yang sama ---
$stmt_check_name = $conn->prepare("SELECT 1 FROM lokasi WHERE kode_wilayah = ? AND nama_tempat = ?");
$stmt_check_name->bind_param("ss", $desa, $nama_tempat);
$stmt_check_name->execute();
$result_check_name = $stmt_check_name->get_result();
if ($result_check_name->num_rows > 0) {
    echo "<script>alert('❌ Nama tempat \"$nama_tempat\" di desa ini sudah ada!'); window.location.href = '../create_lokasi.php';</script>";
    exit;
}

// --- Insert data lokasi ---
$stmt = $conn->prepare("
    INSERT INTO lokasi (
        kode_wilayah, kode_lokasi, nama_tempat, koordinat, 
        keterangan, ketersediaan_sinyal, kecepatan_sinyal
    ) VALUES (?, ?, ?, ?, ?, ?, ?)
");
$stmt->bind_param(
    "ssssssi",
    $desa, $kode_lokasi, $nama_tempat, $koordinat,
    $keterangan, $ketersediaan_sinyal, $kecepatan_sinyal
);

if ($stmt->execute()) {
    // --- CATAT RIWAYAT AKTIVITAS (MANUAL) ---
    $deskripsi = "Lokasi \"$nama_tempat\" ditambahkan";
    
    $data_sesudah = json_encode([
        'kode_lokasi' => $kode_lokasi,
        'nama_tempat' => $nama_tempat,
        'kode_wilayah' => $desa,
        'koordinat' => $koordinat,
        'keterangan' => $keterangan,
        'ketersediaan_sinyal' => $ketersediaan_sinyal,
        'kecepatan_sinyal' => $kecepatan_sinyal,
        'created_at' => date('Y-m-d H:i:s')
    ]);
    
    $sqlRiwayat = "INSERT INTO riwayat_aktivitas 
                  (jenis_aktivitas, kode_data, nama_data, deskripsi, data_sesudah, dibuat_oleh) 
                  VALUES 
                  ('TAMBAH_LOKASI', '$kode_lokasi', '$nama_tempat', '$deskripsi', '$data_sesudah', 'System')";
    
    $conn->query($sqlRiwayat);
    
    echo "<script>
            alert('✅ Lokasi baru berhasil ditambahkan dengan kode lokasi: ".addslashes($kode_lokasi)."');
            window.location.href = '../hasil_pemetaan.php';
          </script>";
} else {
    echo "<script>
            alert('❌ Gagal menambahkan lokasi: ".addslashes($conn->error)."');
            window.location.href = '../create_lokasi.php';
          </script>";
}

$stmt->close();
$conn->close();
?>