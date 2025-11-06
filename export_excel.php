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

// Tentukan level grouping sesuai filter - DITAMBAHKAN KODE
if ($desa) {
    $groupBy = 'l.kode_lokasi, l.nama_tempat';
    $selectField = 'l.nama_tempat AS nama, l.kode_lokasi AS kode_lokasi';
    $orderField = 'l.nama_tempat';
    $countField = 'l.kode_lokasi';
    $level = 'Lokasi';
} elseif ($kecamatan) {
    $groupBy = 'd.kode_wilayah, d.nama';
    $selectField = 'd.nama AS nama, d.kode_wilayah AS kode_wilayah';
    $orderField = 'd.nama';
    $countField = 'l.kode_wilayah';
    $level = 'Desa';
} elseif ($kota) {
    $groupBy = 'c.kode_wilayah, c.nama';
    $selectField = 'c.nama AS nama, c.kode_wilayah AS kode_wilayah';
    $orderField = 'c.nama';
    $countField = 'd.kode_wilayah';
    $level = 'Kecamatan';
} elseif ($provinsi) {
    $groupBy = 'k.kode_wilayah, k.nama';
    $selectField = 'k.nama AS nama, k.kode_wilayah AS kode_wilayah';
    $orderField = 'k.nama';
    $countField = 'c.kode_wilayah';
    $level = 'Kota';
} else {
    $groupBy = 'p.kode_wilayah, p.nama';
    $selectField = 'p.nama AS nama, p.kode_wilayah AS kode_wilayah';
    $orderField = 'p.nama';
    $countField = 'k.kode_wilayah';
    $level = 'Provinsi';
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

$sql = "SELECT
            $selectField,
            COUNT(DISTINCT $countField) AS jumlah,
            SUM(CASE WHEN LOWER(TRIM(COALESCE(l.ketersediaan_sinyal, ''))) = 'yes' THEN 1 ELSE 0 END) AS ada,
            SUM(CASE WHEN LOWER(TRIM(COALESCE(l.ketersediaan_sinyal, ''))) = 'no' THEN 1 ELSE 0 END) AS tidak
        FROM lokasi l
        LEFT JOIN wilayah d ON l.kode_wilayah = d.kode_wilayah
        LEFT JOIN wilayah c ON d.parent_kode = c.kode_wilayah
        LEFT JOIN wilayah k ON c.parent_kode = k.kode_wilayah
        LEFT JOIN wilayah p ON k.parent_kode = p.kode_wilayah
        $whereSQL
        GROUP BY $groupBy
        ORDER BY $orderField ASC";

$result = $conn->query($sql);

// Header untuk download Excel
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="laporan_statistik_lokasi_' . date('Y-m-d_H-i') . '.xls"');
header('Pragma: no-cache');
header('Expires: 0');

// Output Excel dengan styling yang lebih baik
echo "<html>";
echo "<head>";
echo "<meta charset='UTF-8'>";
echo "<style>";
echo "table { border-collapse: collapse; width: 100%; font-family: Arial, sans-serif; }";
echo "th { background-color: #34495e; color: white; font-weight: bold; padding: 8px; text-align: center; }";
echo "td { padding: 6px; border: 1px solid #ddd; }";
echo ".header { background-color: #667eea; color: white; font-size: 16px; font-weight: bold; }";
echo ".subheader { background-color: #f8f9fa; }";
echo ".total { background-color: #34495e; color: white; font-weight: bold; }";
echo ".percentage { color: #2980b9; font-weight: bold; }";
echo ".ada { color: #27ae60; font-weight: bold; }";
echo ".tidak { color: #e74c3c; font-weight: bold; }";
echo "</style>";
echo "</head>";
echo "<body>";

echo "<table border='1'>";
// Header utama
echo "<tr>";
echo "<th colspan='8' class='header'>LAPORAN STATISTIK LOKASI SINYAL</th>";
echo "</tr>";

// Informasi laporan
echo "<tr class='subheader'>";
echo "<td colspan='8'><strong>Sistem Pemetaan Ketersediaan Sinyal</strong></td>";
echo "</tr>";

echo "<tr class='subheader'>";
echo "<td colspan='8'><strong>Tanggal Export:</strong> " . date('d/m/Y H:i') . " | <strong>Level Data:</strong> " . $level . " | <strong>Rentang Wilayah:</strong> " . $filterInfo . "</td>";
echo "</tr>";

echo "<tr class='subheader'>";
echo "<td colspan='8'><strong>Dibuat Oleh:</strong> " . $dibuat_oleh . "</td>";
echo "</tr>";

// Spasi
echo "<tr><td colspan='8' style='height: 10px;'></td></tr>";

// Header tabel
echo "<tr>";
echo "<th>No</th>";
echo "<th>Kode " . $level . "</th>";
echo "<th>Nama " . $level . "</th>";
echo "<th>Jumlah " . ($level == 'Lokasi' ? 'Lokasi' : ($level == 'Desa' ? 'Lokasi' : ($level == 'Kecamatan' ? 'Desa' : ($level == 'Kota' ? 'Kecamatan' : 'Kota')))) . "</th>";
echo "<th>Jumlah Sinyal</th>";
echo "<th>Ada Sinyal</th>";
echo "<th>Tidak Sinyal</th>";
echo "<th>Persentase Sinyal</th>";
echo "</tr>";

if ($result && $result->num_rows > 0) {
    $no = 1;
    $totalLokasi = 0;
    $totalSinyal = 0;
    $totalAda = 0;
    $totalTidak = 0;
    
    while ($row = $result->fetch_assoc()) {
        $ada = $row['ada'] ?? 0;
        $tidak = $row['tidak'] ?? 0;
        $jumlah = $row['jumlah'] ?? 0;
        $jumlahSinyal = $ada + $tidak;
        $persentase = $jumlahSinyal > 0 ? round(($ada / $jumlahSinyal) * 100, 1) : 0;
        
        $totalLokasi += $jumlah;
        $totalSinyal += $jumlahSinyal;
        $totalAda += $ada;
        $totalTidak += $tidak;
        
        echo "<tr>";
        echo "<td>" . $no++ . "</td>";
        echo "<td>" . ($row['kode_wilayah'] ?? $row['kode_lokasi'] ?? '-') . "</td>";
        echo "<td>" . ($row['nama'] ?? 'N/A') . "</td>";
        echo "<td>" . number_format($jumlah) . "</td>";
        echo "<td>" . number_format($jumlahSinyal) . "</td>";
        echo "<td class='ada'>" . number_format($ada) . "</td>";
        echo "<td class='tidak'>" . number_format($tidak) . "</td>";
        echo "<td class='percentage'>" . $persentase . "%</td>";
        echo "</tr>";
    }
    
    // Total
    $totalPersentase = $totalSinyal > 0 ? round(($totalAda / $totalSinyal) * 100, 1) : 0;
    echo "<tr class='total'>";
    echo "<td colspan='3'><strong>TOTAL</strong></td>";
    echo "<td>" . number_format($totalLokasi) . "</td>";
    echo "<td>" . number_format($totalSinyal) . "</td>";
    echo "<td>" . number_format($totalAda) . "</td>";
    echo "<td>" . number_format($totalTidak) . "</td>";
    echo "<td>" . $totalPersentase . "%</td>";
    echo "</tr>";
} else {
    echo "<tr><td colspan='8' style='text-align:center;'>Tidak ada data</td></tr>";
}

// Footer
echo "<tr><td colspan='8' style='height: 10px;'></td></tr>";
echo "<tr class='subheader'>";
echo "<td colspan='8'><em>Laporan ini dihasilkan secara otomatis oleh Sistem Pemetaan Ketersediaan Sinyal</em></td>";
echo "</tr>";

echo "</table>";
echo "</body>";
echo "</html>";
exit;
?>