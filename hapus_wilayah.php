<?php
include "config/db.php";

$kode = isset($_GET['kode_wilayah']) ? $conn->real_escape_string($_GET['kode_wilayah']) : '';

if($kode != ''){
    $provinsi = $conn->query("SELECT * FROM wilayah WHERE kode_wilayah='$kode'");
} else {
    $provinsi = $conn->query("SELECT * FROM wilayah WHERE level='provinsi' ORDER BY nama ASC");
}

// Hitung total wilayah
$totalWilayah = $conn->query("SELECT COUNT(*) as total FROM wilayah")->fetch_assoc()['total'];
$totalProvinsi = $conn->query("SELECT COUNT(*) as total FROM wilayah WHERE level='provinsi'")->fetch_assoc()['total'];
$totalKota = $conn->query("SELECT COUNT(*) as total FROM wilayah WHERE level='kota'")->fetch_assoc()['total'];
$totalKecamatan = $conn->query("SELECT COUNT(*) as total FROM wilayah WHERE level='kecamatan'")->fetch_assoc()['total'];
$totalDesa = $conn->query("SELECT COUNT(*) as total FROM wilayah WHERE level='desa'")->fetch_assoc()['total'];

// Ambil semua data untuk pencarian
$all_data = $conn->query("
    SELECT w.*, 
           COALESCE(p.nama, '') as provinsi_nama,
           COALESCE(k.nama, '') as kota_nama,
           COALESCE(kec.nama, '') as kecamatan_nama
    FROM wilayah w
    LEFT JOIN wilayah kec ON w.parent_kode = kec.kode_wilayah AND kec.level = 'kecamatan'
    LEFT JOIN wilayah k ON kec.parent_kode = k.kode_wilayah AND k.level = 'kota'
    LEFT JOIN wilayah p ON k.parent_kode = p.kode_wilayah AND p.level = 'provinsi'
    WHERE w.level IN ('provinsi', 'kota', 'kecamatan', 'desa')
    ORDER BY w.level, w.nama ASC
");

// Prepare data untuk JavaScript
$search_data = [];
while($row = $all_data->fetch_assoc()) {
    $search_data[] = [
        'kode' => $row['kode_wilayah'],
        'nama' => $row['nama'],
        'level' => $row['level'],
        'provinsi_nama' => $row['provinsi_nama'],
        'kota_nama' => $row['kota_nama'],
        'kecamatan_nama' => $row['kecamatan_nama']
    ];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manajemen Hapus Wilayah</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="css/hapus_wilayah.css">
</head>
<body>
    <!-- Overlay untuk search results -->
    <div class="search-results-overlay" id="searchResultsOverlay"></div>

    <div class="container">
        <!-- Header -->
        <div class="header fade-in">
            <h1 class="page-title"><i class="fas fa-trash-alt"></i> Manajemen Hapus Wilayah</h1>
            <p class="page-subtitle">Kelola dan hapus data wilayah dengan hierarki tree view</p>
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
                            <button class="filter-btn" data-filter="provinsi">
                                <i class="fas fa-map"></i> Provinsi
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
            <div class="stat-card">
                <div class="stat-number"><?= $totalWilayah ?></div>
                <div class="stat-label">Total Wilayah</div>
            </div>
            <div class="stat-card warning">
                <div class="stat-number"><?= $totalProvinsi ?></div>
                <div class="stat-label">Provinsi</div>
            </div>
            <div class="stat-card success">
                <div class="stat-number"><?= $totalKota ?></div>
                <div class="stat-label">Kota/Kabupaten</div>
            </div>
            <div class="stat-card purple">
                <div class="stat-number"><?= $totalKecamatan ?></div>
                <div class="stat-label">Kecamatan</div>
            </div>
            <div class="stat-card danger">
                <div class="stat-number"><?= $totalDesa ?></div>
                <div class="stat-label">Desa/Kelurahan</div>
            </div>
        </div>

        <!-- Warning Banner -->
        <div class="warning-banner fade-in">
            <i class="fas fa-exclamation-triangle"></i>
            <div class="warning-content">
                <h3><i class="fas fa-warning"></i> Peringatan Penting</h3>
                <p>Menghapus wilayah akan menghapus semua data anak wilayah dan lokasi yang terkait. 
                Tindakan ini tidak dapat dibatalkan. Pastikan Anda yakin sebelum menghapus.</p>
            </div>
        </div>

        <!-- Bulk Actions -->
        <div class="bulk-actions fade-in">
            <div class="bulk-info">
                <input type="checkbox" id="selectAll" class="bulk-checkbox">
                <span class="bulk-text">Pilih Semua</span>
            </div>
            <div class="bulk-buttons">
                <button class="btn btn-danger" id="bulkDeleteBtn" disabled>
                    <i class="fas fa-trash"></i> Hapus Terpilih
                </button>
                <button class="btn btn-primary" id="expandAllBtn">
                    <i class="fas fa-expand"></i> Buka Semua
                </button>
                <button class="btn btn-primary" id="collapseAllBtn">
                    <i class="fas fa-compress"></i> Tutup Semua
                </button>
            </div>
        </div>

        <!-- Back Button -->
        <a href="javascript:history.back()" class="back-btn fade-in">
            <i class="fas fa-arrow-left"></i> Kembali
        </a>

        <!-- Loading Spinner -->
        <div class="loading-spinner" id="loadingSpinner">
            <div class="spinner"></div>
            <p>Memproses data...</p>
        </div>

        <?php if ($provinsi->num_rows > 0): ?>
            <?php while($p = $provinsi->fetch_assoc()): ?>
            <div class="provinsi-block fade-in" id="provinsi-<?= $p['kode_wilayah'] ?>">
                <div class="provinsi-header">
                    <div class="provinsi-info">
                        <input type="checkbox" class="wilayah-checkbox" data-kode="<?= $p['kode_wilayah'] ?>" data-level="<?= $p['level'] ?>" data-nama="<?= htmlspecialchars($p['nama']) ?>">
                        <i class="fas fa-map" style="color: var(--purple); font-size: 1.1rem;"></i>
                        <div style="flex: 1;">
                            <div class="provinsi-name"><?= htmlspecialchars($p['nama']) ?></div>
                            <div style="font-size: 0.75rem; color: #64748b; margin-top: 2px;">
                                Kode: <?= $p['kode_wilayah'] ?>
                            </div>
                        </div>
                        <span class="badge provinsi">Provinsi</span>
                    </div>
                    <a href="proses/proses_hapus_wilayah.php?kode_wilayah=<?= $p['kode_wilayah'] ?>" 
                       class="btn btn-danger delete-btn" 
                       data-kode="<?= $p['kode_wilayah'] ?>"
                       data-level="<?= $p['level'] ?>"
                       data-nama="<?= htmlspecialchars($p['nama']) ?>">
                        <i class="fas fa-trash"></i> Hapus
                    </a>
                </div>

                <div class="tree">
                    <?php
                    // fungsi rekursif tampil anak - SEMUA DICOLLAPSE
                    function tampilAnak($conn, $parent, $kode){
                        $childs = $conn->query("SELECT * FROM wilayah WHERE parent_kode='".$parent."' ORDER BY nama ASC");
                        if($childs->num_rows > 0){
                            echo "<ul>";
                            while($c = $childs->fetch_assoc()){
                                // SEMUA DICOLLAPSE
                                $highlight = ($c['kode_wilayah'] == $kode) ? "highlight" : "";
                                
                                echo "<li class='collapsed' id='wilayah-{$c['kode_wilayah']}'>
                                    <div class='tree-item $highlight'>
                                        <div class='tree-left'>
                                            <span class='toggle-icon'>
                                                <i class='fas fa-plus'></i>
                                            </span>
                                            <div class='item-content'>
                                                <input type='checkbox' class='wilayah-checkbox' data-kode='{$c['kode_wilayah']}' data-level='{$c['level']}' data-nama='".htmlspecialchars($c['nama'])."'>
                                                <span class='item-name'>".htmlspecialchars($c['nama'])."</span>
                                                <span class='badge ".$c['level']."'>".ucfirst($c['level'])."</span>
                                                <small style='color: #64748b; font-size: 0.75rem;'>
                                                    (".$c['kode_wilayah'].")
                                                </small>
                                            </div>
                                        </div>
                                        <a href='proses/proses_hapus_wilayah.php?kode_wilayah=".$c['kode_wilayah']."' 
                                           class='btn btn-danger delete-btn btn-small' 
                                           data-kode='".$c['kode_wilayah']."'
                                           data-level='".$c['level']."'
                                           data-nama='".htmlspecialchars($c['nama'])."'>
                                            <i class=\"fas fa-trash\"></i> Hapus
                                        </a>
                                    </div>";
                                tampilAnak($conn, $c['kode_wilayah'], $kode);
                                echo "</li>";
                            }
                            echo "</ul>";
                        }
                    }

                    tampilAnak($conn, $p['kode_wilayah'], $kode);
                    ?>
                </div>
            </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="empty-state fade-in">
                <i class="fas fa-map-marked-alt"></i>
                <h3>Tidak Ada Data Wilayah</h3>
                <p>Belum ada data wilayah yang tersedia di sistem.</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Confirmation Modal -->
    <div class="modal-overlay" id="confirmationModal">
        <div class="modal">
            <div class="modal-header">
                <div class="modal-icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="modal-title">Konfirmasi Penghapusan</div>
            </div>
            <div class="modal-body" id="modalBody">
                <!-- Content will be inserted here -->
            </div>
            <div class="modal-footer">
                <button class="btn btn-cancel" id="cancelDelete">Batal</button>
                <button class="btn btn-danger" id="confirmDelete">Ya, Hapus</button>
            </div>
        </div>
    </div>

    <script>
        // Data untuk pencarian
        const searchData = <?= json_encode($search_data) ?>;
        let currentFilter = 'all';
        let selectedWilayah = [];
        
        // DOM Elements
        const searchInput = document.getElementById('globalSearch');
        const clearSearch = document.getElementById('clearSearch');
        const searchResults = document.getElementById('searchResults');
        const searchResultsOverlay = document.getElementById('searchResultsOverlay');
        const resultsBody = document.getElementById('resultsBody');
        const closeResults = document.getElementById('closeResults');
        const filterButtons = document.querySelectorAll('.filter-btn');
        const selectAllCheckbox = document.getElementById('selectAll');
        const bulkDeleteBtn = document.getElementById('bulkDeleteBtn');
        const expandAllBtn = document.getElementById('expandAllBtn');
        const collapseAllBtn = document.getElementById('collapseAllBtn');
        const confirmationModal = document.getElementById('confirmationModal');
        const modalBody = document.getElementById('modalBody');
        const cancelDeleteBtn = document.getElementById('cancelDelete');
        const confirmDeleteBtn = document.getElementById('confirmDelete');
        const loadingSpinner = document.getElementById('loadingSpinner');
        let currentDeleteUrl = '';

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
                       (item.provinsi_nama && item.provinsi_nama.toLowerCase().includes(lowerQuery)) ||
                       (item.kota_nama && item.kota_nama.toLowerCase().includes(lowerQuery)) ||
                       (item.kecamatan_nama && item.kecamatan_nama.toLowerCase().includes(lowerQuery));
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
                        <button class="btn btn-danger btn-small delete-btn" 
                                data-kode="${item.kode}"
                                data-level="${item.level}"
                                data-nama="${item.nama.replace(/'/g, "\\'")}">
                            <i class="fas fa-trash"></i> Hapus
                        </button>
                        <button class="btn btn-outline btn-small" 
                                onclick="event.stopPropagation(); navigateToItem('${item.kode}', '${item.level}')">
                            <i class="fas fa-eye"></i> Lihat
                        </button>
                    </div>
                </div>
            `).join('');
            
            // Attach event listeners to delete buttons in search results
            document.querySelectorAll('.search-result-item .delete-btn').forEach(btn => {
                btn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    const kode = this.dataset.kode;
                    const level = this.dataset.level;
                    const nama = this.dataset.nama;
                    showConfirmationModal(level, nama, `proses/proses_hapus_wilayah.php?kode_wilayah=${kode}`);
                });
            });
        }
        
        // Fungsi untuk mendapatkan icon berdasarkan level
        function getLevelIcon(level) {
            const icons = {
                'provinsi': 'fas fa-map',
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
            
            // Cari elemen yang sesuai
            const element = document.getElementById(`wilayah-${kode}`) || 
                           document.getElementById(`provinsi-${kode}`);
            
            if (element) {
                // Expand semua parent elements
                expandParents(element);
                
                // Scroll dan highlight
                element.scrollIntoView({ behavior: 'smooth', block: 'center' });
                
                const treeItem = element.querySelector('.tree-item');
                if (treeItem) {
                    treeItem.classList.add('search-highlight');
                    
                    // Hapus highlight setelah 3 detik
                    setTimeout(() => {
                        treeItem.classList.remove('search-highlight');
                    }, 3000);
                }
            }
        }
        
        // Fungsi untuk expand semua parent elements
        function expandParents(element) {
            let current = element;
            while (current) {
                if (current.classList && current.classList.contains('collapsed')) {
                    current.classList.remove('collapsed');
                    const toggleIcon = current.querySelector('.toggle-icon');
                    if (toggleIcon) {
                        toggleIcon.innerHTML = '<i class="fas fa-minus"></i>';
                    }
                }
                current = current.parentElement.closest('li');
            }
        }

        // Fungsi untuk menampilkan modal konfirmasi
        function showConfirmationModal(level, nama, url) {
            const levelText = {
                'provinsi': 'provinsi',
                'kota': 'kota/kabupaten', 
                'kecamatan': 'kecamatan',
                'desa': 'desa/kelurahan'
            };

            modalBody.innerHTML = `
                <p><strong>Anda akan menghapus ${levelText[level]}:</strong></p>
                <p style="font-size: 1rem; font-weight: 600; color: var(--danger); margin: 10px 0; padding: 10px; background: #fef2f2; border-radius: 8px;">"${nama}"</p>
                <p><strong>Tindakan ini akan:</strong></p>
                <ul style="margin-left: 20px; margin-bottom: 15px;">
                    <li>Menghapus semua wilayah anak di bawahnya</li>
                    <li>Menghapus semua data lokasi yang terkait</li>
                    <li><strong>TIDAK DAPAT DIBATALKAN</strong></li>
                </ul>
                <p style="color: var(--danger); font-weight: 600;">Apakah Anda YAKIN ingin melanjutkan?</p>
            `;
            
            currentDeleteUrl = url;
            confirmationModal.style.display = 'flex';
        }

        // Fungsi untuk memperbarui status tombol hapus massal
        function updateBulkDeleteButton() {
            const checkedBoxes = document.querySelectorAll('.wilayah-checkbox:checked');
            bulkDeleteBtn.disabled = checkedBoxes.length === 0;
            
            if (checkedBoxes.length > 0) {
                bulkDeleteBtn.innerHTML = `<i class="fas fa-trash"></i> Hapus ${checkedBoxes.length} Terpilih`;
            } else {
                bulkDeleteBtn.innerHTML = `<i class="fas fa-trash"></i> Hapus Terpilih`;
            }
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

        // Toggle tree nodes
        document.addEventListener("DOMContentLoaded", function(){
            document.querySelectorAll(".toggle-icon").forEach(function(icon){
                icon.addEventListener("click", function(e){
                    let li = this.closest("li");
                    if(li.classList.contains("collapsed")){
                        li.classList.remove("collapsed");
                        this.innerHTML = '<i class="fas fa-minus"></i>';
                    } else {
                        li.classList.add("collapsed");
                        this.innerHTML = '<i class="fas fa-plus"></i>';
                    }
                    e.stopPropagation();
                });
            });

            // Event listeners for delete buttons
            document.querySelectorAll('.delete-btn').forEach(btn => {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    const kode = this.dataset.kode;
                    const level = this.dataset.level;
                    const nama = this.dataset.nama;
                    showConfirmationModal(level, nama, this.href);
                });
            });

            // Event listeners for checkboxes
            document.querySelectorAll('.wilayah-checkbox').forEach(checkbox => {
                checkbox.addEventListener('change', updateBulkDeleteButton);
            });

            // Select all checkbox
            selectAllCheckbox.addEventListener('change', function() {
                const checkboxes = document.querySelectorAll('.wilayah-checkbox');
                checkboxes.forEach(checkbox => {
                    checkbox.checked = this.checked;
                });
                updateBulkDeleteButton();
            });

            // Expand all button
            expandAllBtn.addEventListener('click', function() {
                document.querySelectorAll('.tree li.collapsed').forEach(li => {
                    li.classList.remove('collapsed');
                    const toggleIcon = li.querySelector('.toggle-icon');
                    if (toggleIcon) {
                        toggleIcon.innerHTML = '<i class="fas fa-minus"></i>';
                    }
                });
            });

            // Collapse all button
            collapseAllBtn.addEventListener('click', function() {
                document.querySelectorAll('.tree li:not(.collapsed)').forEach(li => {
                    li.classList.add('collapsed');
                    const toggleIcon = li.querySelector('.toggle-icon');
                    if (toggleIcon) {
                        toggleIcon.innerHTML = '<i class="fas fa-plus"></i>';
                    }
                });
            });

            // Bulk delete button
            bulkDeleteBtn.addEventListener('click', function() {
                const checkedBoxes = document.querySelectorAll('.wilayah-checkbox:checked');
                if (checkedBoxes.length === 0) return;

                const names = Array.from(checkedBoxes).map(cb => cb.dataset.nama).join(', ');
                const kodes = Array.from(checkedBoxes).map(cb => cb.dataset.kode).join(',');
                
                modalBody.innerHTML = `
                    <p><strong>Anda akan menghapus ${checkedBoxes.length} wilayah:</strong></p>
                    <div style="max-height: 120px; overflow-y: auto; margin: 10px 0; padding: 8px; background: #f8fafc; border-radius: 6px; font-size: 0.85rem;">
                        ${Array.from(checkedBoxes).map(cb => 
                            `<p style="margin: 4px 0; padding: 4px; background: white; border-radius: 4px;">${cb.dataset.nama} <small>(${cb.dataset.level})</small></p>`
                        ).join('')}
                    </div>
                    <p><strong>Tindakan ini akan:</strong></p>
                    <ul style="margin-left: 18px; margin-bottom: 12px; font-size: 0.85rem;">
                        <li>Menghapus semua wilayah anak di bawahnya</li>
                        <li>Menghapus semua data lokasi yang terkait</li>
                        <li><strong>TIDAK DAPAT DIBATALKAN</strong></li>
                    </ul>
                    <p style="color: var(--danger); font-weight: 600; font-size: 0.9rem;">Apakah Anda YAKIN ingin melanjutkan?</p>
                `;
                
                // PERBAIKAN: gunakan kode_wilayah untuk multiple deletion
                currentDeleteUrl = `proses/proses_hapus_wilayah.php?kode_wilayah=${kodes}`;
                confirmationModal.style.display = 'flex';
            });

            // Modal buttons
            cancelDeleteBtn.addEventListener('click', function() {
                confirmationModal.style.display = 'none';
            });

            confirmDeleteBtn.addEventListener('click', function() {
                loadingSpinner.style.display = 'block';
                window.location.href = currentDeleteUrl;
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
            
            if (e.target === confirmationModal) {
                confirmationModal.style.display = 'none';
            }
        });

        // Touch improvements for mobile
        document.addEventListener('touchstart', function() {
            // Add touch-specific improvements if needed
        }, { passive: true });
    </script>
</body>
</html>