<?php
include "../config/db.php";
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $provinsi_kode = $_POST['provinsi_kode'] ?? '';
    $selected_level = $_POST['selected_level'] ?? '';
    $selected_parent = $_POST['selected_parent'] ?? '';
    
    $messages = [];
    $success_count = 0;
    $error_count = 0;

    // Fungsi untuk insert wilayah dengan pengecekan duplikat
    function insertWilayah($conn, $kode, $nama, $level, $parent = null) {
        $kode = $conn->real_escape_string($kode);
        $nama = $conn->real_escape_string($nama);
        $parent = $parent ? "'" . $conn->real_escape_string($parent) . "'" : "NULL";

        // Cek duplikat berdasarkan kode_wilayah
        $cek = $conn->query("SELECT 1 FROM wilayah WHERE kode_wilayah='$kode'");
        if ($cek && $cek->num_rows == 0) {
            $sql = "INSERT INTO wilayah (kode_wilayah, nama, level, parent_kode)
                    VALUES ('$kode', '$nama', '$level', $parent)";
            if ($conn->query($sql)) {
                return 'inserted';
            } else {
                error_log("Error insert wilayah: " . $conn->error);
                return 'error';
            }
        } else {
            return 'exists';
        }
    }

    // Fungsi untuk generate kode_lokasi dengan format: kode_wilayah.XX
    function generateKodeLokasi($conn, $kode_wilayah) {
        $counter = 1;
        
        do {
            $kode_lokasi = $kode_wilayah . '.' . str_pad($counter, 2, '0', STR_PAD_LEFT); // Format: kode_wilayah.01
            $cek = $conn->query("SELECT 1 FROM lokasi WHERE kode_lokasi='$kode_lokasi'");
            $counter++;
        } while ($cek && $cek->num_rows > 0 && $counter < 100);
        
        return $kode_lokasi;
    }

    // Fungsi untuk insert lokasi dengan pengecekan duplikat
    function insertLokasi($conn, $kode_wilayah, $nama_tempat, $koordinat, $keterangan = '', $ketersediaan_sinyal = 'No', $kecepatan_sinyal = 0) {
        // Generate kode_lokasi dengan format: kode_wilayah.XX
        $kode_lokasi = generateKodeLokasi($conn, $kode_wilayah);
        
        // Cek duplikat nama tempat di wilayah yang sama
        $cek_nama = $conn->query("SELECT 1 FROM lokasi WHERE kode_wilayah='$kode_wilayah' AND nama_tempat='" . $conn->real_escape_string($nama_tempat) . "'");
        if ($cek_nama && $cek_nama->num_rows > 0) {
            return ['status' => 'exists_name', 'kode_lokasi' => ''];
        }
        
        $nama_tempat = $conn->real_escape_string($nama_tempat);
        $koordinat = $conn->real_escape_string($koordinat);
        $keterangan = $conn->real_escape_string($keterangan);
        $ketersediaan_sinyal = $conn->real_escape_string($ketersediaan_sinyal);
        $kecepatan_sinyal = (float)$kecepatan_sinyal;
        
        $sql = "INSERT INTO lokasi (kode_wilayah, kode_lokasi, nama_tempat, koordinat, keterangan, ketersediaan_sinyal, kecepatan_sinyal)
                VALUES ('$kode_wilayah', '$kode_lokasi', '$nama_tempat', '$koordinat', '$keterangan', '$ketersediaan_sinyal', $kecepatan_sinyal)";
        
        if ($conn->query($sql)) {
            return ['status' => 'inserted', 'kode_lokasi' => $kode_lokasi];
        } else {
            error_log("Error insert lokasi: " . $conn->error);
            return ['status' => 'error', 'kode_lokasi' => ''];
        }
    }

    // Fungsi untuk mencatat ke riwayat aktivitas
    function catatRiwayatWilayah($conn, $kode, $nama, $level, $parent = null, $jenis_aktivitas = 'TAMBAH_WILAYAH') {
        $dibuat_oleh = $_SESSION['username'] ?? 'System';
        $deskripsi = "Menambahkan wilayah $level: $nama";
        $data_sesudah = json_encode([
            'kode_wilayah' => $kode,
            'nama' => $nama,
            'level' => $level,
            'parent_kode' => $parent
        ]);
        
        $stmt = $conn->prepare("INSERT INTO riwayat_aktivitas 
            (jenis_aktivitas, kode_data, nama_data, deskripsi, data_sebelum, data_sesudah, dibuat_oleh) 
            VALUES (?, ?, ?, ?, NULL, ?, ?)");
        $stmt->bind_param("ssssss", $jenis_aktivitas, $kode, $nama, $deskripsi, $data_sesudah, $dibuat_oleh);
        $result = $stmt->execute();
        if (!$result) {
            error_log("Error catat riwayat: " . $stmt->error);
        }
        $stmt->close();
        return $result;
    }

    function catatRiwayatLokasi($conn, $kode_lokasi, $nama_tempat, $kode_wilayah, $jenis_aktivitas = 'TAMBAH_LOKASI') {
        $dibuat_oleh = $_SESSION['username'] ?? 'System';
        $deskripsi = "Menambahkan lokasi: $nama_tempat";
        $data_sesudah = json_encode([
            'kode_lokasi' => $kode_lokasi,
            'nama_tempat' => $nama_tempat,
            'kode_wilayah' => $kode_wilayah,
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        $stmt = $conn->prepare("INSERT INTO riwayat_aktivitas 
            (jenis_aktivitas, kode_data, nama_data, deskripsi, data_sebelum, data_sesudah, dibuat_oleh) 
            VALUES (?, ?, ?, ?, NULL, ?, ?)");
        $stmt->bind_param("ssssss", $jenis_aktivitas, $kode_lokasi, $nama_tempat, $deskripsi, $data_sesudah, $dibuat_oleh);
        $result = $stmt->execute();
        if (!$result) {
            error_log("Error catat riwayat: " . $stmt->error);
        }
        $stmt->close();
        return $result;
    }

    // PROSES BERDASARKAN LEVEL YANG DIPILIH
    if ($selected_level === 'kota') {
        // Proses data kota + kecamatan + desa + lokasi
        if (isset($_POST['kota']) && is_array($_POST['kota'])) {
            foreach ($_POST['kota'] as $kotaIndex => $kotaData) {
                if (!empty($kotaData['kode']) && !empty($kotaData['nama'])) {
                    $kode_kota = $provinsi_kode . '.' . $kotaData['kode'];
                    $nama_kota = $kotaData['nama'];
                    
                    // Insert Kota
                    $status = insertWilayah($conn, $kode_kota, $nama_kota, 'kota', $provinsi_kode);
                    
                    if ($status === 'inserted') {
                        $success_count++;
                        catatRiwayatWilayah($conn, $kode_kota, $nama_kota, 'kota', $provinsi_kode);
                        $messages[] = "✅ Kota {$nama_kota} berhasil ditambahkan";
                        
                        // Proses Kecamatan untuk kota ini
                        if (isset($kotaData['kecamatan']) && is_array($kotaData['kecamatan'])) {
                            foreach ($kotaData['kecamatan'] as $kecIndex => $kecData) {
                                if (!empty($kecData['kode']) && !empty($kecData['nama'])) {
                                    $kode_kec = $kode_kota . '.' . $kecData['kode'];
                                    $nama_kec = $kecData['nama'];
                                    
                                    $status_kec = insertWilayah($conn, $kode_kec, $nama_kec, 'kecamatan', $kode_kota);
                                    
                                    if ($status_kec === 'inserted') {
                                        $success_count++;
                                        catatRiwayatWilayah($conn, $kode_kec, $nama_kec, 'kecamatan', $kode_kota);
                                        $messages[] = "  └ ✅ Kecamatan {$nama_kec} berhasil ditambahkan";
                                        
                                        // Proses Desa untuk kecamatan ini
                                        if (isset($kecData['desa']) && is_array($kecData['desa'])) {
                                            foreach ($kecData['desa'] as $desaIndex => $desaData) {
                                                if (!empty($desaData['kode']) && !empty($desaData['nama'])) {
                                                    $kode_desa = $kode_kec . '.' . $desaData['kode'];
                                                    $nama_desa = $desaData['nama'];
                                                    
                                                    $status_desa = insertWilayah($conn, $kode_desa, $nama_desa, 'desa', $kode_kec);
                                                    
                                                    if ($status_desa === 'inserted') {
                                                        $success_count++;
                                                        catatRiwayatWilayah($conn, $kode_desa, $nama_desa, 'desa', $kode_kec);
                                                        $messages[] = "    └ ✅ Desa {$nama_desa} berhasil ditambahkan";
                                                        
                                                        // Proses Lokasi untuk desa ini
                                                        if (isset($desaData['lokasi']) && is_array($desaData['lokasi'])) {
                                                            foreach ($desaData['lokasi'] as $lokasiIndex => $lokasiData) {
                                                                if (!empty($lokasiData['nama_tempat']) && !empty($lokasiData['koordinat'])) {
                                                                    $result_lokasi = insertLokasi(
                                                                        $conn,
                                                                        $kode_desa, // kode_wilayah = kode desa
                                                                        $lokasiData['nama_tempat'],
                                                                        $lokasiData['koordinat'],
                                                                        $lokasiData['keterangan'] ?? '',
                                                                        $lokasiData['ketersediaan_sinyal'] ?? 'No',
                                                                        $lokasiData['kecepatan_sinyal'] ?? 0
                                                                    );
                                                                    
                                                                    if ($result_lokasi['status'] === 'inserted') {
                                                                        $success_count++;
                                                                        catatRiwayatLokasi($conn, $result_lokasi['kode_lokasi'], $lokasiData['nama_tempat'], $kode_desa);
                                                                        $messages[] = "      └ 📍 Lokasi {$lokasiData['nama_tempat']} berhasil ditambahkan (Kode: {$result_lokasi['kode_lokasi']})";
                                                                    } elseif ($result_lokasi['status'] === 'exists_name') {
                                                                        $messages[] = "      └ ⚠️ Lokasi {$lokasiData['nama_tempat']} sudah ada di desa ini";
                                                                    } else {
                                                                        $error_count++;
                                                                        $messages[] = "      └ ❌ Gagal menambah lokasi {$lokasiData['nama_tempat']}";
                                                                    }
                                                                }
                                                            }
                                                        }
                                                    } elseif ($status_desa === 'exists') {
                                                        $messages[] = "    └ ⚠️ Desa {$nama_desa} sudah ada";
                                                    } else {
                                                        $error_count++;
                                                        $messages[] = "    └ ❌ Gagal menambah desa {$nama_desa}";
                                                    }
                                                }
                                            }
                                        }
                                    } elseif ($status_kec === 'exists') {
                                        $messages[] = "  └ ⚠️ Kecamatan {$nama_kec} sudah ada";
                                    } else {
                                        $error_count++;
                                        $messages[] = "  └ ❌ Gagal menambah kecamatan {$nama_kec}";
                                    }
                                }
                            }
                        }
                    } elseif ($status === 'exists') {
                        $messages[] = "⚠️ Kota {$nama_kota} sudah ada";
                    } else {
                        $error_count++;
                        $messages[] = "❌ Gagal menambah kota {$nama_kota}";
                    }
                }
            }
        }
        
    } elseif ($selected_level === 'kecamatan') {
        // Proses data kecamatan + desa + lokasi untuk kota tertentu
        if (isset($_POST['kecamatan']) && is_array($_POST['kecamatan'])) {
            foreach ($_POST['kecamatan'] as $kecIndex => $kecData) {
                if (!empty($kecData['kode']) && !empty($kecData['nama'])) {
                    $kode_kec = $selected_parent . '.' . $kecData['kode'];
                    $nama_kec = $kecData['nama'];
                    
                    $status = insertWilayah($conn, $kode_kec, $nama_kec, 'kecamatan', $selected_parent);
                    
                    if ($status === 'inserted') {
                        $success_count++;
                        catatRiwayatWilayah($conn, $kode_kec, $nama_kec, 'kecamatan', $selected_parent);
                        $messages[] = "✅ Kecamatan {$nama_kec} berhasil ditambahkan";
                        
                        // Proses Desa untuk kecamatan ini
                        if (isset($kecData['desa']) && is_array($kecData['desa'])) {
                            foreach ($kecData['desa'] as $desaIndex => $desaData) {
                                if (!empty($desaData['kode']) && !empty($desaData['nama'])) {
                                    $kode_desa = $kode_kec . '.' . $desaData['kode'];
                                    $nama_desa = $desaData['nama'];
                                    
                                    $status_desa = insertWilayah($conn, $kode_desa, $nama_desa, 'desa', $kode_kec);
                                    
                                    if ($status_desa === 'inserted') {
                                        $success_count++;
                                        catatRiwayatWilayah($conn, $kode_desa, $nama_desa, 'desa', $kode_kec);
                                        $messages[] = "  └ ✅ Desa {$nama_desa} berhasil ditambahkan";
                                        
                                        // Proses Lokasi untuk desa ini
                                        if (isset($desaData['lokasi']) && is_array($desaData['lokasi'])) {
                                            foreach ($desaData['lokasi'] as $lokasiIndex => $lokasiData) {
                                                if (!empty($lokasiData['nama_tempat']) && !empty($lokasiData['koordinat'])) {
                                                    $result_lokasi = insertLokasi(
                                                        $conn,
                                                        $kode_desa, // kode_wilayah = kode desa
                                                        $lokasiData['nama_tempat'],
                                                        $lokasiData['koordinat'],
                                                        $lokasiData['keterangan'] ?? '',
                                                        $lokasiData['ketersediaan_sinyal'] ?? 'No',
                                                        $lokasiData['kecepatan_sinyal'] ?? 0
                                                    );
                                                    
                                                    if ($result_lokasi['status'] === 'inserted') {
                                                        $success_count++;
                                                        catatRiwayatLokasi($conn, $result_lokasi['kode_lokasi'], $lokasiData['nama_tempat'], $kode_desa);
                                                        $messages[] = "    └ 📍 Lokasi {$lokasiData['nama_tempat']} berhasil ditambahkan (Kode: {$result_lokasi['kode_lokasi']})";
                                                    } elseif ($result_lokasi['status'] === 'exists_name') {
                                                        $messages[] = "    └ ⚠️ Lokasi {$lokasiData['nama_tempat']} sudah ada di desa ini";
                                                    } else {
                                                        $error_count++;
                                                        $messages[] = "    └ ❌ Gagal menambah lokasi {$lokasiData['nama_tempat']}";
                                                    }
                                                }
                                            }
                                        }
                                    } elseif ($status_desa === 'exists') {
                                        $messages[] = "  └ ⚠️ Desa {$nama_desa} sudah ada";
                                    } else {
                                        $error_count++;
                                        $messages[] = "  └ ❌ Gagal menambah desa {$nama_desa}";
                                    }
                                }
                            }
                        }
                    } elseif ($status === 'exists') {
                        $messages[] = "⚠️ Kecamatan {$nama_kec} sudah ada";
                    } else {
                        $error_count++;
                        $messages[] = "❌ Gagal menambah kecamatan {$nama_kec}";
                    }
                }
            }
        }
        
    } elseif ($selected_level === 'desa') {
        // Proses data desa + lokasi untuk kecamatan tertentu
        if (isset($_POST['desa']) && is_array($_POST['desa'])) {
            foreach ($_POST['desa'] as $desaIndex => $desaData) {
                if (!empty($desaData['kode']) && !empty($desaData['nama'])) {
                    $kode_desa = $selected_parent . '.' . $desaData['kode'];
                    $nama_desa = $desaData['nama'];
                    
                    $status = insertWilayah($conn, $kode_desa, $nama_desa, 'desa', $selected_parent);
                    
                    if ($status === 'inserted') {
                        $success_count++;
                        catatRiwayatWilayah($conn, $kode_desa, $nama_desa, 'desa', $selected_parent);
                        $messages[] = "✅ Desa {$nama_desa} berhasil ditambahkan";
                        
                        // Proses Lokasi untuk desa ini
                        if (isset($desaData['lokasi']) && is_array($desaData['lokasi'])) {
                            foreach ($desaData['lokasi'] as $lokasiIndex => $lokasiData) {
                                if (!empty($lokasiData['nama_tempat']) && !empty($lokasiData['koordinat'])) {
                                    $result_lokasi = insertLokasi(
                                        $conn,
                                        $kode_desa, // kode_wilayah = kode desa
                                        $lokasiData['nama_tempat'],
                                        $lokasiData['koordinat'],
                                        $lokasiData['keterangan'] ?? '',
                                        $lokasiData['ketersediaan_sinyal'] ?? 'No',
                                        $lokasiData['kecepatan_sinyal'] ?? 0
                                    );
                                    
                                    if ($result_lokasi['status'] === 'inserted') {
                                        $success_count++;
                                        catatRiwayatLokasi($conn, $result_lokasi['kode_lokasi'], $lokasiData['nama_tempat'], $kode_desa);
                                        $messages[] = "  └ 📍 Lokasi {$lokasiData['nama_tempat']} berhasil ditambahkan (Kode: {$result_lokasi['kode_lokasi']})";
                                    } elseif ($result_lokasi['status'] === 'exists_name') {
                                        $messages[] = "  └ ⚠️ Lokasi {$lokasiData['nama_tempat']} sudah ada di desa ini";
                                    } else {
                                        $error_count++;
                                        $messages[] = "  └ ❌ Gagal menambah lokasi {$lokasiData['nama_tempat']}";
                                    }
                                }
                            }
                        }
                    } elseif ($status === 'exists') {
                        $messages[] = "⚠️ Desa {$nama_desa} sudah ada";
                    } else {
                        $error_count++;
                        $messages[] = "❌ Gagal menambah desa {$nama_desa}";
                    }
                }
            }
        }
    }

    // Siapkan pesan hasil
    $result_message = "Hasil Proses Tambah Wilayah dan Lokasi:\\n";
    $result_message .= "=====================================\\n";
    $result_message .= "Berhasil: {$success_count} data\\n";
    $result_message .= "Gagal: {$error_count} data\\n\\n";
    $result_message .= "Detail:\\n";
    $result_message .= implode("\\n", array_slice($messages, 0, 15)); // Batasi pesan detail
    
    if (count($messages) > 15) {
        $result_message .= "\\n... dan " . (count($messages) - 15) . " pesan lainnya";
    }

    echo "<script>
        alert(`$result_message`);
        window.location.href = '../hasil_pemetaan.php';
    </script>";

} else {
    echo "<script>
        alert('Akses tidak valid!');
        window.location.href = '../hasil_pemetaan.php';
    </script>";
}
?>