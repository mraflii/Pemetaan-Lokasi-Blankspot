<?php
include "config/db.php";

// --- Ambil parent kode jika ada (dari tombol tambah wilayah di blok) ---
$parentKode = $conn->real_escape_string($_GET['kode_wilayah'] ?? '');
$parentLevel = '';
$provDefault = '';
$kotaDefault = '';
$kecamatanDefault = '';

if ($parentKode) {
    $res = $conn->query("SELECT level, parent_kode FROM wilayah WHERE kode_wilayah='$parentKode' LIMIT 1");
    if ($res && $res->num_rows > 0) {
        $row = $res->fetch_assoc();
        $parentLevel = $row['level'];
        $parent_parent = $row['parent_kode'];

        if ($parentLevel === 'provinsi') {
            $provDefault = $parentKode;
        } elseif ($parentLevel === 'kota') {
            $kotaDefault = $parentKode;
            $provDefault = $parent_parent;
        } elseif ($parentLevel === 'kecamatan') {
            $kecamatanDefault = $parentKode;
            $kotaDefault = $parent_parent;
            if ($kotaDefault) {
                $r2 = $conn->query("SELECT parent_kode FROM wilayah WHERE kode_wilayah='" . $conn->real_escape_string($kotaDefault) . "' LIMIT 1");
                if ($r2 && $r2->num_rows > 0) {
                    $provDefault = $r2->fetch_assoc()['parent_kode'];
                }
            }
        } elseif ($parentLevel === 'desa') {
            $kecamatanDefault = $parentKode;
            $kotaDefault = $parent_parent;
            if ($kotaDefault) {
                $r2 = $conn->query("SELECT parent_kode FROM wilayah WHERE kode_wilayah='" . $conn->real_escape_string($kotaDefault) . "' LIMIT 1");
                if ($r2 && $r2->num_rows > 0) {
                    $provDefault = $r2->fetch_assoc()['parent_kode'];
                }
            }
        }
    }
}

