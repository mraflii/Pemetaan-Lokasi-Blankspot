<?php
include "../config/db.php";
session_start();

$level        = trim($_POST['level'] ?? '');
$kode_wilayah = trim($_POST['kode_wilayah'] ?? '');
$nama         = trim($_POST['nama'] ?? '');
$provinsi     = trim($_POST['provinsi'] ?? '');
$kota         = trim($_POST['kota'] ?? '');
$kecamatan    = trim($_POST['kecamatan'] ?? '');

if(!$level || !$kode_wilayah || !$nama){
    exit("<script>alert('❌ Data tidak lengkap!'); window.history.back();</script>");
}

$parent_kode = null;
switch($level){
    case 'provinsi':
        $parent_kode = null;
        break;
    case 'kota':
        if(!$provinsi) exit("<script>alert('❌ Provinsi harus dipilih untuk kota!'); window.history.back();</script>");
        $parent_kode = $provinsi;
        break;
    case 'kecamatan':
        if(!$provinsi || !$kota) exit("<script>alert('❌ Provinsi & Kota harus dipilih untuk kecamatan!'); window.history.back();</script>");
        $parent_kode = $kota;
        break;
    case 'desa':
        if(!$provinsi || !$kota || !$kecamatan) exit("<script>alert('❌ Provinsi, Kota & Kecamatan harus dipilih untuk desa!'); window.history.back();</script>");
        $parent_kode = $kecamatan;
        break;
    default:
        exit("<script>alert('❌ Level wilayah tidak valid!'); window.history.back();</script>");
}

// Cek duplikasi kode
$stmt_check = $conn->prepare("SELECT 1 FROM wilayah WHERE kode_wilayah=?");
$stmt_check->bind_param("s", $kode_wilayah);
$stmt_check->execute();
$res = $stmt_check->get_result();
if($res->num_rows > 0){
    exit("<script>alert('❌ Kode wilayah sudah ada!'); window.history.back();</script>");
}
$stmt_check->close();

// Cek duplikasi nama di dalam parent
if($parent_kode){
    $stmt_name = $conn->prepare("SELECT 1 FROM wilayah WHERE nama=? AND parent_kode=? AND level=?");
    $stmt_name->bind_param("sss", $nama, $parent_kode, $level);
} else {
    // Untuk provinsi (tanpa parent)
    $stmt_name = $conn->prepare("SELECT 1 FROM wilayah WHERE nama=? AND level=?");
    $stmt_name->bind_param("ss", $nama, $level);
}
$stmt_name->execute();
$res_name = $stmt_name->get_result();
if($res_name->num_rows > 0){
    exit("<script>alert('❌ Nama wilayah sudah ada di level/parent ini!'); window.history.back();</script>");
}
$stmt_name->close();

// Insert data wilayah
if($level === 'provinsi'){
    $stmt = $conn->prepare("INSERT INTO wilayah (kode_wilayah, nama, level) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $kode_wilayah, $nama, $level);
} else {
    $stmt = $conn->prepare("INSERT INTO wilayah (kode_wilayah, nama, level, parent_kode) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $kode_wilayah, $nama, $level, $parent_kode);
}

if($stmt->execute()){
    
    // === TAMBAHKAN KE RIWAYAT ===
    $jenis_aktivitas = 'TAMBAH_WILAYAH';
    $deskripsi = "Wilayah $level \"$nama\" ditambahkan";
    $data_sesudah = json_encode([
        'kode_wilayah' => $kode_wilayah,
        'nama' => $nama,
        'level' => $level,
        'parent_kode' => $parent_kode,
        'created_at' => date('Y-m-d H:i:s')
    ]);
    
    $dibuat_oleh = $_SESSION['username'] ?? 'System';
    
    $stmt_riwayat = $conn->prepare("INSERT INTO riwayat_aktivitas 
        (jenis_aktivitas, kode_data, nama_data, deskripsi, data_sebelum, data_sesudah, dibuat_oleh) 
        VALUES (?, ?, ?, ?, NULL, ?, ?)");
    $stmt_riwayat->bind_param("ssssss", $jenis_aktivitas, $kode_wilayah, $nama, $deskripsi, $data_sesudah, $dibuat_oleh);
    
    if($stmt_riwayat->execute()){
        echo "<script>
                alert('✅ Wilayah berhasil ditambahkan dengan kode: $kode_wilayah');
                window.location.href='../hasil_pemetaan.php';
              </script>";
    } else {
        echo "<script>
                alert('✅ Wilayah berhasil ditambahkan, tetapi gagal mencatat riwayat: ".addslashes($stmt_riwayat->error)."');
                window.location.href='../hasil_pemetaan.php';
              </script>";
    }
    $stmt_riwayat->close();
    
} else {
    echo "<script>
            alert('❌ Gagal menambahkan wilayah: ".addslashes($stmt->error)."');
            window.history.back();
          </script>";
}
$stmt->close();
$conn->close();
?>