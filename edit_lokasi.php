<?php
include "config/db.php";

// Ambil kode lokasi dari URL
if (!isset($_GET['kode_lokasi'])) {
    die("Kode lokasi tidak ditemukan!");
}
$kode_lokasi = $_GET['kode_lokasi'];

// Ambil data lokasi
$sqlLokasi = "SELECT * FROM lokasi WHERE kode_lokasi='$kode_lokasi'";
$lokasi = $conn->query($sqlLokasi)->fetch_assoc();

if (!$lokasi) {
    die("Data lokasi tidak ditemukan!");
}

// Ambil data wilayah berdasarkan kode_wilayah dari lokasi
$kode_wilayah = $lokasi['kode_wilayah'];
$sqlWilayah = "SELECT * FROM wilayah WHERE kode_wilayah='$kode_wilayah'";
$wilayah = $conn->query($sqlWilayah)->fetch_assoc();

// Ambil hirarki wilayah sampai ke provinsi
function getWilayahHierarchy($conn, $kode_wilayah) {
    $hierarchy = [];
    while ($kode_wilayah) {
        $sql = "SELECT * FROM wilayah WHERE kode_wilayah='$kode_wilayah'";
        $result = $conn->query($sql)->fetch_assoc();
        if ($result) {
            $hierarchy[] = $result;
            $kode_wilayah = $result['parent_kode']; // naik ke parent
        } else {
            break;
        }
    }
    return array_reverse($hierarchy); // urut dari provinsi → desa
}

