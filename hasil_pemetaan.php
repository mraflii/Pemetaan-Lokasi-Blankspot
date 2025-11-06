<?php
include "config/db.php";
session_start();

// Ambil parameter filter jika ada
$filterSinyal = isset($_GET['filter']) ? $_GET['filter'] : '';
$levelWilayah = isset($_GET['level']) ? $_GET['level'] : '';
$searchKeyword = isset($_GET['search']) ? $_GET['search'] : '';

// Query wilayah
$wilayahQuery = "SELECT * FROM wilayah";
if($levelWilayah != ''){
    $wilayahQuery .= " WHERE level='".$conn->real_escape_string($levelWilayah)."'";
}
$wilayahQuery .= " ORDER BY level ASC, kode_wilayah ASC";

$wilayahData = [];
$result = $conn->query($wilayahQuery);
while($row = $result->fetch_assoc()){
    $wilayahData[$row['parent_kode']][] = $row;
}

// Query lokasi dengan search
$lokasiQuery = "SELECT * FROM lokasi WHERE 1=1";
if($filterSinyal != ''){
    if($filterSinyal == 'Yes' || $filterSinyal == 'No'){
        $lokasiQuery .= " AND ketersediaan_sinyal='".$conn->real_escape_string($filterSinyal)."'";
    }
}
if($searchKeyword != ''){
    $searchKeyword = $conn->real_escape_string($searchKeyword);
    $lokasiQuery .= " AND (nama_tempat LIKE '%$searchKeyword%' 
                          OR keterangan LIKE '%$searchKeyword%' 
                          OR koordinat LIKE '%$searchKeyword%'
                          OR kode_lokasi LIKE '%$searchKeyword%')";
}
$lokasiQuery .= " ORDER BY kode_lokasi ASC";

$lokasiData = [];
$result2 = $conn->query($lokasiQuery);
while($row = $result2->fetch_assoc()){
    $lokasiData[$row['kode_wilayah']][] = $row;
}

// Hitung total hasil
$totalLokasi = 0;
foreach($lokasiData as $lokasis){
    $totalLokasi += count($lokasis);
}

// Level indentation
$levelIndent = ['provinsi'=>0,'kota'=>1,'kecamatan'=>2,'desa'=>3,'lokasi'=>4];

// Ikon per level
$icons = [
    'provinsi' => "<i class='fas fa-flag'></i>",
    'kota' => "<i class='fas fa-city'></i>",
    'kecamatan' => "<i class='fas fa-building'></i>",
    'desa' => "<i class='fas fa-house-chimney'></i>",
    'lokasi' => "<i class='fas fa-map-marker-alt'></i>"
];

