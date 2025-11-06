<?php
include "config/db.php";
session_start();

// Set timezone
date_default_timezone_set('Asia/Jakarta');

// Cek koneksi database
if (!$conn) {
    die("Koneksi database gagal: " . mysqli_connect_error());
}

// Fungsi untuk mendapatkan nama user berdasarkan username
function getNamaUser($conn, $username) {
    if (empty($username) || $username === 'system' || $username === 'System') {
        return 'System';
    }
    
    // Gunakan query langsung untuk menghindari error prepare
    $query = "SELECT nama_lengkap FROM users WHERE username = '" . $conn->real_escape_string($username) . "'";
    $result = $conn->query($query);
    
    if ($result && $result->num_rows === 1) {
        $user = $result->fetch_assoc();
        return $user['nama_lengkap'] ?: $username;
    }
    
    return $username; // Fallback ke username jika tidak ditemukan
}

// Fungsi untuk mendapatkan riwayat lokasi dari tabel riwayat_aktivitas
function getRiwayatLokasi($conn) {
    $query = "
        SELECT 
            jenis_aktivitas as jenis,
            kode_data as kode_lokasi,
            nama_data as nama_tempat,
            deskripsi,
            data_sebelum,
            data_sesudah,
            dibuat_oleh,
            created_at as tanggal
        FROM riwayat_aktivitas 
        WHERE jenis_aktivitas IN ('TAMBAH_LOKASI', 'EDIT_LOKASI', 'HAPUS_LOKASI')
        ORDER BY created_at DESC
        LIMIT 100
    ";
    
    $result = $conn->query($query);
    if (!$result) {
        error_log("Error getRiwayatLokasi: " . $conn->error);
        return [];
    }
    
    // Process results to get actual user names
    $processedResults = [];
    while ($row = $result->fetch_assoc()) {
        $row['nama_user'] = getNamaUser($conn, $row['dibuat_oleh']);
        $processedResults[] = $row;
    }
    
    return $processedResults;
}

// Fungsi untuk mendapatkan riwayat laporan (HANYA PDF/EXCEL, TANPA VIEW)
function getRiwayatLaporan($conn) {
    $query = "
        SELECT 
            id,
            nama_laporan,
            jenis_laporan,
            filter,
            dibuat_oleh,
            dibuat_pada as tanggal,
            path_file
        FROM riwayat_laporan 
        WHERE jenis_laporan IN ('pdf', 'excel')
        ORDER BY dibuat_pada DESC
        LIMIT 100
    ";
    
    $result = $conn->query($query);
    if (!$result) {
        error_log("Error getRiwayatLaporan: " . $conn->error);
        return [];
    }
    
    // Process results to get actual user names
    $processedResults = [];
    while ($row = $result->fetch_assoc()) {
        $row['nama_user'] = getNamaUser($conn, $row['dibuat_oleh']);
        $processedResults[] = $row;
    }
    
    return $processedResults;
}

// Fungsi untuk mendapatkan riwayat wilayah dari tabel riwayat_aktivitas
function getRiwayatWilayah($conn) {
    $query = "
        SELECT 
            jenis_aktivitas as jenis,
            kode_data as kode_wilayah,
            nama_data as nama,
            deskripsi,
            data_sebelum,
            data_sesudah,
            dibuat_oleh,
            created_at as tanggal
        FROM riwayat_aktivitas 
        WHERE jenis_aktivitas IN ('TAMBAH_WILAYAH', 'EDIT_WILAYAH', 'HAPUS_WILAYAH')
        ORDER BY created_at DESC
        LIMIT 100
    ";
    
    $result = $conn->query($query);
    if (!$result) {
        error_log("Error getRiwayatWilayah: " . $conn->error);
        return [];
    }
    
    // Process results to get actual user names
    $processedResults = [];
    while ($row = $result->fetch_assoc()) {
        $row['nama_user'] = getNamaUser($conn, $row['dibuat_oleh']);
        $processedResults[] = $row;
    }
    
    return $processedResults;
}

