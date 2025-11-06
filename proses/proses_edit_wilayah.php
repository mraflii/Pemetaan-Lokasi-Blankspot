<?php
include "../config/db.php";
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $wilayah   = $_POST['wilayah'] ?? [];
    $kodeBaru  = $_POST['kode'] ?? [];
    $provinsi  = $_POST['provinsi'] ?? '';

    if (empty($wilayah) || empty($provinsi)) {
        die("<script>alert('❌ Data tidak lengkap!'); window.history.back();</script>");
    }

    $updated = 0;
    $alreadyExists = [];
    $dibuat_oleh = $_SESSION['username'] ?? 'System';

    foreach ($wilayah as $kodeLama => $nama) {
        $nama = trim($nama);
        $kodeBaruVal = trim($kodeBaru[$kodeLama] ?? $kodeLama);

        if ($nama !== '' && $kodeBaruVal !== '') {
            // Ambil data lama
            $row = $conn->query("SELECT * FROM wilayah WHERE kode_wilayah='$kodeLama'")->fetch_assoc();
            if (!$row) continue;

            $level = $row['level'];
            $parent = $row['parent_kode'];
            $namaLama = $row['nama'];
            $kodeLamaAsli = $row['kode_wilayah'];

            // CEK: Hanya proses jika ada perubahan
            if ($namaLama === $nama && $kodeLamaAsli === $kodeBaruVal) {
                continue; // Skip jika tidak ada perubahan
            }

            $dataSebelum = [
                'kode_wilayah' => $kodeLamaAsli,
                'nama' => $namaLama,
                'level' => $level,
                'parent_kode' => $parent
            ];

            // Cek duplikat
            $cekKode = $conn->query("SELECT 1 FROM wilayah WHERE kode_wilayah='$kodeBaruVal' AND kode_wilayah != '$kodeLama'");
            if ($cekKode && $cekKode->num_rows > 0) {
                $alreadyExists[] = "Kode $kodeBaruVal";
                continue;
            }

            $cekNama = $conn->query("SELECT 1 FROM wilayah WHERE nama='$nama' AND level='$level' AND parent_kode ".($parent ? "= '$parent'" : "IS NULL")." AND kode_wilayah != '$kodeLama'");
            if ($cekNama && $cekNama->num_rows > 0) {
                $alreadyExists[] = "Nama '$nama' di level $level sudah ada";
                continue;
            }

            // Update data
            $stmt = $conn->prepare("UPDATE wilayah SET kode_wilayah=?, nama=? WHERE kode_wilayah=?");
            $stmt->bind_param("sss", $kodeBaruVal, $nama, $kodeLama);

            if ($stmt->execute()) {
                $updated++;
                
                // === CATAT KE RIWAYAT HANYA JIKA ADA PERUBAHAN ===
                $jenis_aktivitas = 'EDIT_WILAYAH';
                $deskripsi = "Mengedit wilayah $level";
                
                $dataSesudah = [
                    'kode_wilayah' => $kodeBaruVal,
                    'nama' => $nama,
                    'level' => $level,
                    'parent_kode' => $parent
                ];
                
                // Detail perubahan
                if ($kodeLamaAsli !== $kodeBaruVal && $namaLama !== $nama) {
                    $deskripsi = "Mengedit wilayah $level: $namaLama → $nama (Kode: $kodeLamaAsli → $kodeBaruVal)";
                } elseif ($kodeLamaAsli !== $kodeBaruVal) {
                    $deskripsi = "Mengedit kode wilayah $level $namaLama: $kodeLamaAsli → $kodeBaruVal";
                } elseif ($namaLama !== $nama) {
                    $deskripsi = "Mengedit nama wilayah $level: $namaLama → $nama";
                }
                
                // Siapkan data JSON sebelum bind_param
                $dataSebelumJson = json_encode($dataSebelum);
                $dataSesudahJson = json_encode($dataSesudah);
                
                $stmt_riwayat = $conn->prepare("INSERT INTO riwayat_aktivitas 
                    (jenis_aktivitas, kode_data, nama_data, deskripsi, data_sebelum, data_sesudah, dibuat_oleh) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)");
                
                // Gunakan variabel langsung, bukan reference
                $stmt_riwayat->bind_param("sssssss", 
                    $jenis_aktivitas, 
                    $kodeBaruVal,
                    $nama, 
                    $deskripsi, 
                    $dataSebelumJson, 
                    $dataSesudahJson, 
                    $dibuat_oleh
                );
                
                $stmt_riwayat->execute();
                $stmt_riwayat->close();
            }
            $stmt->close();
        }
    }

    $conn->close();

    // Buat pesan alert
    $alertMsg = "✅ $updated data wilayah berhasil diupdate";
    if (!empty($alreadyExists)) {
        $alertMsg .= "\\n⚠️ Data tidak dapat di update karena sudah ada:\\n" . implode("\\n", $alreadyExists);
    }

    if ($updated > 0) {
        $alertMsg .= "\\n📝 Perubahan telah tercatat dalam riwayat";
    } else {
        $alertMsg .= "\\nℹ️ Tidak ada perubahan yang dilakukan";
    }

    echo "<script>
        alert('$alertMsg');
        window.location.href='../hasil_pemetaan.php';
    </script>";
    exit;
} else {
    die("Akses tidak valid.");
}
?>