// Fungsi tampil wilayah
function tampilWilayah($parentKode, $wilayahData, $lokasiData, $levelIndent, $icons, $searchKeyword = ''){
    if(isset($wilayahData[$parentKode])){
        foreach($wilayahData[$parentKode] as $w){
            $class = $w['level'];

            if($class == 'provinsi'){
                echo "<div class='provinsi-block fade-in'>";
                echo "<div class='provinsi-header'>";
                echo "<h2>".$icons['provinsi']." ".htmlspecialchars($w['nama'])."</h2>";
                echo "<div class='provinsi-actions'>
                        <a href='create_wilayah_sekaligus.php?parent={$w['kode_wilayah']}' class='btn btn-batch'>
                            <i class='fas fa-layer-group'></i> Tambah Wilayah Sekaligus
                        </a>
                        <a href='create_wilayah.php?kode_wilayah={$w['kode_wilayah']}' class='btn btn-add'>
                            <i class='fas fa-plus'></i> Tambah Wilayah 
                        </a>
                        <a href='create_lokasi.php?kode_wilayah={$w['kode_wilayah']}' class='btn btn-location'>
                            <i class='fas fa-map-pin'></i> Tambah Lokasi
                        </a>
                        <a href='edit_wilayah.php?parent={$w['kode_wilayah']}' class='btn btn-edit'>
                            <i class='fas fa-edit'></i> Edit Wilayah   
                        </a>
                        <a href='hapus_wilayah.php?kode_wilayah={$w['kode_wilayah']}' class='btn btn-delete'>
                            <i class='fas fa-trash-alt'></i> Hapus Wilayah
                        </a>
                      </div>";
                echo "</div>";
            
                echo "<div class='table-container'>";
                echo "<table>
                        <tr>
                            <th>Kode</th>
                            <th>Wilayah / Lokasi</th>
                            <th>Detail</th>
                        </tr>";
            }
            
            $indentClass = 'indent-'.$levelIndent[$class];
            
            // Highlight search keyword
            $namaWilayah = htmlspecialchars($w['nama']);
            if($searchKeyword != ''){
                $namaWilayah = preg_replace("/(".$searchKeyword.")/i", "<span class='highlight'>$1</span>", $namaWilayah);
            }
            
            echo "<tr class='{$class}'>
                    <td data-label='Kode'>{$w['kode_wilayah']}</td>
                    <td data-label='Wilayah / Lokasi' class='{$indentClass}'>".$icons[$class]." ".$namaWilayah."</td>
                    <td data-label='Detail'></td>
                </tr>";

            // Lokasi
            if(isset($lokasiData[$w['kode_wilayah']])){
                $lokasiCount = count($lokasiData[$w['kode_wilayah']]);
                $lokasiId = 'lokasi-' . $w['kode_wilayah'];
                
                echo "<tr class='lokasi-header'>
                        <td colspan='3'>
                            <div class='lokasi-toggle' data-target='{$lokasiId}'>
                                <span>".$icons['lokasi']." Lokasi ({$lokasiCount})</span>
                                <i class='fas fa-chevron-down'></i>
                            </div>
                        </td>
                      </tr>";
                
                echo "<tr class='lokasi-details' id='{$lokasiId}'>
                        <td colspan='3'>
                            <div class='lokasi-grid'>";
                
                foreach($lokasiData[$w['kode_wilayah']] as $l){
                    // Ikon sinyal
                    $ikonSinyal = $l['ketersediaan_sinyal'] === "Yes" 
                        ? "<span class='badge success'><i class='fas fa-signal'></i> Ada</span>" 
                        : "<span class='badge danger'><i class='fas fa-signal-slash'></i> Tidak Ada</span>";

                    // Koordinat link
                    $koord = htmlspecialchars($l['koordinat']);
                    $linkKoord = "<a href='#' class='lihat-peta koordinat-card' data-koordinat='{$koord}' title='Lihat lokasi di peta'>
                                    <i class='fas fa-map-marker-alt'></i> {$koord}
                                  </a>";

                    // Format tanggal
                    $created_at = isset($l['created_at']) ? date('d/m/Y H:i', strtotime($l['created_at'])) : 'Tidak tersedia';
                    $updated_at = isset($l['updated_at']) ? date('d/m/Y H:i', strtotime($l['updated_at'])) : 'Tidak tersedia';

                    // Highlight search keyword
                    $namaTempat = htmlspecialchars($l['nama_tempat']);
                    $keterangan = htmlspecialchars($l['keterangan']);
                    $kodeLokasi = $l['kode_lokasi'];
                    
                    if($searchKeyword != ''){
                        $namaTempat = preg_replace("/(".$searchKeyword.")/i", "<span class='highlight'>$1</span>", $namaTempat);
                        $keterangan = preg_replace("/(".$searchKeyword.")/i", "<span class='highlight'>$1</span>", $keterangan);
                        $kodeLokasi = preg_replace("/(".$searchKeyword.")/i", "<span class='highlight'>$1</span>", $kodeLokasi);
                    }

                    echo "<div class='lokasi-card'>
                            <div class='lokasi-card-header'>
                                <h4>".$namaTempat."</h4>
                                <span class='lokasi-kode'>".$kodeLokasi."</span>
                            </div>
                            <div class='lokasi-card-body'>
                                <p><b><i class='fas fa-info-circle'></i> Keterangan:</b> ".$keterangan."</p>
                                <p><b><i class='fas fa-signal'></i> Sinyal:</b> {$ikonSinyal}</p>
                                <p><b><i class='fas fa-tachometer-alt'></i> Kecepatan:</b> {$l['kecepatan_sinyal']} Mbps</p>
                                <p><b><i class='fas fa-map-pin'></i> Koordinat:</b> {$linkKoord}</p>
                                <!-- TAMBAHAN: Info timestamp -->
                                <p><b><i class='fas fa-calendar-plus'></i> Ditambahkan:</b> <span class='timestamp-info'>{$created_at}</span></p>
                                <p><b><i class='fas fa-calendar-check'></i> Terakhir Update:</b> <span class='timestamp-info'>{$updated_at}</span></p>
                            </div>
                            <div class='lokasi-actions'>
                                <a href='edit_lokasi.php?kode_lokasi={$l['kode_lokasi']}' class='btn btn-edit'><i class='fas fa-edit'></i> Edit</a>
                                <a href='proses/proses_hapus_semua.php?kode_lokasi={$l['kode_lokasi']}' class='btn btn-delete' onclick='return confirm(\"Yakin ingin hapus data ini?\")'><i class='fas fa-trash-alt'></i> Hapus</a>
                            </div>
                          </div>";
                }
                
                echo "      </div>
                        </td>
                      </tr>";
            }

            // Rekursif
            tampilWilayah($w['kode_wilayah'],$wilayahData,$lokasiData,$levelIndent,$icons,$searchKeyword);

            if($class == 'provinsi'){
                echo "</table></div></div>"; // tutup tabel, container & div provinsi
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Hasil Pemetaan Lokasi Blankspot</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css"/>
<!-- <link rel="stylesheet" href="css/hasil_pemetaan.css"> -->
 <style>
    :root {
    --primary: #4361ee;
    --secondary: #3a0ca3;
    --success: #10b981;
    --info: #3b82f6;
    --warning: #f59e0b;
    --danger: #ef4444;
    --purple: #8b5cf6;
    --pink: #ec4899;
    --cyan: #06b6d4;
    --light: #f8f9fa;
    --dark: #212529;
    --gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    --gradient-primary: linear-gradient(135deg, #4361ee 0%, #3a0ca3 100%);
    --gradient-success: linear-gradient(135deg, #10b981 0%, #059669 100%);
    --gradient-warning: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    --gradient-danger: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
    --gradient-purple: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
    --gradient-info: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
    --gradient-cyan: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%);
    --gradient-pink: linear-gradient(135deg, #ec4899 0%, #db2777 100%);
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

/* Sidebar Mobile Fix */
.sidebar {
    width: 280px;
    background: white;
    box-shadow: 2px 0 20px rgba(0,0,0,0.1);
    padding: 20px 0;
    position: fixed;
    height: 100vh;
    overflow-y: auto;
    z-index: 1000;
    transform: translateX(-100%);
    transition: transform 0.3s ease;
}

.sidebar.active {
    transform: translateX(0);
}

.sidebar-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    z-index: 999;
}

.sidebar-overlay.active {
    display: block;
}

/* Mobile Toggle Button */
.menu-toggle {
    display: none;
    position: fixed;
    top: 15px;
    left: 15px;
    background: var(--gradient-primary);
    color: white;
    border: none;
    border-radius: 8px;
    padding: 10px 12px;
    font-size: 1.2rem;
    cursor: pointer;
    z-index: 1001;
    box-shadow: 0 2px 10px rgba(0,0,0,0.2);
}

/* Main Content */
.main-content {
    flex: 1;
    margin-left: 0;
    padding: 70px 15px 15px;
    width: 100%;
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

/* Button Variants for Actions */
.btn-batch, .btn-add, .btn-location, .btn-edit, .btn-delete {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 12px;
    border: none;
    border-radius: 8px;
    font-size: 11px;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
    color: white;
    transition: all 0.3s ease;
}

.btn-batch { background: var(--gradient-purple); }
.btn-add { background: var(--gradient-success); }
.btn-location { background: var(--gradient-info); }
.btn-edit { background: var(--gradient-warning); }
.btn-delete { background: var(--gradient-danger); }

.btn-batch:hover, .btn-add:hover, .btn-location:hover, .btn-edit:hover, .btn-delete:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.2);
}

/* Search & Filter Section */
.search-filter-container {
    background: white;
    padding: 20px;
    border-radius: 15px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.08);
    margin-bottom: 20px;
}

.search-box {
    display: flex;
    flex-direction: column;
    gap: 10px;
    margin-bottom: 15px;
}

.search-input {
    width: 100%;
    padding: 12px 15px;
    border: 2px solid var(--primary);
    border-radius: 10px;
    font-size: 14px;
    outline: none;
    transition: all 0.3s ease;
    background: #f8f9fa;
}

.search-input:focus {
    border-color: var(--primary);
    background: white;
    box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.1);
}

.search-btn {
    background: var(--gradient-primary);
    color: white;
    border: none;
    padding: 12px 20px;
    border-radius: 10px;
    cursor: pointer;
    font-size: 14px;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 8px;
    font-weight: 600;
    justify-content: center;
}

.search-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(67, 97, 238, 0.3);
}

.filter-container {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.filter-group {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.filter-label {
    font-weight: 600;
    color: var(--dark);
    font-size: 14px;
}

.filter-select {
    padding: 10px 12px;
    border: 1px solid #e9ecef;
    border-radius: 8px;
    background: white;
    cursor: pointer;
    transition: all 0.3s ease;
    font-size: 14px;
}

.filter-select:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.1);
}

.results-info {
    background: #e8f4fc;
    padding: 12px;
    border-radius: 10px;
    margin-top: 15px;
    border-left: 4px solid var(--primary);
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
}

.highlight {
    background: #fff3cd;
    padding: 2px 6px;
    border-radius: 4px;
    font-weight: 600;
    color: #e74c3c;
}

/* Action Buttons */
.action-buttons {
    display: flex;
    flex-direction: column;
    gap: 10px;
    margin-bottom: 20px;
}

.action-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 12px 16px;
    border-radius: 10px;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.3s ease;
    justify-content: center;
    font-size: 14px;
}

.action-btn.dashboard { background: var(--gradient-primary); color: white; }
.action-btn.provinsi { background: #e67e22; color: white; }
.action-btn.report { background: var(--info); color: white; }
.action-btn.history { background: #9b59b6; color: white; }

.action-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.2);
}

/* Provinsi block */
.provinsi-block { 
    margin-bottom: 20px; 
    border: 1px solid #e9ecef; 
    border-radius: 15px; 
    padding: 15px; 
    background: white; 
    box-shadow: 0 5px 15px rgba(0,0,0,0.08); 
    transition: 0.3s; 
}

.provinsi-block:hover { 
    box-shadow: 0 8px 25px rgba(0,0,0,0.12); 
}

.provinsi-header { 
    display: flex; 
    flex-direction: column;
    gap: 15px;
    margin-bottom: 15px;
}

.provinsi-header h2 { 
    margin: 0; 
    font-size: 1.3rem; 
    display: flex; 
    align-items: center; 
    gap: 10px; 
    color: var(--dark);
    text-align: center;
}

.provinsi-actions { 
    display: flex; 
    gap: 8px; 
    flex-wrap: wrap;
    justify-content: center;
}

/* Table */
.table-container { 
    max-height: 400px; 
    overflow-x: auto;
    overflow-y: auto; 
    border-radius: 10px;
    border: 1px solid #e9ecef;
    margin-top: 10px;
}

table { 
    width: 100%; 
    border-collapse: collapse; 
    margin-bottom: 10px; 
    background: white;
    min-width: 600px;
}

th, td { 
    padding: 12px 8px; 
    text-align: left; 
    border: 1px solid #e9ecef; 
    vertical-align: top; 
    font-size: 14px;
}

th { 
    background: var(--primary); 
    color: white; 
    font-weight: 600; 
    text-transform: uppercase; 
    position: sticky; 
    top: 0; 
    font-size: 12px;
    letter-spacing: 0.5px;
}

tr:hover { 
    background: #f8f9fa; 
}

/* Level colors */
.provinsi { background: rgba(67, 97, 238, 0.05); font-weight: 600; }
.kota { background: rgba(76, 201, 240, 0.05); font-weight: 500; }
.kecamatan { background: rgba(247, 37, 133, 0.05); font-style: italic; }
.desa { background: #fdfdfd; }
.lokasi-header { background: #f8f9fa; cursor: pointer; transition: all 0.3s ease; }
.lokasi-header:hover { background: #e9ecef; }

/* Indent */
.indent-0 { padding-left: 0; }
.indent-1 { padding-left: 20px; }
.indent-2 { padding-left: 40px; }
.indent-3 { padding-left: 60px; }
.indent-4 { padding-left: 80px; }

/* Lokasi toggle */
.lokasi-toggle { 
    display: flex; 
    justify-content: space-between; 
    align-items: center; 
    padding: 12px 10px; 
    font-weight: 600;
    font-size: 14px;
}

.lokasi-toggle i { 
    transition: transform 0.3s; 
    color: var(--primary);
}

.lokasi-toggle.active i { 
    transform: rotate(180deg); 
}

/* Lokasi details */
.lokasi-details { 
    display: none; 
}

.lokasi-details.active { 
    display: table-row; 
}

/* Lokasi grid */
.lokasi-grid { 
    display: grid; 
    grid-template-columns: 1fr; 
    gap: 15px; 
    padding: 15px; 
}

/* Lokasi card */
.lokasi-card { 
    background: #fff; 
    border: 1px solid #e9ecef; 
    border-radius: 12px; 
    overflow: hidden; 
    box-shadow: 0 3px 10px rgba(0,0,0,0.08); 
    transition: 0.3s; 
}

.lokasi-card:hover { 
    box-shadow: 0 6px 20px rgba(0,0,0,0.12); 
}

.lokasi-card-header { 
    display: flex; 
    flex-direction: column;
    gap: 8px;
    padding: 12px 15px; 
    background: #f8f9fa; 
    border-bottom: 1px solid #e9ecef; 
}

.lokasi-card-header h4 { 
    margin: 0; 
    font-size: 1rem; 
    color: var(--dark);
    word-break: break-word;
}

.lokasi-kode { 
    font-size: 0.75rem; 
    color: #6c757d; 
    background: white;
    padding: 4px 8px;
    border-radius: 6px;
    border: 1px solid #e9ecef;
    align-self: flex-start;
}

.lokasi-card-body { 
    padding: 12px 15px; 
}

.lokasi-card-body p { 
    margin: 6px 0; 
    font-size: 0.85rem; 
    line-height: 1.4;
    word-break: break-word;
}

.lokasi-actions { 
    padding: 10px 15px; 
    background: #f8f9fa; 
    border-top: 1px solid #e9ecef; 
    display: flex; 
    gap: 8px; 
    flex-wrap: wrap;
}

/* Badge */
.badge { 
    display: inline-flex; 
    align-items: center; 
    gap: 4px;
    padding: 4px 8px; 
    border-radius: 15px; 
    font-size: 0.75rem; 
    font-weight: 600; 
}

.badge.success { 
    background: var(--success); 
    color: white; 
}

.badge.danger { 
    background: var(--danger); 
    color: white; 
}

/* Koordinat card */
.koordinat-card {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: #eaf2ff;
    color: var(--primary);
    padding: 6px 10px;
    border-radius: 6px;
    font-weight: 600;
    text-decoration: none;
    transition: 0.2s;
    font-size: 0.8rem;
    word-break: break-all;
}

.koordinat-card i { 
    color: var(--primary); 
    flex-shrink: 0;
}

.koordinat-card:hover { 
    background: #d6e6ff; 
}

/* Timestamp info */
.timestamp-info {
    background: #f8f9fa;
    padding: 4px 8px;
    border-radius: 6px;
    font-family: 'Monaco', 'Consolas', monospace;
    font-size: 0.75rem;
    color: #6c757d;
    border: 1px solid #e9ecef;
    cursor: pointer;
    transition: all 0.3s ease;
    word-break: break-word;
}

.timestamp-info:hover {
    background: var(--primary);
    color: white;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 40px 20px;
    background: white;
    border-radius: 15px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.08);
    margin: 20px 0;
}

.empty-state i {
    font-size: 3rem;
    margin-bottom: 15px;
    opacity: 0.3;
    color: #6c757d;
}

.empty-state h3 {
    font-size: 1.3rem;
    margin-bottom: 10px;
    color: #495057;
}

.empty-state p {
    font-size: 0.9rem;
    opacity: 0.7;
    margin-bottom: 20px;
}

/* Modal peta */
#mapModal { 
    display: none; 
    position: fixed; 
    top: 0; 
    left: 0; 
    width: 100%; 
    height: 100%; 
    background: rgba(0,0,0,0.6); 
    justify-content: center; 
    align-items: center; 
    z-index: 1000; 
    padding: 20px;
}

#mapModal .modal-content { 
    background: #fff; 
    padding: 15px; 
    border-radius: 15px; 
    width: 100%; 
    max-width: 500px; 
    box-shadow: 0 10px 30px rgba(0,0,0,0.3);
    max-height: 90vh;
    overflow-y: auto;
}

#map { 
    width: 100%; 
    height: 300px; 
    border-radius: 10px; 
}

