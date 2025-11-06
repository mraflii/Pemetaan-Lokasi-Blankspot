<?php
include "config/db.php";

$parent = $_GET['parent'] ?? '';
if(!$parent){
    die("Provinsi tidak ditemukan.");
}

// Ambil data provinsi
$provinsi = $conn->query("SELECT * FROM wilayah WHERE kode_wilayah='$parent' AND level='provinsi'")->fetch_assoc();
if(!$provinsi){
    die("Provinsi tidak valid.");
}

// Hitung total wilayah
$totalWilayah = $conn->query("SELECT COUNT(*) as total FROM wilayah WHERE parent_kode='$parent' OR kode_wilayah IN (SELECT parent_kode FROM wilayah WHERE parent_kode='$parent') OR kode_wilayah IN (SELECT parent_kode FROM wilayah WHERE parent_kode IN (SELECT kode_wilayah FROM wilayah WHERE parent_kode='$parent'))")->fetch_assoc()['total'];
$totalKota = $conn->query("SELECT COUNT(*) as total FROM wilayah WHERE parent_kode='$parent' AND level='kota'")->fetch_assoc()['total'];
$totalKecamatan = $conn->query("SELECT COUNT(*) as total FROM wilayah WHERE parent_kode IN (SELECT kode_wilayah FROM wilayah WHERE parent_kode='$parent') AND level='kecamatan'")->fetch_assoc()['total'];
$totalDesa = $conn->query("SELECT COUNT(*) as total FROM wilayah WHERE parent_kode IN (SELECT kode_wilayah FROM wilayah WHERE parent_kode IN (SELECT kode_wilayah FROM wilayah WHERE parent_kode='$parent')) AND level='desa'")->fetch_assoc()['total'];

