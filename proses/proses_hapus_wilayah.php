<?php
include "../config/db.php";
session_start();

// Debug information
error_log("=== PROCESS DELETE WILAYAH ===");
error_log("GET Parameters: " . print_r($_GET, true));

// DEFINISIKAN FUNGSI DI LUAR BLOK IF-ELSE
function hapusWilayahRecursive($conn, $kode, &$semuaDataDihapus, $dibuat_oleh) {
    // Ambil data wilayah sebelum dihapus
    $stmt = $conn->prepare("SELECT * FROM wilayah WHERE kode_wilayah = ?");
    $stmt->bind_param("s", $kode);
    $stmt->execute();
    $result = $stmt->get_result();
    $wilayah = $result->fetch_assoc();
    
    if ($wilayah) {
        // Simpan data untuk riwayat
        $semuaDataDihapus[] = $wilayah;
        
        // Hapus lokasi terkait dan catat ke riwayat
        $stmt_lokasi = $conn->prepare("SELECT * FROM lokasi WHERE kode_wilayah = ?");
        $stmt_lokasi->bind_param("s", $kode);
        $stmt_lokasi->execute();
        $result_lokasi = $stmt_lokasi->get_result();
        
        while ($lokasi = $result_lokasi->fetch_assoc()) {
            // Catat penghapusan lokasi ke riwayat
            $sql_riwayat_lokasi = "INSERT INTO riwayat_aktivitas 
                (jenis_aktivitas, kode_data, nama_data, deskripsi, data_sebelum, data_sesudah, dibuat_oleh) 
                VALUES ('HAPUS_LOKASI', ?, ?, ?, ?, NULL, ?)";
            
            $stmt_riwayat_lokasi = $conn->prepare($sql_riwayat_lokasi);
            $deskripsi_lokasi = "Lokasi terhapus karena penghapusan wilayah: " . $lokasi['nama_tempat'];
            $data_sebelum_lokasi = json_encode($lokasi);
            
            $stmt_riwayat_lokasi->bind_param("sssss", 
                $lokasi['kode_lokasi'],
                $lokasi['nama_tempat'],
                $deskripsi_lokasi,
                $data_sebelum_lokasi,
                $dibuat_oleh
            );
            $stmt_riwayat_lokasi->execute();
            $stmt_riwayat_lokasi->close();
        }
        
        // Hapus lokasi
        $stmt_del_lokasi = $conn->prepare("DELETE FROM lokasi WHERE kode_wilayah = ?");
        $stmt_del_lokasi->bind_param("s", $kode);
        $stmt_del_lokasi->execute();

        // Cari dan hapus anak wilayah
        $stmt_child = $conn->prepare("SELECT kode_wilayah FROM wilayah WHERE parent_kode = ?");
        $stmt_child->bind_param("s", $kode);
        $stmt_child->execute();
        $result_child = $stmt_child->get_result();

        while ($child = $result_child->fetch_assoc()) {
            hapusWilayahRecursive($conn, $child['kode_wilayah'], $semuaDataDihapus, $dibuat_oleh);
        }

        // Hapus wilayah ini sendiri
        $stmt_del = $conn->prepare("DELETE FROM wilayah WHERE kode_wilayah = ?");
        $stmt_del->bind_param("s", $kode);
        $stmt_del->execute();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Terima parameter kode_wilayah (bisa single atau multiple kode dipisah koma)
    $kode_wilayah = $_GET['kode_wilayah'] ?? '';
    
    error_log("Extracted kode_wilayah: " . $kode_wilayah);
    
    if (!$kode_wilayah) {
        $debug_info = "Parameters: " . json_encode($_GET);
        die("<script>alert('❌ Wilayah tidak dipilih!\\n\\nPastikan Anda memilih wilayah yang akan dihapus.'); window.history.back();</script>");
    }

    // Cek apakah multiple kode (dipisah koma)
    $kode_array = explode(',', $kode_wilayah);
    $multiple_delete = count($kode_array) > 1;
    
    if ($multiple_delete) {
        // HAPUS MULTIPLE WILAYAH
        $conn->begin_transaction();
        $dibuat_oleh = $_SESSION['username'] ?? 'System';
        $totalDeleted = 0;
        $semuaDataDihapus = [];

        try {
            foreach ($kode_array as $kode) {
                $kode = trim($kode);
                if (empty($kode)) continue;
                
                // Ambil data wilayah sebelum dihapus
                $stmt = $conn->prepare("SELECT * FROM wilayah WHERE kode_wilayah = ?");
                $stmt->bind_param("s", $kode);
                $stmt->execute();
                $result = $stmt->get_result();
                $wilayah = $result->fetch_assoc();
                
                if (!$wilayah) continue;

                // Hapus wilayah recursive - FUNGSI SUDAH TERDEFINISI
                hapusWilayahRecursive($conn, $kode, $semuaDataDihapus, $dibuat_oleh);
                $totalDeleted++;
            }

            // Catat ke riwayat aktivitas untuk multiple deletion
            if (!empty($semuaDataDihapus)) {
                $jenis_aktivitas = 'HAPUS_WILAYAH_MULTIPLE';
                $deskripsi = "Menghapus " . count($kode_array) . " wilayah secara bersamaan";
                
                // Persiapkan data JSON untuk semua wilayah yang dihapus
                $dataSebelum = [];
                foreach ($semuaDataDihapus as $data) {
                    $dataSebelum[$data['kode_wilayah']] = [
                        'kode_wilayah' => $data['kode_wilayah'],
                        'nama' => $data['nama'],
                        'level' => $data['level'],
                        'parent_kode' => $data['parent_kode']
                    ];
                }
                $dataSebelumJson = json_encode($dataSebelum);
                
                // Insert ke riwayat_aktivitas
                $sql_riwayat = "INSERT INTO riwayat_aktivitas 
                    (jenis_aktivitas, kode_data, nama_data, deskripsi, data_sebelum, data_sesudah, dibuat_oleh) 
                    VALUES (?, ?, ?, ?, ?, NULL, ?)";
                
                $stmt_riwayat = $conn->prepare($sql_riwayat);
                $kode_data = implode(',', $kode_array);
                $nama_data = "Multiple Wilayah (" . count($kode_array) . " items)";
                
                $stmt_riwayat->bind_param("ssssss", 
                    $jenis_aktivitas,
                    $kode_data,
                    $nama_data,
                    $deskripsi,
                    $dataSebelumJson,
                    $dibuat_oleh
                );
                
                $stmt_riwayat->execute();
                $stmt_riwayat->close();
            }

            // Commit transaksi
            $conn->commit();

            echo "<script>
                alert('✅ $totalDeleted wilayah dan semua data turunannya berhasil dihapus!\\\\n📝 Penghapusan telah tercatat dalam riwayat.');
                window.location='../hasil_pemetaan.php';
            </script>";

        } catch (Exception $e) {
            $conn->rollback();
            error_log("Error deleting multiple wilayah: " . $e->getMessage());
            die("<script>alert('❌ Gagal menghapus wilayah: ".addslashes($e->getMessage())."'); window.history.back();</script>");
        }
        
    } else {
        // HAPUS SINGLE WILAYAH (kode asli)
        $kode_wilayah = $kode_array[0];
        
        // Ambil data wilayah sebelum dihapus
        $stmt = $conn->prepare("SELECT * FROM wilayah WHERE kode_wilayah = ?");
        $stmt->bind_param("s", $kode_wilayah);
        $stmt->execute();
        $result = $stmt->get_result();
        $wilayah = $result->fetch_assoc();
        
        if (!$wilayah) {
            die("<script>alert('❌ Wilayah tidak ditemukan!'); window.history.back();</script>");
        }

        $conn->begin_transaction();
        $dibuat_oleh = $_SESSION['username'] ?? 'System';

        try {
            // Array untuk menyimpan semua data yang dihapus
            $semuaDataDihapus = [];
            
            // Jalankan penghapusan - FUNGSI SUDAH TERDEFINISI DI ATAS
            hapusWilayahRecursive($conn, $kode_wilayah, $semuaDataDihapus, $dibuat_oleh);

            // Catat penghapusan wilayah ke riwayat
            if (!empty($semuaDataDihapus)) {
                $jenis_aktivitas = 'HAPUS_WILAYAH';
                $wilayahUtama = $semuaDataDihapus[0]; // Wilayah yang pertama dihapus (utama)
                
                // Buat deskripsi berdasarkan jumlah wilayah yang dihapus
                if (count($semuaDataDihapus) === 1) {
                    $deskripsi = "Menghapus wilayah " . $wilayahUtama['level'] . ": " . $wilayahUtama['nama'];
                } else {
                    // Hitung per level
                    $countPerLevel = [];
                    foreach ($semuaDataDihapus as $data) {
                        $level = $data['level'];
                        $countPerLevel[$level] = ($countPerLevel[$level] ?? 0) + 1;
                    }
                    
                    $parts = [];
                    foreach ($countPerLevel as $level => $count) {
                        $parts[] = "$count $level";
                    }
                    $deskripsi = "Menghapus hierarki wilayah: " . implode(", ", $parts) . 
                                " (berawal dari " . $wilayahUtama['level'] . " " . $wilayahUtama['nama'] . ")";
                }
                
                // Persiapkan data JSON untuk semua wilayah yang dihapus
                $dataSebelum = [];
                foreach ($semuaDataDihapus as $data) {
                    $dataSebelum[$data['kode_wilayah']] = [
                        'kode_wilayah' => $data['kode_wilayah'],
                        'nama' => $data['nama'],
                        'level' => $data['level'],
                        'parent_kode' => $data['parent_kode']
                    ];
                }
                $dataSebelumJson = json_encode($dataSebelum);
                
                // Insert ke riwayat_aktivitas
                $sql_riwayat = "INSERT INTO riwayat_aktivitas 
                    (jenis_aktivitas, kode_data, nama_data, deskripsi, data_sebelum, data_sesudah, dibuat_oleh) 
                    VALUES (?, ?, ?, ?, ?, NULL, ?)";
                
                $stmt_riwayat = $conn->prepare($sql_riwayat);
                $nama_data = count($semuaDataDihapus) === 1 ? $wilayahUtama['nama'] : "Multiple Wilayah";
                
                $stmt_riwayat->bind_param("ssssss", 
                    $jenis_aktivitas,
                    $kode_wilayah,
                    $nama_data,
                    $deskripsi,
                    $dataSebelumJson,
                    $dibuat_oleh
                );
                
                $stmt_riwayat->execute();
                $stmt_riwayat->close();
            }

            // Commit transaksi
            $conn->commit();

            $totalDeleted = count($semuaDataDihapus);
            echo "<script>
                alert('✅ $totalDeleted wilayah dan semua data turunannya berhasil dihapus!\\\\n📝 Penghapusan telah tercatat dalam riwayat.');
                window.location='../hasil_pemetaan.php';
            </script>";

        } catch (Exception $e) {
            $conn->rollback();
            error_log("Error deleting wilayah: " . $e->getMessage());
            die("<script>alert('❌ Gagal menghapus wilayah: ".addslashes($e->getMessage())."'); window.history.back();</script>");
        }
    }
} else {
    die("Akses tidak valid.");
}
?>