.close-btn { 
    cursor: pointer; 
    font-size: 1.5em; 
    color: var(--danger);
    background: none;
    border: none;
    padding: 5px;
}

/* Desktop Styles */
@media (min-width: 769px) {
    .app-container {
        flex-direction: row;
    }
    
    .sidebar {
        transform: translateX(0);
        position: fixed;
    }
    
    .main-content {
        margin-left: 280px;
        padding: 30px;
    }
    
    .menu-toggle {
        display: none;
    }
    
    .sidebar-overlay {
        display: none !important;
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
    
    .search-box {
        flex-direction: row;
        align-items: center;
    }
    
    .search-input {
        min-width: 300px;
    }
    
    .filter-container {
        flex-direction: row;
        gap: 20px;
    }
    
    .action-buttons {
        flex-direction: row;
        justify-content: center;
    }
    
    .provinsi-header {
        flex-direction: row;
        justify-content: space-between;
        align-items: center;
    }
    
    .provinsi-actions {
        justify-content: flex-end;
    }
    
    .lokasi-grid {
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 20px;
    }
    
    .lokasi-card-header {
        flex-direction: row;
        justify-content: space-between;
        align-items: center;
    }
    
    .action-buttons {
        flex-direction: row;
        gap: 15px;
    }
}

/* Tablet Styles */
@media (min-width: 481px) and (max-width: 768px) {
    .main-content {
        padding: 80px 20px 20px;
    }
    
    .lokasi-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .provinsi-actions {
        justify-content: center;
    }
    
    .search-box {
        flex-direction: row;
        flex-wrap: wrap;
    }
    
    .search-input {
        flex: 1;
        min-width: 200px;
    }
}

/* Mobile Styles */
@media (max-width: 480px) {
    .menu-toggle {
        display: block;
    }
    
    .main-content {
        padding: 70px 10px 10px;
    }
    
    .page-header {
        padding: 15px;
    }
    
    .header-title h1 {
        font-size: 1.3rem;
    }
    
    .lokasi-card-body {
        padding: 10px 12px;
    }
    
    .lokasi-actions {
        flex-direction: column;
    }
    
    .table-container {
        max-height: 300px;
    }
    
    th, td {
        padding: 8px 6px;
        font-size: 12px;
    }
    
    #mapModal .modal-content {
        padding: 12px;
    }
    
    #map {
        height: 250px;
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

/* Responsive table for mobile */
@media (max-width: 768px) {
    table {
        display: block;
        overflow-x: auto;
        white-space: nowrap;
    }
    
    .table-container::-webkit-scrollbar {
        height: 8px;
    }
    
    .table-container::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 4px;
    }
    
    .table-container::-webkit-scrollbar-thumb {
        background: var(--primary);
        border-radius: 4px;
    }
}

