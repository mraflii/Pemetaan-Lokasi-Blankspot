<?php
include "config/db.php";

// Ambil parameter provinsi dari URL (jika ada)
$provinsiParam = $_GET['provinsi'] ?? '';

// --- Ambil provinsi untuk dropdown ---
if ($provinsiParam) {
    $provinsi = $conn->query("SELECT * FROM wilayah WHERE level='provinsi' 
        AND kode_wilayah='".$conn->real_escape_string($provinsiParam)."'");
} else {
    $provinsi = $conn->query("SELECT * FROM wilayah WHERE level='provinsi' 
        ORDER BY kode_wilayah ASC");
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Tambah Lokasi Baru</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
<link rel="stylesheet" href="https://unpkg.com/leaflet-control-geocoder/dist/Control.Geocoder.css"/>
<!-- Link ke file CSS eksternal -->
<!-- <link rel="stylesheet" href="css/create_lokasi.css"> -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/leaflet-control-geocoder/dist/Control.Geocoder.js"></script>
<style>
    /* style.css */
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
    max-width: 800px;
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
    font-size: 1rem;
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

.section {
    margin-bottom: 25px;
    padding-bottom: 20px;
    border-bottom: 1px solid #eef2f7;
}

.section:last-child {
    border-bottom: none;
    margin-bottom: 0;
}

.section-title {
    font-size: 1.2rem;
    font-weight: 600;
    color: var(--secondary);
    margin-bottom: 15px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.section-title i {
    color: var(--secondary);
}

.form-group {
    margin-bottom: 18px;
}

label {
    display: block;
    font-weight: 600;
    margin-bottom: 6px;
    color: var(--dark);
    font-size: 0.9rem;
}

.input-group {
    position: relative;
}

input, select, textarea {
    width: 100%;
    padding: 12px 16px;
    border: 2px solid #e3e8f0;
    border-radius: 10px;
    font-size: 14px;
    transition: all 0.3s ease;
    background: white;
    font-family: inherit;
}

input:focus, select:focus, textarea:focus {
    outline: none;
    border-color: var(--secondary);
    box-shadow: 0 0 0 3px rgba(41, 128, 185, 0.1);
    background: #fafbfc;
}

input[readonly] {
    background: #f8fafc;
    color: #64748b;
    border-color: #e2e8f0;
    cursor: not-allowed;
}

textarea {
    resize: vertical;
    min-height: 80px;
}

/* Hierarchical Selection Styles */
.hierarchical-selection {
    margin-bottom: 25px;
}

.selection-steps {
    display: flex;
    flex-direction: column;
    gap: 15px;
    margin-bottom: 20px;
}

.selection-step {
    background: #f8fafc;
    border-radius: 12px;
    padding: 20px;
    border-left: 4px solid var(--secondary);
    transition: all 0.3s ease;
}

.selection-step.active {
    background: #f0f7ff;
    border-left-color: var(--success);
    box-shadow: 0 4px 12px rgba(41, 128, 185, 0.1);
}

.step-title {
    font-size: 1rem;
    font-weight: 600;
    color: var(--dark);
    margin-bottom: 12px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.step-title i {
    color: var(--secondary);
}

.step-content {
    min-height: 60px;
}

.parent-options {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 10px;
}

.parent-option {
    background: white;
    border: 2px solid #e3e8f0;
    border-radius: 8px;
    padding: 12px 15px;
    cursor: pointer;
    transition: all 0.3s ease;
    text-align: center;
    font-weight: 500;
}

.parent-option:hover {
    border-color: var(--secondary);
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.parent-option.selected {
    background: var(--secondary);
    color: white;
    border-color: var(--secondary);
}

.empty-state {
    text-align: center;
    color: #64748b;
    padding: 20px;
}

.empty-state i {
    font-size: 2rem;
    margin-bottom: 10px;
    color: #cbd5e1;
}

.selected-wilayah-info {
    background: linear-gradient(135deg, var(--success), #2ecc71);
    color: white;
    padding: 15px 20px;
    border-radius: 10px;
    margin-top: 15px;
    display: none;
}

.selected-wilayah-info.active {
    display: block;
    animation: fadeIn 0.5s ease;
}

.selected-wilayah-badge {
    display: flex;
    align-items: center;
    gap: 10px;
    font-weight: 600;
}

.kode-display {
    background: #f0f7ff;
    border: 1px solid #b3d9f7;
    border-radius: 8px;
    padding: 15px;
    margin-top: 15px;
}

.kode-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
}

.kode-row:last-child {
    margin-bottom: 0;
}

.kode-label {
    font-weight: 600;
    color: var(--dark);
    font-size: 0.9rem;
}

.kode-value {
    font-family: monospace;
    font-weight: 600;
    color: var(--secondary);
    background: white;
    padding: 4px 8px;
    border-radius: 4px;
    border: 1px solid #e2e8f0;
}

.map-container {
    margin-top: 15px;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

#map {
    height: 300px;
    width: 100%;
}

.location-buttons {
    display: flex;
    gap: 10px;
    margin-top: 10px;
    flex-wrap: wrap;
}

.btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 10px 16px;
    border: none;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
    text-decoration: none;
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.btn-primary {
    background: linear-gradient(135deg, var(--secondary), #3498db);
    color: white;
}

.btn-success {
    background: linear-gradient(135deg, var(--success), #2ecc71);
    color: white;
}

.btn-danger {
    background: linear-gradient(135deg, var(--danger), #c0392b);
    color: white;
}

.btn-warning {
    background: linear-gradient(135deg, var(--warning), #e67e22);
    color: white;
}

.btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.btn:active {
    transform: translateY(0);
}

.form-actions {
    display: flex;
    gap: 12px;
    margin-top: 25px;
    padding-top: 20px;
    border-top: 1px solid #eef2f7;
    flex-wrap: wrap;
}

.btn-lg {
    padding: 12px 24px;
    font-size: 14px;
    flex: 1;
    justify-content: center;
}

.signal-status {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-top: 5px;
}

.status-indicator {
    width: 12px;
    height: 12px;
    border-radius: 50%;
    display: inline-block;
}

.status-yes { background: var(--success); }
.status-no { background: var(--danger); }

.coordinate-display {
    background: #f1f5f9;
    padding: 10px;
    border-radius: 6px;
    font-family: monospace;
    font-size: 13px;
    margin-top: 8px;
    border: 1px solid #e2e8f0;
}

.help-text {
    font-size: 0.8rem;
    color: #64748b;
    margin-top: 4px;
    font-style: italic;
}

@media (max-width: 768px) {
    body {
        padding: 15px;
    }

    .container {
        max-width: 100%;
    }

    .form-container {
        padding: 25px 20px;
        border-radius: 12px;
    }

    .page-title {
        font-size: 1.8rem;
    }

    .parent-options {
        grid-template-columns: 1fr;
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
        height: 250px;
    }

    .section-title {
        font-size: 1.1rem;
    }
}

@media (max-width: 480px) {
    .form-container {
        padding: 20px 15px;
    }

    .page-title {
        font-size: 1.5rem;
    }

    input, select, textarea {
        padding: 10px 14px;
    }

    #map {
        height: 200px;
    }
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

.loading {
    opacity: 0.6;
    pointer-events: none;
}

.loading::after {
    content: '...';
    animation: loading 1.5s infinite;
}

@keyframes loading {
    0%, 33% { content: '.'; }
    34%, 66% { content: '..'; }
    67%, 100% { content: '...'; }
}
</style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header fade-in">
            <h1 class="page-title"><i class="fas fa-plus-circle"></i> Tambah Lokasi Baru</h1>
            <p class="page-subtitle">Tambahkan data lokasi dan informasi sinyal</p>
        </div>

        <!-- Form Container -->
        <div class="form-container fade-in">
            <form action="proses/proses_tambah_lokasi.php" method="post" id="tambahLokasiForm">
                <!-- Data Wilayah Section -->
                <div class="section">
                    <h3 class="section-title">
                        <i class="fas fa-map-marker-alt"></i> Data Wilayah
                    </h3>
                    
                    <!-- Provinsi Selection -->
                    <div class="form-group">
                        <label for="provinsi">
                            <i class="fas fa-map"></i> Provinsi
                        </label>
                        <select name="provinsi" id="provinsi" required <?= $provinsiParam ? 'disabled' : '' ?>>
                            <option value="">-- Pilih Provinsi --</option>
                            <?php while($p = $provinsi->fetch_assoc()): ?>
                                <option value="<?= htmlspecialchars($p['kode_wilayah']) ?>" 
                                    <?= ($provinsiParam == $p['kode_wilayah']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($p['nama']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                        <?php if ($provinsiParam): ?>
                            <input type="hidden" name="provinsi" value="<?= htmlspecialchars($provinsiParam) ?>">
                        <?php endif; ?>
                    </div>

                    <!-- Hierarchical Selection -->
                    <div class="hierarchical-selection">
                        <div class="selection-steps">
                            <!-- Kota Step -->
                            <div class="selection-step" id="stepKota">
                                <div class="step-title">
                                    <i class="fas fa-city"></i>
                                    <span>1. Pilih Kota/Kabupaten</span>
                                </div>
                                <div class="step-content" id="kotaOptions">
                                    <div class="empty-state">
                                        <i class="fas fa-city"></i>
                                        <div>Pilih provinsi terlebih dahulu</div>
                                    </div>
                                </div>
                            </div>

                            <!-- Kecamatan Step -->
                            <div class="selection-step" id="stepKecamatan">
                                <div class="step-title">
                                    <i class="fas fa-building"></i>
                                    <span>2. Pilih Kecamatan</span>
                                </div>
                                <div class="step-content" id="kecamatanOptions">
                                    <div class="empty-state">
                                        <i class="fas fa-building"></i>
                                        <div>Pilih kota terlebih dahulu</div>
                                    </div>
                                </div>
                            </div>

                            <!-- Desa Step -->
                            <div class="selection-step" id="stepDesa">
                                <div class="step-title">
                                    <i class="fas fa-home"></i>
                                    <span>3. Pilih Desa/Kelurahan</span>
                                </div>
                                <div class="step-content" id="desaOptions">
                                    <div class="empty-state">
                                        <i class="fas fa-home"></i>
                                        <div>Pilih kecamatan terlebih dahulu</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Selected Wilayah Info -->
                        <div class="selected-wilayah-info" id="selectedWilayahInfo">
                            <div class="selected-wilayah-badge">
                                <i class="fas fa-check"></i>
                                <span id="selectedWilayahText">Wilayah telah dipilih</span>
                            </div>
                        </div>
                    </div>

                    <!-- Hidden inputs for selected wilayah -->
                    <input type="hidden" name="kota" id="selectedKota">
                    <input type="hidden" name="kecamatan" id="selectedKecamatan">
                    <input type="hidden" name="desa" id="selectedDesa">

                    <!-- Kode Display -->
                    <div class="kode-display">
                        <div class="kode-row">
                            <span class="kode-label">Kode Wilayah:</span>
                            <span class="kode-value" id="kodeDisplay">-</span>
                        </div>
                        <div class="kode-row">
                            <span class="kode-label">Kode Lokasi:</span>
                            <span class="kode-value" id="kodeLokasiDisplay">-</span>
                        </div>
                    </div>
                    <input type="hidden" name="kode" id="kode">
                    <input type="hidden" name="kode_lokasi" id="kode_lokasi">
                </div>

                <!-- Data Lokasi Section -->
                <div class="section">
                    <h3 class="section-title">
                        <i class="fas fa-location-dot"></i> Data Lokasi
                    </h3>
                    
                    <div class="form-group">
                        <label for="nama_tempat">
                            <i class="fas fa-tag"></i> Nama Tempat *
                        </label>
                        <input type="text" 
                               id="nama_tempat" 
                               name="nama_tempat" 
                               required
                               placeholder="Masukkan nama tempat (contoh: Menara Sinyal, Pusat Desa, dll)">
                    </div>

                    <div class="form-group">
                        <label for="koordinat">
                            <i class="fas fa-map-pin"></i> Koordinat *
                        </label>
                        <input type="text" 
                               id="koordinat" 
                               name="koordinat" 
                               required
                               placeholder="Latitude,Longitude (otomatis terisi dari peta)">
                        <div class="coordinate-display" id="coordinateDisplay">
                            Klik peta untuk menentukan koordinat
                        </div>
                    </div>

                    <div class="location-buttons">
                        <button type="button" class="btn btn-primary" id="btnLokasiSaya">
                            <i class="fas fa-location-crosshairs"></i> Gunakan Lokasi Saya
                        </button>
                        <button type="button" class="btn btn-warning" onclick="clearCoordinates()">
                            <i class="fas fa-eraser"></i> Hapus Koordinat
                        </button>
                    </div>

                    <!-- Map -->
                    <div class="map-container">
                        <div id="map"></div>
                    </div>

                    <div class="form-group">
                        <label for="keterangan">
                            <i class="fas fa-file-text"></i> Keterangan
                        </label>
                        <textarea id="keterangan" 
                                  name="keterangan" 
                                  placeholder="Tambahkan keterangan tentang lokasi ini (opsional)"></textarea>
                    </div>
                </div>

                <!-- Data Sinyal Section -->
                <div class="section">
                    <h3 class="section-title">
                        <i class="fas fa-signal"></i> Data Sinyal
                    </h3>
                    
                    <div class="form-group">
                        <label for="ketersediaan_sinyal">
                            <i class="fas fa-wifi"></i> Ketersediaan Sinyal *
                        </label>
                        <select id="ketersediaan_sinyal" name="ketersediaan_sinyal" required>
                            <option value="Yes">Yes - Ada Sinyal</option>
                            <option value="No">No - Tidak Ada Sinyal</option>
                        </select>
                        <div class="signal-status">
                            <span class="status-indicator status-yes"></span>
                            <span>Status: <strong id="statusText">Ada Sinyal</strong></span>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="kecepatan_sinyal">
                            <i class="fas fa-tachometer-alt"></i> Kecepatan Sinyal (Mbps)
                        </label>
                        <input type="number" 
                               id="kecepatan_sinyal" 
                               name="kecepatan_sinyal" 
                               value="0"
                               min="0" 
                               step="0.1"
                               placeholder="Contoh: 10.5">
                        <p class="help-text">Isi dengan angka desimal (contoh: 5.5, 10.0, 25.5)</p>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="form-actions">
                    <button type="button" onclick="history.back()" class="btn btn-danger btn-lg">
                        <i class="fas fa-times"></i> Batal
                    </button>
                    <button type="submit" class="btn btn-success btn-lg pulse" id="submitBtn" disabled>
                        <i class="fas fa-plus-circle"></i> Tambah Lokasi
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // --- Hierarchical Selection Management ---
        const provinsiSelect = document.getElementById('provinsi');
        const selectedKotaInput = document.getElementById('selectedKota');
        const selectedKecamatanInput = document.getElementById('selectedKecamatan');
        const selectedDesaInput = document.getElementById('selectedDesa');
        const kodeDisplay = document.getElementById('kodeDisplay');
        const kodeLokasiDisplay = document.getElementById('kodeLokasiDisplay');
        const kodeInput = document.getElementById('kode');
        const kodeLokasiInput = document.getElementById('kode_lokasi');
        const selectedWilayahInfo = document.getElementById('selectedWilayahInfo');
        const selectedWilayahText = document.getElementById('selectedWilayahText');
        const submitBtn = document.getElementById('submitBtn');

        let selectedProvinsi = null;
        let selectedKota = null;
        let selectedKecamatan = null;
        let selectedDesa = null;

        // Fetch data untuk hierarchical selection
        async function fetchWilayahData(level, parentKode) {
            if (!parentKode) return [];
            
            try {
                const response = await fetch(`get_wilayah_by_level.php?level=${level}&parent_kode=${parentKode}`);
                const data = await response.json();
                return data;
            } catch (error) {
                console.error('Error fetching wilayah data:', error);
                return [];
            }
        }

        // Render options untuk hierarchical selection
        function renderOptions(data, level, clickHandler) {
            if (!data || data.length === 0) {
                return `
                    <div class="empty-state">
                        <i class="fas fa-${getLevelIcon(level)}"></i>
                        <div>Tidak ada data ${getLevelName(level)}</div>
                    </div>
                `;
            }

            let html = '<div class="parent-options">';
            data.forEach(item => {
                html += `
                    <div class="parent-option" onclick="${clickHandler}('${item.kode_wilayah}', '${item.nama.replace(/'/g, "\\'")}')">
                        ${item.nama}
                    </div>
                `;
            });
            html += '</div>';
            return html;
        }

        // Helper functions
        function getLevelIcon(level) {
            const icons = {
                'kota': 'city',
                'kecamatan': 'building',
                'desa': 'home'
            };
            return icons[level] || 'map-marker';
        }

        function getLevelName(level) {
            const names = {
                'kota': 'kota/kabupaten',
                'kecamatan': 'kecamatan',
                'desa': 'desa/kelurahan'
            };
            return names[level] || level;
        }

        // Event handlers untuk hierarchical selection
        async function selectKota(kode, nama) {
            selectedKota = { kode, nama };
            selectedKecamatan = null;
            selectedDesa = null;
            
            // Update UI
            updateSelectionUI('kota', kode, nama);
            document.querySelectorAll('#kotaOptions .parent-option').forEach(opt => {
                opt.classList.remove('selected');
            });
            event.target.classList.add('selected');
            
            // Load kecamatan data
            const kecamatanData = await fetchWilayahData('kecamatan', kode);
            document.getElementById('kecamatanOptions').innerHTML = renderOptions(kecamatanData, 'kecamatan', 'selectKecamatan');
            
            // Aktifkan step kecamatan
            document.getElementById('stepKecamatan').classList.add('active');
            
            // Reset steps selanjutnya
            document.getElementById('stepDesa').classList.remove('active');
            document.getElementById('desaOptions').innerHTML = `
                <div class="empty-state">
                    <i class="fas fa-home"></i>
                    <div>Pilih kecamatan terlebih dahulu</div>
                </div>
            `;
            
            updateKodeLokasi();
        }

        async function selectKecamatan(kode, nama) {
            selectedKecamatan = { kode, nama };
            selectedDesa = null;
            
            // Update UI
            updateSelectionUI('kecamatan', kode, nama);
            document.querySelectorAll('#kecamatanOptions .parent-option').forEach(opt => {
                opt.classList.remove('selected');
            });
            event.target.classList.add('selected');
            
            // Load desa data
            const desaData = await fetchWilayahData('desa', kode);
            document.getElementById('desaOptions').innerHTML = renderOptions(desaData, 'desa', 'selectDesa');
            
            // Aktifkan step desa
            document.getElementById('stepDesa').classList.add('active');
            
            updateKodeLokasi();
        }

        function selectDesa(kode, nama) {
            selectedDesa = { kode, nama };
            
            // Update UI
            updateSelectionUI('desa', kode, nama);
            document.querySelectorAll('#desaOptions .parent-option').forEach(opt => {
                opt.classList.remove('selected');
            });
            event.target.classList.add('selected');
            
            updateKodeLokasi();
        }

        // Update UI untuk selected wilayah
        function updateSelectionUI(level, kode, nama) {
            // Update hidden inputs
            if (level === 'kota') {
                selectedKotaInput.value = kode;
                selectedKecamatanInput.value = '';
                selectedDesaInput.value = '';
            } else if (level === 'kecamatan') {
                selectedKecamatanInput.value = kode;
                selectedDesaInput.value = '';
            } else if (level === 'desa') {
                selectedDesaInput.value = kode;
            }
            
            // Update info text
            let text = '';
            if (selectedKota) text += selectedKota.nama;
            if (selectedKecamatan) text += ' → ' + selectedKecamatan.nama;
            if (selectedDesa) text += ' → ' + selectedDesa.nama;
            
            selectedWilayahText.textContent = text || 'Wilayah telah dipilih';
            selectedWilayahInfo.classList.add('active');
            
            // Enable submit button jika minimal kota sudah dipilih
            submitBtn.disabled = !selectedKota;
        }

        // Update kode lokasi
        function updateKodeLokasi() {
            let kode = '';
            if (selectedDesa) kode = selectedDesa.kode;
            else if (selectedKecamatan) kode = selectedKecamatan.kode;
            else if (selectedKota) kode = selectedKota.kode;
            else if (selectedProvinsi) kode = selectedProvinsi.kode;

            if (kode) {
                kodeDisplay.textContent = kode;
                kodeInput.value = kode;
                
                fetch(`get_next_kode_lokasi.php?prefix=${encodeURIComponent(kode)}`)
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            kodeLokasiDisplay.textContent = data.kode_lokasi;
                            kodeLokasiInput.value = data.kode_lokasi;
                        } else {
                            kodeLokasiDisplay.textContent = '-';
                            kodeLokasiInput.value = '';
                        }
                    })
                    .catch(err => {
                        console.error('Error fetching kode lokasi:', err);
                        kodeLokasiDisplay.textContent = '-';
                        kodeLokasiInput.value = '';
                    });
            } else {
                kodeDisplay.textContent = '-';
                kodeLokasiDisplay.textContent = '-';
                kodeInput.value = '';
                kodeLokasiInput.value = '';
            }
        }

        // Event listener untuk provinsi
        provinsiSelect.addEventListener('change', async function() {
            const provinsiKode = this.value;
            selectedProvinsi = { kode: provinsiKode, nama: this.options[this.selectedIndex].text };
            
            if (provinsiKode) {
                // Load kota data
                const kotaData = await fetchWilayahData('kota', provinsiKode);
                document.getElementById('kotaOptions').innerHTML = renderOptions(kotaData, 'kota', 'selectKota');
                
                // Aktifkan step kota
                document.getElementById('stepKota').classList.add('active');
                
                // Reset selections
                selectedKota = null;
                selectedKecamatan = null;
                selectedDesa = null;
                selectedKotaInput.value = '';
                selectedKecamatanInput.value = '';
                selectedDesaInput.value = '';
                selectedWilayahInfo.classList.remove('active');
                
                // Reset steps selanjutnya
                document.getElementById('stepKecamatan').classList.remove('active');
                document.getElementById('stepDesa').classList.remove('active');
                document.getElementById('kecamatanOptions').innerHTML = `
                    <div class="empty-state">
                        <i class="fas fa-building"></i>
                        <div>Pilih kota terlebih dahulu</div>
                    </div>
                `;
                document.getElementById('desaOptions').innerHTML = `
                    <div class="empty-state">
                        <i class="fas fa-home"></i>
                        <div>Pilih kecamatan terlebih dahulu</div>
                    </div>
                `;
                
                updateKodeLokasi();
            }
        });

        // --- Map Configuration ---
        const map = L.map('map').setView([5.55, 95.32], 12);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors',
            maxZoom: 19
        }).addTo(map);

        let marker = null;

        function setMarker(lat, lon) {
            if (marker) map.removeLayer(marker);
            marker = L.marker([lat, lon]).addTo(map);
            const coordString = lat.toFixed(6) + "," + lon.toFixed(6);
            document.getElementById('koordinat').value = coordString;
            document.getElementById('coordinateDisplay').textContent = coordString;
            
            // Add visual feedback
            const display = document.getElementById('coordinateDisplay');
            display.style.animation = 'pulse 0.5s ease-in-out';
            setTimeout(() => {
                display.style.animation = '';
            }, 500);
        }

        // Map click event
        map.on('click', function(e) {
            setMarker(e.latlng.lat, e.latlng.lng);
        });

        // Get user location
        function getUserLocation() {
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(
                    pos => {
                        const lat = pos.coords.latitude;
                        const lon = pos.coords.longitude;
                        map.setView([lat, lon], 16);
                        setMarker(lat, lon);
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

        // Clear coordinates
        function clearCoordinates() {
            if (confirm("Hapus koordinat saat ini?")) {
                document.getElementById('koordinat').value = '';
                document.getElementById('coordinateDisplay').textContent = 'Klik peta untuk menentukan koordinat';
                if (marker) map.removeLayer(marker);
                marker = null;
            }
        }

        // Event listeners
        document.getElementById('btnLokasiSaya').addEventListener('click', getUserLocation);

        // Geocoder control
        L.Control.geocoder({ 
            defaultMarkGeocode: false,
            placeholder: 'Cari alamat...',
            errorMessage: 'Alamat tidak ditemukan.'
        })
        .on('markgeocode', function(e) {
            const latlng = e.geocode.center;
            map.setView(latlng, 16);
            setMarker(latlng.lat, latlng.lng);
        })
        .addTo(map);

        // Update signal status indicator
        document.getElementById('ketersediaan_sinyal').addEventListener('change', function(e) {
            const statusIndicator = document.querySelector('.status-indicator');
            const statusText = document.getElementById('statusText');
            
            statusIndicator.className = 'status-indicator status-' + e.target.value.toLowerCase();
            statusText.textContent = e.target.value === 'Yes' ? 'Ada Sinyal' : 'Tidak Ada Sinyal';
        });

        // Form validation
        document.getElementById('tambahLokasiForm').addEventListener('submit', function(e) {
            const koordinat = document.getElementById('koordinat').value;
            if (!koordinat) {
                e.preventDefault();
                alert('Harap tentukan koordinat lokasi dengan mengklik peta atau menggunakan "Gunakan Lokasi Saya".');
                document.getElementById('map').scrollIntoView({ behavior: 'smooth' });
                return false;
            }
            
            if (!selectedKota) {
                e.preventDefault();
                alert('Harap pilih minimal kota/kabupaten untuk lokasi ini.');
                document.getElementById('stepKota').scrollIntoView({ behavior: 'smooth' });
                return false;
            }
            
            if (!confirm('Tambahkan lokasi baru?')) {
                e.preventDefault();
                return false;
            }
        });

        // Real-time coordinate validation
        document.getElementById('koordinat').addEventListener('input', function(e) {
            const value = e.target.value;
            const coordRegex = /^-?\d+\.?\d*,-?\d+\.?\d*$/;
            
            if (value && coordRegex.test(value)) {
                const parts = value.split(',');
                const lat = parseFloat(parts[0]);
                const lon = parseFloat(parts[1]);
                
                if (lat >= -90 && lat <= 90 && lon >= -180 && lon <= 180) {
                    setMarker(lat, lon);
                }
            }
        });

        // Initialize if provinsi is pre-selected
        <?php if ($provinsiParam): ?>
            document.addEventListener('DOMContentLoaded', function() {
                // Trigger change event untuk provinsi yang sudah dipilih
                const event = new Event('change');
                provinsiSelect.dispatchEvent(event);
            });
        <?php endif; ?>
    </script>
</body>
</html>