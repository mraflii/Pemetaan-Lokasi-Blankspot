<?php
// dashboard.php

// PASTIKAN session_start() dipanggil di paling atas sebelum ada output apapun
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

include "config/db.php";

// HITUNG BERDASARKAN DESA/WILAYAH - PERBAIKAN LOGIKA
// Ambil hanya wilayah level desa
$desaQuery = $conn->query("SELECT kode_wilayah, nama FROM wilayah WHERE level = 'desa'");
$totalDesa = 0;
$desaData = [];

if ($desaQuery) {
    while ($desa = $desaQuery->fetch_assoc()) {
        $kodeWilayah = $desa['kode_wilayah'];
        $totalDesa++;
        
        // Hitung lokasi dengan sinyal dan tanpa sinyal untuk desa ini
        $sinyalQuery = $conn->query("
            SELECT 
                COUNT(*) as total_lokasi,
                SUM(CASE WHEN ketersediaan_sinyal = 'Yes' THEN 1 ELSE 0 END) as ada_sinyal,
                SUM(CASE WHEN ketersediaan_sinyal = 'No' THEN 1 ELSE 0 END) as tidak_sinyal
            FROM lokasi 
            WHERE kode_wilayah = '$kodeWilayah' AND is_deleted = 0
        ");
        
        if ($sinyalQuery) {
            $sinyalData = $sinyalQuery->fetch_assoc();
            
            // LOGIKA PERBAIKAN: 
            // - Jika ada lokasi dengan sinyal = 'Yes' ATAU belum ada data lokasi, maka desa ADA sinyal
            // - Jika hanya ada lokasi dengan sinyal = 'No', maka desa TIDAK ADA sinyal
            
            $totalLokasi = $sinyalData['total_lokasi'];
            $adaSinyal = $sinyalData['ada_sinyal'];
            $tidakSinyal = $sinyalData['tidak_sinyal'];
            
            if ($totalLokasi == 0 || $adaSinyal > 0) {
                // Desa belum melapor ATAU ada minimal 1 lokasi dengan sinyal = dianggap ADA sinyal
                $statusDesa = 'Ada Sinyal';
                $kategoriSinyal = 'ada_sinyal';
            } else {
                // Tidak ada lokasi dengan sinyal (semua 'No')
                $statusDesa = 'Tidak Ada Sinyal';
                $kategoriSinyal = 'tidak_sinyal';
            }
            
            $desaData[$kodeWilayah] = [
                'nama' => $desa['nama'],
                'total_lokasi' => $totalLokasi,
                'ada_sinyal' => $adaSinyal,
                'tidak_sinyal' => $tidakSinyal,
                'status' => $statusDesa,
                'kategori' => $kategoriSinyal
            ];
        }
    }
}

// Hitung statistik desa berdasarkan kategori
$desaAdaSinyal = 0;
$desaTidakSinyal = 0;

foreach ($desaData as $desa) {
    if ($desa['kategori'] === 'ada_sinyal') {
        $desaAdaSinyal++;
    } else {
        $desaTidakSinyal++;
    }
}

// Hitung persentase
$persentaseDesaAdaSinyal = $totalDesa > 0 ? round(($desaAdaSinyal / $totalDesa) * 100, 1) : 0;

// PERBAIKAN: Hitung total kabupaten/kota - gunakan level 'kota'
$kabupatenQuery = $conn->query("SELECT COUNT(*) as total FROM wilayah WHERE level = 'kota'");
$totalKabupaten = $kabupatenQuery ? $kabupatenQuery->fetch_assoc()['total'] : 0;

// PERBAIKAN: Hitung total kecamatan - gunakan level 'kecamatan'
$kecamatanQuery = $conn->query("SELECT COUNT(*) as total FROM wilayah WHERE level = 'kecamatan'");
$totalKecamatan = $kecamatanQuery ? $kecamatanQuery->fetch_assoc()['total'] : 0;

// Data lokasi untuk statistik lainnya (untuk info tambahan)
$adaRes = $conn->query("SELECT COUNT(*) as jml FROM lokasi WHERE ketersediaan_sinyal='Yes' AND is_deleted=0");
$adaSinyal = $adaRes ? $adaRes->fetch_assoc()['jml'] : 0;

$tidakRes = $conn->query("SELECT COUNT(*) as jml FROM lokasi WHERE ketersediaan_sinyal='No' AND is_deleted=0");
$tidakSinyal = $tidakRes ? $tidakRes->fetch_assoc()['jml'] : 0;

// TAMBAHAN: Data lokasi terbaru (7 hari terakhir)
$lokasiBaruQuery = $conn->query("
    SELECT COUNT(*) as total_baru 
    FROM lokasi 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) AND is_deleted=0
");
$lokasiBaru = $lokasiBaruQuery ? $lokasiBaruQuery->fetch_assoc()['total_baru'] : 0;

// TAMBAHAN: Data update terbaru (7 hari terakhir)
$updateTerbaruQuery = $conn->query("
    SELECT COUNT(*) as total_update 
    FROM lokasi 
    WHERE updated_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    AND updated_at != created_at AND is_deleted=0
");
$updateTerbaru = $updateTerbaruQuery ? $updateTerbaruQuery->fetch_assoc()['total_update'] : 0;

// TAMBAHAN: Ambil 5 data terbaru untuk ditampilkan
$dataTerbaruQuery = $conn->query("
    SELECT l.*, w.nama as nama_wilayah 
    FROM lokasi l 
    LEFT JOIN wilayah w ON l.kode_wilayah = w.kode_wilayah 
    WHERE l.is_deleted=0
    ORDER BY l.created_at DESC 
    LIMIT 5
");
$dataTerbaru = [];
if ($dataTerbaruQuery) {
    while ($row = $dataTerbaruQuery->fetch_assoc()) {
        $dataTerbaru[] = $row;
    }
}

// TAMBAHAN: Statistik mingguan
$statistikMingguanQuery = $conn->query("
    SELECT 
        DATE(created_at) as tanggal,
        COUNT(*) as jumlah,
        SUM(CASE WHEN ketersediaan_sinyal = 'Yes' THEN 1 ELSE 0 END) as ada_sinyal,
        SUM(CASE WHEN ketersediaan_sinyal = 'No' THEN 1 ELSE 0 END) as tidak_sinyal
    FROM lokasi 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) AND is_deleted=0
    GROUP BY DATE(created_at)
    ORDER BY tanggal DESC
");
$statistikMingguan = [];
if ($statistikMingguanQuery) {
    while ($row = $statistikMingguanQuery->fetch_assoc()) {
        $statistikMingguan[] = $row;
    }
}

// Ambil data lokasi dengan semua field yang diperlukan (untuk peta)
$dataLokasi = $conn->query("SELECT nama_tempat, koordinat, ketersediaan_sinyal, keterangan, kecepatan_sinyal, created_at FROM lokasi WHERE is_deleted=0");
$lokasiArray = [];
if ($dataLokasi) {
    while ($row = $dataLokasi->fetch_assoc()) {
        $coords = explode(',', $row['koordinat']);
        $lat = isset($coords[0]) ? floatval($coords[0]) : 0;
        $lng = isset($coords[1]) ? floatval($coords[1]) : 0;

        $lokasiArray[] = [
            "nama_tempat" => $row['nama_tempat'],
            "koordinat" => $row['koordinat'],
            "keterangan" => $row['keterangan'] ? $row['keterangan'] : 'Tidak ada keterangan',
            "kecepatan_sinyal" => $row['kecepatan_sinyal'] ? $row['kecepatan_sinyal'] . ' Mbps' : 'Tidak terukur',
            "lat" => $lat,
            "lng" => $lng,
            "ketersediaan_sinyal" => $row['ketersediaan_sinyal'],
            "created_at" => $row['created_at']
        ];
    }
}

// DEBUG: Cek struktur data wilayah
$debugLevels = $conn->query("SELECT level, COUNT(*) as jumlah FROM wilayah GROUP BY level");
$debugInfo = "";
while ($debug = $debugLevels->fetch_assoc()) {
    $debugInfo .= $debug['level'] . ": " . $debug['jumlah'] . " | ";
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard Pemetaan Blankspot</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"/>
<link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css"/>
<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
<style>
    :root {
    --primary: #4361ee;
    --secondary: #3a0ca3;
    --success: #4cc9f0;
    --info: #7209b7;
    --warning: #f72585;
    --danger: #e63946;
    --light: #f8f9fa;
    --dark: #212529;
    --gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    --gradient-primary: linear-gradient(135deg, #4361ee 0%, #3a0ca3 100%);
    --gradient-success: linear-gradient(135deg, #4cc9f0 0%, #4895ef 100%);
    --gradient-warning: linear-gradient(135deg, #f72585 0%, #b5179e 100%);
    --gradient-danger: linear-gradient(135deg, #e63946 0%, #a4161a 100%);
    --gradient-kabupaten: linear-gradient(135deg, #ff9a00 0%, #ff6a00 100%);
    --gradient-kecamatan: linear-gradient(135deg, #00b09b 0%, #96c93d 100%);
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Inter', 'Segoe UI', system-ui, -apple-system, sans-serif;
    background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
    min-height: 100vh;
    color: var(--dark);
    line-height: 1.6;
}

.app-container {
    display: flex;
    min-height: 100vh;
    flex-direction: column;
}

/* Main Content */
.main-content {
    flex: 1;
    margin-left: 0;
    padding: 70px 15px 15px;
    width: 100%;
    transition: margin-left 0.3s ease;
}

/* Header */
.page-header {
    display: flex;
    flex-direction: column;
    gap: 15px;
    margin-bottom: 20px;
    background: white;
    padding: 20px;
    border-radius: 15px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.08);
    text-align: center;
}

.header-title h1 {
    font-size: 1.5rem;
    font-weight: 800;
    background: var(--gradient-primary);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    margin-bottom: 5px;
}

.header-title p {
    color: #6c757d;
    font-size: 0.9rem;
}

.header-actions {
    display: flex;
    gap: 10px;
    justify-content: center;
    flex-wrap: wrap;
}

.btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 16px;
    border: none;
    border-radius: 10px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    text-decoration: none;
    font-family: inherit;
}

.btn-primary {
    background: var(--gradient-primary);
    color: white;
    box-shadow: 0 4px 15px rgba(67, 97, 238, 0.3);
}

.btn-light {
    background: white;
    color: var(--dark);
    border: 1px solid #e9ecef;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
}

/* Quick Stats */
.quick-stats {
    display: grid;
    grid-template-columns: 1fr;
    gap: 15px;
    margin-bottom: 20px;
}

.quick-stat-card {
    background: white;
    padding: 20px;
    border-radius: 15px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.08);
    text-align: center;
    position: relative;
    overflow: hidden;
}

.quick-stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: var(--gradient);
}

.quick-stat-number {
    font-size: 2.5rem;
    font-weight: 800;
    margin-bottom: 10px;
}

.quick-stat-card:nth-child(1) .quick-stat-number { color: var(--success); }
.quick-stat-card:nth-child(2) .quick-stat-number { color: var(--primary); }

.quick-stat-label {
    color: #6c757d;
    font-size: 0.9rem;
    font-weight: 500;
}

/* Stats Grid */
.stats-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 15px;
    margin-bottom: 20px;
}

.stat-card {
    background: white;
    padding: 20px;
    border-radius: 15px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.08);
    position: relative;
    overflow: hidden;
    transition: all 0.3s ease;
    border-left: 4px solid;
    cursor: pointer;
}

.stat-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.12);
}

.stat-card.total { border-left-color: var(--primary); }
.stat-card.ada { border-left-color: var(--success); }
.stat-card.tidak { border-left-color: var(--danger); }
.stat-card.baru { border-left-color: var(--info); }
.stat-card.update { border-left-color: #ff6b00; }
.stat-card.kabupaten { border-left-color: #ff6a00; }
.stat-card.kecamatan { border-left-color: #00b09b; }

.stat-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 15px;
}

.stat-icon {
    width: 45px;
    height: 45px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.3rem;
}

.stat-card.total .stat-icon { background: rgba(67, 97, 238, 0.1); color: var(--primary); }
.stat-card.ada .stat-icon { background: rgba(76, 201, 240, 0.1); color: var(--success); }
.stat-card.tidak .stat-icon { background: rgba(230, 57, 70, 0.1); color: var(--danger); }
.stat-card.baru .stat-icon { background: rgba(114, 9, 183, 0.1); color: var(--info); }
.stat-card.update .stat-icon { background: rgba(255, 107, 0, 0.1); color: #ff6b00; }
.stat-card.kabupaten .stat-icon { background: rgba(255, 106, 0, 0.1); color: #ff6a00; }
.stat-card.kecamatan .stat-icon { background: rgba(0, 176, 155, 0.1); color: #00b09b; }

.stat-badge {
    background: #e9ecef;
    color: #6c757d;
    padding: 4px 8px;
    border-radius: 20px;
    font-size: 0.65rem;
    font-weight: 600;
}

.stat-value {
    font-size: 2rem;
    font-weight: 800;
    margin-bottom: 5px;
}

.stat-card.total .stat-value { color: var(--primary); }
.stat-card.ada .stat-value { color: var(--success); }
.stat-card.tidak .stat-value { color: var(--danger); }
.stat-card.baru .stat-value { color: var(--info); }
.stat-card.update .stat-value { color: #ff6b00; }
.stat-card.kabupaten .stat-value { color: #ff6a00; }
.stat-card.kecamatan .stat-value { color: #00b09b; }

.stat-label {
    color: #6c757d;
    font-weight: 500;
    margin-bottom: 10px;
    font-size: 0.9rem;
}

.stat-details {
    display: flex;
    flex-direction: column;
    gap: 8px;
    font-size: 0.75rem;
    color: #6c757d;
}

.stat-detail {
    display: flex;
    align-items: center;
    gap: 4px;
}

/* Recent Data Section */
.recent-data-section {
    background: white;
    border-radius: 15px;
    padding: 20px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.08);
    margin-bottom: 20px;
}

.section-header {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 15px;
}

.section-header i {
    color: var(--primary);
    font-size: 1.3rem;
}

.section-header h3 {
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--dark);
    margin: 0;
}

.weekly-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
    gap: 10px;
    margin-bottom: 15px;
}

.day-stat {
    background: #f8f9fa;
    padding: 12px;
    border-radius: 10px;
    text-align: center;
    border-left: 3px solid var(--primary);
}

.day-stat .date {
    font-size: 0.7rem;
    color: #6c757d;
    margin-bottom: 6px;
    font-weight: 600;
}

.day-stat .count {
    font-size: 1rem;
    font-weight: 700;
    color: var(--dark);
    margin-bottom: 4px;
}

.day-stat .signal-stats {
    display: flex;
    justify-content: center;
    gap: 8px;
    font-size: 0.65rem;
}

.day-stat .signal-ada {
    color: var(--success);
    font-weight: 600;
}

.day-stat .signal-tidak {
    color: var(--danger);
    font-weight: 600;
}

.table-wrapper {
    overflow-x: auto;
    border-radius: 10px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    margin-top: 10px;
}

.recent-table {
    width: 100%;
    border-collapse: collapse;
    background: white;
    min-width: 500px;
}

.recent-table th {
    background: var(--primary);
    color: white;
    font-weight: 600;
    padding: 12px 8px;
    text-align: left;
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.recent-table td {
    padding: 12px 8px;
    border-bottom: 1px solid #f1f3f4;
    vertical-align: top;
    font-size: 0.8rem;
}

.recent-table tr:hover td {
    background: #f8f9fa;
}

.status-badge {
    padding: 4px 8px;
    border-radius: 15px;
    font-size: 0.7rem;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

.status-ada {
    background: #d4edda;
    color: #155724;
}

.status-tidak {
    background: #f8d7da;
    color: #721c24;
}

.timestamp-cell {
    color: #6c757d;
    font-size: 0.7rem;
    font-family: 'Monaco', 'Consolas', monospace;
}

/* Map Container */
.map-container {
    background: white;
    border-radius: 15px;
    padding: 20px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.08);
    margin-bottom: 20px;
    position: relative;
}

.map-header {
    display: flex;
    flex-direction: column;
    gap: 15px;
    margin-bottom: 15px;
}

.map-title {
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--dark);
    display: flex;
    align-items: center;
    gap: 8px;
    justify-content: center;
}

.map-controls {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    justify-content: center;
}

.map-control-btn {
    padding: 6px 12px;
    border: none;
    border-radius: 8px;
    background: white;
    color: var(--dark);
    cursor: pointer;
    font-size: 0.7rem;
    font-weight: 500;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 4px;
    border: 1px solid #e9ecef;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}

.map-control-btn:hover {
    background: var(--primary);
    color: white;
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(67, 97, 238, 0.3);
}

#mapDashboard {
    width: 100%;
    height: 400px;
    border-radius: 10px;
    overflow: hidden;
    box-shadow: 0 3px 10px rgba(0,0,0,0.1);
    position: relative;
}

.legend {
    display: flex;
    flex-direction: column;
    gap: 8px;
    margin-top: 15px;
    align-items: center;
}

.legend-item {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 0.7rem;
    color: var(--dark);
    padding: 4px 10px;
    background: white;
    border-radius: 15px;
    box-shadow: 0 2px 6px rgba(0,0,0,0.1);
}

.legend-color {
    width: 10px;
    height: 10px;
    border-radius: 50%;
}

/* Search Box Styling - PERBAIKAN: Ditempatkan di DALAM peta */
.search-container {
    position: absolute;
    top: 10px;
    left: 10px;
    right: 10px;
    z-index: 1000;
    margin-bottom: 0;
}

.search-wrapper {
    background: white;
    border-radius: 20px;
    padding: 8px 12px;
    box-shadow: 0 3px 15px rgba(0,0,0,0.2);
    display: flex;
    align-items: center;
    gap: 8px;
    border: 2px solid var(--primary);
    position: relative;
    max-width: 400px;
    margin: 0 auto;
}

.search-wrapper i {
    color: var(--primary);
    font-size: 14px;
}

.search-input {
    border: none;
    outline: none;
    width: 100%;
    font-size: 12px;
    color: var(--dark);
    background: transparent;
}

.search-input::placeholder {
    color: #95a5a6;
    font-size: 12px;
}

/* Autocomplete Suggestions */
.suggestions-container {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background: white;
    border-radius: 10px;
    margin-top: 5px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.15);
    max-height: 250px;
    overflow-y: auto;
    z-index: 1001;
    display: none;
}

.suggestion-item {
    padding: 10px 12px;
    cursor: pointer;
    border-bottom: 1px solid #f1f1f1;
    transition: all 0.2s ease;
    display: flex;
    flex-direction: column;
}

.suggestion-item:hover {
    background: #f8f9fa;
}

.suggestion-item:last-child {
    border-bottom: none;
}

.suggestion-title {
    font-weight: 600;
    color: var(--dark);
    font-size: 12px;
    margin-bottom: 2px;
}

.suggestion-desc {
    font-size: 10px;
    color: #7f8c8d;
}

.suggestion-highlight {
    background: #fff3cd;
    padding: 1px 2px;
    border-radius: 3px;
}

.no-results {
    padding: 12px;
    text-align: center;
    color: #7f8c8d;
    font-size: 12px;
}

/* Desktop Styles */
@media (min-width: 769px) {
    .app-container {
        flex-direction: row;
    }
    
    .main-content {
        margin-left: 280px;
        padding: 30px;
    }
    
    .page-header {
        flex-direction: row;
        justify-content: space-between;
        align-items: center;
        text-align: left;
    }
    
    .header-actions {
        justify-content: flex-end;
    }
    
    .stats-grid {
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
    }
    
    .quick-stats {
        grid-template-columns: 1fr 1fr;
        gap: 20px;
    }
    
    .weekly-stats {
        grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
        gap: 12px;
    }
    
    .map-header {
        flex-direction: row;
        justify-content: space-between;
        align-items: center;
    }
    
    .map-controls {
        justify-content: flex-end;
    }
    
    /* PERBAIKAN: Search container untuk desktop - di dalam peta */
    .search-container {
        position: absolute;
        top: 10px;
        left: 50px;
        right: auto;
        width: 350px;
        margin: 0;
    }
    
    .search-wrapper {
        margin: 0;
        max-width: 100%;
    }
    
    .legend {
        flex-direction: row;
        justify-content: center;
        gap: 15px;
    }
}

/* Tablet Styles */
@media (min-width: 481px) and (max-width: 768px) {
    .main-content {
        padding: 80px 20px 20px;
    }
    
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 15px;
    }
    
    .quick-stats {
        grid-template-columns: 1fr 1fr;
    }
    
    .weekly-stats {
        grid-template-columns: repeat(auto-fit, minmax(110px, 1fr));
    }
    
    .map-controls {
        justify-content: center;
    }
    
    /* PERBAIKAN: Search container untuk tablet - di dalam peta */
    .search-container {
        position: absolute;
        top: 10px;
        left: 10px;
        right: 10px;
        margin-bottom: 0;
    }
}

/* Mobile Styles */
@media (max-width: 480px) {
    .main-content {
        padding: 70px 10px 10px;
    }
    
    .page-header {
        padding: 15px;
    }
    
    .header-title h1 {
        font-size: 1.3rem;
    }
    
    .stat-card {
        padding: 15px;
    }
    
    .stat-value {
        font-size: 1.8rem;
    }
    
    .quick-stat-number {
        font-size: 2rem;
    }
    
    #mapDashboard {
        height: 350px;
    }
    
    .recent-table {
        min-width: 600px;
    }
    
    /* PERBAIKAN: Search container untuk mobile - di dalam peta */
    .search-container {
        position: absolute;
        top: 10px;
        left: 10px;
        right: 10px;
        margin-bottom: 0;
    }
    
    .search-wrapper {
        max-width: 100%;
    }
}

/* Animation */
.fade-in {
    animation: fadeIn 0.6s ease-out;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Notification Styles */
.notification {
    position: fixed;
    top: 20px;
    right: 20px;
    background: #17a2b8;
    color: white;
    padding: 15px 20px;
    border-radius: 10px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.2);
    z-index: 10000;
    animation: slideInRight 0.3s ease;
    max-width: 400px;
}

.notification-success {
    background: #28a745;
}

.notification-warning {
    background: #ffc107;
    color: #212529;
}

.notification-error {
    background: #dc3545;
}

.notification-content {
    display: flex;
    align-items: center;
    gap: 10px;
}

@keyframes slideInRight {
    from { transform: translateX(100%); opacity: 0; }
    to { transform: translateX(0); opacity: 1; }
}

@keyframes slideOutRight {
    from { transform: translateX(0); opacity: 1; }
    to { transform: translateX(100%); opacity: 0; }
}
</style>
</head>
<body>

<!-- DEBUG INFO (akan muncul di source code) -->
<!-- DEBUG Wilayah Levels: <?= $debugInfo ?> -->

<div class="app-container">
    <!-- Sidebar -->
    <?php include 'sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <div class="page-header fade-in">
            <div class="header-title">
                <h1><i class="fas fa-satellite-dish"></i> Dashboard Pemetaan</h1>
                <p>Monitor Ketersediaan Sinyal di Seluruh Wilayah</p>
            </div>
            <div class="header-actions">
                <button onclick="location.reload()" class="btn btn-light">
                    <i class="fas fa-sync-alt"></i> Refresh
                </button>
                <a href="report.php" class="btn btn-primary">
                    <i class="fas fa-chart-bar"></i> Laporan
                </a>
            </div>
        </div>

        <!-- Quick Stats - HANYA 2 CARD (persentase dan total desa) -->
        <div class="quick-stats fade-in">
            <div class="quick-stat-card">
                <div class="quick-stat-number"><?= $persentaseDesaAdaSinyal ?>%</div>
                <div class="quick-stat-label">Desa dengan Sinyal</div>
            </div>
            <div class="quick-stat-card">
                <div class="quick-stat-number"><?= $totalDesa ?></div>
                <div class="quick-stat-label">Total Desa </div>
            </div>
        </div>

        <!-- Stats Grid - DENGAN CARD KABUPATEN & KECAMATAN -->
        <div class="stats-grid fade-in">
            <div class="stat-card kabupaten" onclick="showNotification('Total Kabupaten/Kota: <?= $totalKabupaten ?> wilayah', 'info')">
                <div class="stat-header">
                    <div class="stat-icon">
                        <i class="fas fa-city"></i>
                    </div>
                    <div class="stat-badge">KABUPATEN/KOTA</div>
                </div>
                <div class="stat-value"><?= number_format($totalKabupaten) ?></div>
                <div class="stat-label">Total Kabupaten/Kota</div>
                <div class="stat-details">
                    <div class="stat-detail">
                        <i class="fas fa-map-marked-alt"></i>
                        <span>Wilayah Administrasi</span>
                    </div>
                    <div class="stat-detail">
                        <i class="fas fa-layer-group"></i>
                        <span>Level Kabupaten/Kota</span>
                    </div>
                </div>
            </div>
            <div class="stat-card kecamatan" onclick="showNotification('Total Kecamatan: <?= $totalKecamatan ?> wilayah', 'info')">
                <div class="stat-header">
                    <div class="stat-icon">
                        <i class="fas fa-map"></i>
                    </div>
                    <div class="stat-badge">KECAMATAN</div>
                </div>
                <div class="stat-value"><?= number_format($totalKecamatan) ?></div>
                <div class="stat-label">Total Kecamatan</div>
                <div class="stat-details">
                    <div class="stat-detail">
                        <i class="fas fa-map-marked-alt"></i>
                        <span>Wilayah Administrasi</span>
                    </div>
                    <div class="stat-detail">
                        <i class="fas fa-layer-group"></i>
                        <span>Level Kecamatan</span>
                    </div>
                </div>
            </div>
            
            <div class="stat-card total" onclick="zoomToAll()">
                <div class="stat-header">
                    <div class="stat-icon">
                        <i class="fas fa-village"></i>
                    </div>
                    <div class="stat-badge">DESA</div>
                </div>
                <div class="stat-value"><?= number_format($totalDesa) ?></div>
                <div class="stat-label">Total Desa </div>
                <div class="stat-details">
                    <div class="stat-detail">
                        <i class="fas fa-check-circle" style="color: var(--success);"></i>
                        <span><?= $desaAdaSinyal ?> Desa Ada Sinyal</span>
                    </div>
                    <div class="stat-detail">
                        <i class="fas fa-times-circle" style="color: var(--danger);"></i>
                        <span><?= $desaTidakSinyal ?> Desa Tidak Ada</span>
                    </div>
                </div>
            </div>
           

            <div class="stat-card ada" onclick="zoomToGroup('Yes')">
                <div class="stat-header">
                    <div class="stat-icon">
                        <i class="fas fa-signal"></i>
                    </div>
                    <div class="stat-badge">SINYAL</div>
                </div>
                <div class="stat-value"><?= number_format($desaAdaSinyal) ?></div>
                <div class="stat-label">Desa dengan Sinyal</div>
                <div class="stat-details">
                    <div class="stat-detail">
                        <i class="fas fa-map-marker-alt"></i>
                        <span><?= $adaSinyal ?> Lokasi Tercover</span>
                    </div>
                    <div class="stat-detail">
                        <i class="fas fa-percentage"></i>
                        <span><?= $persentaseDesaAdaSinyal ?>% dari total</span>
                    </div>
                </div>
            </div>

            <div class="stat-card tidak" onclick="zoomToGroup('No')">
                <div class="stat-header">
                    <div class="stat-icon">
                        <i class="fas fa-signal-slash"></i>
                    </div>
                    <div class="stat-badge">BLANKSPOT</div>
                </div>
                <div class="stat-value"><?= number_format($desaTidakSinyal) ?></div>
                <div class="stat-label">Desa Tanpa Sinyal</div>
                <div class="stat-details">
                    <div class="stat-detail">
                        <i class="fas fa-map-marker-alt"></i>
                        <span><?= $tidakSinyal ?> Lokasi Blankspot</span>
                    </div>
                    <div class="stat-detail">
                        <i class="fas fa-percentage"></i>
                        <span><?= round(($desaTidakSinyal / $totalDesa) * 100, 1) ?>% dari total</span>
                    </div>
                </div>
            </div>

            <div class="stat-card baru" onclick="showRecentData('new')">
                <div class="stat-header">
                    <div class="stat-icon">
                        <i class="fas fa-star"></i>
                    </div>
                    <div class="stat-badge">BARU</div>
                </div>
                <div class="stat-value"><?= number_format($lokasiBaru) ?></div>
                <div class="stat-label">Data Baru (7 Hari)</div>
                <div class="stat-details">
                    <div class="stat-detail">
                        <i class="fas fa-calendar"></i>
                        <span>Terakhir ditambahkan</span>
                    </div>
                </div>
            </div>

            <div class="stat-card update" onclick="showRecentData('updated')">
                <div class="stat-header">
                    <div class="stat-icon">
                        <i class="fas fa-sync"></i>
                    </div>
                    <div class="stat-badge">UPDATE</div>
                </div>
                <div class="stat-value"><?= number_format($updateTerbaru) ?></div>
                <div class="stat-label">Update Terbaru (7 Hari)</div>
                <div class="stat-details">
                    <div class="stat-detail">
                        <i class="fas fa-history"></i>
                        <span>Data diperbarui</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Data Terbaru Section -->
        <div class="recent-data-section fade-in">
            <div class="section-header">
                <i class="fas fa-history"></i>
                <h3>Data Lokasi Terbaru</h3>
            </div>
            
            <!-- Statistik Mingguan -->
            <?php if (!empty($statistikMingguan)): ?>
            <div class="weekly-stats">
                <?php foreach ($statistikMingguan as $stat): ?>
                    <div class="day-stat">
                        <div class="date"><?= date('d/m', strtotime($stat['tanggal'])) ?></div>
                        <div class="count">+<?= $stat['jumlah'] ?> data</div>
                        <div class="signal-stats">
                            <span class="signal-ada">✓ <?= $stat['ada_sinyal'] ?></span>
                            <span class="signal-tidak">✗ <?= $stat['tidak_sinyal'] ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <div class="table-wrapper">
                <table class="recent-table">
                    <thead>
                        <tr>
                            <th>Nama Tempat</th>
                            <th>Wilayah</th>
                            <th>Status Sinyal</th>
                            <th>Kecepatan</th>
                            <th>Ditambahkan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($dataTerbaru)): ?>
                            <?php foreach ($dataTerbaru as $data): ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($data['nama_tempat']) ?></strong>
                                        <div style="font-size: 11px; color: #6c757d; margin-top: 2px;">
                                            <?= htmlspecialchars($data['keterangan']) ?>
                                        </div>
                                    </td>
                                    <td><?= htmlspecialchars($data['nama_wilayah']) ?></td>
                                    <td>
                                        <span class="status-badge <?= $data['ketersediaan_sinyal'] === 'Yes' ? 'status-ada' : 'status-tidak' ?>">
                                            <i class="fas <?= $data['ketersediaan_sinyal'] === 'Yes' ? 'fa-signal' : 'fa-signal-slash' ?>"></i>
                                            <?= $data['ketersediaan_sinyal'] === 'Yes' ? 'Ada Sinyal' : 'Tidak Ada Sinyal' ?>
                                        </span>
                                    </td>
                                    <td style="font-weight: bold; text-align: center;">
                                        <?= $data['kecepatan_sinyal'] ?> Mbps
                                    </td>
                                    <td class="timestamp-cell">
                                        <?= date('d/m/Y H:i', strtotime($data['created_at'])) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" style="padding: 20px; text-align: center; color: #7f8c8d;">
                                    <i class="fas fa-info-circle" style="margin-right: 8px;"></i>
                                    Tidak ada data terbaru
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Peta -->
        <div class="map-container fade-in">
            <div class="map-header">
                <h2 class="map-title">
                    <i class="fas fa-map"></i> Peta Sebaran Lokasi
                </h2>
                <div class="map-controls">
                    <button class="map-control-btn" onclick="zoomToAll()">
                        <i class="fas fa-globe-asia"></i> Semua
                    </button>
                    <button class="map-control-btn" onclick="zoomToGroup('Yes')">
                        <i class="fas fa-signal"></i> Ada Sinyal
                    </button>
                    <button class="map-control-btn" onclick="zoomToGroup('No')">
                        <i class="fas fa-signal-slash"></i> Tidak Ada
                    </button>
                    <button class="map-control-btn" onclick="showNewDataOnMap()">
                        <i class="fas fa-star"></i> Data Baru
                    </button>
                    <button class="map-control-btn" onclick="resetView()">
                        <i class="fas fa-sync"></i> Reset
                    </button>
                </div>
            </div>
            
            <!-- Map Container dengan Search Box di DALAM peta -->
            <div id="mapDashboard">
                <!-- Search Box ditempatkan di DALAM peta -->
                <div class="search-container">
                    <div class="search-wrapper">
                        <i class="fas fa-search"></i>
                        <input type="text" id="searchInput" class="search-input" placeholder="Cari lokasi, alamat, atau koordinat...">
                        <div class="suggestions-container" id="suggestionsContainer">
                            <!-- Suggestions akan muncul di sini -->
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Legend -->
            <div class="legend">
                <div class="legend-item">
                    <div class="legend-color" style="background: #4cc9f0;"></div>
                    <span>Ada Sinyal (<?= $desaAdaSinyal ?> Desa)</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color" style="background: #e63946;"></div>
                    <span>Tidak Ada Sinyal (<?= $desaTidakSinyal ?> Desa)</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color" style="background: #7209b7;"></div>
                    <span>Data Baru (<?= $lokasiBaru ?>)</span>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Inisialisasi Peta
    var map = L.map('mapDashboard').setView([5.5483, 95.3238], 12);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '© OpenStreetMap contributors'
    }).addTo(map);

    // Custom icons
    var greenIcon = new L.Icon({
        iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-green.png',
        shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.3/images/marker-shadow.png',
        iconSize: [25, 41],
        iconAnchor: [12, 41],
        popupAnchor: [1, -34],
        shadowSize: [41, 41]
    });

    var redIcon = new L.Icon({
        iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-red.png',
        shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.3/images/marker-shadow.png',
        iconSize: [25, 41],
        iconAnchor: [12, 41],
        popupAnchor: [1, -34],
        shadowSize: [41, 41]
    });

    var purpleIcon = new L.Icon({
        iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-violet.png',
        shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.3/images/marker-shadow.png',
        iconSize: [25, 41],
        iconAnchor: [12, 41],
        popupAnchor: [1, -34],
        shadowSize: [41, 41]
    });

    // Data lokasi dari PHP
    var lokasiData = <?= json_encode($lokasiArray) ?>;

    // Group marker
    var groupYes = L.featureGroup();
    var groupNo = L.featureGroup();
    var groupNew = L.featureGroup();
    var groupAll = L.featureGroup();

    // Array untuk pencarian
    var searchData = [];

    // TAMBAHAN: Fungsi untuk menentukan apakah data baru (7 hari terakhir)
    function isNewData(createdAt) {
        const sevenDaysAgo = new Date();
        sevenDaysAgo.setDate(sevenDaysAgo.getDate() - 7);
        return new Date(createdAt) > sevenDaysAgo;
    }

    // Tambahkan marker ke peta
    lokasiData.forEach(function(lokasi){
        var isNew = isNewData(lokasi.created_at);
        var icon = lokasi.ketersediaan_sinyal === "Yes" ? 
                   (isNew ? purpleIcon : greenIcon) : 
                   redIcon;

        // TAMBAHAN: Format tanggal untuk popup
        var createdDate = new Date(lokasi.created_at).toLocaleDateString('id-ID', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });

        var marker = L.marker([lokasi.lat, lokasi.lng], { icon: icon })
            .bindPopup(`
                <div style="min-width: 220px; font-family: 'Inter', sans-serif;">
                    <h4 style="margin: 0 0 10px 0; color: #2c3e50; font-size: 14px; border-bottom: 1px solid #eee; padding-bottom: 5px;">
                        <i class="fas fa-map-marker-alt" style="color: #e74c3c;"></i> 
                        ${lokasi.nama_tempat}
                        ${isNew ? '<span style="background: #7209b7; color: white; padding: 2px 6px; border-radius: 8px; font-size: 10px; margin-left: 5px;">BARU</span>' : ''}
                    </h4>
                    <p style="margin: 6px 0; font-size: 12px; color: #555;">
                        <i class="fas fa-info-circle" style="color: #3498db;"></i> 
                        <strong>Keterangan:</strong> ${lokasi.keterangan}
                    </p>
                    <p style="margin: 6px 0; font-size: 12px; color: #555;">
                        <i class="fas fa-location-dot" style="color: #27ae60;"></i> 
                        <strong>Koordinat:</strong> ${lokasi.koordinat}
                    </p>
                    <p style="margin: 6px 0; font-size: 12px; color: #555;">
                        <i class="fas fa-tachometer-alt" style="color: #f39c12;"></i> 
                        <strong>Kecepatan:</strong> ${lokasi.kecepatan_sinyal}
                    </p>
                    <p style="margin: 6px 0; font-size: 12px;">
                        <i class="fas ${lokasi.ketersediaan_sinyal === "Yes" ? "fa-signal text-success" : "fa-signal-slash text-danger"}" 
                           style="margin-right: 5px;"></i>
                        Status: <strong style="color: ${lokasi.ketersediaan_sinyal === "Yes" ? "#27ae60" : "#e74c3c"}">
                            ${lokasi.ketersediaan_sinyal === "Yes" ? "Ada Sinyal" : "Tidak Ada Sinyal"}
                        </strong>
                    </p>
                    <!-- TAMBAHAN: Info timestamp di popup -->
                    <p style="margin: 6px 0; font-size: 11px; color: #95a5a6; border-top: 1px solid #f1f1f1; padding-top: 5px;">
                        <i class="fas fa-calendar-plus" style="margin-right: 5px;"></i>
                        Ditambahkan: ${createdDate}
                    </p>
                </div>
            `);
        
        groupAll.addLayer(marker);
        if(lokasi.ketersediaan_sinyal === "Yes"){
            groupYes.addLayer(marker);
        } else {
            groupNo.addLayer(marker);
        }
        
        if (isNew) {
            groupNew.addLayer(marker);
        }

        // Tambahkan data untuk pencarian
        searchData.push({
            loc: [lokasi.lat, lokasi.lng],
            title: lokasi.nama_tempat,
            keterangan: lokasi.keterangan,
            koordinat: lokasi.koordinat,
            kecepatan: lokasi.kecepatan_sinyal,
            status: lokasi.ketersediaan_sinyal,
            isNew: isNew,
            marker: marker
        });
    });

    // Tambahkan group ke peta
    groupAll.addTo(map);

    // TAMBAHAN: Fungsi untuk menampilkan data terbaru
    function showRecentData(type) {
        if (type === 'new') {
            showNotification('Menampilkan data lokasi baru 7 hari terakhir: <?= $lokasiBaru ?> data', 'info');
            showNewDataOnMap();
        } else if (type === 'updated') {
            showNotification('Menampilkan data yang diupdate 7 hari terakhir: <?= $updateTerbaru ?> data', 'info');
            showNewDataOnMap();
        }
    }

    // TAMBAHAN: Fungsi untuk menampilkan data baru di peta
    function showNewDataOnMap() {
        map.removeLayer(groupAll);
        map.removeLayer(groupYes);
        map.removeLayer(groupNo);
        groupNew.addTo(map);
        if (groupNew.getBounds().isValid()) {
            map.fitBounds(groupNew.getBounds().pad(0.1));
        }
    }

    // Fungsi untuk menampilkan highlight teks
    function highlightText(text, searchTerm) {
        if (!searchTerm) return text;
        
        const regex = new RegExp(`(${searchTerm})`, 'gi');
        return text.replace(regex, '<span class="suggestion-highlight">$1</span>');
    }

    // Fungsi untuk menampilkan suggestions
    function showSuggestions(searchTerm) {
        const suggestionsContainer = document.getElementById('suggestionsContainer');
        
        if (!searchTerm.trim()) {
            suggestionsContainer.style.display = 'none';
            return;
        }

        const filteredResults = searchData.filter(item => 
            item.title.toLowerCase().includes(searchTerm.toLowerCase()) || 
            item.keterangan.toLowerCase().includes(searchTerm.toLowerCase()) ||
            item.koordinat.toLowerCase().includes(searchTerm.toLowerCase())
        );

        if (filteredResults.length === 0) {
            suggestionsContainer.innerHTML = '<div class="no-results">Tidak ada hasil ditemukan</div>';
            suggestionsContainer.style.display = 'block';
            return;
        }

        let suggestionsHTML = '';
        filteredResults.slice(0, 8).forEach(item => {
            const statusIcon = item.status === "Yes" ? 
                '<i class="fas fa-signal" style="color: #4cc9f0; margin-right: 5px;"></i>' : 
                '<i class="fas fa-signal-slash" style="color: #e63946; margin-right: 5px;"></i>';
            
            const newBadge = item.isNew ? '<span style="background: #7209b7; color: white; padding: 1px 4px; border-radius: 6px; font-size: 9px; margin-left: 3px;">BARU</span>' : '';
            
            suggestionsHTML += `
                <div class="suggestion-item" data-lat="${item.loc[0]}" data-lng="${item.loc[1]}">
                    <div class="suggestion-title">
                        ${statusIcon}${highlightText(item.title, searchTerm)}${newBadge}
                    </div>
                    <div class="suggestion-desc">
                        ${highlightText(item.keterangan, searchTerm)}
                    </div>
                    <div class="suggestion-desc">
                        <small>Koordinat: ${highlightText(item.koordinat, searchTerm)}</small>
                    </div>
                </div>
            `;
        });

        suggestionsContainer.innerHTML = suggestionsHTML;
        suggestionsContainer.style.display = 'block';

        // Tambahkan event listener untuk setiap suggestion item
        document.querySelectorAll('.suggestion-item').forEach(item => {
            item.addEventListener('click', function() {
                const lat = parseFloat(this.getAttribute('data-lat'));
                const lng = parseFloat(this.getAttribute('data-lng'));
                
                map.setView([lat, lng], 15);
                
                // Cari marker yang sesuai dan buka popup
                searchData.forEach(data => {
                    if (data.loc[0] === lat && data.loc[1] === lng) {
                        data.marker.openPopup();
                    }
                });
                
                // Sembunyikan suggestions
                suggestionsContainer.style.display = 'none';
                document.getElementById('searchInput').value = this.querySelector('.suggestion-title').textContent.replace(/[🔴🟢]/g, '').trim();
            });
        });
    }

    // Event listener untuk input search
    document.getElementById('searchInput').addEventListener('input', function(e) {
        showSuggestions(e.target.value);
    });

    // Event listener untuk ikon search
    document.querySelector('.search-wrapper i').addEventListener('click', function() {
        const searchTerm = document.getElementById('searchInput').value;
        if (searchTerm.trim()) {
            searchLocation(searchTerm);
        }
    });

    // Sembunyikan suggestions ketika klik di luar
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.search-wrapper')) {
            document.getElementById('suggestionsContainer').style.display = 'none';
        }
    });

    // Fungsi pencarian
    function searchLocation(searchTerm) {
        if (!searchTerm.trim()) {
            zoomToAll();
            return;
        }

        const foundItems = searchData.filter(item => 
            item.title.toLowerCase().includes(searchTerm.toLowerCase()) || 
            item.keterangan.toLowerCase().includes(searchTerm.toLowerCase()) ||
            item.koordinat.toLowerCase().includes(searchTerm.toLowerCase())
        );

        if (foundItems.length > 0) {
            // Zoom ke hasil pertama
            const firstResult = foundItems[0];
            map.setView(firstResult.loc, 15);
            firstResult.marker.openPopup();
        } else {
            showNotification('Lokasi tidak ditemukan. Coba dengan kata kunci lain.', 'warning');
        }
    }

    // Fungsi zoom dan filter
    function zoomToGroup(status){
        if(status === "Yes"){
            map.removeLayer(groupAll);
            map.removeLayer(groupNo);
            map.removeLayer(groupNew);
            groupYes.addTo(map);
            if (groupYes.getBounds().isValid()) {
                map.fitBounds(groupYes.getBounds().pad(0.1));
            }
        } else if(status === "No"){
            map.removeLayer(groupAll);
            map.removeLayer(groupYes);
            map.removeLayer(groupNew);
            groupNo.addTo(map);
            if (groupNo.getBounds().isValid()) {
                map.fitBounds(groupNo.getBounds().pad(0.1));
            }
        }
    }

    function zoomToAll(){
        map.removeLayer(groupYes);
        map.removeLayer(groupNo);
        map.removeLayer(groupNew);
        groupAll.addTo(map);
        if (groupAll.getBounds().isValid()) {
            map.fitBounds(groupAll.getBounds().pad(0.1));
        }
    }

    function resetView(){
        map.setView([5.5483, 95.3238], 12);
        zoomToAll();
        document.getElementById('searchInput').value = '';
        document.getElementById('suggestionsContainer').style.display = 'none';
    }

    // Auto zoom ke semua marker saat load
    setTimeout(() => {
        if (groupAll.getBounds().isValid()) {
            map.fitBounds(groupAll.getBounds().pad(0.1));
        }
    }, 500);

    // Notification system
    function showNotification(message, type = 'info') {
        // Remove existing notification
        const existingNotification = document.querySelector('.notification');
        if (existingNotification) {
            existingNotification.remove();
        }

        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.innerHTML = `
            <div class="notification-content">
                <i class="fas fa-${getNotificationIcon(type)}"></i>
                <span>${message}</span>
            </div>
        `;

        document.body.appendChild(notification);

        // Auto remove after 3 seconds
        setTimeout(() => {
            notification.style.animation = 'slideOutRight 0.3s ease';
            setTimeout(() => notification.remove(), 300);
        }, 3000);
    }

    function getNotificationIcon(type) {
        switch(type) {
            case 'success': return 'check-circle';
            case 'warning': return 'exclamation-triangle';
            case 'error': return 'times-circle';
            default: return 'info-circle';
        }
    }

</script>

</body>
</html>