/* Print Styles */
@media print {
    .menu-toggle,
    .sidebar,
    .header-actions,
    .provinsi-actions,
    .lokasi-actions {
        display: none !important;
    }
    
    .main-content {
        margin-left: 0 !important;
        padding: 0 !important;
    }
    
    .app-container {
        flex-direction: column;
    }
    
    .search-filter-container,
    .action-buttons {
        display: none;
    }
}
 </style>
</head>
<body>

<div class="app-container">
    <!-- Sidebar -->
    <?php include 'sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <div class="page-header fade-in">
            <div class="header-title">
                <h1><i class="fas fa-map-marker-alt"></i> Hasil Pemetaan</h1>
                <p>Kelola dan eksplorasi data lokasi blankspot secara detail</p>
            </div>
            <div class="header-actions">
                <button onclick="location.reload()" class="btn btn-light">
                    <i class="fas fa-sync-alt"></i> Refresh
                </button>
                <a href="dashboard.php" class="btn btn-primary">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
            </div>
        </div>

        <!-- Search & Filter Section -->
        <div class="search-filter-container fade-in">
            <form method="GET" action="">
                <div class="search-box">
                    <input type="text" name="search" class="search-input" placeholder="Cari nama tempat, keterangan, koordinat, atau kode lokasi..." value="<?= htmlspecialchars($searchKeyword) ?>">
                    <button type="submit" class="search-btn">
                        <i class="fas fa-search"></i> Cari
                    </button>
                    <?php if($searchKeyword != '' || $filterSinyal != '' || $levelWilayah != ''): ?>
                    <a href="hasil_pemetaan.php" class="search-btn" style="background: #6c757d;">
                        <i class="fas fa-times"></i> Reset
                    </a>
                    <?php endif; ?>
                </div>
                
                <div class="filter-container">
                    <div class="filter-group">
                        <label class="filter-label">Filter Sinyal:</label>
                        <select name="filter" class="filter-select" onchange="this.form.submit()">
                            <option value="">Semua Status</option>
                            <option value="Yes" <?= $filterSinyal == 'Yes' ? 'selected' : '' ?>>Ada Sinyal</option>
                            <option value="No" <?= $filterSinyal == 'No' ? 'selected' : '' ?>>Tidak Ada Sinyal</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label class="filter-label">Filter Tanggal:</label>
                        <select name="date_filter" class="filter-select" onchange="this.form.submit()">
                            <option value="">Semua Tanggal</option>
                            <option value="today" <?= isset($_GET['date_filter']) && $_GET['date_filter'] == 'today' ? 'selected' : '' ?>>Hari Ini</option>
                            <option value="week" <?= isset($_GET['date_filter']) && $_GET['date_filter'] == 'week' ? 'selected' : '' ?>>7 Hari Terakhir</option>
                            <option value="month" <?= isset($_GET['date_filter']) && $_GET['date_filter'] == 'month' ? 'selected' : '' ?>>30 Hari Terakhir</option>
                        </select>
                    </div>
                </div>
                
                <?php if($searchKeyword != '' || $filterSinyal != '' || $levelWilayah != '' || isset($_GET['date_filter'])): ?>
                <div class="results-info">
                    <i class="fas fa-info-circle" style="color: var(--primary);"></i> 
                    Menampilkan <strong><?= $totalLokasi ?></strong> lokasi 
                    <?php if($searchKeyword != ''): ?> dengan kata kunci "<strong><?= htmlspecialchars($searchKeyword) ?></strong>"<?php endif; ?>
                    <?php if($filterSinyal != ''): ?> | Status: <strong><?= $filterSinyal == 'Yes' ? 'Ada Sinyal' : 'Tidak Ada Sinyal' ?></strong><?php endif; ?>
                    <?php if(isset($_GET['date_filter']) && $_GET['date_filter'] != ''): ?> 
                        | Periode: <strong>
                        <?php 
                        switch($_GET['date_filter']) {
                            case 'today': echo 'Hari Ini'; break;
                            case 'week': echo '7 Hari Terakhir'; break;
                            case 'month': echo '30 Hari Terakhir'; break;
                            default: echo 'Semua Tanggal';
                        }
                        ?>
                        </strong>
                    <?php endif; ?>
                    <?php if($levelWilayah != ''): ?> | Level: <strong><?= ucfirst($levelWilayah) ?></strong><?php endif; ?>
                </div>
                <?php endif; ?>
            </form>
        </div>

        <!-- Action Buttons -->
        <div class="action-buttons fade-in">
            <a href="create_provinsi.php" class="action-btn provinsi">
                <i class="fas fa-flag"></i> Tambah Provinsi
            </a>
            <a href="report.php" class="action-btn report">
                <i class="fas fa-chart-bar"></i> Laporan Statistik
            </a>
            <a href="riwayat.php" class="action-btn history">
                <i class="fas fa-history"></i> Riwayat Aktivitas
            </a>
        </div>

        <?php 
        if($totalLokasi == 0 && ($searchKeyword != '' || $filterSinyal != '' || $levelWilayah != '' || isset($_GET['date_filter']))): 
        ?>
            <div class="empty-state fade-in">
                <i class="fas fa-search"></i>
                <h3>Tidak ada hasil ditemukan</h3>
                <p>Coba ubah kata kunci pencarian atau filter yang digunakan</p>
                <a href="hasil_pemetaan.php" class="btn btn-primary">
                    <i class="fas fa-undo"></i> Tampilkan Semua Data
                </a>
            </div>
        <?php else: ?>
            <?php tampilWilayah(NULL,$wilayahData,$lokasiData,$levelIndent,$icons,$searchKeyword); ?>
        <?php endif; ?>
    </div>