$namaProv = '';
if ($provDefault) {
    $res = $conn->query("SELECT nama FROM wilayah WHERE kode_wilayah='$provDefault' LIMIT 1");
    if ($res && $res->num_rows > 0) {
        $namaProv = $res->fetch_assoc()['nama'];
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Tambah Wilayah Baru</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
<link rel="stylesheet" href="css/create_wilayah.css">
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header fade-in">
            <h1 class="page-title"><i class="fas fa-plus-circle"></i> Tambah Wilayah Baru</h1>
            <p class="page-subtitle">Lengkapi data wilayah baru di bawah ini</p>
        </div>

        <!-- Form Container -->
        <div class="form-container fade-in">
            <?php if ($parentKode): ?>
            <div class="hierarchy-path">
                <div class="hierarchy-title">
                    <i class="fas fa-sitemap"></i> Hierarki Wilayah Induk
                </div>
                <div class="hierarchy-items">
                    <?php
                    $hierarchy = [];
                    $currentKode = $parentKode;
                    
                    while ($currentKode) {
                        $res = $conn->query("SELECT kode_wilayah, nama, level FROM wilayah WHERE kode_wilayah='$currentKode'");
                        if ($res && $res->num_rows > 0) {
                            $row = $res->fetch_assoc();
                            $hierarchy[] = $row;
                            $currentKode = $row['level'] !== 'provinsi' ? $row['parent_kode'] : null;
                        } else {
                            break;
                        }
                    }
                    
                    $hierarchy = array_reverse($hierarchy);
                    foreach ($hierarchy as $index => $item):
                    ?>
                        <div class="hierarchy-item">
                            <span class="badge level-<?php echo $item['level']; ?>">
                                <?php echo ucfirst($item['level']); ?>
                            </span>
                            <span><?php echo htmlspecialchars($item['nama']); ?></span>
                            <?php if ($index < count($hierarchy) - 1): ?>
                                <span class="hierarchy-arrow"><i class="fas fa-chevron-right"></i></span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <form action="proses/proses_tambah_wilayah.phpW" method="post" id="formWilayah">
                <!-- Level Selection -->
                <div class="form-group">
                    <label for="level">
                        <i class="fas fa-layer-group"></i> Level Wilayah
                        <span class="level-badge" id="levelBadge">Pilih level</span>
                    </label>
                    <select name="level" id="level" required>
                        <option value="">-- Pilih Level Wilayah --</option>
                        <option value="kota">Kota/Kabupaten</option>
                        <option value="kecamatan">Kecamatan</option>
                        <option value="desa">Desa/Kelurahan</option>
                    </select>
                    <p class="help-text">Pilih tingkat wilayah yang ingin ditambahkan</p>
                </div>

                <!-- Parent Selection (dynamic) -->
                <div class="form-group" id="provinsiGroup" style="display:none;">
                    <label for="provinsi">
                        <i class="fas fa-map"></i> Provinsi
                    </label>
                    <input type="hidden" name="provinsi" id="provinsi" value="<?php echo $provDefault; ?>">
                    <input type="text" value="<?php echo htmlspecialchars($namaProv); ?>" readonly>
                </div>

                <div class="form-group" id="kotaGroup" style="display:none;">
                    <label for="kota">
                        <i class="fas fa-city"></i> Kota/Kabupaten
                    </label>
                    <select name="kota" id="kota">
                        <option value="">-- Pilih Kota/Kabupaten --</option>
                    </select>
                    <p class="help-text" id="kotaHelp">Pilih kota/kabupaten sebagai induk</p>
                </div>

                <div class="form-group" id="kecamatanGroup" style="display:none;">
                    <label for="kecamatan">
                        <i class="fas fa-building"></i> Kecamatan
                    </label>
                    <select name="kecamatan" id="kecamatan">
                        <option value="">-- Pilih Kecamatan --</option>
                    </select>
                    <p class="help-text" id="kecamatanHelp">Pilih kecamatan sebagai induk</p>
                </div>

                <!-- Kode dan Nama Wilayah -->
                <div class="form-group">
                    <label for="kode_wilayah" id="labelKode">
                        <i class="fas fa-code"></i> Kode Wilayah
                    </label>
                    <input type="text" 
                           name="kode_wilayah" 
                           id="kode_wilayah" 
                           placeholder="Masukkan kode wilayah atau gunakan saran otomatis" 
                           required>
                    <div class="kode-actions">
                        <button type="button" class="kode-btn" onclick="generateKode()">
                            <i class="fas fa-magic"></i> Generate Kode Otomatis
                        </button>
                        <button type="button" class="kode-btn" onclick="suggestKode()">
                            <i class="fas fa-lightbulb"></i> Lihat Saran Kode
                        </button>
                        <button type="button" class="kode-btn" onclick="clearKode()">
                            <i class="fas fa-times"></i> Hapus Kode
                        </button>
                    </div>
                    <p class="help-text" id="kodeHelp">
                        Masukkan kode wilayah unik. Gunakan tombol di atas untuk bantuan.
                        <br><strong>Format:</strong> [Kode Parent].[Kode Unik] (contoh: 11.01.01.2001)
                    </p>
                </div>

                <div class="form-group">
                    <label for="nama" id="labelNama">
                        <i class="fas fa-tag"></i> Nama Wilayah
                    </label>
                    <input type="text" 
                           name="nama" 
                           id="nama" 
                           placeholder="Masukkan nama wilayah baru..." 
                           required>
                    <p class="help-text" id="namaHelp">Masukkan nama wilayah sesuai dengan level yang dipilih</p>
                </div>

                <!-- Form Actions -->
                <div class="form-actions">
                    <button type="button" onclick="history.back()" class="btn btn-danger">
                        <i class="fas fa-arrow-left"></i> Kembali
                    </button>
                    <button type="button" onclick="validateForm()" class="btn btn-secondary">
                        <i class="fas fa-check-circle"></i> Validasi Data
                    </button>
                    <button type="submit" class="btn btn-success pulse">
                        <i class="fas fa-plus-circle"></i> Tambah Wilayah Baru
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Elements
        const levelSelect = document.getElementById('level');
        const provinsiGroup = document.getElementById('provinsiGroup');
        const kotaGroup = document.getElementById('kotaGroup');
        const kecamatanGroup = document.getElementById('kecamatanGroup');
        const levelBadge = document.getElementById('levelBadge');

        const provinsiSelect = document.getElementById('provinsi');
        const kotaSelect = document.getElementById('kota');
        const kecamatanSelect = document.getElementById('kecamatan');
        const kodeWilayahInput = document.getElementById('kode_wilayah');

        const labelKode = document.getElementById('labelKode');
        const labelNama = document.getElementById('labelNama');
        const kodeHelp = document.getElementById('kodeHelp');
        const namaHelp = document.getElementById('namaHelp');
        const kotaHelp = document.getElementById('kotaHelp');
        const kecamatanHelp = document.getElementById('kecamatanHelp');

        // Data from PHP
        const parentKode = "<?php echo $parentKode; ?>";
        const parentLevel = "<?php echo $parentLevel; ?>";
        const provDefault = "<?php echo $provDefault; ?>";
        const kotaDefault = "<?php echo $kotaDefault; ?>";
        const kecDefault = "<?php echo $kecamatanDefault; ?>";

        // Level configuration
        const levelConfig = {
            kota: { 
                badge: 'Kota/Kabupaten', 
                kodeLabel: 'Kode Kota/Kabupaten', 
                namaLabel: 'Nama Kota/Kabupaten',
                kodeHelp: 'Kode kota/kabupaten akan digenerate otomatis berdasarkan provinsi',
                namaHelp: 'Masukkan nama kota atau kabupaten (contoh: Banda Aceh, Aceh Besar)'
            },
            kecamatan: { 
                badge: 'Kecamatan', 
                kodeLabel: 'Kode Kecamatan', 
                namaLabel: 'Nama Kecamatan',
                kodeHelp: 'Kode kecamatan akan digenerate otomatis berdasarkan kota/kabupaten',
                namaHelp: 'Masukkan nama kecamatan (contoh: Kuta Alam, Meuraxa)'
            },
            desa: { 
                badge: 'Desa/Kelurahan', 
                kodeLabel: 'Kode Desa/Kelurahan', 
                namaLabel: 'Nama Desa/Kelurahan',
                kodeHelp: 'Kode desa/kelurahan akan digenerate otomatis berdasarkan kecamatan',
                namaHelp: 'Masukkan nama desa atau kelurahan (contoh: Lampuuk, Peunayong)'
            }
        };

        // Initialize based on parent level
        function initializeForm() {
            if (parentLevel && parentLevel !== 'provinsi') {
                const nextLevel = getNextLevel(parentLevel);
                if (nextLevel) {
                    levelSelect.value = nextLevel;
                    triggerLevelChange();
                    
                    // Auto-select parent values
                    if (provDefault) {
                        setTimeout(() => {
                            if (kotaDefault && (nextLevel === 'kecamatan' || nextLevel === 'desa')) {
                                fetchChild('kota', provDefault, kotaSelect, 'Pilih Kota', () => {
                                    kotaSelect.value = kotaDefault;
                                    if (kecDefault && nextLevel === 'desa') {
                                        fetchChild('kecamatan', kotaDefault, kecamatanSelect, 'Pilih Kecamatan', () => {
                                            kecamatanSelect.value = kecDefault;
                                            suggestKode();
                                        });
                                    } else {
                                        suggestKode();
                                    }
                                });
                            } else {
                                suggestKode();
                            }
                        }, 100);
                    }
                }
            }
        }

        function getNextLevel(currentLevel) {
            const levels = ['provinsi', 'kota', 'kecamatan', 'desa'];
            const currentIndex = levels.indexOf(currentLevel);
            return levels[currentIndex + 1] || null;
        }

        function triggerLevelChange() {
            const event = new Event('change');
            levelSelect.dispatchEvent(event);
        }

        // Dropdown management
        function resetDropdown(dropdown, placeholder) {
            dropdown.innerHTML = `<option value="">-- ${placeholder} --</option>`;
        }

        function fetchChild(level, parentKode, dropdown, placeholder, callback) {
            resetDropdown(dropdown, placeholder);
            if (!parentKode) {
                dropdown.disabled = true;
                if (callback) callback();
                return;
            }

            dropdown.disabled = true;
            dropdown.classList.add('loading');

            fetch(`get_wilayah_by_level.php?level=${encodeURIComponent(level)}&parent_kode=${encodeURIComponent(parentKode)}`)
                .then(res => res.json())
                .then(data => {
                    resetDropdown(dropdown, placeholder);
                    if (data.length > 0) {
                        data.forEach(item => {
                            const option = document.createElement('option');
                            option.value = item.kode_wilayah;
                            option.textContent = item.nama;
                            dropdown.appendChild(option);
                        });
                    } else {
                        const option = document.createElement('option');
                        option.value = '';
                        option.textContent = '-- Tidak ada data --';
                        dropdown.appendChild(option);
                    }
                    dropdown.disabled = false;
                    dropdown.classList.remove('loading');
                    if (callback) callback();
                })
                .catch(err => {
                    console.error('Fetch error:', err);
                    resetDropdown(dropdown, placeholder);
                    const option = document.createElement('option');
                    option.value = '';
                    option.textContent = '-- Error memuat data --';
                    dropdown.appendChild(option);
                    dropdown.disabled = false;
                    dropdown.classList.remove('loading');
                    if (callback) callback();
                });
        }

        // Kode generation functions
        function generateKode() {
            const level = levelSelect.value;
            let prefix = getParentPrefix();
            
            if (!prefix) {
                alert('Pilih level dan parent terlebih dahulu untuk generate kode otomatis');
                return;
            }

            // Generate unique code (timestamp based for uniqueness)
            const timestamp = Date.now().toString().slice(-4);
            const randomNum = Math.floor(Math.random() * 90) + 10; // 10-99
            
            let suffix = '';
            if (level === 'kota') {
                suffix = (parseInt(timestamp.slice(-2)) + 1).toString().padStart(2, '0');
            } else if (level === 'kecamatan') {
                suffix = (parseInt(randomNum.toString().slice(0,2)) + 1).toString().padStart(2, '0');
            } else if (level === 'desa') {
                suffix = (parseInt(randomNum) + 1).toString().padStart(4, '0');
            }

            kodeWilayahInput.value = prefix + suffix;
            kodeWilayahInput.classList.add('suggested');
        }

        function suggestKode() {
            const level = levelSelect.value;
            const prefix = getParentPrefix();
            
            if (!prefix) {
                alert('Pilih level dan parent terlebih dahulu untuk melihat saran kode');
                return;
            }

            let examples = [];
            if (level === 'kota') {
                examples = ['01', '02', '03', '04', '05'];
            } else if (level === 'kecamatan') {
                examples = ['01', '02', '03', '04', '05'];
            } else if (level === 'desa') {
                examples = ['0001', '0002', '0003', '0004', '0005'];
            }

            const suggestions = examples.map(example => prefix + example).join(', ');
            alert(`Saran kode untuk ${levelConfig[level].badge}:\n${suggestions}\n\nAnda bisa menggunakan salah satu contoh di atas atau membuat kode sendiri.`);
        }

        function clearKode() {
            kodeWilayahInput.value = '';
            kodeWilayahInput.classList.remove('suggested');
            kodeWilayahInput.focus();
        }

        function getParentPrefix() {
            const level = levelSelect.value;
            let prefix = "";

            if (level === "kota" && provinsiSelect.value) {
                prefix = provinsiSelect.value + ".";
            } else if (level === "kecamatan" && kotaSelect.value) {
                prefix = kotaSelect.value + ".";
            } else if (level === "desa" && kecamatanSelect.value) {
                prefix = kecamatanSelect.value + ".";
            }

            return prefix;
        }

        // Update labels and help texts
        function updateLabels(level) {
            const config = levelConfig[level];
            if (config) {
                levelBadge.textContent = config.badge;
                labelKode.innerHTML = `<i class="fas fa-code"></i> ${config.kodeLabel}`;
                labelNama.innerHTML = `<i class="fas fa-tag"></i> ${config.namaLabel}`;
                kodeHelp.textContent = config.kodeHelp;
                namaHelp.textContent = config.namaHelp;
                
                if (level === 'kecamatan') {
                    kotaHelp.textContent = 'Pilih kota/kabupaten sebagai induk kecamatan';
                } else if (level === 'desa') {
                    kecamatanHelp.textContent = 'Pilih kecamatan sebagai induk desa/kelurahan';
                }
            }
        }

        // Form validation
        function validateForm() {
            const level = levelSelect.value;
            const kode = kodeWilayahInput.value.trim();
            const nama = document.getElementById('nama').value.trim();

            let errors = [];

            if (!level) {
                errors.push('• Pilih level wilayah terlebih dahulu');
            }

            if (!kode) {
                errors.push('• Masukkan kode wilayah');
            } else if (!/^[0-9.]+$/.test(kode)) {
                errors.push('• Kode wilayah hanya boleh berisi angka dan titik');
            }

            if (!nama) {
                errors.push('• Masukkan nama wilayah');
            }

            // Check kode format based on level
            if (kode && level) {
                const prefix = getParentPrefix();
                if (prefix && !kode.startsWith(prefix)) {
                    errors.push(`• Kode wilayah harus diawali dengan "${prefix}"`);
                }
            }

            if (errors.length > 0) {
                alert('Perbaiki kesalahan berikut:\n\n' + errors.join('\n'));
                return false;
            } else {
                alert('✅ Data valid!\n\nLevel: ' + levelConfig[level].badge + 
                      '\nKode: ' + kode + 
                      '\nNama: ' + nama +
                      '\n\nKlik "Tambah Wilayah Baru" untuk menyimpan.');
                return true;
            }
        }

        // Check if kode already exists
        function checkKodeExists(kode) {
            return fetch(`check_kode_wilayah.php?kode=${encodeURIComponent(kode)}`)
                .then(res => res.json())
                .then(data => data.exists)
                .catch(err => {
                    console.error('Error checking kode:', err);
                    return false;
                });
        }

        // Event: Level change
        levelSelect.addEventListener('change', function() {
            const level = this.value;
            
            // Reset all groups
            provinsiGroup.style.display = "none";
            kotaGroup.style.display = "none";
            kecamatanGroup.style.display = "none";

            // Show relevant groups
            if (level === "kota") {
                provinsiGroup.style.display = "block";
            } else if (level === "kecamatan") {
                provinsiGroup.style.display = "block";
                kotaGroup.style.display = "block";
                if (provinsiSelect.value) {
                    fetchChild('kota', provinsiSelect.value, kotaSelect, 'Pilih Kota');
                }
            } else if (level === "desa") {
                provinsiGroup.style.display = "block";
                kotaGroup.style.display = "block";
                kecamatanGroup.style.display = "block";
                if (provinsiSelect.value) {
                    fetchChild('kota', provinsiSelect.value, kotaSelect, 'Pilih Kota', () => {
                        if (kotaSelect.value) {
                            fetchChild('kecamatan', kotaSelect.value, kecamatanSelect, 'Pilih Kecamatan');
                        }
                    });
                }
            }

            updateLabels(level);
            kodeWilayahInput.placeholder = "Masukkan kode wilayah atau gunakan tombol generate";
        });

        // Event: Parent changes
        provinsiSelect.addEventListener('change', function() {
            const val = this.value;
            if (val && (levelSelect.value === "kecamatan" || levelSelect.value === "desa")) {
                fetchChild('kota', val, kotaSelect, 'Pilih Kota', () => {
                    if (kotaDefault) kotaSelect.value = kotaDefault;
                    resetDropdown(kecamatanSelect, 'Pilih Kecamatan');
                });
                kotaGroup.style.display = "block";
            } else {
                resetDropdown(kotaSelect, 'Pilih Kota');
                resetDropdown(kecamatanSelect, 'Pilih Kecamatan');
                kotaGroup.style.display = (levelSelect.value === "kecamatan" || levelSelect.value === "desa") ? "block" : "none";
                kecamatanGroup.style.display = "none";
            }
        });

        kotaSelect.addEventListener('change', function() {
            const val = this.value;
            if (val && levelSelect.value === "desa") {
                fetchChild('kecamatan', val, kecamatanSelect, 'Pilih Kecamatan', () => {
                    if (kecDefault) kecamatanSelect.value = kecDefault;
                });
                kecamatanGroup.style.display = "block";
            } else {
                resetDropdown(kecamatanSelect, 'Pilih Kecamatan');
                kecamatanGroup.style.display = (levelSelect.value === "desa") ? "block" : "none";
            }
        });

        // Form submission
        document.getElementById('formWilayah').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            if (!validateForm()) {
                return;
            }

            const kode = kodeWilayahInput.value.trim();
            
            // Check if kode already exists
            const kodeExists = await checkKodeExists(kode);
            if (kodeExists) {
                if (!confirm(`Kode wilayah "${kode}" sudah digunakan.\nApakah Anda yakin ingin melanjutkan?`)) {
                    kodeWilayahInput.focus();
                    return;
                }
            }

            if (confirm(`Tambahkan wilayah baru:\n\nLevel: ${levelConfig[levelSelect.value].badge}\nKode: ${kode}\nNama: ${document.getElementById('nama').value}`)) {
                this.submit();
            }
        });

        // Initialize form
        document.addEventListener('DOMContentLoaded', initializeForm);
    </script>
</body>
</html>