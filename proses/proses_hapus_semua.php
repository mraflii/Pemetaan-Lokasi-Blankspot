<?php
include "../config/db.php";
session_start();

// Cek parameter
if (!isset($_GET['kode_lokasi'])) {
    die("Kode lokasi tidak ditemukan!");
}

$kode_lokasi = $_GET['kode_lokasi'];
$dibuat_oleh = $_SESSION['username'] ?? 'System';

// Ambil data lokasi
$sqlLokasi = "SELECT * FROM lokasi WHERE kode_lokasi='$kode_lokasi'";
$lokasi = $conn->query($sqlLokasi)->fetch_assoc();

if (!$lokasi) {
    die("Data lokasi tidak ditemukan!");
}

// Mulai transaksi
$conn->begin_transaction();

try {
    // Simpan data sebelum dihapus untuk riwayat
    $data_lokasi_sebelum = [
        'kode_lokasi' => $lokasi['kode_lokasi'],
        'nama_tempat' => $lokasi['nama_tempat'],
        'kode_wilayah' => $lokasi['kode_wilayah'],
        'koordinat' => $lokasi['koordinat'],
        'keterangan' => $lokasi['keterangan'],
        'ketersediaan_sinyal' => $lokasi['ketersediaan_sinyal'],
        'kecepatan_sinyal' => $lokasi['kecepatan_sinyal'],
        'created_at' => $lokasi['created_at'],
        'deleted_at' => date('Y-m-d H:i:s')
    ];

    // Catat riwayat hapus lokasi SEBELUM menghapus data
    $deskripsi_lokasi = "Lokasi \"{$lokasi['nama_tempat']}\" dihapus";
    $data_sebelum_lokasi = json_encode($data_lokasi_sebelum);

    $sqlRiwayatLokasi = "INSERT INTO riwayat_aktivitas 
                        (jenis_aktivitas, kode_data, nama_data, deskripsi, data_sebelum, dibuat_oleh) 
                        VALUES 
                        ('HAPUS_LOKASI', '$kode_lokasi', '{$lokasi['nama_tempat']}', '$deskripsi_lokasi', '$data_sebelum_lokasi', '$dibuat_oleh')";

    $conn->query($sqlRiwayatLokasi);

    // HAPUS LOKASI SAJA - TIDAK HAPUS WILAYAH
    $conn->query("DELETE FROM lokasi WHERE kode_lokasi='$kode_lokasi'");

    // Commit transaksi
    $conn->commit();

    // Pesan sukses
    $pesan_sukses = "✅ Data lokasi \"{$lokasi['nama_tempat']}\" berhasil dihapus!";

    echo "<script>
            alert('$pesan_sukses');
            window.location='../hasil_pemetaan.php';
          </script>";

} catch (Exception $e) {
    // Rollback transaksi jika ada error
    $conn->rollback();
    
    echo "<script>
            alert('❌ Gagal menghapus data lokasi: " . addslashes($e->getMessage()) . "');
            window.history.back();
          </script>";
}
?>