</div>

<!-- Modal untuk peta -->
<div id="mapModal">
    <div class="modal-content">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
            <h3 style="margin:0; color:var(--dark);">Lokasi di Peta</h3>
            <button class="close-btn">&times;</button>
        </div>
        <div id="map"></div>
    </div>
</div>

<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
<script>
const modal = document.getElementById('mapModal');
const closeBtn = document.querySelector('.close-btn');
let map, marker;

// Toggle lokasi details
document.querySelectorAll('.lokasi-toggle').forEach(toggle => {
    toggle.addEventListener('click', function() {
        const targetId = this.getAttribute('data-target');
        const target = document.getElementById(targetId);
        this.classList.toggle('active');
        target.classList.toggle('active');
    });
});

// Auto expand lokasi yang mengandung hasil pencarian
document.addEventListener('DOMContentLoaded', function() {
    const searchKeyword = "<?= $searchKeyword ?>";
    if(searchKeyword !== '') {
        document.querySelectorAll('.lokasi-toggle').forEach(toggle => {
            const targetId = toggle.getAttribute('data-target');
            const target = document.getElementById(targetId);
            toggle.classList.add('active');
            target.classList.add('active');
        });
    }
    
    // Auto expand jika ada filter tanggal
    const dateFilter = "<?= isset($_GET['date_filter']) ? $_GET['date_filter'] : '' ?>";
    if(dateFilter !== '') {
        document.querySelectorAll('.lokasi-toggle').forEach(toggle => {
            const targetId = toggle.getAttribute('data-target');
            const target = document.getElementById(targetId);
            toggle.classList.add('active');
            target.classList.add('active');
        });
    }
});

