<?php
include "config/db.php";
session_start();

// Set timezone
date_default_timezone_set('Asia/Jakarta');

// Ambil parameter filter
$provinsi = $_GET['provinsi'] ?? '';
$kota = $_GET['kota'] ?? '';
$kecamatan = $_GET['kecamatan'] ?? '';
$desa = $_GET['desa'] ?? '';

// Simpan ke riwayat laporan
$filters = [
    'provinsi' => $provinsi,
    'kota' => $kota,
    'kecamatan' => $kecamatan,
    'desa' => $desa,
    'timestamp' => date('Y-m-d H:i:s')
];

$filters_json = json_encode($filters, JSON_UNESCAPED_UNICODE);
$nama_laporan = "Export Excel Laporan " . date('d/m/Y H:i');
$dibuat_oleh = $_SESSION['username'] ?? 'System';

// Simpan ke database
$stmt = $conn->prepare("INSERT INTO riwayat_laporan (nama_laporan, jenis_laporan, filter, dibuat_oleh) VALUES (?, 'excel', ?, ?)");
if ($stmt) {
    $stmt->bind_param("sss", $nama_laporan, $filters_json, $dibuat_oleh);
    $stmt->execute();
    $stmt->close();
}

// Query data untuk Excel - SAMA SEPERTI report_data.php
$where = [];
if ($provinsi) $where[] = "p.kode_wilayah='$provinsi'";
if ($kota) $where[] = "k.kode_wilayah='$kota'";
if ($kecamatan) $where[] = "c.kode_wilayah='$kecamatan'";
if ($desa) $where[] = "d.kode_wilayah='$desa'";

$whereSQL = count($where) > 0 ? "WHERE " . implode(' AND ', $where) : '';

