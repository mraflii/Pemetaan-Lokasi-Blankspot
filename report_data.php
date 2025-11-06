<?php
include "config/db.php";

$type = $_GET['type'] ?? '';
$provinsi = $_GET['provinsi'] ?? '';
$kota = $_GET['kota'] ?? '';
$kecamatan = $_GET['kecamatan'] ?? '';
$desa = $_GET['desa'] ?? '';

header('Content-Type: application/json');

// ----------------- DROPDOWN -----------------
if(in_array($type, ['provinsi', 'kota', 'kecamatan', 'desa'])) {
    $res = [];

    try {
        if ($type == 'provinsi') {
            $q = $conn->query("SELECT kode_wilayah, nama FROM wilayah WHERE level='provinsi' ORDER BY nama ASC");
        } elseif ($type == 'kota') {
            $q = $conn->query("SELECT kode_wilayah, nama FROM wilayah WHERE level='kota' AND parent_kode='$provinsi' ORDER BY nama ASC");
        } elseif ($type == 'kecamatan') {
            $q = $conn->query("SELECT kode_wilayah, nama FROM wilayah WHERE level='kecamatan' AND parent_kode='$kota' ORDER BY nama ASC");
        } elseif ($type == 'desa') {
            $q = $conn->query("SELECT kode_wilayah, nama FROM wilayah WHERE level='desa' AND parent_kode='$kecamatan' ORDER BY nama ASC");
        }

        if (!$q) {
            throw new Exception($conn->error);
        }

        while ($r = $q->fetch_assoc()) {
            $res[] = $r;
        }
        
        echo json_encode($res);
        
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

// ----------------- STATISTIK TABEL -----------------
if ($type == 'statistik') {
    $res = [];
    
    try {
        $where = [];
        $params = [];
        
        // Build WHERE conditions dengan prepared statements
        if (!empty($provinsi)) {
            $where[] = "p.kode_wilayah = ?";
            $params[] = $provinsi;
        }
        if (!empty($kota)) {
            $where[] = "k.kode_wilayah = ?";
            $params[] = $kota;
        }
        if (!empty($kecamatan)) {
            $where[] = "c.kode_wilayah = ?";
            $params[] = $kecamatan;
        }
        if (!empty($desa)) {
            $where[] = "d.kode_wilayah = ?";
            $params[] = $desa;
        }
        
        $whereSQL = !empty($where) ? "WHERE " . implode(' AND ', $where) : '';

        // Tentukan level dan field yang sesuai
        $level = '';
        $countField = '';
        
        if (!empty($desa)) {
            // Level Desa → tampil detail lokasi di desa tersebut
            $level = 'desa';
            $groupBy = 'l.kode_lokasi, l.nama_tempat, l.koordinat, l.keterangan, l.ketersediaan_sinyal, l.kecepatan_sinyal';
            $selectField = 'l.nama_tempat, l.kode_lokasi, l.koordinat, l.keterangan, l.ketersediaan_sinyal, l.kecepatan_sinyal';
            $orderField = 'l.nama_tempat';
            $countField = 'l.kode_lokasi';
            $countSQL = "1 AS jumlah";
            
        } elseif (!empty($kecamatan)) {
            // Filter sampai Kecamatan → tampil nama Desa
            $level = 'kecamatan';
            $groupBy = 'd.kode_wilayah, d.nama';
            $selectField = 'd.nama AS nama, d.kode_wilayah AS kode_wilayah';
            $orderField = 'd.nama';
            $countField = 'd.kode_wilayah';
            $countSQL = "COUNT(DISTINCT l.kode_lokasi) AS jumlah";
            
        } elseif (!empty($kota)) {
            // Filter sampai Kota → tampil nama Kecamatan
            $level = 'kota';
            $groupBy = 'c.kode_wilayah, c.nama';
            $selectField = 'c.nama AS nama, c.kode_wilayah AS kode_wilayah';
            $orderField = 'c.nama';
            $countField = 'c.kode_wilayah';
            $countSQL = "COUNT(DISTINCT d.kode_wilayah) AS jumlah";
            
        } elseif (!empty($provinsi)) {
            // Filter Provinsi saja → tampil nama Kota
            $level = 'provinsi';
            $groupBy = 'k.kode_wilayah, k.nama';
            $selectField = 'k.nama AS nama, k.kode_wilayah AS kode_wilayah';
            $orderField = 'k.nama';
            $countField = 'k.kode_wilayah';
            $countSQL = "COUNT(DISTINCT c.kode_wilayah) AS jumlah";
            
        } else {
            // DEFAULT → HANYA tampil Provinsi saja (level nasional)
            $level = 'nasional';
            $groupBy = 'p.kode_wilayah, p.nama';
            $selectField = 'p.nama AS nama, p.kode_wilayah AS kode_wilayah';
            $orderField = 'p.nama';
            $countField = 'p.kode_wilayah';
            $countSQL = "COUNT(DISTINCT k.kode_wilayah) AS jumlah";
            
            // TAMBAHKAN WHERE CLAUSE untuk memastikan hanya data provinsi yang diambil
            $whereSQL = "WHERE p.level = 'provinsi'";
        }

        // Query utama
        if ($level === 'desa') {
            // Untuk level desa (detail lokasi) - ambil semua field dari tabel lokasi
            $sql = "SELECT
                        l.kode_lokasi,
                        l.nama_tempat,
                        l.koordinat,
                        l.keterangan,
                        l.ketersediaan_sinyal,
                        l.kecepatan_sinyal,
                        1 AS jumlah
                    FROM lokasi l
                    INNER JOIN wilayah d ON l.kode_wilayah = d.kode_wilayah
                    INNER JOIN wilayah c ON d.parent_kode = c.kode_wilayah
                    INNER JOIN wilayah k ON c.parent_kode = k.kode_wilayah
                    INNER JOIN wilayah p ON k.parent_kode = p.kode_wilayah
                    $whereSQL
                    ORDER BY l.nama_tempat ASC";
                    
        } else {
            // Untuk level kecamatan ke atas
            $sql = "SELECT
                        $selectField,
                        $countSQL,
                        COALESCE(SUM(CASE WHEN LOWER(TRIM(COALESCE(l.ketersediaan_sinyal, ''))) = 'yes' THEN 1 ELSE 0 END), 0) AS ada,
                        COALESCE(SUM(CASE WHEN LOWER(TRIM(COALESCE(l.ketersediaan_sinyal, ''))) = 'no' THEN 1 ELSE 0 END), 0) AS tidak
                    FROM wilayah p
                    LEFT JOIN wilayah k ON p.kode_wilayah = k.parent_kode AND k.level = 'kota'
                    LEFT JOIN wilayah c ON k.kode_wilayah = c.parent_kode AND c.level = 'kecamatan'  
                    LEFT JOIN wilayah d ON c.kode_wilayah = d.parent_kode AND d.level = 'desa'
                    LEFT JOIN lokasi l ON d.kode_wilayah = l.kode_wilayah
                    $whereSQL
                    GROUP BY $groupBy
                    HAVING COUNT(DISTINCT $countField) > 0
                    ORDER BY $orderField ASC";
        }

        // Debug: Log SQL
        error_log("Level: " . $level);
        error_log("Statistik SQL: " . $sql);
        error_log("Parameters: " . implode(', ', $params));
        error_log("Where SQL: " . $whereSQL);

        // Gunakan prepared statement jika ada parameter
        if (!empty($params)) {
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                // Bind parameters
                $types = str_repeat('s', count($params));
                $stmt->bind_param($types, ...$params);
                $stmt->execute();
                $q = $stmt->get_result();
            } else {
                throw new Exception("Failed to prepare statement: " . $conn->error);
            }
        } else {
            $q = $conn->query($sql);
        }

        if (!$q) {
            throw new Exception($conn->error);
        }

        while ($r = $q->fetch_assoc()) {
            // Untuk level desa, ambil data langsung dari tabel lokasi
            if ($level === 'desa') {
                // Pastikan ketersediaan_sinyal konsisten
                $r['ketersediaan_sinyal'] = trim($r['ketersediaan_sinyal'] ?? '');
                $r['kecepatan_sinyal'] = intval($r['kecepatan_sinyal'] ?? 0);
            } else {
                // Untuk level statistik, konversi ke integer
                $r['jumlah'] = intval($r['jumlah'] ?? 0);
                $r['ada'] = intval($r['ada'] ?? 0);
                $r['tidak'] = intval($r['tidak'] ?? 0);
            }
            $res[] = $r;
        }
        
        // Debug: Log jumlah data akhir
        error_log("Jumlah data ditemukan: " . count($res));
        
        echo json_encode($res);
        
    } catch (Exception $e) {
        error_log("Error in statistik: " . $e->getMessage());
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

// ----------------- DEFAULT RESPONSE -----------------
echo json_encode(['error' => 'Invalid request type']);
?>