// Event klik koordinat
document.querySelectorAll('.lihat-peta').forEach(link => {
    link.addEventListener('click', function(e){
        e.preventDefault();
        const coords = this.getAttribute('data-koordinat').split(',');
        const lat = parseFloat(coords[0]);
        const lon = parseFloat(coords[1]);

        modal.style.display = 'flex';

        // Inisialisasi peta
        setTimeout(() => {
            if(!map){
                map = L.map('map').setView([lat, lon], 15);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '&copy; OpenStreetMap contributors'
                }).addTo(map);
            } else {
                map.setView([lat, lon], 15);
            }

            if(marker){ map.removeLayer(marker); }
            marker = L.marker([lat, lon]).addTo(map);
        }, 200);
    });
});

// Tombol close
closeBtn.addEventListener('click', ()=> modal.style.display='none');
window.addEventListener('click', e => { if(e.target == modal){ modal.style.display='none'; } });

// Fungsi untuk menampilkan info timestamp dengan tooltip
document.addEventListener('DOMContentLoaded', function() {
    const timestampElements = document.querySelectorAll('.timestamp-info');
    
    timestampElements.forEach(element => {
        element.addEventListener('mouseenter', function() {
            this.title = 'Klik untuk menyalin tanggal';
        });
        
        element.addEventListener('click', function() {
            const textToCopy = this.textContent;
            navigator.clipboard.writeText(textToCopy).then(() => {
                const originalText = this.textContent;
                this.textContent = 'Tersalin!';
                this.style.background = '#d4edda';
                this.style.color = '#155724';
                
                setTimeout(() => {
                    this.textContent = originalText;
                    this.style.background = '#f8f9fa';
                    this.style.color = '#6c757d';
                }, 1500);
            });
        });
    });
});

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

    // Add styles
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: ${getNotificationColor(type)};
        color: white;
        padding: 15px 20px;
        border-radius: 10px;
        box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        z-index: 10000;
        animation: slideInRight 0.3s ease;
        max-width: 400px;
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

function getNotificationColor(type) {
    switch(type) {
        case 'success': return '#28a745';
        case 'warning': return '#ffc107';
        case 'error': return '#dc3545';
        default: return '#17a2b8';
    }
}

// Add CSS animations for notifications
const style = document.createElement('style');
style.textContent = `
    @keyframes slideInRight {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
    @keyframes slideOutRight {
        from { transform: translateX(0); opacity: 1; }
        to { transform: translateX(100%); opacity: 0; }
    }
`;
document.head.appendChild(style);
</script>
</body>
</html>