// Fungsi untuk menghitung statistik per jenis aktivitas
function getStatistikPerJenis($conn) {
    $stats = [
        'lokasi' => [
            'TAMBAH_LOKASI' => 0,
            'EDIT_LOKASI' => 0,
            'HAPUS_LOKASI' => 0,
            'total' => 0
        ],
        'laporan' => [
            'pdf' => 0,
            'excel' => 0,
            'total' => 0
        ],
        'wilayah' => [
            'TAMBAH_WILAYAH' => 0,
            'EDIT_WILAYAH' => 0,
            'HAPUS_WILAYAH' => 0,
            'total' => 0
        ]
    ];
    
    // Hitung statistik lokasi
    $queryLokasi = "SELECT jenis_aktivitas, COUNT(*) as jumlah 
                   FROM riwayat_aktivitas 
                   WHERE jenis_aktivitas IN ('TAMBAH_LOKASI', 'EDIT_LOKASI', 'HAPUS_LOKASI')
                   GROUP BY jenis_aktivitas";
    $result = $conn->query($queryLokasi);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $stats['lokasi'][$row['jenis_aktivitas']] = $row['jumlah'];
            $stats['lokasi']['total'] += $row['jumlah'];
        }
    }
    
    // Hitung statistik laporan
    $queryLaporan = "SELECT jenis_laporan, COUNT(*) as jumlah 
                    FROM riwayat_laporan 
                    WHERE jenis_laporan IN ('pdf', 'excel')
                    GROUP BY jenis_laporan";
    $result = $conn->query($queryLaporan);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $stats['laporan'][$row['jenis_laporan']] = $row['jumlah'];
            $stats['laporan']['total'] += $row['jumlah'];
        }
    }
    
    // Hitung statistik wilayah
    $queryWilayah = "SELECT jenis_aktivitas, COUNT(*) as jumlah 
                    FROM riwayat_aktivitas 
                    WHERE jenis_aktivitas IN ('TAMBAH_WILAYAH', 'EDIT_WILAYAH', 'HAPUS_WILAYAH')
                    GROUP BY jenis_aktivitas";
    $result = $conn->query($queryWilayah);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $stats['wilayah'][$row['jenis_aktivitas']] = $row['jumlah'];
            $stats['wilayah']['total'] += $row['jumlah'];
        }
    }
    
    return $stats;
}

// Fungsi untuk mendapatkan statistik hari ini
function getStatistikHariIni($conn) {
    $today = date('Y-m-d');
    
    $stats = [
        'lokasi_baru' => 0,
        'laporan_dibuat' => 0,
        'wilayah_baru' => 0,
        'total_aktivitas' => 0
    ];
    
    // Hitung aktivitas lokasi hari ini
    $queryLokasi = "SELECT COUNT(*) as total FROM riwayat_aktivitas 
                   WHERE DATE(created_at) = '$today' 
                   AND jenis_aktivitas IN ('TAMBAH_LOKASI', 'EDIT_LOKASI', 'HAPUS_LOKASI')";
    $result = $conn->query($queryLokasi);
    if ($result && $row = $result->fetch_assoc()) {
        $stats['lokasi_baru'] = $row['total'];
    }
    
    // Hitung laporan hari ini (HANYA PDF/EXCEL)
    $queryLaporan = "SELECT COUNT(*) as total FROM riwayat_laporan 
                    WHERE DATE(dibuat_pada) = '$today'
                    AND jenis_laporan IN ('pdf', 'excel')";
    $result = $conn->query($queryLaporan);
    if ($result && $row = $result->fetch_assoc()) {
        $stats['laporan_dibuat'] = $row['total'];
    }
    
    // Hitung aktivitas wilayah hari ini (estimasi)
    $queryWilayah = "SELECT COUNT(*) as total FROM riwayat_aktivitas 
                    WHERE DATE(created_at) = '$today' 
                    AND jenis_aktivitas IN ('TAMBAH_WILAYAH', 'EDIT_WILAYAH')";
    $result = $conn->query($queryWilayah);
    if ($result && $row = $result->fetch_assoc()) {
        $stats['wilayah_baru'] = $row['total'];
    }
    
    $stats['total_aktivitas'] = $stats['lokasi_baru'] + $stats['laporan_dibuat'] + $stats['wilayah_baru'];
    
    return $stats;
}

// Ambil semua data riwayat
$riwayatLokasi = getRiwayatLokasi($conn);
$riwayatLaporan = getRiwayatLaporan($conn);
$riwayatWilayah = getRiwayatWilayah($conn);
$statistikHariIni = getStatistikHariIni($conn);
$statistikPerJenis = getStatistikPerJenis($conn);

