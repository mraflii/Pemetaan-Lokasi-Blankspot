<?php
include "../config/db.php";

// Cek apakah form disubmit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Ambil data dari form
    $kode_lokasi = $conn->real_escape_string($_POST['kode_lokasi']);
    $nama_tempat = $conn->real_escape_string($_POST['nama_tempat']);
    $koordinat = $conn->real_escape_string($_POST['koordinat']);
    $keterangan = $conn->real_escape_string($_POST['keterangan']);
    $ketersediaan_sinyal = $conn->real_escape_string($_POST['ketersediaan_sinyal']);
    $kecepatan_sinyal = floatval($_POST['kecepatan_sinyal']);
    
    // Cek apakah nama tempat sudah ada di lokasi lain
    $cekNama = $conn->query("SELECT 1 FROM lokasi WHERE nama_tempat='$nama_tempat' AND kode_lokasi != '$kode_lokasi'");
    if ($cekNama && $cekNama->num_rows > 0) {
        // Nama tempat sudah ada di baris lain
        echo "<script>alert('Nama tempat \"$nama_tempat\" sudah digunakan oleh lokasi lain.'); window.history.back();</script>";
        exit;
    }
    
    // Ambil data lokasi lama dari database
    $cekLokasi = $conn->query("SELECT * FROM lokasi WHERE kode_lokasi='$kode_lokasi'");
    if (!$cekLokasi || $cekLokasi->num_rows === 0) {
        echo "<script>alert('Data lokasi tidak ditemukan!'); window.location='../hasil_pemetaan.php';</script>";
        exit;
    }
    
    $lokasiLama = $cekLokasi->fetch_assoc();
    
    // CEK APAKAH ADA PERUBAHAN SEBELUM UPDATE
    $ada_perubahan = false;
    $perubahan_detail = [];
    
    if ($lokasiLama['nama_tempat'] != $nama_tempat) {
        $ada_perubahan = true;
        $perubahan_detail[] = "Nama: {$lokasiLama['nama_tempat']} → $nama_tempat";
    }
    if ($lokasiLama['koordinat'] != $koordinat) {
        $ada_perubahan = true;
        $perubahan_detail[] = "Koordinat diubah";
    }
    if ($lokasiLama['ketersediaan_sinyal'] != $ketersediaan_sinyal) {
        $ada_perubahan = true;
        $perubahan_detail[] = "Sinyal: {$lokasiLama['ketersediaan_sinyal']} → $ketersediaan_sinyal";
    }
    if ($lokasiLama['kecepatan_sinyal'] != $kecepatan_sinyal) {
        $ada_perubahan = true;
        $perubahan_detail[] = "Kecepatan: {$lokasiLama['kecepatan_sinyal']} → {$kecepatan_sinyal} Mbps";
    }
    if ($lokasiLama['keterangan'] != $keterangan) {
        $ada_perubahan = true;
        $perubahan_detail[] = "Keterangan diubah";
    }
    
    // Hanya update dan catat riwayat jika ada perubahan
    if ($ada_perubahan) {
        // Simpan data lama untuk riwayat
        $data_lama = [
            'nama_tempat' => $lokasiLama['nama_tempat'],
            'koordinat' => $lokasiLama['koordinat'],
            'keterangan' => $lokasiLama['keterangan'],
            'ketersediaan_sinyal' => $lokasiLama['ketersediaan_sinyal'],
            'kecepatan_sinyal' => $lokasiLama['kecepatan_sinyal']
        ];
        
        // Update data lokasi
        $sqlUpdate = "UPDATE lokasi SET 
                      nama_tempat = '$nama_tempat',
                      koordinat = '$koordinat',
                      keterangan = '$keterangan',
                      ketersediaan_sinyal = '$ketersediaan_sinyal',
                      kecepatan_sinyal = $kecepatan_sinyal,
                      updated_at = NOW()
                      WHERE kode_lokasi = '$kode_lokasi'";
        
        if ($conn->query($sqlUpdate)) {
            // HANYA CATAT RIWAYAT JIKA TIDAK ADA TRIGGER
            // Cek apakah trigger masih aktif dengan melihat riwayat yang baru dibuat
            $cek_trigger = "SELECT COUNT(*) as total FROM riwayat_aktivitas 
                           WHERE kode_data = '$kode_lokasi' 
                           AND created_at >= NOW() - INTERVAL 1 SECOND";
            $result_cek = $conn->query($cek_trigger);
            $row_cek = $result_cek->fetch_assoc();
            
            // Jika belum ada riwayat yang tercatat (trigger tidak aktif), buat manual
            if ($row_cek['total'] == 0) {
                $deskripsi = "Lokasi \"$nama_tempat\" diperbarui";
                
                if (!empty($perubahan_detail)) {
                    $deskripsi .= ". Perubahan: " . implode(", ", $perubahan_detail);
                }
                
                $data_sebelum = json_encode($data_lama);
                $data_sesudah = json_encode([
                    'nama_tempat' => $nama_tempat,
                    'koordinat' => $koordinat,
                    'keterangan' => $keterangan,
                    'ketersediaan_sinyal' => $ketersediaan_sinyal,
                    'kecepatan_sinyal' => $kecepatan_sinyal,
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
                
                $sqlRiwayat = "INSERT INTO riwayat_aktivitas 
                              (jenis_aktivitas, kode_data, nama_data, deskripsi, data_sebelum, data_sesudah, dibuat_oleh) 
                              VALUES 
                              ('EDIT_LOKASI', '$kode_lokasi', '$nama_tempat', '$deskripsi', '$data_sebelum', '$data_sesudah', 'System')";
                
                $conn->query($sqlRiwayat);
            }
            
            // Redirect dengan pesan sukses
            echo "<script>alert('Data lokasi berhasil diperbarui!'); window.location='../hasil_pemetaan.php';</script>";
            exit();
        } else {
            $error = "Error: " . $conn->error;
            echo "<script>alert('Terjadi kesalahan: $error'); window.history.back();</script>";
            exit();
        }
    } else {
        // Tidak ada perubahan, redirect saja
        echo "<script>alert('Tidak ada perubahan data yang dilakukan.'); window.location='../hasil_pemetaan.php';</script>";
        exit();
    }
} else {
    // Jika akses langsung tanpa POST, redirect
    header("Location: ../hasil_pemetaan.php");
    exit();
}
?>