$hierarchy = getWilayahHierarchy($conn, $wilayah['kode_wilayah']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Edit Lokasi - <?php echo htmlspecialchars($lokasi['nama_tempat']); ?></title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
<link rel="stylesheet" href="https://unpkg.com/leaflet-control-geocoder/dist/Control.Geocoder.css"/>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/leaflet-control-geocoder/dist/Control.Geocoder.js"></script>
<link rel="stylesheet" href="css/edit_lokasi.css">
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header fade-in">
            <h1 class="page-title"><i class="fas fa-edit"></i> Edit Data Lokasi</h1>
            <p class="page-subtitle"><?php echo htmlspecialchars($lokasi['nama_tempat']); ?></p>
        </div>

        <!-- Info tentang perubahan -->
        <div class="alert alert-info fade-in">
            <i class="fas fa-info-circle"></i> 
            <strong>Info:</strong> Perubahan akan tercatat dalam riwayat sistem. Pastikan hanya mengubah data yang diperlukan.
        </div>

        <!-- Form Container -->
        <div class="form-container fade-in">
            <form action="proses/proses_edit_lokasi.php" method="post" id="editForm">
                <input type="hidden" name="kode_lokasi" value="<?php echo $lokasi['kode_lokasi']; ?>">
                <input type="hidden" name="kode_wilayah" value="<?php echo $wilayah['kode_wilayah']; ?>">

                <!-- Data Wilayah Section -->
                <div class="section">
                    <h3 class="section-title">
                        <i class="fas fa-map-marker-alt"></i> Data Wilayah
                    </h3>
                    <div class="hierarchy-grid">
                        <?php foreach ($hierarchy as $h): ?>
                            <div class="hierarchy-item">
                                <div class="hierarchy-label">
                                    <?php echo ucfirst($h['level']); ?> 
                                    <small>(<?php echo $h['kode_wilayah']; ?>)</small>
                                </div>
                                <div class="hierarchy-value"><?php echo htmlspecialchars($h['nama']); ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <p class="help-text">Wilayah tidak dapat diubah dari halaman ini</p>
                </div>

                <!-- Data Lokasi Section -->
                <div class="section">
                    <h3 class="section-title">
                        <i class="fas fa-location-dot"></i> Data Lokasi
                    </h3>
                    
                    <div class="form-group">
                        <label for="nama_tempat">Nama Tempat *</label>
                        <input type="text" 
                               id="nama_tempat" 
                               name="nama_tempat" 
                               value="<?php echo htmlspecialchars($lokasi['nama_tempat']); ?>" 
                               required
                               placeholder="Masukkan nama tempat...">
                    </div>

                    <div class="form-group">
                        <label for="koordinat">Koordinat *</label>
                        <input type="text" 
                               id="koordinat" 
                               name="koordinat" 
                               value="<?php echo htmlspecialchars($lokasi['koordinat']); ?>"
                               required
                               placeholder="Latitude,Longitude">
                        <div class="coordinate-display" id="coordinateDisplay">
                            <?php echo htmlspecialchars($lokasi['koordinat']); ?>
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
                        <label for="keterangan">Keterangan</label>
                        <textarea id="keterangan" 
                                  name="keterangan" 
                                  placeholder="Tambahkan keterangan tentang lokasi ini..."><?php echo htmlspecialchars($lokasi['keterangan']); ?></textarea>
                    </div>
                </div>

                <!-- Data Sinyal Section -->
                <div class="section">
                    <h3 class="section-title">
                        <i class="fas fa-signal"></i> Data Sinyal
                    </h3>
                    
                    <div class="form-group">
                        <label for="ketersediaan_sinyal">Ketersediaan Sinyal *</label>
                        <select id="ketersediaan_sinyal" name="ketersediaan_sinyal" required>
                            <option value="Yes" <?php if($lokasi['ketersediaan_sinyal']=="Yes") echo "selected"; ?>>Yes - Ada Sinyal</option>
                            <option value="No" <?php if($lokasi['ketersediaan_sinyal']=="No") echo "selected"; ?>>No - Tidak Ada Sinyal</option>
                        </select>
                        <div class="signal-status">
                            <span class="status-indicator status-<?php echo strtolower($lokasi['ketersediaan_sinyal']); ?>"></span>
                            <span>Status saat ini: <strong><?php echo $lokasi['ketersediaan_sinyal']; ?></strong></span>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="kecepatan_sinyal">Kecepatan Sinyal (Mbps)</label>
                        <input type="number" 
                               id="kecepatan_sinyal" 
                               name="kecepatan_sinyal" 
                               value="<?php echo $lokasi['kecepatan_sinyal']; ?>"
                               step="0.1" 
                               min="0" 
                               placeholder="Contoh: 10.5">
                        <p class="help-text">Isi dengan angka desimal (contoh: 5.5, 10.0, 25.5)</p>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="form-actions">
                    <a href="hasil_pemetaan.php" class="btn btn-danger">
                        <i class="fas fa-times"></i> Batal
                    </a>
                    <button type="submit" class="btn btn-success pulse">
                        <i class="fas fa-save"></i> Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // --- MAP Configuration ---
        const defaultCoord = "<?php echo $lokasi['koordinat']; ?>";
        let lat = 5.55, lon = 95.32; // default Aceh
        
        if(defaultCoord){
            const parts = defaultCoord.split(',');
            if(parts.length===2){
                lat = parseFloat(parts[0]);
                lon = parseFloat(parts[1]);
            }
        }

        // Initialize map
        const map = L.map('map').setView([lat, lon], 13);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors'
        }).addTo(map);

        let marker = L.marker([lat, lon]).addTo(map);

        // Update marker and coordinates
        function setMarker(lat, lon){
            if(marker) map.removeLayer(marker);
            marker = L.marker([lat, lon]).addTo(map);
            const coordString = lat.toFixed(6) + "," + lon.toFixed(6);
            document.getElementById('koordinat').value = coordString;
            document.getElementById('coordinateDisplay').textContent = coordString;
            
            // Add pulse animation to coordinate display
            const display = document.getElementById('coordinateDisplay');
            display.style.animation = 'pulse 0.5s ease-in-out';
            setTimeout(() => {
                display.style.animation = '';
            }, 500);
        }

        // Map click event
        map.on('click', function(e){
            setMarker(e.latlng.lat, e.latlng.lng);
        });

        // Get user location
        function getUserLocation(){
            if(navigator.geolocation){
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
                    { enableHighAccuracy:true, timeout:15000, maximumAge:0 }
                );
            } else {
                alert("Browser tidak mendukung Geolocation API.");
            }
        }

        // Clear coordinates
        function clearCoordinates(){
            if(confirm("Hapus koordinat saat ini?")){
                document.getElementById('koordinat').value = '';
                document.getElementById('coordinateDisplay').textContent = '-';
                if(marker) map.removeLayer(marker);
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

        // Update signal status indicator when selection changes
        document.getElementById('ketersediaan_sinyal').addEventListener('change', function(e) {
            const statusIndicator = document.querySelector('.status-indicator');
            const statusText = document.querySelector('.signal-status strong');
            
            statusIndicator.className = 'status-indicator status-' + e.target.value.toLowerCase();
            statusText.textContent = e.target.value;
        });

        // Form validation
        document.getElementById('editForm').addEventListener('submit', function(e) {
            const koordinat = document.getElementById('koordinat').value;
            if (!koordinat) {
                e.preventDefault();
                alert('Harap tentukan koordinat lokasi dengan mengklik peta atau menggunakan "Gunakan Lokasi Saya".');
                document.getElementById('map').scrollIntoView({ behavior: 'smooth' });
                return false;
            }
            
            if (!confirm('Simpan perubahan data lokasi?')) {
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
    </script>
</body>
</html>