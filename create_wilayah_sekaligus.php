<?php
include "config/db.php";

$parent = $_GET['parent'] ?? '';
if(!$parent){
    die("Provinsi tidak ditemukan.");
}

// Ambil nama provinsi
$provinsi = $conn->query("SELECT * FROM wilayah WHERE kode_wilayah='$parent' AND level='provinsi'")->fetch_assoc();
if(!$provinsi){
    die("Provinsi tidak valid.");
}

// Ambil data kota yang sudah ada
$kota_list = $conn->query("SELECT * FROM wilayah WHERE parent_kode='$parent' AND level='kota' ORDER BY nama ASC");

// Ambil data kecamatan untuk dropdown
$kecamatan_data = [];
if($kota_list->num_rows > 0) {
    $kota_kodes = [];
    while($kota = $kota_list->fetch_assoc()) {
        $kota_kodes[] = $kota['kode_wilayah'];
    }
    
    // Reset pointer untuk digunakan lagi nanti
    $kota_list->data_seek(0);
    
    // Ambil kecamatan untuk semua kota
    $kota_ids = implode("','", $kota_kodes);
    $kecamatan_query = $conn->query("
        SELECT k.*, kota.nama as kota_nama 
        FROM wilayah k 
        JOIN wilayah kota ON k.parent_kode = kota.kode_wilayah 
        WHERE k.parent_kode IN ('$kota_ids') AND k.level='kecamatan' 
        ORDER BY kota.nama, k.nama ASC
    ");
    
    while($kec = $kecamatan_query->fetch_assoc()) {
        $kecamatan_data[] = $kec;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Tambah Wilayah Sekaligus - <?= htmlspecialchars($provinsi['nama']) ?></title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
<link rel="stylesheet" href="https://unpkg.com/leaflet-control-geocoder/dist/Control.Geocoder.css"/>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/leaflet-control-geocoder/dist/Control.Geocoder.js"></script>
<style>
    :root {
    --primary: #34495e;
    --secondary: #2980b9;
    --success: #27ae60;
    --danger: #e74c3c;
    --warning: #f39c12;
    --purple: #8e44ad;
    --light: #ecf0f1;
    --dark: #2c3e50;
    --gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
    min-height: 100vh;
    padding: 20px;
    color: var(--dark);
}

.container {
    max-width: 1400px;
    margin: 0 auto;
}

.header {
    text-align: center;
    margin-bottom: 30px;
}

.page-title {
    font-size: 2.2rem;
    font-weight: 700;
    background: var(--gradient);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    margin-bottom: 8px;
}

.page-subtitle {
    color: var(--dark);
    font-size: 1.1rem;
    opacity: 0.8;
}

.form-container {
    background: white;
    border-radius: 16px;
    padding: 30px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
    margin-bottom: 20px;
    border: 1px solid rgba(255,255,255,0.2);
}

.provinsi-info {
    background: linear-gradient(135deg, #e8f4fd, #d4edfa);
    border: 1px solid #b3d9f7;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 25px;
    text-align: center;
}

.provinsi-badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 8px 16px;
    background: var(--purple);
    color: white;
    border-radius: 20px;
    font-weight: 600;
    font-size: 0.9rem;
}

/* Level Selection */
.level-selection {
    background: #f8fafc;
    border: 2px dashed #cbd5e1;
    border-radius: 12px;
    padding: 25px;
    margin-bottom: 25px;
    text-align: center;
}

.level-selection h3 {
    margin-bottom: 15px;
    color: var(--dark);
    font-size: 1.2rem;
}

.level-options {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin-bottom: 20px;
}

.level-option {
    background: white;
    border: 2px solid #e2e8f0;
    border-radius: 10px;
    padding: 20px;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s ease;
}

.level-option:hover {
    border-color: var(--secondary);
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
}

.level-option.selected {
    border-color: var(--secondary);
    background: linear-gradient(135deg, #e8f4fd, #d4edfa);
}

.level-icon {
    font-size: 2rem;
    margin-bottom: 10px;
}

.level-option.kota .level-icon { color: var(--secondary); }
.level-option.kecamatan .level-icon { color: var(--success); }
.level-option.desa .level-icon { color: var(--warning); }

.level-name {
    font-weight: 600;
    margin-bottom: 5px;
}

.level-desc {
    font-size: 0.8rem;
    color: #64748b;
}

/* Parent Selection */
.parent-selection {
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 20px;
    margin-bottom: 25px;
    display: none;
}

.parent-selection.active {
    display: block;
    animation: fadeIn 0.5s ease;
}

.parent-header {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 15px;
    color: var(--secondary);
    font-weight: 600;
}

.parent-steps {
    display: flex;
    gap: 10px;
    margin-bottom: 15px;
    flex-wrap: wrap;
}

.step {
    flex: 1;
    min-width: 200px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 15px;
}

.step.active {
    border-color: var(--secondary);
    background: #e8f4fd;
}

.step-title {
    font-weight: 600;
    margin-bottom: 10px;
    color: var(--dark);
    font-size: 0.9rem;
}

.step-content {
    min-height: 40px;
}

.parent-options {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 10px;
    max-height: 200px;
    overflow-y: auto;
    padding: 5px;
}

.parent-option {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 12px;
    cursor: pointer;
    transition: all 0.3s ease;
    text-align: center;
    font-size: 0.9rem;
}

.parent-option:hover {
    border-color: var(--secondary);
    background: #e8f4fd;
}

.parent-option.selected {
    border-color: var(--secondary);
    background: var(--secondary);
    color: white;
}

/* Form Sections */
.form-section {
    display: none;
    margin-bottom: 25px;
}

.form-section.active {
    display: block;
    animation: fadeIn 0.5s ease;
}

.section-header {
    background: var(--gradient);
    color: white;
    padding: 15px 20px;
    border-radius: 10px 10px 0 0;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.section-title {
    display: flex;
    align-items: center;
    gap: 10px;
    font-weight: 600;
}

.section-content {
    background: white;
    border: 1px solid #e2e8f0;
    border-top: none;
    border-radius: 0 0 10px 10px;
    padding: 20px;
}

.wilayah-group {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 15px;
    margin-bottom: 15px;
    transition: all 0.3s ease;
    position: relative;
}

.wilayah-group:hover {
    background: #f1f5f9;
    border-color: #cbd5e1;
}

.input-row {
    display: flex;
    gap: 10px;
    margin-bottom: 10px;
    align-items: flex-start;
}

.kode-input {
    display: flex;
    align-items: center;
    flex: 1;
}

.kode-prefix {
    background: #e2e8f0;
    padding: 10px 12px;
    border: 1px solid #cbd5e1;
    border-right: none;
    border-radius: 8px 0 0 8px;
    font-size: 0.9rem;
    color: #64748b;
    font-weight: 500;
    min-width: 80px;
}

.kode-field, .nama-field, .select-field {
    padding: 10px 12px;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    font-size: 14px;
    transition: all 0.3s ease;
    font-family: inherit;
}

.kode-field {
    border-radius: 0 8px 8px 0;
    flex: 1;
}

.nama-field {
    flex: 2;
}

.select-field {
    flex: 1;
    background: white;
}

.kode-field:focus, .nama-field:focus, .select-field:focus {
    outline: none;
    border-color: var(--secondary);
    box-shadow: 0 0 0 2px rgba(41, 128, 185, 0.1);
}

.btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 10px 16px;
    border: none;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
    font-family: inherit;
}

.btn-primary {
    background: linear-gradient(135deg, var(--secondary), #3498db);
    color: white;
}

.btn-success {
    background: linear-gradient(135deg, var(--success), #2ecc71);
    color: white;
}

.btn-warning {
    background: linear-gradient(135deg, var(--warning), #e67e22);
    color: white;
}

.btn-danger {
    background: linear-gradient(135deg, var(--danger), #c0392b);
    color: white;
}

.btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.btn-sm {
    padding: 8px 12px;
    font-size: 12px;
}

.action-buttons {
    display: flex;
    gap: 8px;
    margin-top: 10px;
    flex-wrap: wrap;
}

.form-actions {
    display: flex;
    gap: 12px;
    margin-top: 30px;
    padding-top: 20px;
    border-top: 1px solid #eef2f7;
}

.btn-lg {
    padding: 12px 24px;
    font-size: 14px;
    flex: 1;
    justify-content: center;
}

.counter-badge {
    background: rgba(255,255,255,0.2);
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 0.8rem;
    font-weight: 600;
}

.help-text {
    font-size: 0.8rem;
    color: #64748b;
    margin-top: 4px;
    font-style: italic;
}

.empty-state {
    text-align: center;
    padding: 20px;
    color: #64748b;
    font-size: 0.9rem;
}

.empty-state i {
    font-size: 1.5rem;
    margin-bottom: 10px;
    opacity: 0.5;
}

.selected-parent-info {
    background: #e8f4fd;
    border: 1px solid #b3d9f7;
    border-radius: 8px;
    padding: 15px;
    margin-bottom: 15px;
    display: none;
}

.selected-parent-info.active {
    display: block;
}

.parent-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    background: var(--success);
    color: white;
    border-radius: 15px;
    font-size: 0.8rem;
    font-weight: 500;
}

.fade-in {
    animation: fadeIn 0.6s ease-in;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

.pulse {
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.02); }
    100% { transform: scale(1); }
}

.delete-btn {
    position: absolute;
    top: 10px;
    right: 10px;
    background: var(--danger);
    color: white;
    border: none;
    border-radius: 50%;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    opacity: 0.7;
    transition: all 0.3s ease;
}

.delete-btn:hover {
    opacity: 1;
    transform: scale(1.1);
}

.sub-level-toggle {
    margin-top: 10px;
    padding: 8px 12px;
    background: #e2e8f0;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-size: 0.8rem;
    display: flex;
    align-items: center;
    gap: 5px;
    transition: all 0.3s ease;
}

.sub-level-toggle:hover {
    background: #cbd5e1;
}

.sub-level-content {
    margin-top: 15px;
    padding: 15px;
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    display: none;
}

.sub-level-content.active {
    display: block;
    animation: fadeIn 0.3s ease;
}

/* Lokasi Form Styles */
.lokasi-form {
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 15px;
    margin-top: 15px;
}

.lokasi-form h4 {
    margin-bottom: 15px;
    color: var(--dark);
    font-size: 1rem;
    display: flex;
    align-items: center;
    gap: 8px;
}

.form-group {
    margin-bottom: 15px;
}

.form-group label {
    display: block;
    font-weight: 600;
    margin-bottom: 5px;
    color: var(--dark);
    font-size: 0.9rem;
}

.map-container {
    margin-top: 15px;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

#map {
    height: 200px;
    width: 100%;
}

.location-buttons {
    display: flex;
    gap: 8px;
    margin-top: 10px;
    flex-wrap: wrap;
}

.coordinate-display {
    background: #f1f5f9;
    padding: 8px;
    border-radius: 6px;
    font-family: monospace;
    font-size: 12px;
    margin-top: 8px;
    border: 1px solid #e2e8f0;
}

.signal-status {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-top: 5px;
}

.status-indicator {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    display: inline-block;
}

.status-yes { background: var(--success); }
.status-no { background: var(--danger); }

.lokasi-counter {
    background: rgba(255,255,255,0.2);
    padding: 2px 6px;
    border-radius: 8px;
    font-size: 0.7rem;
    font-weight: 600;
    margin-left: 8px;
}

.coordinate-input-group {
    display: flex;
    gap: 8px;
    align-items: stretch;
}

.coordinate-input-group input {
    flex: 1;
}

.coordinate-input-group .btn {
    white-space: nowrap;
}

.manual-coordinate-input {
    margin-top: 10px;
    padding: 10px;
    background: #f8fafc;
    border-radius: 6px;
    border: 1px solid #e2e8f0;
}

.manual-coordinate-input h5 {
    margin-bottom: 8px;
    font-size: 0.9rem;
    color: var(--dark);
    display: flex;
    align-items: center;
    gap: 6px;
}

.coordinate-fields {
    display: grid;
    grid-template-columns: 1fr 1fr auto;
    gap: 8px;
    align-items: end;
}

.coordinate-field {
    display: flex;
    flex-direction: column;
}

.coordinate-field label {
    font-size: 0.8rem;
    margin-bottom: 4px;
    color: #64748b;
    font-weight: 500;
}

.coordinate-field input {
    padding: 8px 10px;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    font-size: 13px;
    font-family: monospace;
}

.coordinate-field input:focus {
    outline: none;
    border-color: var(--secondary);
    box-shadow: 0 0 0 2px rgba(41, 128, 185, 0.1);
}

@media (max-width: 768px) {
    .container {
        padding: 10px;
    }

    .form-container {
        padding: 20px;
        border-radius: 12px;
    }

    .page-title {
        font-size: 1.8rem;
    }

    .level-options {
        grid-template-columns: 1fr;
    }

    .parent-steps {
        flex-direction: column;
    }

    .parent-options {
        grid-template-columns: 1fr;
    }

    .input-row {
        flex-direction: column;
    }

    .kode-input {
        width: 100%;
    }

    .form-actions {
        flex-direction: column;
    }

    .btn-lg {
        width: 100%;
    }

    .location-buttons {
        flex-direction: column;
    }

    #map {
        height: 150px;
    }

    .coordinate-fields {
        grid-template-columns: 1fr;
    }

    .coordinate-input-group {
        flex-direction: column;
    }
}
</style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header fade-in">
            <h1 class="page-title"><i class="fas fa-layer-group"></i> Tambah Wilayah Sekaligus</h1>
            <p class="page-subtitle">Pilih level dan tambahkan beberapa wilayah sekaligus</p>
        </div>

        <!-- Form Container -->
        <div class="form-container fade-in">
            <!-- Provinsi Info -->
            <div class="provinsi-info">
                <div class="provinsi-badge">
                    <i class="fas fa-map"></i>
                    <?= htmlspecialchars($provinsi['nama']) ?>
                </div>
                <p style="margin-top: 10px; color: #2c3e50; font-size: 0.9rem;">
                    Kode Provinsi: <strong><?= $provinsi['kode_wilayah'] ?></strong>
                </p>
            </div>

            <!-- Level Selection -->
            <div class="level-selection">
                <h3><i class="fas fa-layer-group"></i> Pilih Level yang Ingin Ditambahkan</h3>
                <div class="level-options">
                    <div class="level-option kota" onclick="selectLevel('kota')">
                        <div class="level-icon">
                            <i class="fas fa-city"></i>
                        </div>
                        <div class="level-name">Kota/Kabupaten</div>
                        <div class="level-desc">Tambahkan kota/kabupaten baru + kecamatan & desa</div>
                    </div>
                    <div class="level-option kecamatan" onclick="selectLevel('kecamatan')">
                        <div class="level-icon">
                            <i class="fas fa-building"></i>
                        </div>
                        <div class="level-name">Kecamatan</div>
                        <div class="level-desc">Tambahkan kecamatan ke kota tertentu + desa</div>
                    </div>
                    <div class="level-option desa" onclick="selectLevel('desa')">
                        <div class="level-icon">
                            <i class="fas fa-home"></i>
                        </div>
                        <div class="level-name">Desa/Kelurahan</div>
                        <div class="level-desc">Tambahkan desa ke kecamatan tertentu</div>
                    </div>
                </div>
            </div>

            <!-- Parent Selection (untuk kecamatan dan desa) -->
            <div class="parent-selection" id="parentSelection">
                <div class="parent-header">
                    <i class="fas fa-sitemap"></i>
                    <span id="parentTitle">Pilih Wilayah Induk</span>
                </div>
                
                <div class="parent-steps" id="parentSteps">
                    <!-- Steps akan diisi oleh JavaScript -->
                </div>
                
                <div class="selected-parent-info" id="selectedParentInfo">
                    <div class="parent-badge">
                        <i class="fas fa-check"></i>
                        <span id="selectedParentText">Wilayah induk telah dipilih</span>
                    </div>
                </div>
            </div>

            <form action="proses/proses_add_wilayah_sekaligus.php" method="post" id="formBulk">
                <input type="hidden" name="provinsi_kode" value="<?= $provinsi['kode_wilayah'] ?>">
                <input type="hidden" name="selected_level" id="selectedLevel" value="">
                <input type="hidden" name="selected_parent" id="selectedParent" value="">

                <!-- Kota Section -->
                <div class="form-section" id="kotaSection">
                    <div class="section-header">
                        <div class="section-title">
                            <i class="fas fa-city"></i>
                            <span>Tambahkan Kota/Kabupaten Baru</span>
                        </div>
                        <div class="counter-badge" id="kotaCounter">0 kota</div>
                    </div>
                    <div class="section-content" id="kotaWrapper">
                        <!-- Kota template akan ditambahkan di sini -->
                    </div>
                </div>

                <!-- Kecamatan Section -->
                <div class="form-section" id="kecamatanSection">
                    <div class="section-header">
                        <div class="section-title">
                            <i class="fas fa-building"></i>
                            <span>Tambahkan Kecamatan Baru</span>
                        </div>
                        <div class="counter-badge" id="kecamatanCounter">0 kecamatan</div>
                    </div>
                    <div class="section-content" id="kecamatanWrapper">
                        <!-- Kecamatan template akan ditambahkan di sini -->
                    </div>
                </div>

                <!-- Desa Section -->
                <div class="form-section" id="desaSection">
                    <div class="section-header">
                        <div class="section-title">
                            <i class="fas fa-home"></i>
                            <span>Tambahkan Desa/Kelurahan Baru</span>
                        </div>
                        <div class="counter-badge" id="desaCounter">0 desa</div>
                    </div>
                    <div class="section-content" id="desaWrapper">
                        <!-- Desa template akan ditambahkan di sini -->
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="form-actions">
                    <button type="button" onclick="history.back()" class="btn btn-danger btn-lg">
                        <i class="fas fa-arrow-left"></i> Kembali
                    </button>
                    <button type="submit" class="btn btn-success btn-lg pulse" id="submitBtn" disabled>
                        <i class="fas fa-save"></i> Simpan Data
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal for Map -->
    <div id="mapModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
        <div style="background: white; border-radius: 12px; padding: 20px; width: 90%; max-width: 800px; max-height: 90vh; overflow-y: auto;">
            <div style="display: flex; justify-content: between; align-items: center; margin-bottom: 15px;">
                <h3 style="margin: 0; color: var(--dark);"><i class="fas fa-map"></i> Pilih Koordinat</h3>
                <button type="button" onclick="closeMapModal()" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--danger);">&times;</button>
            </div>
            
            <!-- Manual Coordinate Input -->
            <div class="manual-coordinate-input">
                <h5><i class="fas fa-keyboard"></i> Input Koordinat Manual</h5>
                <div class="coordinate-fields">
                    <div class="coordinate-field">
                        <label for="manualLatitude">Latitude</label>
                        <input type="text" id="manualLatitude" placeholder="Contoh: -6.2088" oninput="validateCoordinateInput('manualLatitude')">
                    </div>
                    <div class="coordinate-field">
                        <label for="manualLongitude">Longitude</label>
                        <input type="text" id="manualLongitude" placeholder="Contoh: 106.8456" oninput="validateCoordinateInput('manualLongitude')">
                    </div>
                    <button type="button" class="btn btn-primary" onclick="goToManualCoordinates()" style="height: fit-content;">
                        <i class="fas fa-search-location"></i> Cari
                    </button>
                </div>
                <p class="help-text" style="margin-top: 8px; margin-bottom: 0;">
                    Format: Decimal degrees (contoh: -6.2088, 106.8456)
                </p>
            </div>
            
            <div id="modalMap" style="height: 400px; width: 100%; border-radius: 8px; overflow: hidden; margin-top: 15px;"></div>
            
            <div class="location-buttons" style="margin-top: 15px;">
                <button type="button" class="btn btn-primary" onclick="getUserLocationModal()">
                    <i class="fas fa-location-crosshairs"></i> Gunakan Lokasi Saya
                </button>
                <button type="button" class="btn btn-warning" onclick="clearCoordinatesModal()">
                    <i class="fas fa-eraser"></i> Hapus Koordinat
                </button>
                <button type="button" class="btn btn-success" onclick="saveCoordinates()">
                    <i class="fas fa-check"></i> Simpan Koordinat
                </button>
            </div>
            
            <div class="coordinate-display" id="modalCoordinateDisplay" style="margin-top: 10px;">
                Klik peta untuk menentukan koordinat
            </div>
            
            <!-- Current Coordinate Display -->
            <div style="margin-top: 10px; padding: 10px; background: #f1f5f9; border-radius: 6px; font-family: monospace; font-size: 12px;">
                <strong>Koordinat Saat Ini:</strong> 
                <span id="currentCoordinate">-</span>
            </div>
        </div>
    </div>

    <script>
        let selectedLevel = '';
        let selectedKota = null;
        let selectedKecamatan = null;
        let kotaList = <?= json_encode($kota_list->fetch_all(MYSQLI_ASSOC)) ?>;
        let kecamatanList = <?= json_encode($kecamatan_data) ?>;
        let formCounter = {
            kota: 0,
            kecamatan: 0,
            desa: 0,
            lokasi: 0
        };

        // Map variables
        let modalMap = null;
        let modalMarker = null;
        let currentCoordinates = null;
        let currentLokasiContext = null;

        // Pilih level
        function selectLevel(level) {
            selectedLevel = level;
            
            // Reset selection
            document.querySelectorAll('.level-option').forEach(opt => {
                opt.classList.remove('selected');
            });
            document.querySelector(`.level-option.${level}`).classList.add('selected');
            
            // Update hidden field
            document.getElementById('selectedLevel').value = level;
            
            // Tampilkan parent selection untuk kecamatan dan desa
            if (level === 'kecamatan' || level === 'desa') {
                showParentSelection(level);
            } else {
                hideParentSelection();
                showFormSection(level);
            }
            
            // Enable submit button
            document.getElementById('submitBtn').disabled = false;
        }

        // Tampilkan parent selection dengan steps
        function showParentSelection(level) {
            const parentSelection = document.getElementById('parentSelection');
            const parentTitle = document.getElementById('parentTitle');
            const parentSteps = document.getElementById('parentSteps');
            
            parentTitle.textContent = `Pilih Wilayah Induk untuk ${level}`;
            
            // Reset selections
            selectedKota = null;
            selectedKecamatan = null;
            document.getElementById('selectedParentInfo').classList.remove('active');
            
            if (level === 'kecamatan') {
                parentSteps.innerHTML = `
                    <div class="step active" id="stepKota">
                        <div class="step-title">1. Pilih Kota/Kabupaten</div>
                        <div class="step-content" id="kotaOptions">
                            ${renderKotaOptions()}
                        </div>
                    </div>
                `;
            } else if (level === 'desa') {
                parentSteps.innerHTML = `
                    <div class="step active" id="stepKota">
                        <div class="step-title">1. Pilih Kota/Kabupaten</div>
                        <div class="step-content" id="kotaOptions">
                            ${renderKotaOptions()}
                        </div>
                    </div>
                    <div class="step" id="stepKecamatan">
                        <div class="step-title">2. Pilih Kecamatan</div>
                        <div class="step-content" id="kecamatanOptions">
                            <div class="empty-state">
                                <i class="fas fa-building"></i>
                                <div>Pilih kota terlebih dahulu</div>
                            </div>
                        </div>
                    </div>
                `;
            }
            
            parentSelection.classList.add('active');
        }

        // Render options kota
        function renderKotaOptions() {
            if (kotaList.length === 0) {
                return `
                    <div class="empty-state">
                        <i class="fas fa-city"></i>
                        <div>Belum ada kota/kabupaten. Tambahkan kota terlebih dahulu.</div>
                    </div>
                `;
            }
            
            let html = '<div class="parent-options">';
            kotaList.forEach(kota => {
                html += `
                    <div class="parent-option" onclick="selectKota('${kota.kode_wilayah}', '${kota.nama.replace(/'/g, "\\'")}')">
                        ${kota.nama}
                    </div>
                `;
            });
            html += '</div>';
            return html;
        }

        // Render options kecamatan berdasarkan kota
        function renderKecamatanOptions(kotaKode) {
            const kecamatanInKota = kecamatanList.filter(kec => kec.parent_kode === kotaKode);
            
            if (kecamatanInKota.length === 0) {
                return `
                    <div class="empty-state">
                        <i class="fas fa-building"></i>
                        <div>Belum ada kecamatan di kota ini.</div>
                    </div>
                `;
            }
            
            let html = '<div class="parent-options">';
            kecamatanInKota.forEach(kec => {
                html += `
                    <div class="parent-option" onclick="selectKecamatan('${kec.kode_wilayah}', '${kec.nama.replace(/'/g, "\\'")}')">
                        ${kec.nama}
                    </div>
                `;
            });
            html += '</div>';
            return html;
        }

        // Pilih kota
        function selectKota(kode, nama) {
            selectedKota = { kode, nama };
            
            // Update UI
            document.querySelectorAll('#kotaOptions .parent-option').forEach(opt => {
                opt.classList.remove('selected');
            });
            event.target.classList.add('selected');
            
            if (selectedLevel === 'kecamatan') {
                // Langsung tampilkan form kecamatan
                showSelectedParentInfo(`${nama} (Kota)`);
                showFormSection('kecamatan');
            } else if (selectedLevel === 'desa') {
                // Tampilkan step kecamatan
                document.getElementById('stepKecamatan').classList.add('active');
                document.getElementById('kecamatanOptions').innerHTML = renderKecamatanOptions(kode);
            }
        }

        // Pilih kecamatan
        function selectKecamatan(kode, nama) {
            selectedKecamatan = { kode, nama };
            
            // Update UI
            document.querySelectorAll('#kecamatanOptions .parent-option').forEach(opt => {
                opt.classList.remove('selected');
            });
            event.target.classList.add('selected');
            
            // Tampilkan form desa
            showSelectedParentInfo(`${selectedKota.nama} → ${nama} (Kecamatan)`);
            showFormSection('desa');
        }

        // Tampilkan info parent yang dipilih
        function showSelectedParentInfo(text) {
            document.getElementById('selectedParentText').textContent = text;
            document.getElementById('selectedParentInfo').classList.add('active');
            
            // Update hidden field
            const parentKode = selectedLevel === 'kecamatan' ? selectedKota.kode : selectedKecamatan.kode;
            document.getElementById('selectedParent').value = parentKode;
        }

        // Sembunyikan parent selection
        function hideParentSelection() {
            document.getElementById('parentSelection').classList.remove('active');
        }

        // Tampilkan form section
        function showFormSection(level) {
            // Sembunyikan semua section
            document.querySelectorAll('.form-section').forEach(section => {
                section.classList.remove('active');
            });
            
            // Tampilkan section yang dipilih
            document.getElementById(level + 'Section').classList.add('active');
            
            // Enable submit button
            document.getElementById('submitBtn').disabled = false;
            
            // Initialize form untuk level tersebut
            initializeForm(level);
        }

        // Initialize form berdasarkan level
        function initializeForm(level) {
            const wrapper = document.getElementById(level + 'Wrapper');
            wrapper.innerHTML = '';
            
            if (level === 'kota') {
                addKotaForm();
            } else if (level === 'kecamatan') {
                addKecamatanForm();
            } else if (level === 'desa') {
                addDesaForm();
            }
            
            updateCounters();
        }

        // Tambah form kota
        function addKotaForm(kotaData = null) {
            const wrapper = document.getElementById('kotaWrapper');
            const index = formCounter.kota++;
            
            const kotaDiv = document.createElement('div');
            kotaDiv.className = 'wilayah-group fade-in';
            kotaDiv.innerHTML = `
                <button type="button" class="delete-btn" onclick="removeForm(this, 'kota')">
                    <i class="fas fa-times"></i>
                </button>
                <div class="input-row">
                    <div class="kode-input">
                        <span class="kode-prefix"><?= $provinsi['kode_wilayah'] ?>.</span>
                        <input type="text" class="kode-field" name="kota[${index}][kode]" 
                               placeholder="Kode kota" value="${kotaData ? kotaData.kode : ''}" required
                               oninput="updateKotaPrefix(${index})">
                    </div>
                    <input type="text" class="nama-field" name="kota[${index}][nama]" 
                           placeholder="Nama Kota/Kabupaten" value="${kotaData ? kotaData.nama : ''}" required>
                </div>
                <p class="help-text">Contoh: 01 untuk kode, Banda Aceh untuk nama</p>
                
                <button type="button" class="sub-level-toggle" onclick="toggleSubLevel(this, 'kecamatan')">
                    <i class="fas fa-plus"></i> Tambah Kecamatan untuk Kota Ini
                </button>
                
                <div class="sub-level-content" id="kecamatanForKota${index}">
                    <div id="kecamatanWrapper${index}">
                        <!-- Kecamatan akan ditambahkan di sini -->
                    </div>
                    <div class="action-buttons">
                        <button type="button" class="btn btn-success btn-sm" onclick="addKecamatanForKota(${index})">
                            <i class="fas fa-plus"></i> Tambah Kecamatan
                        </button>
                    </div>
                </div>
            `;
            
            wrapper.appendChild(kotaDiv);
            updateCounters();
        }

        // Tambah kecamatan untuk kota tertentu (dalam form kota)
        function addKecamatanForKota(kotaIndex, kecamatanData = null) {
            const wrapper = document.getElementById(`kecamatanWrapper${kotaIndex}`);
            const kecIndex = formCounter.kecamatan++;
            
            const kecamatanDiv = document.createElement('div');
            kecamatanDiv.className = 'wilayah-group fade-in';
            kecamatanDiv.innerHTML = `
                <button type="button" class="delete-btn" onclick="removeForm(this, 'kecamatan')">
                    <i class="fas fa-times"></i>
                </button>
                <div class="input-row">
                    <div class="kode-input">
                        <span class="kode-prefix"><?= $provinsi['kode_wilayah'] ?>.${document.querySelector(`input[name="kota[${kotaIndex}][kode]"]`).value}.</span>
                        <input type="text" class="kode-field" name="kota[${kotaIndex}][kecamatan][${kecIndex}][kode]" 
                               placeholder="Kode kecamatan" value="${kecamatanData ? kecamatanData.kode : ''}" required
                               oninput="updateKecamatanPrefix(${kotaIndex}, ${kecIndex})">
                    </div>
                    <input type="text" class="nama-field" name="kota[${kotaIndex}][kecamatan][${kecIndex}][nama]" 
                           placeholder="Nama Kecamatan" value="${kecamatanData ? kecamatanData.nama : ''}" required>
                </div>
                
                <button type="button" class="sub-level-toggle" onclick="toggleSubLevel(this, 'desa')">
                    <i class="fas fa-plus"></i> Tambah Desa untuk Kecamatan Ini
                </button>
                
                <div class="sub-level-content" id="desaForKecamatan${kecIndex}">
                    <div id="desaWrapper${kecIndex}">
                        <!-- Desa akan ditambahkan di sini -->
                    </div>
                    <div class="action-buttons">
                        <button type="button" class="btn btn-warning btn-sm" onclick="addDesaForKecamatan(${kotaIndex}, ${kecIndex})">
                            <i class="fas fa-plus"></i> Tambah Desa
                        </button>
                    </div>
                </div>
            `;
            
            wrapper.appendChild(kecamatanDiv);
            updateCounters();
        }

        // Tambah desa untuk kecamatan tertentu (dalam form kota)
        function addDesaForKecamatan(kotaIndex, kecIndex, desaData = null) {
            const wrapper = document.getElementById(`desaWrapper${kecIndex}`);
            const desaIndex = formCounter.desa++;
            
            const kotaKode = document.querySelector(`input[name="kota[${kotaIndex}][kode]"]`).value;
            const kecKode = document.querySelector(`input[name="kota[${kotaIndex}][kecamatan][${kecIndex}][kode]"]`).value;
            
            const desaDiv = document.createElement('div');
            desaDiv.className = 'wilayah-group fade-in';
            desaDiv.innerHTML = `
                <button type="button" class="delete-btn" onclick="removeForm(this, 'desa')">
                    <i class="fas fa-times"></i>
                </button>
                <div class="input-row">
                    <div class="kode-input">
                        <span class="kode-prefix"><?= $provinsi['kode_wilayah'] ?>.${kotaKode}.${kecKode}.</span>
                        <input type="text" class="kode-field" name="kota[${kotaIndex}][kecamatan][${kecIndex}][desa][${desaIndex}][kode]" 
                               placeholder="Kode desa" value="${desaData ? desaData.kode : ''}" required
                               oninput="updateDesaPrefix(${kotaIndex}, ${kecIndex}, ${desaIndex})">
                    </div>
                    <input type="text" class="nama-field" name="kota[${kotaIndex}][kecamatan][${kecIndex}][desa][${desaIndex}][nama]" 
                           placeholder="Nama Desa/Kelurahan" value="${desaData ? desaData.nama : ''}" required>
                </div>
                
                <button type="button" class="sub-level-toggle" onclick="toggleSubLevel(this, 'lokasi')">
                    <i class="fas fa-map-marker-alt"></i> Tambah Lokasi di Desa Ini
                </button>
                
                <div class="sub-level-content" id="lokasiForDesa${desaIndex}">
                    <div id="lokasiWrapper${desaIndex}">
                        <!-- Lokasi akan ditambahkan di sini -->
                    </div>
                    <div class="action-buttons">
                        <button type="button" class="btn btn-primary btn-sm" onclick="addLokasiForDesa(${kotaIndex}, ${kecIndex}, ${desaIndex})">
                            <i class="fas fa-plus"></i> Tambah Lokasi
                        </button>
                    </div>
                </div>
            `;
            
            wrapper.appendChild(desaDiv);
            updateCounters();
        }

        // Tambah lokasi untuk desa tertentu
        function addLokasiForDesa(kotaIndex, kecIndex, desaIndex, lokasiData = null) {
            const wrapper = document.getElementById(`lokasiWrapper${desaIndex}`);
            const lokasiIndex = formCounter.lokasi++;
            
            const kotaKode = document.querySelector(`input[name="kota[${kotaIndex}][kode]"]`).value;
            const kecKode = document.querySelector(`input[name="kota[${kotaIndex}][kecamatan][${kecIndex}][kode]"]`).value;
            const desaKode = document.querySelector(`input[name="kota[${kotaIndex}][kecamatan][${kecIndex}][desa][${desaIndex}][kode]"]`).value;
            const fullDesaKode = `<?= $provinsi['kode_wilayah'] ?>.${kotaKode}.${kecKode}.${desaKode}`;
            
            const lokasiDiv = document.createElement('div');
            lokasiDiv.className = 'wilayah-group fade-in';
            lokasiDiv.innerHTML = `
                <button type="button" class="delete-btn" onclick="removeForm(this, 'lokasi')">
                    <i class="fas fa-times"></i>
                </button>
                
                <div class="lokasi-form">
                    <h4><i class="fas fa-map-marker-alt"></i> Data Lokasi <span class="lokasi-counter">#${lokasiIndex + 1}</span></h4>
                    
                    <div class="form-group">
                        <label for="lokasi_nama_${lokasiIndex}"><i class="fas fa-tag"></i> Nama Tempat *</label>
                        <input type="text" 
                               class="nama-field" 
                               id="lokasi_nama_${lokasiIndex}" 
                               name="kota[${kotaIndex}][kecamatan][${kecIndex}][desa][${desaIndex}][lokasi][${lokasiIndex}][nama_tempat]" 
                               placeholder="Masukkan nama tempat"
                               value="${lokasiData ? lokasiData.nama_tempat : ''}"
                               required>
                    </div>
                    
                    <div class="form-group">
                        <label for="lokasi_koordinat_${lokasiIndex}"><i class="fas fa-map-pin"></i> Koordinat *</label>
                        <div class="coordinate-input-group">
                            <input type="text" 
                                   class="kode-field" 
                                   id="lokasi_koordinat_${lokasiIndex}" 
                                   name="kota[${kotaIndex}][kecamatan][${kecIndex}][desa][${desaIndex}][lokasi][${lokasiIndex}][koordinat]" 
                                   placeholder="Latitude,Longitude"
                                   value="${lokasiData ? lokasiData.koordinat : ''}"
                                   oninput="handleCoordinateInput('${fullDesaKode}', ${lokasiIndex}, this.value)"
                                   required>
                            <button type="button" class="btn btn-primary btn-sm" onclick="openMapModal('${fullDesaKode}', ${lokasiIndex})">
                                <i class="fas fa-map"></i> Pilih di Peta
                            </button>
                        </div>
                        <div class="coordinate-display" id="lokasi_coordinate_display_${lokasiIndex}">
                            ${lokasiData ? lokasiData.koordinat : 'Klik "Pilih di Peta" untuk menentukan koordinat'}
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="lokasi_keterangan_${lokasiIndex}"><i class="fas fa-file-text"></i> Keterangan</label>
                        <textarea class="nama-field" 
                                  id="lokasi_keterangan_${lokasiIndex}" 
                                  name="kota[${kotaIndex}][kecamatan][${kecIndex}][desa][${desaIndex}][lokasi][${lokasiIndex}][keterangan]" 
                                  placeholder="Tambahkan keterangan tentang lokasi ini (opsional)"
                                  rows="2">${lokasiData ? lokasiData.keterangan : ''}</textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="lokasi_sinyal_${lokasiIndex}"><i class="fas fa-wifi"></i> Ketersediaan Sinyal *</label>
                        <select class="select-field" 
                                id="lokasi_sinyal_${lokasiIndex}" 
                                name="kota[${kotaIndex}][kecamatan][${kecIndex}][desa][${desaIndex}][lokasi][${lokasiIndex}][ketersediaan_sinyal]"
                                required>
                            <option value="Yes" ${lokasiData && lokasiData.ketersediaan_sinyal === 'Yes' ? 'selected' : ''}>Yes - Ada Sinyal</option>
                            <option value="No" ${lokasiData && lokasiData.ketersediaan_sinyal === 'No' ? 'selected' : ''}>No - Tidak Ada Sinyal</option>
                        </select>
                        <div class="signal-status">
                            <span class="status-indicator status-${lokasiData && lokasiData.ketersediaan_sinyal === 'Yes' ? 'yes' : 'no'}"></span>
                            <span>Status: <strong id="lokasi_status_text_${lokasiIndex}">${lokasiData && lokasiData.ketersediaan_sinyal === 'Yes' ? 'Ada Sinyal' : 'Tidak Ada Sinyal'}</strong></span>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="lokasi_kecepatan_${lokasiIndex}"><i class="fas fa-tachometer-alt"></i> Kecepatan Sinyal (Mbps)</label>
                        <input type="number" 
                               class="kode-field" 
                               id="lokasi_kecepatan_${lokasiIndex}" 
                               name="kota[${kotaIndex}][kecamatan][${kecIndex}][desa][${desaIndex}][lokasi][${lokasiIndex}][kecepatan_sinyal]" 
                               value="${lokasiData ? lokasiData.kecepatan_sinyal : '0'}"
                               min="0" 
                               step="0.1"
                               placeholder="Contoh: 10.5">
                        <p class="help-text">Isi dengan angka desimal (contoh: 5.5, 10.0, 25.5)</p>
                    </div>
                    
                    <input type="hidden" 
                           name="kota[${kotaIndex}][kecamatan][${kecIndex}][desa][${desaIndex}][lokasi][${lokasiIndex}][kode_wilayah]" 
                           value="${fullDesaKode}">
                </div>
                
                <div class="action-buttons">
                    <button type="button" class="btn btn-primary btn-sm" onclick="addLokasiForDesa(${kotaIndex}, ${kecIndex}, ${desaIndex})">
                        <i class="fas fa-plus"></i> Tambah Lokasi Lain
                    </button>
                </div>
            `;
            
            wrapper.appendChild(lokasiDiv);
            
            // Add event listener for signal status change
            document.getElementById(`lokasi_sinyal_${lokasiIndex}`).addEventListener('change', function(e) {
                const statusIndicator = document.querySelector(`#lokasi_status_text_${lokasiIndex}`).previousElementSibling;
                const statusText = document.getElementById(`lokasi_status_text_${lokasiIndex}`);
                
                statusIndicator.className = 'status-indicator status-' + e.target.value.toLowerCase();
                statusText.textContent = e.target.value === 'Yes' ? 'Ada Sinyal' : 'Tidak Ada Sinyal';
            });
            
            updateCounters();
        }

        // Handle coordinate input from text field
        function handleCoordinateInput(kodeWilayah, lokasiIndex, coordinateValue) {
            // Update display
            document.getElementById(`lokasi_coordinate_display_${lokasiIndex}`).textContent = coordinateValue;
            
            // If coordinate is valid, automatically open map and go to that location
            if (isValidCoordinate(coordinateValue)) {
                // Store context for later use
                currentLokasiContext = { kodeWilayah, lokasiIndex, type: 'kota' };
                
                // Open map modal if not already open
                if (document.getElementById('mapModal').style.display !== 'flex') {
                    openMapModal(kodeWilayah, lokasiIndex);
                }
                
                // Parse and go to coordinates
                const [lat, lng] = coordinateValue.split(',').map(coord => parseFloat(coord.trim()));
                goToCoordinates(lat, lng);
            }
        }

        // Validate coordinate format
        function isValidCoordinate(coordString) {
            if (!coordString) return false;
            
            const parts = coordString.split(',');
            if (parts.length !== 2) return false;
            
            const lat = parseFloat(parts[0].trim());
            const lng = parseFloat(parts[1].trim());
            
            return !isNaN(lat) && !isNaN(lng) && 
                   lat >= -90 && lat <= 90 && 
                   lng >= -180 && lng <= 180;
        }

        // Go to specific coordinates on map
        function goToCoordinates(lat, lng) {
            if (!modalMap) return;
            
            modalMap.setView([lat, lng], 16);
            setModalMarker(lat, lng);
            
            // Update current coordinate display
            document.getElementById('currentCoordinate').textContent = `${lat.toFixed(6)}, ${lng.toFixed(6)}`;
        }

        // Fungsi untuk update prefix ketika kode berubah
        function updateKotaPrefix(kotaIndex) {
            const kotaKode = document.querySelector(`input[name="kota[${kotaIndex}][kode]"]`).value;
            // Update semua kecamatan dalam kota ini
            document.querySelectorAll(`[id^="kecamatanWrapper${kotaIndex}"] .wilayah-group`).forEach((kecGroup, kecIndex) => {
                const prefixSpan = kecGroup.querySelector('.kode-prefix');
                if (prefixSpan) {
                    prefixSpan.textContent = `<?= $provinsi['kode_wilayah'] ?>.${kotaKode}.`;
                }
            });
        }

        function updateKecamatanPrefix(kotaIndex, kecIndex) {
            const kotaKode = document.querySelector(`input[name="kota[${kotaIndex}][kode]"]`).value;
            const kecKode = document.querySelector(`input[name="kota[${kotaIndex}][kecamatan][${kecIndex}][kode]"]`).value;
            const prefixSpan = document.querySelector(`input[name="kota[${kotaIndex}][kecamatan][${kecIndex}][kode]"]`).previousElementSibling;
            if (prefixSpan) {
                prefixSpan.textContent = `<?= $provinsi['kode_wilayah'] ?>.${kotaKode}.`;
            }
            
            // Update semua desa dalam kecamatan ini
            document.querySelectorAll(`#desaWrapper${kecIndex} .wilayah-group`).forEach((desaGroup, desaIndex) => {
                const desaPrefixSpan = desaGroup.querySelector('.kode-prefix');
                if (desaPrefixSpan) {
                    desaPrefixSpan.textContent = `<?= $provinsi['kode_wilayah'] ?>.${kotaKode}.${kecKode}.`;
                }
            });
        }

        function updateDesaPrefix(kotaIndex, kecIndex, desaIndex) {
            const kotaKode = document.querySelector(`input[name="kota[${kotaIndex}][kode]"]`).value;
            const kecKode = document.querySelector(`input[name="kota[${kotaIndex}][kecamatan][${kecIndex}][kode]"]`).value;
            const prefixSpan = document.querySelector(`input[name="kota[${kotaIndex}][kecamatan][${kecIndex}][desa][${desaIndex}][kode]"]`).previousElementSibling;
            if (prefixSpan) {
                prefixSpan.textContent = `<?= $provinsi['kode_wilayah'] ?>.${kotaKode}.${kecKode}.`;
            }
        }

        // Tambah form kecamatan (standalone)
        function addKecamatanForm(kecamatanData = null) {
            const wrapper = document.getElementById('kecamatanWrapper');
            const index = formCounter.kecamatan++;
            
            const kecamatanDiv = document.createElement('div');
            kecamatanDiv.className = 'wilayah-group fade-in';
            kecamatanDiv.innerHTML = `
                <button type="button" class="delete-btn" onclick="removeForm(this, 'kecamatan')">
                    <i class="fas fa-times"></i>
                </button>
                <div class="input-row">
                    <div class="kode-input">
                        <span class="kode-prefix">${selectedKota.kode}.</span>
                        <input type="text" class="kode-field" name="kecamatan[${index}][kode]" 
                               placeholder="Kode kecamatan" value="${kecamatanData ? kecamatanData.kode : ''}" required
                               oninput="updateKecamatanStandalonePrefix(${index})">
                    </div>
                    <input type="text" class="nama-field" name="kecamatan[${index}][nama]" 
                           placeholder="Nama Kecamatan" value="${kecamatanData ? kecamatanData.nama : ''}" required>
                </div>
                <p class="help-text">Parent: ${selectedKota.nama} (Kota)</p>
                
                <button type="button" class="sub-level-toggle" onclick="toggleSubLevel(this, 'desa')">
                    <i class="fas fa-plus"></i> Tambah Desa untuk Kecamatan Ini
                </button>
                
                <div class="sub-level-content" id="desaForKecamatan${index}">
                    <div id="desaWrapper${index}">
                        <!-- Desa akan ditambahkan di sini -->
                    </div>
                    <div class="action-buttons">
                        <button type="button" class="btn btn-warning btn-sm" onclick="addDesaForKecamatanStandalone(${index})">
                            <i class="fas fa-plus"></i> Tambah Desa
                        </button>
                    </div>
                </div>
                
                <div class="action-buttons">
                    <button type="button" class="btn btn-success btn-sm" onclick="addKecamatanForm()">
                        <i class="fas fa-plus"></i> Tambah Kecamatan Lain
                    </button>
                </div>
            `;
            
            wrapper.appendChild(kecamatanDiv);
            updateCounters();
        }

        // Update prefix untuk kecamatan standalone
        function updateKecamatanStandalonePrefix(kecIndex) {
            const kecKode = document.querySelector(`input[name="kecamatan[${kecIndex}][kode]"]`).value;
            const prefixSpan = document.querySelector(`input[name="kecamatan[${kecIndex}][kode]"]`).previousElementSibling;
            if (prefixSpan) {
                prefixSpan.textContent = `${selectedKota.kode}.`;
            }
            
            // Update semua desa dalam kecamatan ini
            document.querySelectorAll(`#desaWrapper${kecIndex} .wilayah-group`).forEach((desaGroup, desaIndex) => {
                const desaPrefixSpan = desaGroup.querySelector('.kode-prefix');
                if (desaPrefixSpan) {
                    desaPrefixSpan.textContent = `${selectedKota.kode}.${kecKode}.`;
                }
            });
        }

        // Tambah desa untuk kecamatan standalone
        function addDesaForKecamatanStandalone(kecIndex, desaData = null) {
            const wrapper = document.getElementById(`desaWrapper${kecIndex}`);
            const desaIndex = formCounter.desa++;
            
            const kecKode = document.querySelector(`input[name="kecamatan[${kecIndex}][kode]"]`).value;
            const fullDesaKode = `${selectedKota.kode}.${kecKode}.${desaData ? desaData.kode : ''}`;
            
            const desaDiv = document.createElement('div');
            desaDiv.className = 'wilayah-group fade-in';
            desaDiv.innerHTML = `
                <button type="button" class="delete-btn" onclick="removeForm(this, 'desa')">
                    <i class="fas fa-times"></i>
                </button>
                <div class="input-row">
                    <div class="kode-input">
                        <span class="kode-prefix">${selectedKota.kode}.${kecKode}.</span>
                        <input type="text" class="kode-field" name="kecamatan[${kecIndex}][desa][${desaIndex}][kode]" 
                               placeholder="Kode desa" value="${desaData ? desaData.kode : ''}" required
                               oninput="updateDesaStandalonePrefix(${kecIndex}, ${desaIndex})">
                    </div>
                    <input type="text" class="nama-field" name="kecamatan[${kecIndex}][desa][${desaIndex}][nama]" 
                           placeholder="Nama Desa/Kelurahan" value="${desaData ? desaData.nama : ''}" required>
                </div>
                <p class="help-text">Parent: ${document.querySelector(`input[name="kecamatan[${kecIndex}][nama]"]`).value} (Kecamatan)</p>
                
                <button type="button" class="sub-level-toggle" onclick="toggleSubLevel(this, 'lokasi')">
                    <i class="fas fa-map-marker-alt"></i> Tambah Lokasi di Desa Ini
                </button>
                
                <div class="sub-level-content" id="lokasiForDesaStandalone${desaIndex}">
                    <div id="lokasiWrapperStandalone${desaIndex}">
                        <!-- Lokasi akan ditambahkan di sini -->
                    </div>
                    <div class="action-buttons">
                        <button type="button" class="btn btn-primary btn-sm" onclick="addLokasiForDesaStandalone(${kecIndex}, ${desaIndex})">
                            <i class="fas fa-plus"></i> Tambah Lokasi
                        </button>
                    </div>
                </div>
                
                <div class="action-buttons">
                    <button type="button" class="btn btn-warning btn-sm" onclick="addDesaForKecamatanStandalone(${kecIndex})">
                        <i class="fas fa-plus"></i> Tambah Desa Lain
                    </button>
                </div>
            `;
            
            wrapper.appendChild(desaDiv);
            updateCounters();
        }

        // Tambah lokasi untuk desa standalone
        function addLokasiForDesaStandalone(kecIndex, desaIndex, lokasiData = null) {
            const wrapper = document.getElementById(`lokasiWrapperStandalone${desaIndex}`);
            const lokasiIndex = formCounter.lokasi++;
            
            const kecKode = document.querySelector(`input[name="kecamatan[${kecIndex}][kode]"]`).value;
            const desaKode = document.querySelector(`input[name="kecamatan[${kecIndex}][desa][${desaIndex}][kode]"]`).value;
            const fullDesaKode = `${selectedKota.kode}.${kecKode}.${desaKode}`;
            
            const lokasiDiv = document.createElement('div');
            lokasiDiv.className = 'wilayah-group fade-in';
            lokasiDiv.innerHTML = `
                <button type="button" class="delete-btn" onclick="removeForm(this, 'lokasi')">
                    <i class="fas fa-times"></i>
                </button>
                
                <div class="lokasi-form">
                    <h4><i class="fas fa-map-marker-alt"></i> Data Lokasi <span class="lokasi-counter">#${lokasiIndex + 1}</span></h4>
                    
                    <div class="form-group">
                        <label for="lokasi_standalone_nama_${lokasiIndex}"><i class="fas fa-tag"></i> Nama Tempat *</label>
                        <input type="text" 
                               class="nama-field" 
                               id="lokasi_standalone_nama_${lokasiIndex}" 
                               name="kecamatan[${kecIndex}][desa][${desaIndex}][lokasi][${lokasiIndex}][nama_tempat]" 
                               placeholder="Masukkan nama tempat"
                               value="${lokasiData ? lokasiData.nama_tempat : ''}"
                               required>
                    </div>
                    
                    <div class="form-group">
                        <label for="lokasi_standalone_koordinat_${lokasiIndex}"><i class="fas fa-map-pin"></i> Koordinat *</label>
                        <div class="coordinate-input-group">
                            <input type="text" 
                                   class="kode-field" 
                                   id="lokasi_standalone_koordinat_${lokasiIndex}" 
                                   name="kecamatan[${kecIndex}][desa][${desaIndex}][lokasi][${lokasiIndex}][koordinat]" 
                                   placeholder="Latitude,Longitude"
                                   value="${lokasiData ? lokasiData.koordinat : ''}"
                                   oninput="handleCoordinateInput('${fullDesaKode}', ${lokasiIndex}, this.value)"
                                   required>
                            <button type="button" class="btn btn-primary btn-sm" onclick="openMapModal('${fullDesaKode}', ${lokasiIndex}, 'standalone')">
                                <i class="fas fa-map"></i> Pilih di Peta
                            </button>
                        </div>
                        <div class="coordinate-display" id="lokasi_standalone_coordinate_display_${lokasiIndex}">
                            ${lokasiData ? lokasiData.koordinat : 'Klik "Pilih di Peta" untuk menentukan koordinat'}
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="lokasi_standalone_keterangan_${lokasiIndex}"><i class="fas fa-file-text"></i> Keterangan</label>
                        <textarea class="nama-field" 
                                  id="lokasi_standalone_keterangan_${lokasiIndex}" 
                                  name="kecamatan[${kecIndex}][desa][${desaIndex}][lokasi][${lokasiIndex}][keterangan]" 
                                  placeholder="Tambahkan keterangan tentang lokasi ini (opsional)"
                                  rows="2">${lokasiData ? lokasiData.keterangan : ''}</textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="lokasi_standalone_sinyal_${lokasiIndex}"><i class="fas fa-wifi"></i> Ketersediaan Sinyal *</label>
                        <select class="select-field" 
                                id="lokasi_standalone_sinyal_${lokasiIndex}" 
                                name="kecamatan[${kecIndex}][desa][${desaIndex}][lokasi][${lokasiIndex}][ketersediaan_sinyal]"
                                required>
                            <option value="Yes" ${lokasiData && lokasiData.ketersediaan_sinyal === 'Yes' ? 'selected' : ''}>Yes - Ada Sinyal</option>
                            <option value="No" ${lokasiData && lokasiData.ketersediaan_sinyal === 'No' ? 'selected' : ''}>No - Tidak Ada Sinyal</option>
                        </select>
                        <div class="signal-status">
                            <span class="status-indicator status-${lokasiData && lokasiData.ketersediaan_sinyal === 'Yes' ? 'yes' : 'no'}"></span>
                            <span>Status: <strong id="lokasi_standalone_status_text_${lokasiIndex}">${lokasiData && lokasiData.ketersediaan_sinyal === 'Yes' ? 'Ada Sinyal' : 'Tidak Ada Sinyal'}</strong></span>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="lokasi_standalone_kecepatan_${lokasiIndex}"><i class="fas fa-tachometer-alt"></i> Kecepatan Sinyal (Mbps)</label>
                        <input type="number" 
                               class="kode-field" 
                               id="lokasi_standalone_kecepatan_${lokasiIndex}" 
                               name="kecamatan[${kecIndex}][desa][${desaIndex}][lokasi][${lokasiIndex}][kecepatan_sinyal]" 
                               value="${lokasiData ? lokasiData.kecepatan_sinyal : '0'}"
                               min="0" 
                               step="0.1"
                               placeholder="Contoh: 10.5">
                        <p class="help-text">Isi dengan angka desimal (contoh: 5.5, 10.0, 25.5)</p>
                    </div>
                    
                    <input type="hidden" 
                           name="kecamatan[${kecIndex}][desa][${desaIndex}][lokasi][${lokasiIndex}][kode_wilayah]" 
                           value="${fullDesaKode}">
                </div>
                
                <div class="action-buttons">
                    <button type="button" class="btn btn-primary btn-sm" onclick="addLokasiForDesaStandalone(${kecIndex}, ${desaIndex})">
                        <i class="fas fa-plus"></i> Tambah Lokasi Lain
                    </button>
                </div>
            `;
            
            wrapper.appendChild(lokasiDiv);
            
            // Add event listener for signal status change
            document.getElementById(`lokasi_standalone_sinyal_${lokasiIndex}`).addEventListener('change', function(e) {
                const statusIndicator = document.querySelector(`#lokasi_standalone_status_text_${lokasiIndex}`).previousElementSibling;
                const statusText = document.getElementById(`lokasi_standalone_status_text_${lokasiIndex}`);
                
                statusIndicator.className = 'status-indicator status-' + e.target.value.toLowerCase();
                statusText.textContent = e.target.value === 'Yes' ? 'Ada Sinyal' : 'Tidak Ada Sinyal';
            });
            
            updateCounters();
        }

        // Update prefix untuk desa standalone
        function updateDesaStandalonePrefix(kecIndex, desaIndex) {
            const kecKode = document.querySelector(`input[name="kecamatan[${kecIndex}][kode]"]`).value;
            const prefixSpan = document.querySelector(`input[name="kecamatan[${kecIndex}][desa][${desaIndex}][kode]"]`).previousElementSibling;
            if (prefixSpan) {
                prefixSpan.textContent = `${selectedKota.kode}.${kecKode}.`;
            }
        }

        // Tambah form desa (standalone)
        function addDesaForm(desaData = null) {
            const wrapper = document.getElementById('desaWrapper');
            const index = formCounter.desa++;
            
            const fullDesaKode = `${selectedKecamatan.kode}.${desaData ? desaData.kode : ''}`;
            
            const desaDiv = document.createElement('div');
            desaDiv.className = 'wilayah-group fade-in';
            desaDiv.innerHTML = `
                <button type="button" class="delete-btn" onclick="removeForm(this, 'desa')">
                    <i class="fas fa-times"></i>
                </button>
                <div class="input-row">
                    <div class="kode-input">
                        <span class="kode-prefix">${selectedKecamatan.kode}.</span>
                        <input type="text" class="kode-field" name="desa[${index}][kode]" 
                               placeholder="Kode desa" value="${desaData ? desaData.kode : ''}" required>
                    </div>
                    <input type="text" class="nama-field" name="desa[${index}][nama]" 
                           placeholder="Nama Desa/Kelurahan" value="${desaData ? desaData.nama : ''}" required>
                </div>
                <p class="help-text">Parent: ${selectedKecamatan.nama} (Kecamatan)</p>
                
                <button type="button" class="sub-level-toggle" onclick="toggleSubLevel(this, 'lokasi')">
                    <i class="fas fa-map-marker-alt"></i> Tambah Lokasi di Desa Ini
                </button>
                
                <div class="sub-level-content" id="lokasiForDesaOnly${index}">
                    <div id="lokasiWrapperOnly${index}">
                        <!-- Lokasi akan ditambahkan di sini -->
                    </div>
                    <div class="action-buttons">
                        <button type="button" class="btn btn-primary btn-sm" onclick="addLokasiForDesaOnly(${index})">
                            <i class="fas fa-plus"></i> Tambah Lokasi
                        </button>
                    </div>
                </div>
                
                <div class="action-buttons">
                    <button type="button" class="btn btn-warning btn-sm" onclick="addDesaForm()">
                        <i class="fas fa-plus"></i> Tambah Desa Lain
                    </button>
                </div>
            `;
            
            wrapper.appendChild(desaDiv);
            updateCounters();
        }

        // Tambah lokasi untuk desa only
        function addLokasiForDesaOnly(desaIndex, lokasiData = null) {
            const wrapper = document.getElementById(`lokasiWrapperOnly${desaIndex}`);
            const lokasiIndex = formCounter.lokasi++;
            
            const desaKode = document.querySelector(`input[name="desa[${desaIndex}][kode]"]`).value;
            const fullDesaKode = `${selectedKecamatan.kode}.${desaKode}`;
            
            const lokasiDiv = document.createElement('div');
            lokasiDiv.className = 'wilayah-group fade-in';
            lokasiDiv.innerHTML = `
                <button type="button" class="delete-btn" onclick="removeForm(this, 'lokasi')">
                    <i class="fas fa-times"></i>
                </button>
                
                <div class="lokasi-form">
                    <h4><i class="fas fa-map-marker-alt"></i> Data Lokasi <span class="lokasi-counter">#${lokasiIndex + 1}</span></h4>
                    
                    <div class="form-group">
                        <label for="lokasi_only_nama_${lokasiIndex}"><i class="fas fa-tag"></i> Nama Tempat *</label>
                        <input type="text" 
                               class="nama-field" 
                               id="lokasi_only_nama_${lokasiIndex}" 
                               name="desa[${desaIndex}][lokasi][${lokasiIndex}][nama_tempat]" 
                               placeholder="Masukkan nama tempat"
                               value="${lokasiData ? lokasiData.nama_tempat : ''}"
                               required>
                    </div>
                    
                    <div class="form-group">
                        <label for="lokasi_only_koordinat_${lokasiIndex}"><i class="fas fa-map-pin"></i> Koordinat *</label>
                        <div class="coordinate-input-group">
                            <input type="text" 
                                   class="kode-field" 
                                   id="lokasi_only_koordinat_${lokasiIndex}" 
                                   name="desa[${desaIndex}][lokasi][${lokasiIndex}][koordinat]" 
                                   placeholder="Latitude,Longitude"
                                   value="${lokasiData ? lokasiData.koordinat : ''}"
                                   oninput="handleCoordinateInput('${fullDesaKode}', ${lokasiIndex}, this.value)"
                                   required>
                            <button type="button" class="btn btn-primary btn-sm" onclick="openMapModal('${fullDesaKode}', ${lokasiIndex}, 'only')">
                                <i class="fas fa-map"></i> Pilih di Peta
                            </button>
                        </div>
                        <div class="coordinate-display" id="lokasi_only_coordinate_display_${lokasiIndex}">
                            ${lokasiData ? lokasiData.koordinat : 'Klik "Pilih di Peta" untuk menentukan koordinat'}
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="lokasi_only_keterangan_${lokasiIndex}"><i class="fas fa-file-text"></i> Keterangan</label>
                        <textarea class="nama-field" 
                                  id="lokasi_only_keterangan_${lokasiIndex}" 
                                  name="desa[${desaIndex}][lokasi][${lokasiIndex}][keterangan]" 
                                  placeholder="Tambahkan keterangan tentang lokasi ini (opsional)"
                                  rows="2">${lokasiData ? lokasiData.keterangan : ''}</textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="lokasi_only_sinyal_${lokasiIndex}"><i class="fas fa-wifi"></i> Ketersediaan Sinyal *</label>
                        <select class="select-field" 
                                id="lokasi_only_sinyal_${lokasiIndex}" 
                                name="desa[${desaIndex}][lokasi][${lokasiIndex}][ketersediaan_sinyal]"
                                required>
                            <option value="Yes" ${lokasiData && lokasiData.ketersediaan_sinyal === 'Yes' ? 'selected' : ''}>Yes - Ada Sinyal</option>
                            <option value="No" ${lokasiData && lokasiData.ketersediaan_sinyal === 'No' ? 'selected' : ''}>No - Tidak Ada Sinyal</option>
                        </select>
                        <div class="signal-status">
                            <span class="status-indicator status-${lokasiData && lokasiData.ketersediaan_sinyal === 'Yes' ? 'yes' : 'no'}"></span>
                            <span>Status: <strong id="lokasi_only_status_text_${lokasiIndex}">${lokasiData && lokasiData.ketersediaan_sinyal === 'Yes' ? 'Ada Sinyal' : 'Tidak Ada Sinyal'}</strong></span>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="lokasi_only_kecepatan_${lokasiIndex}"><i class="fas fa-tachometer-alt"></i> Kecepatan Sinyal (Mbps)</label>
                        <input type="number" 
                               class="kode-field" 
                               id="lokasi_only_kecepatan_${lokasiIndex}" 
                               name="desa[${desaIndex}][lokasi][${lokasiIndex}][kecepatan_sinyal]" 
                               value="${lokasiData ? lokasiData.kecepatan_sinyal : '0'}"
                               min="0" 
                               step="0.1"
                               placeholder="Contoh: 10.5">
                        <p class="help-text">Isi dengan angka desimal (contoh: 5.5, 10.0, 25.5)</p>
                    </div>
                    
                    <input type="hidden" 
                           name="desa[${desaIndex}][lokasi][${lokasiIndex}][kode_wilayah]" 
                           value="${fullDesaKode}">
                </div>
                
                <div class="action-buttons">
                    <button type="button" class="btn btn-primary btn-sm" onclick="addLokasiForDesaOnly(${desaIndex})">
                        <i class="fas fa-plus"></i> Tambah Lokasi Lain
                    </button>
                </div>
            `;
            
            wrapper.appendChild(lokasiDiv);
            
            // Add event listener for signal status change
            document.getElementById(`lokasi_only_sinyal_${lokasiIndex}`).addEventListener('change', function(e) {
                const statusIndicator = document.querySelector(`#lokasi_only_status_text_${lokasiIndex}`).previousElementSibling;
                const statusText = document.getElementById(`lokasi_only_status_text_${lokasiIndex}`);
                
                statusIndicator.className = 'status-indicator status-' + e.target.value.toLowerCase();
                statusText.textContent = e.target.value === 'Yes' ? 'Ada Sinyal' : 'Tidak Ada Sinyal';
            });
            
            updateCounters();
        }

        // Toggle sub level content
        function toggleSubLevel(button, level) {
            const content = button.nextElementSibling;
            content.classList.toggle('active');
            
            const icon = button.querySelector('i');
            if (content.classList.contains('active')) {
                icon.className = 'fas fa-minus';
                button.innerHTML = `<i class="fas fa-minus"></i> Sembunyikan ${level}`;
            } else {
                icon.className = 'fas fa-plus';
                button.innerHTML = `<i class="fas fa-plus"></i> Tambah ${level}`;
            }
        }

        // Hapus form
        function removeForm(button, level) {
            const formGroup = button.closest('.wilayah-group');
            formGroup.style.animation = 'fadeOut 0.3s ease';
            
            setTimeout(() => {
                formGroup.remove();
                updateCounters();
            }, 300);
        }

        // Update counters
        function updateCounters() {
            const kotaCount = document.querySelectorAll('#kotaWrapper .wilayah-group').length;
            const kecamatanCount = document.querySelectorAll('#kecamatanWrapper .wilayah-group').length;
            const desaCount = document.querySelectorAll('#desaWrapper .wilayah-group').length;
            
            document.getElementById('kotaCounter').textContent = `${kotaCount} kota`;
            document.getElementById('kecamatanCounter').textContent = `${kecamatanCount} kecamatan`;
            document.getElementById('desaCounter').textContent = `${desaCount} desa`;
        }

        // Map Modal Functions
        function openMapModal(kodeWilayah, lokasiIndex, type = 'kota') {
            currentLokasiContext = { kodeWilayah, lokasiIndex, type };
            currentCoordinates = null;
            
            document.getElementById('mapModal').style.display = 'flex';
            
            // Initialize map if not exists
            if (!modalMap) {
                modalMap = L.map('modalMap').setView([-2.5489, 118.0149], 5);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '© OpenStreetMap contributors',
                    maxZoom: 19
                }).addTo(modalMap);
                
                // Add geocoder control
                L.Control.geocoder({ 
                    defaultMarkGeocode: false,
                    placeholder: 'Cari alamat...',
                    errorMessage: 'Alamat tidak ditemukan.'
                })
                .on('markgeocode', function(e) {
                    const latlng = e.geocode.center;
                    modalMap.setView(latlng, 16);
                    setModalMarker(latlng.lat, latlng.lng);
                })
                .addTo(modalMap);
                
                // Map click event
                modalMap.on('click', function(e) {
                    setModalMarker(e.latlng.lat, e.latlng.lng);
                });
            }
            
            // Pre-fill manual coordinate inputs if there's existing coordinate
            let inputId;
            if (type === 'kota') {
                inputId = `lokasi_koordinat_${lokasiIndex}`;
            } else if (type === 'standalone') {
                inputId = `lokasi_standalone_koordinat_${lokasiIndex}`;
            } else if (type === 'only') {
                inputId = `lokasi_only_koordinat_${lokasiIndex}`;
            }
            
            const existingCoord = document.getElementById(inputId)?.value;
            if (existingCoord && isValidCoordinate(existingCoord)) {
                const [lat, lng] = existingCoord.split(',').map(coord => parseFloat(coord.trim()));
                document.getElementById('manualLatitude').value = lat;
                document.getElementById('manualLongitude').value = lng;
                goToCoordinates(lat, lng);
            } else {
                document.getElementById('manualLatitude').value = '';
                document.getElementById('manualLongitude').value = '';
                document.getElementById('modalCoordinateDisplay').textContent = 'Klik peta untuk menentukan koordinat';
                document.getElementById('currentCoordinate').textContent = '-';
            }
        }

        function closeMapModal() {
            document.getElementById('mapModal').style.display = 'none';
            if (modalMarker) {
                modalMap.removeLayer(modalMarker);
                modalMarker = null;
            }
        }

        function setModalMarker(lat, lon) {
            if (modalMarker) modalMap.removeLayer(modalMarker);
            modalMarker = L.marker([lat, lon]).addTo(modalMap);
            currentCoordinates = { lat, lon };
            
            const coordString = lat.toFixed(6) + "," + lon.toFixed(6);
            document.getElementById('modalCoordinateDisplay').textContent = coordString;
            document.getElementById('currentCoordinate').textContent = coordString;
            
            // Update manual input fields
            document.getElementById('manualLatitude').value = lat;
            document.getElementById('manualLongitude').value = lon;
            
            // Add visual feedback
            const display = document.getElementById('modalCoordinateDisplay');
            display.style.animation = 'pulse 0.5s ease-in-out';
            setTimeout(() => {
                display.style.animation = '';
            }, 500);
        }

        function getUserLocationModal() {
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(
                    pos => {
                        const lat = pos.coords.latitude;
                        const lon = pos.coords.longitude;
                        modalMap.setView([lat, lon], 16);
                        setModalMarker(lat, lon);
                    },
                    err => { 
                        alert("Gagal mengambil lokasi: " + err.message + "\n\nPastikan GPS aktif dan izin lokasi diberikan."); 
                    },
                    { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 }
                );
            } else {
                alert("Browser tidak mendukung Geolocation API.");
            }
        }

        function clearCoordinatesModal() {
            if (confirm("Hapus koordinat saat ini?")) {
                document.getElementById('modalCoordinateDisplay').textContent = 'Klik peta untuk menentukan koordinat';
                document.getElementById('currentCoordinate').textContent = '-';
                document.getElementById('manualLatitude').value = '';
                document.getElementById('manualLongitude').value = '';
                if (modalMarker) modalMap.removeLayer(modalMarker);
                modalMarker = null;
                currentCoordinates = null;
            }
        }

        function saveCoordinates() {
            if (!currentCoordinates) {
                alert("Harap pilih koordinat terlebih dahulu dengan mengklik peta atau menggunakan input manual.");
                return;
            }
            
            const { kodeWilayah, lokasiIndex, type } = currentLokasiContext;
            const coordString = currentCoordinates.lat.toFixed(6) + "," + currentCoordinates.lon.toFixed(6);
            
            // Update the appropriate input field based on type
            let inputId, displayId;
            if (type === 'kota') {
                inputId = `lokasi_koordinat_${lokasiIndex}`;
                displayId = `lokasi_coordinate_display_${lokasiIndex}`;
            } else if (type === 'standalone') {
                inputId = `lokasi_standalone_koordinat_${lokasiIndex}`;
                displayId = `lokasi_standalone_coordinate_display_${lokasiIndex}`;
            } else if (type === 'only') {
                inputId = `lokasi_only_koordinat_${lokasiIndex}`;
                displayId = `lokasi_only_coordinate_display_${lokasiIndex}`;
            }
            
            document.getElementById(inputId).value = coordString;
            document.getElementById(displayId).textContent = coordString;
            
            closeMapModal();
        }

        // Manual coordinate input functions
        function validateCoordinateInput(fieldId) {
            const input = document.getElementById(fieldId);
            const value = input.value.trim();
            
            // Allow negative sign, decimal points, and numbers
            const validChars = /^[-]?[0-9]*\.?[0-9]*$/;
            
            if (!validChars.test(value)) {
                input.value = value.slice(0, -1); // Remove invalid character
            }
        }

        function goToManualCoordinates() {
            const latInput = document.getElementById('manualLatitude');
            const lngInput = document.getElementById('manualLongitude');
            
            const lat = parseFloat(latInput.value.trim());
            const lng = parseFloat(lngInput.value.trim());
            
            if (isNaN(lat) || isNaN(lng)) {
                alert('Harap masukkan latitude dan longitude yang valid.');
                return;
            }
            
            if (lat < -90 || lat > 90) {
                alert('Latitude harus antara -90 dan 90 derajat.');
                return;
            }
            
            if (lng < -180 || lng > 180) {
                alert('Longitude harus antara -180 dan 180 derajat.');
                return;
            }
            
            goToCoordinates(lat, lng);
        }

        // Form validation
        document.getElementById('formBulk').addEventListener('submit', function(e) {
            if (!selectedLevel) {
                e.preventDefault();
                alert('Pilih level yang ingin ditambahkan terlebih dahulu!');
                return;
            }
            
            let count = 0;
            if (selectedLevel === 'kota') {
                count = document.querySelectorAll('#kotaWrapper .wilayah-group').length;
            } else if (selectedLevel === 'kecamatan') {
                count = document.querySelectorAll('#kecamatanWrapper .wilayah-group').length;
            } else if (selectedLevel === 'desa') {
                count = document.querySelectorAll('#desaWrapper .wilayah-group').length;
            }
            
            if (count === 0) {
                e.preventDefault();
                alert(`Tambahkan setidaknya satu ${selectedLevel}!`);
                return;
            }
            
            // Validate that all locations have coordinates
            const locationInputs = document.querySelectorAll('input[name*="[lokasi]"][name*="[koordinat]"]');
            let missingCoordinates = false;
            locationInputs.forEach(input => {
                if (!input.value.trim()) {
                    missingCoordinates = true;
                    // Highlight the problematic field
                    input.style.borderColor = 'var(--danger)';
                } else {
                    input.style.borderColor = '';
                }
            });
            
            if (missingCoordinates) {
                e.preventDefault();
                alert('Beberapa lokasi belum memiliki koordinat. Harap tentukan koordinat untuk semua lokasi sebelum menyimpan.');
                return;
            }
            
            if (!confirm(`Anda akan menambahkan data wilayah dan lokasi baru. Lanjutkan?`)) {
                e.preventDefault();
            }
        });

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            // Disable submit button sampai level dipilih
            document.getElementById('submitBtn').disabled = true;
            
            // Add fadeOut animation
            const style = document.createElement('style');
            style.textContent = `
                @keyframes fadeOut {
                    from { opacity: 1; transform: translateY(0); }
                    to { opacity: 0; transform: translateY(-20px); }
                }
            `;
            document.head.appendChild(style);
        });
    </script>
</body>
</html>