// Tentukan level grouping sesuai filter - SAMA SEPERTI PDF
if ($desa) {
    // Level Desa - Detail Lokasi
    $sql = "SELECT
                l.kode_lokasi,
                l.nama_tempat AS nama,
                l.koordinat,
                l.keterangan,
                l.ketersediaan_sinyal,
                l.kecepatan_sinyal
            FROM lokasi l
            LEFT JOIN wilayah d ON l.kode_wilayah = d.kode_wilayah
            LEFT JOIN wilayah c ON d.parent_kode = c.kode_wilayah
            LEFT JOIN wilayah k ON c.parent_kode = k.kode_wilayah
            LEFT JOIN wilayah p ON k.parent_kode = p.kode_wilayah
            $whereSQL
            ORDER BY l.nama_tempat ASC";
    
    $level = 'Lokasi';
    $isDetailLevel = true;
    
} else {
    // Level di atas Desa - Statistik
    if ($kecamatan) {
        $groupBy = 'd.kode_wilayah, d.nama';
        $selectField = 'd.nama AS nama, d.kode_wilayah AS kode_wilayah';
        $orderField = 'd.nama';
        $countField = 'l.kode_lokasi';
        $level = 'Desa';
        $countLabel = 'Lokasi';
        
        // Query untuk statistik level desa
        $sql = "SELECT
                    $selectField,
                    COUNT(DISTINCT $countField) AS jumlah_lokasi,
                    SUM(CASE WHEN LOWER(TRIM(COALESCE(l.ketersediaan_sinyal, ''))) = 'yes' THEN 1 ELSE 0 END) AS lokasi_ada_sinyal,
                    SUM(CASE WHEN LOWER(TRIM(COALESCE(l.ketersediaan_sinyal, ''))) = 'no' THEN 1 ELSE 0 END) AS lokasi_blankspot
                FROM wilayah d
                LEFT JOIN lokasi l ON d.kode_wilayah = l.kode_wilayah
                LEFT JOIN wilayah c ON d.parent_kode = c.kode_wilayah
                LEFT JOIN wilayah k ON c.parent_kode = k.kode_wilayah
                LEFT JOIN wilayah p ON k.parent_kode = p.kode_wilayah
                $whereSQL
                GROUP BY $groupBy
                ORDER BY $orderField ASC";
                
    } elseif ($kota) {
        $groupBy = 'c.kode_wilayah, c.nama';
        $selectField = 'c.nama AS nama, c.kode_wilayah AS kode_wilayah';
        $orderField = 'c.nama';
        $level = 'Kecamatan';
        
        // Query untuk statistik level kecamatan
        $sql = "SELECT
                    $selectField,
                    COUNT(DISTINCT d.kode_wilayah) AS total_desa,
                    COUNT(DISTINCT CASE WHEN EXISTS (
                        SELECT 1 FROM lokasi l 
                        WHERE l.kode_wilayah = d.kode_wilayah 
                        AND LOWER(TRIM(COALESCE(l.ketersediaan_sinyal, ''))) = 'yes'
                    ) THEN d.kode_wilayah END) AS desa_ada_sinyal,
                    COUNT(DISTINCT CASE WHEN EXISTS (
                        SELECT 1 FROM lokasi l 
                        WHERE l.kode_wilayah = d.kode_wilayah 
                        AND LOWER(TRIM(COALESCE(l.ketersediaan_sinyal, ''))) = 'no'
                    ) THEN d.kode_wilayah END) AS desa_blankspot
                FROM wilayah c
                LEFT JOIN wilayah d ON c.kode_wilayah = d.parent_kode
                LEFT JOIN wilayah k ON c.parent_kode = k.kode_wilayah
                LEFT JOIN wilayah p ON k.parent_kode = p.kode_wilayah
                $whereSQL
                GROUP BY $groupBy
                ORDER BY $orderField ASC";
                
    } elseif ($provinsi) {
        $groupBy = 'k.kode_wilayah, k.nama';
        $selectField = 'k.nama AS nama, k.kode_wilayah AS kode_wilayah';
        $orderField = 'k.nama';
        $level = 'Kota';
        
        // Query untuk statistik level kota
        $sql = "SELECT
                    $selectField,
                    COUNT(DISTINCT c.kode_wilayah) AS jumlah_kecamatan,
                    COUNT(DISTINCT d.kode_wilayah) AS total_desa,
                    COUNT(DISTINCT CASE WHEN EXISTS (
                        SELECT 1 FROM lokasi l 
                        WHERE l.kode_wilayah = d.kode_wilayah 
                        AND LOWER(TRIM(COALESCE(l.ketersediaan_sinyal, ''))) = 'yes'
                    ) THEN d.kode_wilayah END) AS desa_ada_sinyal,
                    COUNT(DISTINCT CASE WHEN EXISTS (
                        SELECT 1 FROM lokasi l 
                        WHERE l.kode_wilayah = d.kode_wilayah 
                        AND LOWER(TRIM(COALESCE(l.ketersediaan_sinyal, ''))) = 'no'
                    ) THEN d.kode_wilayah END) AS desa_blankspot
                FROM wilayah k
                LEFT JOIN wilayah c ON k.kode_wilayah = c.parent_kode
                LEFT JOIN wilayah d ON c.kode_wilayah = d.parent_kode
                LEFT JOIN wilayah p ON k.parent_kode = p.kode_wilayah
                $whereSQL
                GROUP BY $groupBy
                ORDER BY $orderField ASC";
                
    } else {
        $groupBy = 'p.kode_wilayah, p.nama';
        $selectField = 'p.nama AS nama, p.kode_wilayah AS kode_wilayah';
        $orderField = 'p.nama';
        $level = 'Provinsi';
        
        // Query untuk statistik level provinsi
        $sql = "SELECT
                    $selectField,
                    COUNT(DISTINCT k.kode_wilayah) AS jumlah_kota,
                    COUNT(DISTINCT c.kode_wilayah) AS jumlah_kecamatan,
                    COUNT(DISTINCT d.kode_wilayah) AS total_desa,
                    COUNT(DISTINCT CASE WHEN EXISTS (
                        SELECT 1 FROM lokasi l 
                        WHERE l.kode_wilayah = d.kode_wilayah 
                        AND LOWER(TRIM(COALESCE(l.ketersediaan_sinyal, ''))) = 'yes'
                    ) THEN d.kode_wilayah END) AS desa_ada_sinyal,
                    COUNT(DISTINCT CASE WHEN EXISTS (
                        SELECT 1 FROM lokasi l 
                        WHERE l.kode_wilayah = d.kode_wilayah 
                        AND LOWER(TRIM(COALESCE(l.ketersediaan_sinyal, ''))) = 'no'
                    ) THEN d.kode_wilayah END) AS desa_blankspot
                FROM wilayah p
                LEFT JOIN wilayah k ON p.kode_wilayah = k.parent_kode
                LEFT JOIN wilayah c ON k.kode_wilayah = c.parent_kode
                LEFT JOIN wilayah d ON c.kode_wilayah = d.parent_kode
                $whereSQL
                GROUP BY $groupBy
                ORDER BY $orderField ASC";
    }
    
    $isDetailLevel = false;
}