// Ambil semua data untuk pencarian
$all_data = $conn->query("
    SELECT w.*, 
           COALESCE(k.nama, '') as kota_nama,
           COALESCE(kec.nama, '') as kecamatan_nama,
           COALESCE(prov.nama, '') as provinsi_nama
    FROM wilayah w
    LEFT JOIN wilayah k ON w.parent_kode = k.kode_wilayah AND k.level = 'kota'
    LEFT JOIN wilayah kec ON k.parent_kode = kec.kode_wilayah AND kec.level = 'kecamatan'
    LEFT JOIN wilayah prov ON kec.parent_kode = prov.kode_wilayah AND prov.level = 'provinsi'
    WHERE w.level IN ('kota', 'kecamatan', 'desa') 
    AND (w.parent_kode = '$parent' OR k.parent_kode = '$parent' OR kec.parent_kode = '$parent')
    ORDER BY w.level, w.nama ASC
");

// Organize data by level untuk tabs
$kota_data = $conn->query("SELECT * FROM wilayah WHERE parent_kode='$parent' AND level='kota' ORDER BY nama ASC");
$kecamatan_data = $conn->query("
    SELECT w.*, k.nama as kota_nama 
    FROM wilayah w 
    JOIN wilayah k ON w.parent_kode = k.kode_wilayah 
    WHERE k.parent_kode='$parent' AND w.level='kecamatan' 
    ORDER BY k.nama, w.nama ASC
");
$desa_data = $conn->query("
    SELECT w.*, kec.nama as kecamatan_nama, k.nama as kota_nama 
    FROM wilayah w 
    JOIN wilayah kec ON w.parent_kode = kec.kode_wilayah 
    JOIN wilayah k ON kec.parent_kode = k.kode_wilayah 
    WHERE k.parent_kode='$parent' AND w.level='desa' 
    ORDER BY k.nama, kec.nama, w.nama ASC
");

// Hitung jumlah setiap level
$counts = [
    'kota' => $kota_data->num_rows,
    'kecamatan' => $kecamatan_data->num_rows,
    'desa' => $desa_data->num_rows
];

// Prepare data untuk JavaScript
$search_data = [];
while($row = $all_data->fetch_assoc()) {
    $search_data[] = [
        'kode' => $row['kode_wilayah'],
        'nama' => $row['nama'],
        'level' => $row['level'],
        'kota_nama' => $row['kota_nama'],
        'kecamatan_nama' => $row['kecamatan_nama'],
        'provinsi_nama' => $row['provinsi_nama']
    ];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Edit Wilayah - <?= htmlspecialchars($provinsi['nama']) ?></title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="css/edit_wilayah.css">
</head>
<body>
    <!-- Overlay untuk search results -->
    <div class="search-results-overlay" id="searchResultsOverlay"></div>

    <div class="container">
        <!-- Header -->
        <div class="header fade-in">
            <h1 class="page-title"><i class="fas fa-edit"></i> Edit Wilayah</h1>
            <p class="page-subtitle"><?= htmlspecialchars($provinsi['nama']) ?></p>
        </div>

        <!-- Enhanced Global Search -->
        <div class="search-hero fade-in">
            <div class="search-container">
                <div class="search-header">
                    <h3><i class="fas fa-search"></i> Cari Wilayah</h3>
                    <div class="search-stats">
                        <span class="stat-badge">
                            <i class="fas fa-database"></i>
                            <?= $totalWilayah ?> Total
                        </span>
                    </div>
                </div>
                
                <div class="search-input-group">
                    <div class="search-input-wrapper">
                        <i class="fas fa-search search-icon"></i>
                        <input type="text" 
                               id="globalSearch" 
                               placeholder="Cari wilayah... (Min. 2 karakter)"
                               autocomplete="off">
                        <div class="search-actions">
                            <button class="clear-search" id="clearSearch" style="display: none;">
                                <i class="fas fa-times"></i>
                            </button>
                            <span class="search-shortcut">Ctrl+K</span>
                        </div>
                    </div>
                    
                    <div class="search-filters">
                        <div class="filter-buttons">
                            <button class="filter-btn active" data-filter="all">
                                <i class="fas fa-layer-group"></i> Semua
                            </button>
                            <button class="filter-btn" data-filter="kota">
                                <i class="fas fa-city"></i> Kota
                            </button>
                            <button class="filter-btn" data-filter="kecamatan">
                                <i class="fas fa-map-marked-alt"></i> Kecamatan
                            </button>
                            <button class="filter-btn" data-filter="desa">
                                <i class="fas fa-home"></i> Desa
                            </button>
                        </div>
                    </div>
                </div>

                <div class="search-results-container" id="searchResults">
                    <div class="results-header">
                        <span class="results-count">Hasil Pencarian</span>
                        <span class="results-actions">
                            <button class="action-btn" id="closeResults">
                                <i class="fas fa-times"></i>
                            </button>
                        </span>
                    </div>
                    <div class="results-body" id="resultsBody">
                        <div class="empty-state-search">
                            <i class="fas fa-search"></i>
                            <h4>Mulai mengetik untuk mencari</h4>
                            <p>Ketik nama wilayah, kode, atau lokasi untuk menemukan data</p>
                        </div>
                    </div>
                </div>

                <div class="search-tips mobile-only">
                    <div class="tip-item">
                        <i class="fas fa-lightbulb"></i>
                        <span>Swipe untuk melihat lebih banyak hasil</span>
                    </div>
                </div>

                <div class="search-tips desktop-only">
                    <div class="tip-item">
                        <i class="fas fa-lightbulb"></i>
                        <span>Gunakan <kbd>Ctrl+K</kbd> untuk fokus ke pencarian</span>
                    </div>
                    <div class="tip-item">
                        <i class="fas fa-filter"></i>
                        <span>Filter berdasarkan level wilayah</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Stats Overview -->
        <div class="stats-overview fade-in">
            <div class="stat-card" onclick="showTab('kota')">
                <div class="stat-number"><?= $totalKota ?></div>
                <div class="stat-label">Kota/Kabupaten</div>
            </div>
            <div class="stat-card success" onclick="showTab('kecamatan')">
                <div class="stat-number"><?= $totalKecamatan ?></div>
                <div class="stat-label">Kecamatan</div>
            </div>
            <div class="stat-card warning" onclick="showTab('desa')">
                <div class="stat-number"><?= $totalDesa ?></div>
                <div class="stat-label">Desa/Kelurahan</div>
            </div>
            <div class="stat-card purple">
                <div class="stat-number"><?= $totalWilayah ?></div>
                <div class="stat-label">Total Wilayah</div>
            </div>
        </div>

        <!-- Info Banner -->
        <div class="info-banner fade-in">
            <i class="fas fa-info-circle"></i>
            <div class="info-content">
                <h3><i class="fas fa-edit"></i> Edit Data Wilayah</h3>
                <p>Gunakan pencarian untuk menemukan wilayah dengan cepat, atau klik statistik untuk menuju level tertentu. 
                Pastikan untuk menyimpan perubahan setelah melakukan pengeditan.</p>
            </div>
        </div>

        <!-- Back Button -->
        <a href="hasil_pemetaan.php" class="back-btn fade-in">
            <i class="fas fa-arrow-left"></i> Kembali ke Pemetaan
        </a>

        <!-- Form Container -->
        <div class="form-container fade-in">
            <form action="proses/proses_edit_wilayah.php" method="post">
                <input type="hidden" name="provinsi" value="<?= $provinsi['kode_wilayah'] ?>">

                <!-- Tabs Navigation -->
                <div class="tabs">
                    <button type="button" class="tab active" onclick="showTab('kota')">
                        <i class="fas fa-city"></i> Kota/Kabupaten (<?= $counts['kota'] ?>)
                    </button>
                    <button type="button" class="tab" onclick="showTab('kecamatan')">
                        <i class="fas fa-map-marked-alt"></i> Kecamatan (<?= $counts['kecamatan'] ?>)
                    </button>
                    <button type="button" class="tab" onclick="showTab('desa')">
                        <i class="fas fa-home"></i> Desa/Kelurahan (<?= $counts['desa'] ?>)
                    </button>
                </div>

                <!-- Tab Contents -->
                <div class="tab-content active" id="kota-tab">
                    <div class="table-container">
                        <table id="kota-table">
                            <thead>
                                <tr>
                                    <th style="width: 20%;">Kode</th>
                                    <th>Nama Kota/Kabupaten</th>
                                    <th style="width: 15%;">Tipe</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $kota_data->data_seek(0); // Reset pointer
                                while($row = $kota_data->fetch_assoc()): ?>
                                <tr data-kode="<?= $row['kode_wilayah'] ?>" data-level="kota">
                                    <td>
                                        <input type="text" 
                                               name="kode[<?= $row['kode_wilayah'] ?>]" 
                                               value="<?= htmlspecialchars($row['kode_wilayah']) ?>" 
                                               readonly>
                                    </td>
                                    <td>
                                        <input type="text" 
                                               name="wilayah[<?= $row['kode_wilayah'] ?>]" 
                                               value="<?= htmlspecialchars($row['nama']) ?>"
                                               placeholder="Nama kota/kabupaten...">
                                    </td>
                                    <td>
                                        <span class="badge kota">Kota</span>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="tab-content" id="kecamatan-tab">
                    <div class="table-container">
                        <table id="kecamatan-table">
                            <thead>
                                <tr>
                                    <th style="width: 20%;">Kode</th>
                                    <th>Nama Kecamatan</th>
                                    <th style="width: 20%;">Kota/Kabupaten</th>
                                    <th style="width: 15%;">Tipe</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $kecamatan_data->data_seek(0); // Reset pointer
                                while($row = $kecamatan_data->fetch_assoc()): ?>
                                <tr data-kode="<?= $row['kode_wilayah'] ?>" data-level="kecamatan">
                                    <td>
                                        <input type="text" 
                                               name="kode[<?= $row['kode_wilayah'] ?>]" 
                                               value="<?= htmlspecialchars($row['kode_wilayah']) ?>" 
                                               readonly>
                                    </td>
                                    <td>
                                        <input type="text" 
                                               name="wilayah[<?= $row['kode_wilayah'] ?>]" 
                                               value="<?= htmlspecialchars($row['nama']) ?>"
                                               placeholder="Nama kecamatan...">
                                    </td>
                                    <td>
                                        <?= htmlspecialchars($row['kota_nama']) ?>
                                    </td>
                                    <td>
                                        <span class="badge kecamatan">Kecamatan</span>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="tab-content" id="desa-tab">
                    <div class="table-container">
                        <table id="desa-table">
                            <thead>
                                <tr>
                                    <th style="width: 20%;">Kode</th>
                                    <th>Nama Desa/Kelurahan</th>
                                    <th style="width: 20%;">Kecamatan</th>
                                    <th style="width: 15%;">Tipe</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $desa_data->data_seek(0); // Reset pointer
                                while($row = $desa_data->fetch_assoc()): ?>
                                <tr data-kode="<?= $row['kode_wilayah'] ?>" data-level="desa">
                                    <td>
                                        <input type="text" 
                                               name="kode[<?= $row['kode_wilayah'] ?>]" 
                                               value="<?= htmlspecialchars($row['kode_wilayah']) ?>" 
                                               readonly>
                                    </td>
                                    <td>
                                        <input type="text" 
                                               name="wilayah[<?= $row['kode_wilayah'] ?>]" 
                                               value="<?= htmlspecialchars($row['nama']) ?>"
                                               placeholder="Nama desa/kelurahan...">
                                    </td>
                                    <td>
                                        <?= htmlspecialchars($row['kecamatan_nama']) ?>
                                    </td>
                                    <td>
                                        <span class="badge desa">Desa</span>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="actions">
                    <button type="button" class="btn btn-back" onclick="window.location.href='hasil_pemetaan.php'">
                        <i class="fas fa-arrow-left"></i> Kembali
                    </button>
                    <button type="submit" class="btn btn-save">
                        <i class="fas fa-save"></i> Simpan Semua Perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Data untuk pencarian
        const searchData = <?= json_encode($search_data) ?>;
        let currentFilter = 'all';
        
        // DOM Elements
        const searchInput = document.getElementById('globalSearch');
        const clearSearch = document.getElementById('clearSearch');
        const searchResults = document.getElementById('searchResults');
        const searchResultsOverlay = document.getElementById('searchResultsOverlay');
        const resultsBody = document.getElementById('resultsBody');
        const closeResults = document.getElementById('closeResults');
        const filterButtons = document.querySelectorAll('.filter-btn');

        // Fungsi untuk mencari wilayah dengan filter
        function searchWilayah(query, filter = 'all') {
            if (query.length < 2) {
                return [];
            }
            
            const lowerQuery = query.toLowerCase();
            return searchData.filter(item => {
                // Filter by level
                if (filter !== 'all' && item.level !== filter) {
                    return false;
                }
                
                // Search in all fields
                return item.nama.toLowerCase().includes(lowerQuery) ||
                       item.kode.toLowerCase().includes(lowerQuery) ||
                       (item.kota_nama && item.kota_nama.toLowerCase().includes(lowerQuery)) ||
                       (item.kecamatan_nama && item.kecamatan_nama.toLowerCase().includes(lowerQuery)) ||
                       (item.provinsi_nama && item.provinsi_nama.toLowerCase().includes(lowerQuery));
            }).slice(0, 10); // Batasi hasil maksimal 10 di mobile
        }
        
        // Fungsi untuk menampilkan hasil pencarian
        function showSearchResults(results, query) {
            if (query.length < 2) {
                resultsBody.innerHTML = `
                    <div class="empty-state-search">
                        <i class="fas fa-search"></i>
                        <h4>Mulai mengetik untuk mencari</h4>
                        <p>Ketik minimal 2 karakter untuk memulai pencarian</p>
                    </div>
                `;
                return;
            }
            
            if (results.length === 0) {
                resultsBody.innerHTML = `
                    <div class="empty-state-search">
                        <i class="fas fa-search-minus"></i>
                        <h4>Tidak ada hasil ditemukan</h4>
                        <p>Tidak ada wilayah yang cocok dengan "${query}"</p>
                        <div class="suggestions">
                            <p><strong>Tips:</strong></p>
                            <ul>
                                <li>Periksa ejaan kata kunci</li>
                                <li>Coba kata kunci yang lebih umum</li>
                                <li>Gunakan filter level wilayah</li>
                            </ul>
                        </div>
                    </div>
                `;
                return;
            }
            
            resultsBody.innerHTML = results.map(item => `
                <div class="search-result-item" onclick="navigateToItem('${item.kode}', '${item.level}')">
                    <div class="result-icon">
                        <i class="${getLevelIcon(item.level)}"></i>
                    </div>
                    <div class="result-content">
                        <div class="result-main">
                            <span class="result-name">${item.nama}</span>
                            <span class="badge ${item.level}">${item.level}</span>
                        </div>
                        <div class="result-path">
                            ${getLocationPath(item)}
                        </div>
                        <div class="result-meta">
                            <span class="result-code">Kode: ${item.kode}</span>
                            <span class="result-level">Level: ${item.level}</span>
                        </div>
                    </div>
                    <div class="result-actions">
                        <button class="btn btn-outline btn-small" 
                                onclick="event.stopPropagation(); navigateToItem('${item.kode}', '${item.level}')">
                            <i class="fas fa-edit"></i> Edit
                        </button>
                    </div>
                </div>
            `).join('');
        }
        
        // Fungsi untuk mendapatkan icon berdasarkan level
        function getLevelIcon(level) {
            const icons = {
                'kota': 'fas fa-city',
                'kecamatan': 'fas fa-map-marked-alt',
                'desa': 'fas fa-home'
            };
            return icons[level] || 'fas fa-map-marker-alt';
        }
        
        // Fungsi untuk mendapatkan path lokasi
        function getLocationPath(item) {
            const parts = [];
            if (item.kecamatan_nama) parts.push(item.kecamatan_nama);
            if (item.kota_nama) parts.push(item.kota_nama);
            if (item.provinsi_nama) parts.push(item.provinsi_nama);
            
            return parts.length > 0 ? parts.join(' → ') : 'Lokasi utama';
        }
        
        // Fungsi untuk navigasi ke item yang dipilih
        function navigateToItem(kode, level) {
            // Sembunyikan hasil pencarian dan overlay
            searchResults.classList.remove('active');
            searchResultsOverlay.classList.remove('active');
            searchInput.value = '';
            clearSearch.style.display = 'none';
            
            // Tampilkan tab yang sesuai
            showTab(level);
            
            // Cari dan highlight baris yang sesuai
            setTimeout(() => {
                const row = document.querySelector(`tr[data-kode="${kode}"]`);
                if (row) {
                    // Hapus highlight sebelumnya
                    document.querySelectorAll('tr.highlighted').forEach(el => {
                        el.classList.remove('highlighted');
                    });
                    
                    // Tambahkan highlight baru
                    row.classList.add('highlighted');
                    row.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    
                    // Hapus highlight setelah 3 detik
                    setTimeout(() => {
                        row.classList.remove('highlighted');
                    }, 3000);
                }
            }, 100);
        }

        // Event Listeners
        searchInput.addEventListener('input', function(e) {
            const query = e.target.value.trim();
            const results = searchWilayah(query, currentFilter);
            
            if (query.length > 0) {
                clearSearch.style.display = 'flex';
                searchResults.classList.add('active');
                searchResultsOverlay.classList.add('active');
            } else {
                clearSearch.style.display = 'none';
                searchResults.classList.remove('active');
                searchResultsOverlay.classList.remove('active');
            }
            
            showSearchResults(results, query);
        });

        clearSearch.addEventListener('click', function() {
            searchInput.value = '';
            searchInput.focus();
            clearSearch.style.display = 'none';
            searchResults.classList.remove('active');
            searchResultsOverlay.classList.remove('active');
            showSearchResults([], '');
        });

        closeResults.addEventListener('click', function() {
            searchResults.classList.remove('active');
            searchResultsOverlay.classList.remove('active');
        });

        // Close search results when clicking on overlay
        searchResultsOverlay.addEventListener('click', function() {
            searchResults.classList.remove('active');
            searchResultsOverlay.classList.remove('active');
        });

        // Filter buttons
        filterButtons.forEach(btn => {
            btn.addEventListener('click', function() {
                filterButtons.forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                currentFilter = this.dataset.filter;
                
                // Refresh search if there's a query
                const query = searchInput.value.trim();
                if (query.length >= 2) {
                    const results = searchWilayah(query, currentFilter);
                    showSearchResults(results, query);
                }
            });
        });

        // Keyboard shortcuts - disable Ctrl+K on mobile
        document.addEventListener('keydown', function(e) {
            // Only enable Ctrl+K on desktop
            if (window.innerWidth > 768 && (e.ctrlKey || e.metaKey) && e.key === 'k') {
                e.preventDefault();
                searchInput.focus();
            }
            
            // Escape untuk clear search
            if (e.key === 'Escape') {
                if (searchInput.value) {
                    searchInput.value = '';
                    searchResults.classList.remove('active');
                    searchResultsOverlay.classList.remove('active');
                    clearSearch.style.display = 'none';
                    showSearchResults([], '');
                } else {
                    searchInput.blur();
                }
            }
        });

        // Click outside to close search results
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.search-container')) {
                searchResults.classList.remove('active');
                searchResultsOverlay.classList.remove('active');
            }
        });

        // Touch improvements for mobile
        document.addEventListener('touchstart', function() {
            // Add touch-specific improvements if needed
        }, { passive: true });

        // Tab functionality
        function showTab(tabName) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('active');
            });

            // Show selected tab
            document.getElementById(tabName + '-tab').classList.add('active');
            document.querySelectorAll('.tab').forEach(tab => {
                if (tab.textContent.includes(tabName.charAt(0).toUpperCase() + tabName.slice(1))) {
                    tab.classList.add('active');
                }
            });
        }

        // Confirm sebelum submit
        document.querySelector('form').addEventListener('submit', function(e) {
            if (!confirm('Apakah Anda yakin ingin menyimpan semua perubahan?')) {
                e.preventDefault();
            }
        });

        // Initialize first tab
        showTab('kota');
    </script>
</body>
</html>