// Hitung total riwayat
$totalRiwayat = 0;
$totalLokasi = is_array($riwayatLokasi) ? count($riwayatLokasi) : 0;
$totalLaporan = is_array($riwayatLaporan) ? count($riwayatLaporan) : 0;
$totalWilayah = is_array($riwayatWilayah) ? count($riwayatWilayah) : 0;
$totalRiwayat = $totalLokasi + $totalLaporan + $totalWilayah;
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Sistem - Pemetaan Blankspot</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"/>
    <link rel="stylesheet" href="css/riwayat.css">
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
                    <h1><i class="fas fa-history"></i> Riwayat Aktivitas</h1>
                    <p>Monitor semua aktivitas dan perubahan data dalam sistem</p>
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

            <!-- Stats Grid -->
            <div class="stats-grid fade-in">
                <div class="stat-card total">
                    <div class="stat-header">
                        <div class="stat-icon">
                            <i class="fas fa-history"></i>
                        </div>
                        <div class="stat-badge">TOTAL</div>
                    </div>
                    <div class="stat-value"><?= number_format($totalRiwayat) ?></div>
                    <div class="stat-label">Total Riwayat Aktivitas</div>
                    <div class="stat-details">
                        <div class="stat-detail">
                            <i class="fas fa-map-marker-alt"></i>
                            <span><?= $totalLokasi ?> Lokasi</span>
                        </div>
                        <div class="stat-detail">
                            <i class="fas fa-file-alt"></i>
                            <span><?= $totalLaporan ?> Laporan</span>
                        </div>
                        <div class="stat-detail">
                            <i class="fas fa-layer-group"></i>
                            <span><?= $totalWilayah ?> Wilayah</span>
                        </div>
                    </div>
                </div>

                <div class="stat-card lokasi">
                    <div class="stat-header">
                        <div class="stat-icon">
                            <i class="fas fa-map-marker-alt"></i>
                        </div>
                        <div class="stat-badge">LOKASI</div>
                    </div>
                    <div class="stat-value"><?= number_format($statistikPerJenis['lokasi']['total']) ?></div>
                    <div class="stat-label">Riwayat Data Lokasi</div>
                    <div class="stat-details">
                        <div class="stat-detail">
                            <i class="fas fa-plus-circle"></i>
                            <span>Tambah: <?= number_format($statistikPerJenis['lokasi']['TAMBAH_LOKASI']) ?></span>
                        </div>
                        <div class="stat-detail">
                            <i class="fas fa-edit"></i>
                            <span>Edit: <?= number_format($statistikPerJenis['lokasi']['EDIT_LOKASI']) ?></span>
                        </div>
                        <div class="stat-detail">
                            <i class="fas fa-trash"></i>
                            <span>Hapus: <?= number_format($statistikPerJenis['lokasi']['HAPUS_LOKASI']) ?></span>
                        </div>
                    </div>
                </div>

                <div class="stat-card laporan">
                    <div class="stat-header">
                        <div class="stat-icon">
                            <i class="fas fa-file-alt"></i>
                        </div>
                        <div class="stat-badge">LAPORAN</div>
                    </div>
                    <div class="stat-value"><?= number_format($statistikPerJenis['laporan']['total']) ?></div>
                    <div class="stat-label">Riwayat Laporan</div>
                    <div class="stat-details">
                        <div class="stat-detail">
                            <i class="fas fa-file-excel"></i>
                            <span>Excel: <?= number_format($statistikPerJenis['laporan']['excel']) ?></span>
                        </div>
                        <div class="stat-detail">
                            <i class="fas fa-file-pdf"></i>
                            <span>PDF: <?= number_format($statistikPerJenis['laporan']['pdf']) ?></span>
                        </div>
                        <div class="stat-detail">
                            <i class="fas fa-calendar-day"></i>
                            <span>Hari ini: <?= $statistikHariIni['laporan_dibuat'] ?></span>
                        </div>
                    </div>
                </div>
                
                <div class="stat-card wilayah">
                    <div class="stat-header">
                        <div class="stat-icon">
                            <i class="fas fa-layer-group"></i>
                        </div>
                        <div class="stat-badge">WILAYAH</div>
                    </div>
                    <div class="stat-value"><?= number_format($statistikPerJenis['wilayah']['total']) ?></div>
                    <div class="stat-label">Riwayat Data Wilayah</div>
                    <div class="stat-details">
                        <div class="stat-detail">
                            <i class="fas fa-plus-circle"></i>
                            <span>Tambah: <?= number_format($statistikPerJenis['wilayah']['TAMBAH_WILAYAH']) ?></span>
                        </div>
                        <div class="stat-detail">
                            <i class="fas fa-edit"></i>
                            <span>Edit: <?= number_format($statistikPerJenis['wilayah']['EDIT_WILAYAH']) ?></span>
                        </div>
                        <div class="stat-detail">
                            <i class="fas fa-trash"></i>
                            <span>Hapus: <?= number_format($statistikPerJenis['wilayah']['HAPUS_WILAYAH']) ?></span>
                        </div>
                    </div>
                </div>

                <div class="stat-card today">
                    <div class="stat-header">
                        <div class="stat-icon">
                            <i class="fas fa-calendar-day"></i>
                        </div>
                        <div class="stat-badge">HARI INI</div>
                    </div>
                    <div class="stat-value"><?= number_format($statistikHariIni['total_aktivitas']) ?></div>
                    <div class="stat-label">Aktivitas Hari Ini</div>
                    <div class="stat-details">
                        <div class="stat-detail">
                            <i class="fas fa-map-marker-alt"></i>
                            <span><?= $statistikHariIni['lokasi_baru'] ?> Lokasi</span>
                        </div>
                        <div class="stat-detail">
                            <i class="fas fa-file-alt"></i>
                            <span><?= $statistikHariIni['laporan_dibuat'] ?> Laporan</span>
                        </div>
                        <div class="stat-detail">
                            <i class="fas fa-layer-group"></i>
                            <span><?= $statistikHariIni['wilayah_baru'] ?> Wilayah</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Content Area -->
            <div class="content-area fade-in">
                <!-- Tabs -->
                <div class="tabs-header">
                    <button class="tab-btn active" onclick="openTab('tab-lokasi')">
                        <i class="fas fa-map-marker-alt"></i>
                        <span>Riwayat Lokasi</span>
                        <span class="tab-badge"><?= $totalLokasi ?></span>
                    </button>
                    <button class="tab-btn" onclick="openTab('tab-laporan')">
                        <i class="fas fa-file-alt"></i>
                        <span>Riwayat Laporan</span>
                        <span class="tab-badge"><?= $totalLaporan ?></span>
                    </button>
                    <button class="tab-btn" onclick="openTab('tab-wilayah')">
                        <i class="fas fa-layer-group"></i>
                        <span>Riwayat Wilayah</span>
                        <span class="tab-badge"><?= $totalWilayah ?></span>
                    </button>
                </div>

                <!-- Tab Content - Riwayat Lokasi -->
                <div id="tab-lokasi" class="tab-content active">
                    <!-- Filter Section -->
                    <div class="filter-section">
                        <div class="filter-grid">
                            <div class="filter-group">
                                <label class="filter-label">Jenis Aktivitas</label>
                                <select class="filter-select" id="filterJenisLokasi">
                                    <option value="">Semua Jenis</option>
                                    <option value="TAMBAH_LOKASI">Data Ditambahkan</option>
                                    <option value="EDIT_LOKASI">Data Diedit</option>
                                    <option value="HAPUS_LOKASI">Data Dihapus</option>
                                </select>
                            </div>
                            
                            <div class="filter-group">
                                <label class="filter-label">Periode Waktu</label>
                                <select class="filter-select" id="filterTanggalLokasi">
                                    <option value="">Semua Tanggal</option>
                                    <option value="today">Hari Ini</option>
                                    <option value="week">7 Hari Terakhir</option>
                                    <option value="month">30 Hari Terakhir</option>
                                </select>
                            </div>
                            
                            <div class="filter-actions">
                                <button class="btn btn-primary" onclick="filterRiwayatLokasi()">
                                    <i class="fas fa-filter"></i> Terapkan Filter
                                </button>
                                <button class="btn btn-light" onclick="resetFilterLokasi()">
                                    <i class="fas fa-refresh"></i> Reset
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Table -->
                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th width="120">Jenis</th>
                                    <th width="120">Kode</th>
                                    <th>Nama Tempat</th>
                                    <th>Deskripsi</th>
                                    <th width="150">Waktu</th>
                                    <th width="100">Aksi</th>
                                </tr>
                            </thead>
                            <tbody id="tbody-lokasi">
                                <?php if (is_array($riwayatLokasi) && count($riwayatLokasi) > 0): ?>
                                    <?php foreach ($riwayatLokasi as $row): ?>
                                        <?php
                                        $jenis = $row['jenis'];
                                        $badgeClass = '';
                                        $icon = '';
                                        
                                        switch($jenis) {
                                            case 'TAMBAH_LOKASI':
                                                $badgeClass = 'badge-tambah';
                                                $icon = 'fa-plus';
                                                break;
                                            case 'EDIT_LOKASI':
                                                $badgeClass = 'badge-edit';
                                                $icon = 'fa-edit';
                                                break;
                                            case 'HAPUS_LOKASI':
                                                $badgeClass = 'badge-hapus';
                                                $icon = 'fa-trash';
                                                break;
                                        }
                                        ?>
                                        <tr data-jenis="<?= $jenis ?>" data-tanggal="<?= date('Y-m-d', strtotime($row['tanggal'])) ?>">
                                            <td>
                                                <span class="badge <?= $badgeClass ?>">
                                                    <i class="fas <?= $icon ?>"></i> 
                                                    <?= str_replace('_', ' ', $jenis) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <code><?= htmlspecialchars($row['kode_lokasi']) ?></code>
                                            </td>
                                            <td>
                                                <strong><?= htmlspecialchars($row['nama_tempat']) ?></strong>
                                            </td>
                                            <td>
                                                <div>
                                                    <strong><?= htmlspecialchars($row['deskripsi']) ?></strong>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="timestamp" title="Klik untuk menyalin">
                                                    <?= date('d/m/Y H:i', strtotime($row['tanggal'])) ?>
                                                </span>
                                                <div class="user-info">
                                                    <i class="fas fa-user"></i> <?= htmlspecialchars($row['nama_user']) ?>
                                                </div>
                                            </td>
                                            <td>
                                                <button onclick="hapusRiwayatLokasi('<?= $row['kode_lokasi'] ?>', '<?= htmlspecialchars($row['nama_tempat']) ?>', '<?= $jenis ?>')" 
                                                        class="action-btn delete" title="Hapus Riwayat">
                                                    <i class="fas fa-trash"></i> Hapus
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6">
                                            <div class="empty-state">
                                                <i class="fas fa-inbox"></i>
                                                <h3>Tidak ada riwayat lokasi</h3>
                                                <p>Belum ada aktivitas pada data lokasi</p>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Tab Content - Riwayat Laporan -->
                <div id="tab-laporan" class="tab-content">
                    <!-- Filter Section -->
                    <div class="filter-section">
                        <div class="filter-grid">
                            <div class="filter-group">
                                <label class="filter-label">Jenis Laporan</label>
                                <select class="filter-select" id="filterJenisLaporan">
                                    <option value="">Semua Jenis</option>
                                    <option value="excel">Excel</option>
                                    <option value="pdf">PDF</option>
                                </select>
                            </div>
                            
                            <div class="filter-group">
                                <label class="filter-label">Periode Waktu</label>
                                <select class="filter-select" id="filterTanggalLaporan">
                                    <option value="">Semua Tanggal</option>
                                    <option value="today">Hari Ini</option>
                                    <option value="week">7 Hari Terakhir</option>
                                    <option value="month">30 Hari Terakhir</option>
                                </select>
                            </div>
                            
                            <div class="filter-actions">
                                <button class="btn btn-primary" onclick="filterRiwayatLaporan()">
                                    <i class="fas fa-filter"></i> Terapkan Filter
                                </button>
                                <button class="btn btn-light" onclick="resetFilterLaporan()">
                                    <i class="fas fa-refresh"></i> Reset
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Table -->
                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th width="100">Jenis</th>
                                    <th>Nama Laporan</th>
                                    <th>Filter</th>
                                    <th width="120">Dibuat Oleh</th>
                                    <th width="150">Waktu</th>
                                    <th width="100">Aksi</th>
                                </tr>
                            </thead>
                            <tbody id="tbody-laporan">
                                <?php if (is_array($riwayatLaporan) && count($riwayatLaporan) > 0): ?>
                                    <?php foreach ($riwayatLaporan as $row): ?>
                                        <?php 
                                        $badgeClass = '';
                                        $icon = '';
                                        $filter = json_decode($row['filter'], true);
                                        
                                        switch($row['jenis_laporan']) {
                                            case 'excel':
                                                $badgeClass = 'badge-excel';
                                                $icon = 'fa-file-excel';
                                                $jenis_text = 'EXCEL';
                                                break;
                                            case 'pdf':
                                                $badgeClass = 'badge-pdf';
                                                $icon = 'fa-file-pdf';
                                                $jenis_text = 'PDF';
                                                break;
                                            default:
                                                $badgeClass = 'badge-laporan';
                                                $icon = 'fa-file';
                                                $jenis_text = strtoupper($row['jenis_laporan']);
                                        }
                                        
                                        // Format informasi filter
                                        $filter_info = "Semua Wilayah";
                                        if ($filter && is_array($filter)) {
                                            $filter_parts = [];
                                            if (!empty($filter['provinsi'])) {
                                                $filter_parts[] = "Provinsi: " . $filter['provinsi'];
                                            }
                                            if (!empty($filter['kota'])) {
                                                $filter_parts[] = "Kota: " . $filter['kota'];
                                            }
                                            if (!empty($filter['kecamatan'])) {
                                                $filter_parts[] = "Kecamatan: " . $filter['kecamatan'];
                                            }
                                            if (!empty($filter['desa'])) {
                                                $filter_parts[] = "Desa: " . $filter['desa'];
                                            }
                                            if (!empty($filter_parts)) {
                                                $filter_info = implode(" → ", $filter_parts);
                                            }
                                        }
                                        ?>
                                        <tr data-jenis="<?= $row['jenis_laporan'] ?>" data-tanggal="<?= date('Y-m-d', strtotime($row['tanggal'])) ?>">
                                            <td>
                                                <span class="badge <?= $badgeClass ?>">
                                                    <i class="fas <?= $icon ?>"></i> <?= $jenis_text ?>
                                                </span>
                                            </td>
                                            <td>
                                                <strong><?= htmlspecialchars($row['nama_laporan']) ?></strong>
                                            </td>
                                            <td>
                                                <div class="filter-display">
                                                    <strong><?= htmlspecialchars($filter_info) ?></strong>
                                                    <?php if ($filter && is_array($filter)): ?>
                                                        <div class="json-display" style="margin-top: 5px;">
                                                            <strong>Detail Filter:</strong><br>
                                                            <?php 
                                                            foreach($filter as $key => $value) {
                                                                if (!empty($value) && $key !== 'timestamp') {
                                                                    echo htmlspecialchars($key) . ": " . htmlspecialchars($value) . "<br>";
                                                                }
                                                            }
                                                            if (isset($filter['timestamp'])) {
                                                                echo "Waktu: " . date('d/m/Y H:i', strtotime($filter['timestamp']));
                                                            }
                                                            ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <span style="color: #6c757d;">
                                                    <i class="fas fa-user"></i> <?= htmlspecialchars($row['nama_user']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="timestamp" title="Klik untuk menyalin">
                                                    <?= date('d/m/Y H:i', strtotime($row['tanggal'])) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <button onclick="hapusRiwayatLaporan(<?= $row['id'] ?>, '<?= htmlspecialchars($row['nama_laporan']) ?>')" 
                                                        class="action-btn delete" title="Hapus Riwayat">
                                                    <i class="fas fa-trash"></i> Hapus
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6">
                                            <div class="empty-state">
                                                <i class="fas fa-file-alt"></i>
                                                <h3>Tidak ada riwayat laporan</h3>
                                                <p>Belum ada laporan PDF/Excel yang dibuat</p>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Tab Content - Riwayat Wilayah -->
                <div id="tab-wilayah" class="tab-content">
                    <!-- Filter Section -->
                    <div class="filter-section">
                        <div class="filter-grid">
                            <div class="filter-group">
                                <label class="filter-label">Jenis Aktivitas</label>
                                <select class="filter-select" id="filterJenisWilayah">
                                    <option value="">Semua Jenis</option>
                                    <option value="TAMBAH_WILAYAH">Data Ditambahkan</option>
                                    <option value="EDIT_WILAYAH">Data Diedit</option>
                                    <option value="HAPUS_WILAYAH">Data Dihapus</option>
                                </select>
                            </div>
                            
                            <div class="filter-group">
                                <label class="filter-label">Periode Waktu</label>
                                <select class="filter-select" id="filterTanggalWilayah">
                                    <option value="">Semua Tanggal</option>
                                    <option value="today">Hari Ini</option>
                                    <option value="week">7 Hari Terakhir</option>
                                    <option value="month">30 Hari Terakhir</option>
                                </select>
                            </div>
                            
                            <div class="filter-actions">
                                <button class="btn btn-primary" onclick="filterRiwayatWilayah()">
                                    <i class="fas fa-filter"></i> Terapkan Filter
                                </button>
                                <button class="btn btn-light" onclick="resetFilterWilayah()">
                                    <i class="fas fa-refresh"></i> Reset
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Table -->
                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th width="100">Jenis</th>
                                    <th width="120">Kode</th>
                                    <th>Nama Wilayah</th>
                                    <th>Deskripsi</th>
                                    <th width="150">Waktu</th>
                                    <th width="100">Aksi</th>
                                </tr>
                            </thead>
                            <tbody id="tbody-wilayah">
                                <?php if (is_array($riwayatWilayah) && count($riwayatWilayah) > 0): ?>
                                    <?php foreach ($riwayatWilayah as $row): ?>
                                        <?php
                                        $jenis = $row['jenis'];
                                        $badgeClass = '';
                                        $icon = '';
                                        
                                        switch($jenis) {
                                            case 'TAMBAH_WILAYAH':
                                                $badgeClass = 'badge-tambah';
                                                $icon = 'fa-plus';
                                                break;
                                            case 'EDIT_WILAYAH':
                                                $badgeClass = 'badge-edit';
                                                $icon = 'fa-edit';
                                                break;
                                            case 'HAPUS_WILAYAH':
                                                $badgeClass = 'badge-hapus';
                                                $icon = 'fa-trash';
                                                break;
                                        }
                                        ?>
                                        <tr data-jenis="<?= $jenis ?>" data-tanggal="<?= date('Y-m-d', strtotime($row['tanggal'])) ?>">
                                            <td>
                                                <span class="badge <?= $badgeClass ?>">
                                                    <i class="fas <?= $icon ?>"></i> 
                                                    <?= str_replace('_', ' ', $jenis) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <code><?= htmlspecialchars($row['kode_wilayah']) ?></code>
                                            </td>
                                            <td>
                                                <strong><?= htmlspecialchars($row['nama']) ?></strong>
                                            </td>
                                            <td>
                                                <div>
                                                    <strong><?= $row['deskripsi'] ?></strong>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="timestamp" title="Klik untuk menyalin">
                                                    <?= date('d/m/Y H:i', strtotime($row['tanggal'])) ?>
                                                </span>
                                                <div class="user-info">
                                                    <i class="fas fa-user"></i> <?= htmlspecialchars($row['nama_user']) ?>
                                                </div>
                                            </td>
                                            <td>
                                                <button onclick="hapusRiwayatWilayah('<?= $row['kode_wilayah'] ?>', '<?= htmlspecialchars($row['nama']) ?>', '<?= $jenis ?>')" 
                                                        class="action-btn delete" title="Hapus Riwayat">
                                                    <i class="fas fa-trash"></i> Hapus
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6">
                                            <div class="empty-state">
                                                <i class="fas fa-layer-group"></i>
                                                <h3>Tidak ada riwayat wilayah</h3>
                                                <p>Belum ada data wilayah yang tercatat</p>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Last Updated -->
                <div class="last-updated">
                    <i class="fas fa-clock"></i> Data terakhir diperbarui: <?= date('d/m/Y H:i:s') ?>
                </div>
            </div>
        </div>
    </div>

    <script>
           // Tab functionality
           function openTab(tabName) {
            // Hide all tab contents
            const tabContents = document.getElementsByClassName('tab-content');
            for (let i = 0; i < tabContents.length; i++) {
                tabContents[i].classList.remove('active');
            }

            // Remove active class from all tab buttons
            const tabButtons = document.getElementsByClassName('tab-btn');
            for (let i = 0; i < tabButtons.length; i++) {
                tabButtons[i].classList.remove('active');
            }

            // Show the specific tab content
            document.getElementById(tabName).classList.add('active');

            // Add active class to the button that opened the tab
            event.currentTarget.classList.add('active');
        }

        // Filter functions
        function filterRiwayatLokasi() {
            const jenis = document.getElementById('filterJenisLokasi').value;
            const tanggal = document.getElementById('filterTanggalLokasi').value;
            
            const rows = document.querySelectorAll('#tbody-lokasi tr');
            let visibleCount = 0;
            
            rows.forEach(row => {
                let showRow = true;
                
                // Filter by jenis
                if (jenis) {
                    const rowJenis = row.getAttribute('data-jenis');
                    if (rowJenis !== jenis) {
                        showRow = false;
                    }
                }
                
                // Filter by tanggal
                if (tanggal && showRow) {
                    const rowTanggal = row.getAttribute('data-tanggal');
                    const rowDate = new Date(rowTanggal);
                    const today = new Date();
                    let dateLimit = new Date();
                    
                    switch(tanggal) {
                        case 'today':
                            dateLimit.setDate(today.getDate() - 1);
                            break;
                        case 'week':
                            dateLimit.setDate(today.getDate() - 7);
                            break;
                        case 'month':
                            dateLimit.setMonth(today.getMonth() - 1);
                            break;
                    }
                    
                    if (rowDate < dateLimit) {
                        showRow = false;
                    }
                }
                
                row.style.display = showRow ? '' : 'none';
                if (showRow) visibleCount++;
            });
            
            // Show message if no results
            if (visibleCount === 0) {
                showNotification('Tidak ada data yang sesuai dengan filter yang dipilih.', 'warning');
            } else {
                showNotification(`Menampilkan ${visibleCount} data`, 'success');
            }
        }

        function filterRiwayatLaporan() {
            const jenis = document.getElementById('filterJenisLaporan').value;
            const tanggal = document.getElementById('filterTanggalLaporan').value;
            
            const rows = document.querySelectorAll('#tbody-laporan tr');
            let visibleCount = 0;
            
            rows.forEach(row => {
                let showRow = true;
                
                // Filter by jenis
                if (jenis) {
                    const rowJenis = row.getAttribute('data-jenis');
                    if (rowJenis !== jenis) {
                        showRow = false;
                    }
                }
                
                // Filter by tanggal
                if (tanggal && showRow) {
                    const rowTanggal = row.getAttribute('data-tanggal');
                    const rowDate = new Date(rowTanggal);
                    const today = new Date();
                    let dateLimit = new Date();
                    
                    switch(tanggal) {
                        case 'today':
                            dateLimit.setDate(today.getDate() - 1);
                            break;
                        case 'week':
                            dateLimit.setDate(today.getDate() - 7);
                            break;
                        case 'month':
                            dateLimit.setMonth(today.getMonth() - 1);
                            break;
                    }
                    
                    if (rowDate < dateLimit) {
                        showRow = false;
                    }
                }
                
                row.style.display = showRow ? '' : 'none';
                if (showRow) visibleCount++;
            });
            
            if (visibleCount === 0) {
                showNotification('Tidak ada data yang sesuai dengan filter yang dipilih.', 'warning');
            } else {
                showNotification(`Menampilkan ${visibleCount} data`, 'success');
            }
        }

        function filterRiwayatWilayah() {
            const jenis = document.getElementById('filterJenisWilayah').value;
            const tanggal = document.getElementById('filterTanggalWilayah').value;
            
            const rows = document.querySelectorAll('#tbody-wilayah tr');
            let visibleCount = 0;
            
            rows.forEach(row => {
                let showRow = true;
                
                // Filter by jenis
                if (jenis) {
                    const rowJenis = row.getAttribute('data-jenis');
                    if (rowJenis !== jenis) {
                        showRow = false;
                    }
                }
                
                // Filter by tanggal
                if (tanggal && showRow) {
                    const rowTanggal = row.getAttribute('data-tanggal');
                    const rowDate = new Date(rowTanggal);
                    const today = new Date();
                    let dateLimit = new Date();
                    
                    switch(tanggal) {
                        case 'today':
                            dateLimit.setDate(today.getDate() - 1);
                            break;
                        case 'week':
                            dateLimit.setDate(today.getDate() - 7);
                            break;
                        case 'month':
                            dateLimit.setMonth(today.getMonth() - 1);
                            break;
                    }
                    
                    if (rowDate < dateLimit) {
                        showRow = false;
                    }
                }
                
                row.style.display = showRow ? '' : 'none';
                if (showRow) visibleCount++;
            });
            
            if (visibleCount === 0) {
                showNotification('Tidak ada data yang sesuai dengan filter yang dipilih.', 'warning');
            } else {
                showNotification(`Menampilkan ${visibleCount} data`, 'success');
            }
        }

        function resetFilterLokasi() {
            document.getElementById('filterJenisLokasi').value = '';
            document.getElementById('filterTanggalLokasi').value = '';
            
            const rows = document.querySelectorAll('#tbody-lokasi tr');
            rows.forEach(row => {
                row.style.display = '';
            });
            
            showNotification('Filter telah direset', 'info');
        }

        function resetFilterLaporan() {
            document.getElementById('filterJenisLaporan').value = '';
            document.getElementById('filterTanggalLaporan').value = '';
            
            const rows = document.querySelectorAll('#tbody-laporan tr');
            rows.forEach(row => {
                row.style.display = '';
            });
            
            showNotification('Filter telah direset', 'info');
        }

        function resetFilterWilayah() {
            document.getElementById('filterJenisWilayah').value = '';
            document.getElementById('filterTanggalWilayah').value = '';
            
            const rows = document.querySelectorAll('#tbody-wilayah tr');
            rows.forEach(row => {
                row.style.display = '';
            });
            
            showNotification('Filter telah direset', 'info');
        }

        // Fungsi hapus riwayat
        function hapusRiwayatLokasi(kode, nama, jenis) {
            if (confirm(`Apakah Anda yakin ingin menghapus riwayat ${jenis} untuk lokasi "${nama}"?`)) {
                // AJAX request untuk hapus riwayat lokasi
                fetch('proses/proses_hapus_riwayat.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `tipe=lokasi&kode=${encodeURIComponent(kode)}&jenis=${encodeURIComponent(jenis)}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showNotification('Riwayat lokasi berhasil dihapus', 'success');
                        // Refresh halaman setelah 1 detik
                        setTimeout(() => {
                            location.reload();
                        }, 1000);
                    } else {
                        showNotification('Gagal menghapus riwayat: ' + data.message, 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showNotification('Terjadi kesalahan saat menghapus riwayat', 'error');
                });
            }
        }

        function hapusRiwayatLaporan(id, nama) {
            if (confirm(`Apakah Anda yakin ingin menghapus riwayat laporan "${nama}"?`)) {
                // AJAX request untuk hapus riwayat laporan
                fetch('proses/proses_hapus_riwayat.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `tipe=laporan&id=${id}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showNotification('Riwayat laporan berhasil dihapus', 'success');
                        // Refresh halaman setelah 1 detik
                        setTimeout(() => {
                            location.reload();
                        }, 1000);
                    } else {
                        showNotification('Gagal menghapus riwayat: ' + data.message, 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showNotification('Terjadi kesalahan saat menghapus riwayat', 'error');
                });
            }
        }

        function hapusRiwayatWilayah(kode, nama, jenis) {
            if (confirm(`Apakah Anda yakin ingin menghapus riwayat ${jenis} untuk wilayah "${nama}"?`)) {
                // AJAX request untuk hapus riwayat wilayah
                fetch('proses/proses_hapus_riwayat.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `tipe=wilayah&kode=${encodeURIComponent(kode)}&jenis=${encodeURIComponent(jenis)}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showNotification('Riwayat wilayah berhasil dihapus', 'success');
                        // Refresh halaman setelah 1 detik
                        setTimeout(() => {
                            location.reload();
                        }, 1000);
                    } else {
                        showNotification('Gagal menghapus riwayat: ' + data.message, 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showNotification('Terjadi kesalahan saat menghapus riwayat', 'error');
                });
            }
        }

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

        // Copy timestamp functionality
        document.addEventListener('DOMContentLoaded', function() {
            const timestamps = document.querySelectorAll('.timestamp');
            timestamps.forEach(timestamp => {
                timestamp.addEventListener('click', function() {
                    const textToCopy = this.textContent;
                    navigator.clipboard.writeText(textToCopy).then(() => {
                        const originalText = this.textContent;
                        this.textContent = 'Tersalin!';
                        this.style.background = '#28a745';
                        this.style.color = 'white';
                        
                        setTimeout(() => {
                            this.textContent = originalText;
                            this.style.background = '#f8f9fa';
                            this.style.color = '#6c757d';
                        }, 1500);
                    });
                });
            });
        });

        // Auto refresh every 60 seconds
        setInterval(() => {
            const lastUpdated = document.querySelector('.last-updated');
            if (lastUpdated) {
                lastUpdated.innerHTML = `<i class="fas fa-clock"></i> Data terakhir diperbarui: ${new Date().toLocaleString('id-ID')}`;
            }
        }, 60000);
    </script>
</body>
</html>