// Dapatkan nama wilayah untuk info filter
$filterInfo = "Semua Wilayah Indonesia";
if ($desa) {
    $q = $conn->query("SELECT p.nama as provinsi, k.nama as kota, c.nama as kecamatan, d.nama as desa 
                    FROM wilayah d 
                    LEFT JOIN wilayah c ON d.parent_kode = c.kode_wilayah 
                    LEFT JOIN wilayah k ON c.parent_kode = k.kode_wilayah 
                    LEFT JOIN wilayah p ON k.parent_kode = p.kode_wilayah 
                    WHERE d.kode_wilayah='$desa'");
    if ($r = $q->fetch_assoc()) {
        $filterInfo = $r['provinsi'] . " → " . $r['kota'] . " → " . $r['kecamatan'] . " → " . $r['desa'];
    }
} elseif ($kecamatan) {
    $q = $conn->query("SELECT p.nama as provinsi, k.nama as kota, c.nama as kecamatan 
                    FROM wilayah c 
                    LEFT JOIN wilayah k ON c.parent_kode = k.kode_wilayah 
                    LEFT JOIN wilayah p ON k.parent_kode = p.kode_wilayah 
                    WHERE c.kode_wilayah='$kecamatan'");
    if ($r = $q->fetch_assoc()) {
        $filterInfo = $r['provinsi'] . " → " . $r['kota'] . " → " . $r['kecamatan'];
    }
} elseif ($kota) {
    $q = $conn->query("SELECT p.nama as provinsi, k.nama as kota 
                    FROM wilayah k 
                    LEFT JOIN wilayah p ON k.parent_kode = p.kode_wilayah 
                    WHERE k.kode_wilayah='$kota'");
    if ($r = $q->fetch_assoc()) {
        $filterInfo = $r['provinsi'] . " → " . $r['kota'];
    }
} elseif ($provinsi) {
    $q = $conn->query("SELECT nama as provinsi FROM wilayah WHERE kode_wilayah='$provinsi'");
    if ($r = $q->fetch_assoc()) {
        $filterInfo = $r['provinsi'];
    }
}

$result = $conn->query($sql);

// Header untuk download Excel
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="laporan_statistik_lokasi_' . date('Y-m-d_H-i') . '.xls"');
header('Pragma: no-cache');
header('Expires: 0');

// Output Excel dengan styling yang polos (SESUAI PDF)
echo "<html>";
echo "<head>";
echo "<meta charset='UTF-8'>";
echo "<style>";
echo "table { border-collapse: collapse; width: 100%; font-family: Arial, sans-serif; font-size: 10px; }";
echo "th { background-color: #f8f9fa; color: #000; font-weight: bold; padding: 6px; text-align: center; border: 1px solid #000; }";
echo "td { padding: 4px; border: 1px solid #000; }";
echo ".header { background-color: #f8f9fa; color: #000; font-size: 14px; font-weight: bold; text-align: center; }";
echo ".subheader { background-color: #f8f9fa; color: #000; }";
echo ".total { background-color: #f8f9fa; color: #000; font-weight: bold; }";
echo ".info-row { background-color: #f8f9fa; }";
echo ".text-center { text-align: center; }";
echo ".text-left { text-align: left; }";
echo ".text-right { text-align: right; }";
echo ".border-all { border: 1px solid #000; }";
echo "</style>";
echo "</head>";
echo "<body>";

echo "<table border='1'>";

// Header utama - SAMA SEPERTI PDF
echo "<tr>";
if ($isDetailLevel) {
    echo "<td colspan='7' class='header border-all'>LAPORAN STATISTIK LOKASI</td>";
} else {
    if ($kecamatan) {
        echo "<td colspan='7' class='header border-all'>LAPORAN STATISTIK LOKASI</td>";
    } elseif ($kota) {
        echo "<td colspan='7' class='header border-all'>LAPORAN STATISTIK LOKASI</td>";
    } elseif ($provinsi) {
        echo "<td colspan='8' class='header border-all'>LAPORAN STATISTIK LOKASI</td>";
    } else {
        echo "<td colspan='9' class='header border-all'>LAPORAN STATISTIK LOKASI</td>";
    }
}
echo "</tr>";

// Subheader
echo "<tr>";
if ($isDetailLevel) {
    echo "<td colspan='7' class='subheader border-all'><strong>Sistem Pemetaan Ketersediaan Sinyal Berdasarkan Desa</strong></td>";
} else {
    if ($kecamatan) {
        echo "<td colspan='7' class='subheader border-all'><strong>Sistem Pemetaan Ketersediaan Sinyal Berdasarkan Desa</strong></td>";
    } elseif ($kota) {
        echo "<td colspan='7' class='subheader border-all'><strong>Sistem Pemetaan Ketersediaan Sinyal Berdasarkan Desa</strong></td>";
    } elseif ($provinsi) {
        echo "<td colspan='8' class='subheader border-all'><strong>Sistem Pemetaan Ketersediaan Sinyal Berdasarkan Desa</strong></td>";
    } else {
        echo "<td colspan='9' class='subheader border-all'><strong>Sistem Pemetaan Ketersediaan Sinyal Berdasarkan Desa</strong></td>";
    }
}
echo "</tr>";

// Informasi filter
echo "<tr class='info-row'>";
if ($isDetailLevel) {
    echo "<td colspan='7' class='border-all'><strong>Tanggal Export:</strong> " . date('d/m/Y H:i') . "</td>";
} else {
    if ($kecamatan) {
        echo "<td colspan='7' class='border-all'><strong>Tanggal Export:</strong> " . date('d/m/Y H:i') . "</td>";
    } elseif ($kota) {
        echo "<td colspan='7' class='border-all'><strong>Tanggal Export:</strong> " . date('d/m/Y H:i') . "</td>";
    } elseif ($provinsi) {
        echo "<td colspan='8' class='border-all'><strong>Tanggal Export:</strong> " . date('d/m/Y H:i') . "</td>";
    } else {
        echo "<td colspan='9' class='border-all'><strong>Tanggal Export:</strong> " . date('d/m/Y H:i') . "</td>";
    }
}
echo "</tr>";

echo "<tr class='info-row'>";
if ($isDetailLevel) {
    echo "<td colspan='7' class='border-all'><strong>Level Data:</strong> " . $level . "</td>";
} else {
    if ($kecamatan) {
        echo "<td colspan='7' class='border-all'><strong>Level Data:</strong> " . $level . "</td>";
    } elseif ($kota) {
        echo "<td colspan='7' class='border-all'><strong>Level Data:</strong> " . $level . "</td>";
    } elseif ($provinsi) {
        echo "<td colspan='8' class='border-all'><strong>Level Data:</strong> " . $level . "</td>";
    } else {
        echo "<td colspan='9' class='border-all'><strong>Level Data:</strong> " . $level . "</td>";
    }
}
echo "</tr>";

echo "<tr class='info-row'>";
if ($isDetailLevel) {
    echo "<td colspan='7' class='border-all'><strong>Rentang Wilayah:</strong> " . $filterInfo . "</td>";
} else {
    if ($kecamatan) {
        echo "<td colspan='7' class='border-all'><strong>Rentang Wilayah:</strong> " . $filterInfo . "</td>";
    } elseif ($kota) {
        echo "<td colspan='7' class='border-all'><strong>Rentang Wilayah:</strong> " . $filterInfo . "</td>";
    } elseif ($provinsi) {
        echo "<td colspan='8' class='border-all'><strong>Rentang Wilayah:</strong> " . $filterInfo . "</td>";
    } else {
        echo "<td colspan='9' class='border-all'><strong>Rentang Wilayah:</strong> " . $filterInfo . "</td>";
    }
}
echo "</tr>";

echo "<tr class='info-row'>";
if ($isDetailLevel) {
    echo "<td colspan='7' class='border-all'><strong>Dibuat Oleh:</strong> " . $dibuat_oleh . "</td>";
} else {
    if ($kecamatan) {
        echo "<td colspan='7' class='border-all'><strong>Dibuat Oleh:</strong> " . $dibuat_oleh . "</td>";
    } elseif ($kota) {
        echo "<td colspan='7' class='border-all'><strong>Dibuat Oleh:</strong> " . $dibuat_oleh . "</td>";
    } elseif ($provinsi) {
        echo "<td colspan='8' class='border-all'><strong>Dibuat Oleh:</strong> " . $dibuat_oleh . "</td>";
    } else {
        echo "<td colspan='9' class='border-all'><strong>Dibuat Oleh:</strong> " . $dibuat_oleh . "</td>";
    }
}
echo "</tr>";

// Spasi
if ($isDetailLevel) {
    echo "<tr><td colspan='7' style='height: 10px; border: none;'></td></tr>";
} else {
    if ($kecamatan) {
        echo "<tr><td colspan='7' style='height: 10px; border: none;'></td></tr>";
    } elseif ($kota) {
        echo "<tr><td colspan='7' style='height: 10px; border: none;'></td></tr>";
    } elseif ($provinsi) {
        echo "<tr><td colspan='8' style='height: 10px; border: none;'></td></tr>";
    } else {
        echo "<tr><td colspan='9' style='height: 10px; border: none;'></td></tr>";
    }
}

// Header tabel berdasarkan level - SAMA SEPERTI PDF
echo "<tr>";
if ($isDetailLevel) {
    // Header untuk detail lokasi (level desa)
    echo "<th class='border-all'>No</th>";
    echo "<th class='border-all'>Kode Lokasi</th>";
    echo "<th class='border-all'>Nama Lokasi</th>";
    echo "<th class='border-all'>Koordinat</th>";
    echo "<th class='border-all'>Keterangan</th>";
    echo "<th class='border-all'>Status Sinyal</th>";
    echo "<th class='border-all'>Kecepatan Sinyal</th>";
} else {
    // Header untuk statistik (level di atas desa)
    if ($kecamatan) {
        // Level Kecamatan
        echo "<th class='border-all'>No</th>";
        echo "<th class='border-all'>Kode Desa</th>";
        echo "<th class='border-all'>Nama Desa</th>";
        echo "<th class='border-all'>Jumlah Lokasi</th>";
        echo "<th class='border-all'>Lokasi Ada Sinyal</th>";
        echo "<th class='border-all'>Lokasi Blankspot</th>";
        echo "<th class='border-all'>Persentase</th>";
    } elseif ($kota) {
        // Level Kota
        echo "<th class='border-all'>No</th>";
        echo "<th class='border-all'>Kode Kecamatan</th>";
        echo "<th class='border-all'>Nama Kecamatan</th>";
        echo "<th class='border-all'>Jumlah Desa</th>";
        echo "<th class='border-all'>Desa Ada Sinyal</th>";
        echo "<th class='border-all'>Desa Blankspot</th>";
        echo "<th class='border-all'>Persentase</th>";
    } elseif ($provinsi) {
        // Level Provinsi
        echo "<th class='border-all'>No</th>";
        echo "<th class='border-all'>Kode Kota</th>";
        echo "<th class='border-all'>Nama Kota</th>";
        echo "<th class='border-all'>Jumlah Kecamatan</th>";
        echo "<th class='border-all'>Jumlah Desa</th>";
        echo "<th class='border-all'>Desa Ada Sinyal</th>";
        echo "<th class='border-all'>Desa Blankspot</th>";
        echo "<th class='border-all'>Persentase</th>";
    } else {
        // Level Nasional
        echo "<th class='border-all'>No</th>";
        echo "<th class='border-all'>Kode Provinsi</th>";
        echo "<th class='border-all'>Nama Provinsi</th>";
        echo "<th class='border-all'>Jumlah Kota</th>";
        echo "<th class='border-all'>Jumlah Kecamatan</th>";
        echo "<th class='border-all'>Jumlah Desa</th>";
        echo "<th class='border-all'>Desa Ada Sinyal</th>";
        echo "<th class='border-all'>Desa Blankspot</th>";
        echo "<th class='border-all'>Persentase</th>";
    }
}
echo "</tr>";

// Data rows
if ($result && $result->num_rows > 0) {
    $no = 1;
    
    if ($isDetailLevel) {
        // Tampilan detail lokasi - SAMA SEPERTI PDF
        $totalLokasi = 0;
        $totalAda = 0;
        $totalTidak = 0;
        
        while ($row = $result->fetch_assoc()) {
            $statusSinyal = $row['ketersediaan_sinyal'] === 'Yes' ? 'Ada' : 
                           ($row['ketersediaan_sinyal'] === 'No' ? 'Tidak Ada' : 'Tidak Diketahui');
            $kecepatanSinyal = $row['kecepatan_sinyal'] > 0 ? $row['kecepatan_sinyal'] . ' Mbps' : '-';
            
            if ($row['ketersediaan_sinyal'] === 'Yes') $totalAda++;
            if ($row['ketersediaan_sinyal'] === 'No') $totalTidak++;
            $totalLokasi++;
            
            echo "<tr>";
            echo "<td class='border-all text-center'>" . $no++ . "</td>";
            echo "<td class='border-all'>" . ($row['kode_lokasi'] ?? '-') . "</td>";
            echo "<td class='border-all'>" . ($row['nama'] ?? 'N/A') . "</td>";
            echo "<td class='border-all text-center'>" . ($row['koordinat'] ?? '-') . "</td>";
            echo "<td class='border-all'>" . ($row['keterangan'] ?? '-') . "</td>";
            echo "<td class='border-all text-center'>" . $statusSinyal . "</td>";
            echo "<td class='border-all text-center'>" . $kecepatanSinyal . "</td>";
            echo "</tr>";
        }
        
        // Total untuk detail lokasi
        $totalPersentase = $totalLokasi > 0 ? round(($totalAda / $totalLokasi) * 100, 1) : 0;
        echo "<tr class='total'>";
        echo "<td colspan='3' class='border-all'><strong>TOTAL</strong></td>";
        echo "<td class='border-all text-center' colspan='2'><strong>" . number_format($totalLokasi) . " Lokasi</strong></td>";
        echo "<td class='border-all text-center'><strong>Ada: " . number_format($totalAda) . ", Tidak: " . number_format($totalTidak) . "</strong></td>";
        echo "<td class='border-all text-center'><strong>" . $totalPersentase . "% Ada Sinyal</strong></td>";
        echo "</tr>";
        
    } else {
        // Tampilan statistik - SAMA SEPERTI PDF
        $totalJumlah = 0;
        $totalAda = 0;
        $totalTidak = 0;
        $totalDesa = 0;
        $totalKecamatan = 0;
        $totalKota = 0;
        
        while ($row = $result->fetch_assoc()) {
            if ($kecamatan) {
                // Level Kecamatan
                $jumlahLokasi = $row['jumlah_lokasi'] ?? 0;
                $lokasiAdaSinyal = $row['lokasi_ada_sinyal'] ?? 0;
                $lokasiBlankspot = $row['lokasi_blankspot'] ?? 0;
                $persentase = $jumlahLokasi > 0 ? round(($lokasiAdaSinyal / $jumlahLokasi) * 100, 1) : 0;
                
                $totalJumlah += $jumlahLokasi;
                $totalAda += $lokasiAdaSinyal;
                $totalTidak += $lokasiBlankspot;
                
                echo "<tr>";
                echo "<td class='border-all text-center'>" . $no++ . "</td>";
                echo "<td class='border-all'>" . ($row['kode_wilayah'] ?? '-') . "</td>";
                echo "<td class='border-all'>" . ($row['nama'] ?? 'N/A') . "</td>";
                echo "<td class='border-all text-center'>" . number_format($jumlahLokasi) . "</td>";
                echo "<td class='border-all text-center'>" . number_format($lokasiAdaSinyal) . "</td>";
                echo "<td class='border-all text-center'>" . number_format($lokasiBlankspot) . "</td>";
                echo "<td class='border-all text-center'>" . $persentase . "%</td>";
                echo "</tr>";
                
            } elseif ($kota) {
                // Level Kota
                $totalDesaRow = $row['total_desa'] ?? 0;
                $desaAdaSinyal = $row['desa_ada_sinyal'] ?? 0;
                $desaBlankspot = $row['desa_blankspot'] ?? 0;
                $persentase = $totalDesaRow > 0 ? round(($desaAdaSinyal / $totalDesaRow) * 100, 1) : 0;
                
                $totalJumlah += $totalDesaRow;
                $totalAda += $desaAdaSinyal;
                $totalTidak += $desaBlankspot;
                
                echo "<tr>";
                echo "<td class='border-all text-center'>" . $no++ . "</td>";
                echo "<td class='border-all'>" . ($row['kode_wilayah'] ?? '-') . "</td>";
                echo "<td class='border-all'>" . ($row['nama'] ?? 'N/A') . "</td>";
                echo "<td class='border-all text-center'>" . number_format($totalDesaRow) . "</td>";
                echo "<td class='border-all text-center'>" . number_format($desaAdaSinyal) . "</td>";
                echo "<td class='border-all text-center'>" . number_format($desaBlankspot) . "</td>";
                echo "<td class='border-all text-center'>" . $persentase . "%</td>";
                echo "</tr>";
                
            } elseif ($provinsi) {
                // Level Provinsi
                $jumlahKecamatan = $row['jumlah_kecamatan'] ?? 0;
                $totalDesaRow = $row['total_desa'] ?? 0;
                $desaAdaSinyal = $row['desa_ada_sinyal'] ?? 0;
                $desaBlankspot = $row['desa_blankspot'] ?? 0;
                $persentase = $totalDesaRow > 0 ? round(($desaAdaSinyal / $totalDesaRow) * 100, 1) : 0;
                
                $totalJumlah += $jumlahKecamatan;
                $totalDesa += $totalDesaRow;
                $totalAda += $desaAdaSinyal;
                $totalTidak += $desaBlankspot;
                
                echo "<tr>";
                echo "<td class='border-all text-center'>" . $no++ . "</td>";
                echo "<td class='border-all'>" . ($row['kode_wilayah'] ?? '-') . "</td>";
                echo "<td class='border-all'>" . ($row['nama'] ?? 'N/A') . "</td>";
                echo "<td class='border-all text-center'>" . number_format($jumlahKecamatan) . "</td>";
                echo "<td class='border-all text-center'>" . number_format($totalDesaRow) . "</td>";
                echo "<td class='border-all text-center'>" . number_format($desaAdaSinyal) . "</td>";
                echo "<td class='border-all text-center'>" . number_format($desaBlankspot) . "</td>";
                echo "<td class='border-all text-center'>" . $persentase . "%</td>";
                echo "</tr>";
                
            } else {
                // Level Nasional
                $jumlahKota = $row['jumlah_kota'] ?? 0;
                $jumlahKecamatan = $row['jumlah_kecamatan'] ?? 0;
                $totalDesaRow = $row['total_desa'] ?? 0;
                $desaAdaSinyal = $row['desa_ada_sinyal'] ?? 0;
                $desaBlankspot = $row['desa_blankspot'] ?? 0;
                $persentase = $totalDesaRow > 0 ? round(($desaAdaSinyal / $totalDesaRow) * 100, 1) : 0;
                
                $totalKota += $jumlahKota;
                $totalKecamatan += $jumlahKecamatan;
                $totalDesa += $totalDesaRow;
                $totalAda += $desaAdaSinyal;
                $totalTidak += $desaBlankspot;
                
                echo "<tr>";
                echo "<td class='border-all text-center'>" . $no++ . "</td>";
                echo "<td class='border-all'>" . ($row['kode_wilayah'] ?? '-') . "</td>";
                echo "<td class='border-all'>" . ($row['nama'] ?? 'N/A') . "</td>";
                echo "<td class='border-all text-center'>" . number_format($jumlahKota) . "</td>";
                echo "<td class='border-all text-center'>" . number_format($jumlahKecamatan) . "</td>";
                echo "<td class='border-all text-center'>" . number_format($totalDesaRow) . "</td>";
                echo "<td class='border-all text-center'>" . number_format($desaAdaSinyal) . "</td>";
                echo "<td class='border-all text-center'>" . number_format($desaBlankspot) . "</td>";
                echo "<td class='border-all text-center'>" . $persentase . "%</td>";
                echo "</tr>";
            }
        }
        
        // Total untuk statistik - DIPERBAIKI LOGIKA PERSENTASE
        echo "<tr class='total'>";
        if ($kecamatan) {
            $totalPersentase = $totalJumlah > 0 ? round(($totalAda / $totalJumlah) * 100, 1) : 0;
            echo "<td colspan='3' class='border-all'><strong>TOTAL</strong></td>";
            echo "<td class='border-all text-center'><strong>" . number_format($totalJumlah) . "</strong></td>";
            echo "<td class='border-all text-center'><strong>" . number_format($totalAda) . "</strong></td>";
            echo "<td class='border-all text-center'><strong>" . number_format($totalTidak) . "</strong></td>";
            echo "<td class='border-all text-center'><strong>" . $totalPersentase . "%</strong></td>";
        } elseif ($kota) {
            $totalPersentase = $totalJumlah > 0 ? round(($totalAda / $totalJumlah) * 100, 1) : 0;
            echo "<td colspan='3' class='border-all'><strong>TOTAL</strong></td>";
            echo "<td class='border-all text-center'><strong>" . number_format($totalJumlah) . "</strong></td>";
            echo "<td class='border-all text-center'><strong>" . number_format($totalAda) . "</strong></td>";
            echo "<td class='border-all text-center'><strong>" . number_format($totalTidak) . "</strong></td>";
            echo "<td class='border-all text-center'><strong>" . $totalPersentase . "%</strong></td>";
        } elseif ($provinsi) {
            $totalPersentase = $totalDesa > 0 ? round(($totalAda / $totalDesa) * 100, 1) : 0;
            echo "<td colspan='3' class='border-all'><strong>TOTAL</strong></td>";
            echo "<td class='border-all text-center'><strong>" . number_format($totalJumlah) . "</strong></td>";
            echo "<td class='border-all text-center'><strong>" . number_format($totalDesa) . "</strong></td>";
            echo "<td class='border-all text-center'><strong>" . number_format($totalAda) . "</strong></td>";
            echo "<td class='border-all text-center'><strong>" . number_format($totalTidak) . "</strong></td>";
            echo "<td class='border-all text-center'><strong>" . $totalPersentase . "%</strong></td>";
        } else {
            $totalPersentase = $totalDesa > 0 ? round(($totalAda / $totalDesa) * 100, 1) : 0;
            echo "<td colspan='3' class='border-all'><strong>TOTAL</strong></td>";
            echo "<td class='border-all text-center'><strong>" . number_format($totalKota) . "</strong></td>";
            echo "<td class='border-all text-center'><strong>" . number_format($totalKecamatan) . "</strong></td>";
            echo "<td class='border-all text-center'><strong>" . number_format($totalDesa) . "</strong></td>";
            echo "<td class='border-all text-center'><strong>" . number_format($totalAda) . "</strong></td>";
            echo "<td class='border-all text-center'><strong>" . number_format($totalTidak) . "</strong></td>";
            echo "<td class='border-all text-center'><strong>" . $totalPersentase . "%</strong></td>";
        }
        echo "</tr>";
    }
} else {
    $colspan = $isDetailLevel ? 7 : ($kecamatan ? 7 : ($kota ? 7 : ($provinsi ? 8 : 9)));
    echo "<tr>";
    echo "<td colspan='" . $colspan . "' class='border-all text-center'>Tidak ada data yang sesuai dengan filter</td>";
    echo "</tr>";
}

// Footer
if ($isDetailLevel) {
    echo "<tr><td colspan='7' style='height: 10px; border: none;'></td></tr>";
} else {
    if ($kecamatan) {
        echo "<tr><td colspan='7' style='height: 10px; border: none;'></td></tr>";
    } elseif ($kota) {
        echo "<tr><td colspan='7' style='height: 10px; border: none;'></td></tr>";
    } elseif ($provinsi) {
        echo "<tr><td colspan='8' style='height: 10px; border: none;'></td></tr>";
    } else {
        echo "<tr><td colspan='9' style='height: 10px; border: none;'></td></tr>";
    }
}
echo "</table>";
echo "</body>";
echo "</html>";

// Tutup koneksi
$conn->close();
exit;
?>