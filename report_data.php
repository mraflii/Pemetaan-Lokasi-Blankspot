<?php
include "config/db.php";

// Enable error reporting untuk debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

$type = $_GET['type'] ?? '';
$provinsi = $_GET['provinsi'] ?? '';
$kota = $_GET['kota'] ?? '';
$kecamatan = $_GET['kecamatan'] ?? '';
$desa = $_GET['desa'] ?? '';

header('Content-Type: application/json');

// Cek koneksi database
if (!$conn) {
    echo json_encode(['error' => 'Database connection failed: ' . $conn->connect_error]);
    exit;
}

try {
    // ----------------- DROPDOWN -----------------
    if(in_array($type, ['provinsi', 'kota', 'kecamatan', 'desa'])) {
        $res = [];

        if ($type == 'provinsi') {
            $q = $conn->query("SELECT kode_wilayah, nama FROM wilayah WHERE level='provinsi' ORDER BY nama ASC");
        } elseif ($type == 'kota') {
            if (empty($provinsi)) {
                echo json_encode(['error' => 'Provinsi is required for kota dropdown']);
                exit;
            }
            $stmt = $conn->prepare("SELECT kode_wilayah, nama FROM wilayah WHERE level='kota' AND parent_kode=? ORDER BY nama ASC");
            $stmt->bind_param('s', $provinsi);
            $stmt->execute();
            $q = $stmt->get_result();
        } elseif ($type == 'kecamatan') {
            if (empty($kota)) {
                echo json_encode(['error' => 'Kota is required for kecamatan dropdown']);
                exit;
            }
            $stmt = $conn->prepare("SELECT kode_wilayah, nama FROM wilayah WHERE level='kecamatan' AND parent_kode=? ORDER BY nama ASC");
            $stmt->bind_param('s', $kota);
            $stmt->execute();
            $q = $stmt->get_result();
        } elseif ($type == 'desa') {
            if (empty($kecamatan)) {
                echo json_encode(['error' => 'Kecamatan is required for desa dropdown']);
                exit;
            }
            $stmt = $conn->prepare("SELECT kode_wilayah, nama FROM wilayah WHERE level='desa' AND parent_kode=? ORDER BY nama ASC");
            $stmt->bind_param('s', $kecamatan);
            $stmt->execute();
            $q = $stmt->get_result();
        }

        if (!$q) {
            throw new Exception($conn->error);
        }

        while ($r = $q->fetch_assoc()) {
            $res[] = $r;
        }
        
        echo json_encode($res);
        exit;
    }

    // ----------------- STATISTIK TABEL -----------------
    if ($type == 'statistik') {
        $res = [];
        
        // Jika level desa, ambil detail lokasi
        if (!empty($desa)) {
            $sql = "SELECT 
                        l.kode_lokasi,
                        l.nama_tempat,
                        l.koordinat,
                        l.keterangan,
                        l.ketersediaan_sinyal,
                        l.kecepatan_sinyal,
                        d.nama as nama_desa
                    FROM lokasi l
                    INNER JOIN wilayah d ON l.kode_wilayah = d.kode_wilayah
                    WHERE l.kode_wilayah = ? AND l.is_deleted = 0
                    ORDER BY l.nama_tempat ASC";
            
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            $stmt->bind_param('s', $desa);
            $stmt->execute();
            $q = $stmt->get_result();
            
            while ($r = $q->fetch_assoc()) {
                $res[] = $r;
            }
            
        } else {
            // Untuk level di atas desa (statistik berdasarkan desa)
            if (!empty($kecamatan)) {
                // Level Kecamatan - statistik per desa - LOGIKA DIPERBAIKI
                $sql = "SELECT 
                            d.kode_wilayah,
                            d.nama,
                            COUNT(l.kode_lokasi) as jumlah_lokasi,
                            SUM(CASE WHEN l.ketersediaan_sinyal = 'Yes' THEN 1 ELSE 0 END) as lokasi_ada_sinyal,
                            SUM(CASE WHEN l.ketersediaan_sinyal = 'No' THEN 1 ELSE 0 END) as lokasi_blankspot,
                            -- Logika baru: desa tanpa data lokasi = dianggap ada sinyal
                            CASE 
                                WHEN COUNT(l.kode_lokasi) = 0 THEN 'Ada Sinyal' -- Tidak ada data = Ada Sinyal
                                WHEN SUM(CASE WHEN l.ketersediaan_sinyal = 'Yes' THEN 1 ELSE 0 END) > 0 THEN 'Ada Sinyal'
                                ELSE 'Blankspot'
                            END as status_desa,
                            -- Untuk kompatibilitas dengan kode existing
                            CASE 
                                WHEN COUNT(l.kode_lokasi) = 0 THEN 1 -- Tidak ada data = Ada Sinyal
                                WHEN SUM(CASE WHEN l.ketersediaan_sinyal = 'Yes' THEN 1 ELSE 0 END) > 0 THEN 1
                                ELSE 0
                            END as desa_ada_sinyal,
                            CASE 
                                WHEN COUNT(l.kode_lokasi) = 0 THEN 0 -- Tidak ada data = BUKAN Blankspot
                                WHEN SUM(CASE WHEN l.ketersediaan_sinyal = 'Yes' THEN 1 ELSE 0 END) = 0 THEN 1
                                ELSE 0
                            END as desa_blankspot
                        FROM wilayah d
                        LEFT JOIN lokasi l ON d.kode_wilayah = l.kode_wilayah AND l.is_deleted = 0
                        WHERE d.level = 'desa' AND d.parent_kode = ?
                        GROUP BY d.kode_wilayah, d.nama
                        ORDER BY d.nama";
                
                $stmt = $conn->prepare($sql);
                if (!$stmt) {
                    throw new Exception("Prepare failed: " . $conn->error);
                }
                $stmt->bind_param('s', $kecamatan);
                
            } elseif (!empty($kota)) {
                // Level Kota - statistik per kecamatan - LOGIKA DIPERBAIKI
                $sql = "SELECT 
                            kc.kode_wilayah,
                            kc.nama,
                            COUNT(DISTINCT d.kode_wilayah) as total_desa,
                            -- Hitung desa yang: ada sinyal ATAU tidak ada data lokasi
                            COUNT(DISTINCT CASE 
                                WHEN NOT EXISTS (
                                    SELECT 1 FROM lokasi l 
                                    WHERE l.kode_wilayah = d.kode_wilayah 
                                    AND l.is_deleted = 0
                                ) THEN d.kode_wilayah -- Tidak ada data = Ada Sinyal
                                WHEN EXISTS (
                                    SELECT 1 FROM lokasi l 
                                    WHERE l.kode_wilayah = d.kode_wilayah 
                                    AND l.ketersediaan_sinyal = 'Yes'
                                    AND l.is_deleted = 0
                                ) THEN d.kode_wilayah -- Ada minimal 1 sinyal
                            END) as desa_ada_sinyal,
                            -- Hitung desa yang: punya lokasi tapi SEMUA tidak ada sinyal
                            COUNT(DISTINCT CASE 
                                WHEN EXISTS (
                                    SELECT 1 FROM lokasi l 
                                    WHERE l.kode_wilayah = d.kode_wilayah 
                                    AND l.is_deleted = 0
                                ) AND NOT EXISTS (
                                    SELECT 1 FROM lokasi l2 
                                    WHERE l2.kode_wilayah = d.kode_wilayah 
                                    AND l2.ketersediaan_sinyal = 'Yes'
                                    AND l2.is_deleted = 0
                                ) THEN d.kode_wilayah
                            END) as desa_blankspot
                        FROM wilayah kc
                        LEFT JOIN wilayah d ON kc.kode_wilayah = d.parent_kode AND d.level = 'desa'
                        WHERE kc.level = 'kecamatan' AND kc.parent_kode = ?
                        GROUP BY kc.kode_wilayah, kc.nama
                        ORDER BY kc.nama";
                
                $stmt = $conn->prepare($sql);
                if (!$stmt) {
                    throw new Exception("Prepare failed: " . $conn->error);
                }
                $stmt->bind_param('s', $kota);
                
            } elseif (!empty($provinsi)) {
                // Level Provinsi - statistik per kota - LOGIKA DIPERBAIKI
                $sql = "SELECT 
                            k.kode_wilayah,
                            k.nama,
                            COUNT(DISTINCT kc.kode_wilayah) as jumlah_kecamatan,
                            COUNT(DISTINCT d.kode_wilayah) as total_desa,
                            -- Hitung desa yang: ada sinyal ATAU tidak ada data lokasi
                            COUNT(DISTINCT CASE 
                                WHEN NOT EXISTS (
                                    SELECT 1 FROM lokasi l 
                                    WHERE l.kode_wilayah = d.kode_wilayah 
                                    AND l.is_deleted = 0
                                ) THEN d.kode_wilayah -- Tidak ada data = Ada Sinyal
                                WHEN EXISTS (
                                    SELECT 1 FROM lokasi l 
                                    WHERE l.kode_wilayah = d.kode_wilayah 
                                    AND l.ketersediaan_sinyal = 'Yes'
                                    AND l.is_deleted = 0
                                ) THEN d.kode_wilayah -- Ada minimal 1 sinyal
                            END) as desa_ada_sinyal,
                            -- Hitung desa yang: punya lokasi tapi SEMUA tidak ada sinyal
                            COUNT(DISTINCT CASE 
                                WHEN EXISTS (
                                    SELECT 1 FROM lokasi l 
                                    WHERE l.kode_wilayah = d.kode_wilayah 
                                    AND l.is_deleted = 0
                                ) AND NOT EXISTS (
                                    SELECT 1 FROM lokasi l2 
                                    WHERE l2.kode_wilayah = d.kode_wilayah 
                                    AND l2.ketersediaan_sinyal = 'Yes'
                                    AND l2.is_deleted = 0
                                ) THEN d.kode_wilayah
                            END) as desa_blankspot
                        FROM wilayah k
                        LEFT JOIN wilayah kc ON k.kode_wilayah = kc.parent_kode AND kc.level = 'kecamatan'
                        LEFT JOIN wilayah d ON kc.kode_wilayah = d.parent_kode AND d.level = 'desa'
                        WHERE k.level = 'kota' AND k.parent_kode = ?
                        GROUP BY k.kode_wilayah, k.nama
                        ORDER BY k.nama";
                
                $stmt = $conn->prepare($sql);
                if (!$stmt) {
                    throw new Exception("Prepare failed: " . $conn->error);
                }
                $stmt->bind_param('s', $provinsi);
                
            } else {
                // Level Nasional - statistik per provinsi - LOGIKA DIPERBAIKI
                $sql = "SELECT 
                            p.kode_wilayah,
                            p.nama,
                            COUNT(DISTINCT k.kode_wilayah) as jumlah_kota,
                            COUNT(DISTINCT kc.kode_wilayah) as jumlah_kecamatan,
                            COUNT(DISTINCT d.kode_wilayah) as total_desa,
                            -- Hitung desa yang: ada sinyal ATAU tidak ada data lokasi
                            COUNT(DISTINCT CASE 
                                WHEN NOT EXISTS (
                                    SELECT 1 FROM lokasi l 
                                    WHERE l.kode_wilayah = d.kode_wilayah 
                                    AND l.is_deleted = 0
                                ) THEN d.kode_wilayah -- Tidak ada data = Ada Sinyal
                                WHEN EXISTS (
                                    SELECT 1 FROM lokasi l 
                                    WHERE l.kode_wilayah = d.kode_wilayah 
                                    AND l.ketersediaan_sinyal = 'Yes'
                                    AND l.is_deleted = 0
                                ) THEN d.kode_wilayah -- Ada minimal 1 sinyal
                            END) as desa_ada_sinyal,
                            -- Hitung desa yang: punya lokasi tapi SEMUA tidak ada sinyal
                            COUNT(DISTINCT CASE 
                                WHEN EXISTS (
                                    SELECT 1 FROM lokasi l 
                                    WHERE l.kode_wilayah = d.kode_wilayah 
                                    AND l.is_deleted = 0
                                ) AND NOT EXISTS (
                                    SELECT 1 FROM lokasi l2 
                                    WHERE l2.kode_wilayah = d.kode_wilayah 
                                    AND l2.ketersediaan_sinyal = 'Yes'
                                    AND l2.is_deleted = 0
                                ) THEN d.kode_wilayah
                            END) as desa_blankspot
                        FROM wilayah p
                        LEFT JOIN wilayah k ON p.kode_wilayah = k.parent_kode AND k.level = 'kota'
                        LEFT JOIN wilayah kc ON k.kode_wilayah = kc.parent_kode AND kc.level = 'kecamatan'
                        LEFT JOIN wilayah d ON kc.kode_wilayah = d.parent_kode AND d.level = 'desa'
                        WHERE p.level = 'provinsi'
                        GROUP BY p.kode_wilayah, p.nama
                        ORDER BY p.nama";
                
                $stmt = $conn->prepare($sql);
                if (!$stmt) {
                    throw new Exception("Prepare failed: " . $conn->error);
                }
            }
            
            $stmt->execute();
            $q = $stmt->get_result();
            
            if (!$q) {
                throw new Exception("Query failed: " . $stmt->error);
            }
            
            while ($r = $q->fetch_assoc()) {
                // Konversi nilai ke integer
                $r['jumlah_lokasi'] = intval($r['jumlah_lokasi'] ?? 0);
                $r['lokasi_ada_sinyal'] = intval($r['lokasi_ada_sinyal'] ?? 0);
                $r['lokasi_blankspot'] = intval($r['lokasi_blankspot'] ?? 0);
                $r['total_desa'] = intval($r['total_desa'] ?? 0);
                $r['desa_ada_sinyal'] = intval($r['desa_ada_sinyal'] ?? 0);
                $r['desa_blankspot'] = intval($r['desa_blankspot'] ?? 0);
                $r['jumlah_kota'] = intval($r['jumlah_kota'] ?? 0);
                $r['jumlah_kecamatan'] = intval($r['jumlah_kecamatan'] ?? 0);
                
                $res[] = $r;
            }
        }
        
        echo json_encode($res);
        exit;
    }

    // ----------------- DEFAULT RESPONSE -----------------
    echo json_encode(['error' => 'Invalid request type']);

} catch (Exception $e) {
    error_log("Error in report_data.php: " . $e->getMessage());
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}
?>