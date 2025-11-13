<?php
include "config/db.php";
session_start();

// Set timezone
date_default_timezone_set('Asia/Jakarta');

// Fungsi untuk mendapatkan nama user berdasarkan username
function getNamaUser($conn, $username) {
    if (empty($username) || $username === 'system' || $username === 'System') {
        return 'System';
    }
    
    // Gunakan query langsung untuk menghindari error prepare
    $query = "SELECT nama_user FROM users WHERE username = '" . $conn->real_escape_string($username) . "'";
    $result = $conn->query($query);
    
    if ($result && $result->num_rows === 1) {
        $user = $result->fetch_assoc();
        return $user['nama_user'] ?: $username;
    }
    
    return $username; // Fallback ke username jika tidak ditemukan
}

// Simpan ke history report jika perlu
$current_report_id = "report_" . date('Y-m-d_H:i');
if (!isset($_SESSION['last_report_id']) || $_SESSION['last_report_id'] !== $current_report_id) {
    
    $filters = [
        'provinsi' => $_GET['provinsi'] ?? '',
        'kota' => $_GET['kota'] ?? '',
        'kecamatan' => $_GET['kecamatan'] ?? '',
        'desa' => $_GET['desa'] ?? '',
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    $filters_json = json_encode($filters, JSON_UNESCAPED_UNICODE);
    $nama_laporan = "Laporan Statistik Blankspot " . date('d/m/Y H:i');
    $dibuat_oleh = $_SESSION['username'] ?? 'System'; // Gunakan username dari session
    
    // Gunakan prepared statement untuk keamanan
    $stmt = $conn->prepare("INSERT INTO riwayat_laporan (nama_laporan, jenis_laporan, filter, dibuat_oleh) VALUES (?, 'view', ?, ?)");
    
    if ($stmt) {
        $stmt->bind_param("sss", $nama_laporan, $filters_json, $dibuat_oleh);
        
        if ($stmt->execute()) {
            $_SESSION['last_report_id'] = $current_report_id;
            $_SESSION['last_report_time'] = time();
        } else {
            error_log("Error menyimpan riwayat laporan: " . $stmt->error);
        }
        $stmt->close();
    } else {
        error_log("Error preparing statement: " . $conn->error);
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<title>Laporan Statistik Desa - Pemetaan Blankspot</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"/>
<!-- Tambahkan library jsPDF -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.28/jspdf.plugin.autotable.min.js"></script>
<link rel="stylesheet" href="css/report.css">
<style>
/* CSS untuk Print */
@media print {
    .app-container {
        display: block !important;
    }
    
    .sidebar {
        display: none !important;
    }
    
    .main-content {
        margin-left: 0 !important;
        width: 100% !important;
    }
    
    .page-header,
    .actions,
    .filters,
    .range-controls,
    .filter-info,
    .pagination {
        display: none !important;
    }
    
    .container {
        padding: 0 !important;
        margin: 0 !important;
        width: 100% !important;
    }
    
    .report-header {
        text-align: center;
        margin-bottom: 20px;
        border-bottom: 2px solid #000;
        padding-bottom: 10px;
    }
    
    .report-title {
        font-size: 18px !important;
        margin: 0;
        color: #000 !important;
    }
    
    .report-subtitle {
        font-size: 12px !important;
        margin: 5px 0 0 0;
        color: #000 !important;
    }
    
    .table-responsive {
        overflow: visible !important;
    }
    
    #dataTable {
        width: 100% !important;
        border-collapse: collapse;
        font-size: 10px;
    }
    
    #dataTable th,
    #dataTable td {
        border: 1px solid #000 !important;
        padding: 4px 6px;
        background: white !important;
        color: #000 !important;
    }
    
    #dataTable th {
        background: #f8f9fa !important;
        font-weight: bold;
        text-align: center;
    }
    
    #dataTable tfoot tr {
        background: #f8f9fa !important;
        font-weight: bold;
    }
    
    .status-ada,
    .status-tidak,
    .status-unknown,
    .kecepatan {
        background: transparent !important;
        color: #000 !important;
        padding: 0 !important;
        border: none !important;
    }
    
    .ada,
    .tidak {
        color: #000 !important;
    }
    
    /* Hide elements during print */
    .no-print {
        display: none !important;
    }
    
    /* Page break avoidance */
    table {
        page-break-inside: auto;
    }
    
    tr {
        page-break-inside: avoid;
        page-break-after: auto;
    }
    
    thead {
        display: table-header-group;
    }
    
    tfoot {
        display: table-footer-group;
    }
}

/* CSS untuk Excel-like appearance */
.excel-style {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}

.excel-style table {
    border-collapse: collapse;
    width: 100%;
}

.excel-style th,
.excel-style td {
    border: 1px solid #d4d4d4;
    padding: 8px 10px;
    font-size: 13px;
}

.excel-style th {
    background-color: #2c5aa0;
    color: white;
    font-weight: 600;
    text-align: center;
}

.excel-style tfoot tr {
    background-color: #f0f7ff;
    font-weight: 600;
}

.excel-style .status-ada,
.excel-style .status-tidak,
.excel-style .status-unknown {
    padding: 4px 8px;
    border-radius: 4px;
    font-weight: normal;
}

.excel-style .kecepatan {
    font-weight: normal;
}

/* Perbaikan untuk pagination - DIPINDAH KE BAWAH TABEL */
.pagination-container {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 20px;
    padding: 15px;
    background-color: #f8f9fa;
    border-radius: 8px;
    border: 1px solid #e9ecef;
}

.pagination-info {
    font-size: 14px;
    color: #495057;
    font-weight: 500;
}

.pagination-controls {
    display: flex;
    align-items: center;
    gap: 15px;
}

.pagination-buttons {
    display: flex;
    align-items: center;
    gap: 10px;
}

.pagination-btn {
    background-color: #2c5aa0;
    color: white;
    border: none;
    padding: 8px 15px;
    border-radius: 5px;
    cursor: pointer;
    font-size: 13px;
    transition: background-color 0.2s;
}

.pagination-btn:hover:not(:disabled) {
    background-color: #1e3d6f;
}

.pagination-btn:disabled {
    background-color: #a0aec0;
    cursor: not-allowed;
}

.pagination-page {
    font-size: 13px;
    color: #495057;
    font-weight: 500;
}

.jump-controls {
    display: flex;
    align-items: center;
    gap: 8px;
}

.jump-controls input {
    width: 60px;
    padding: 6px;
    border: 1px solid #ced4da;
    border-radius: 4px;
    text-align: center;
}

/* HAPUS range-info yang lama */
.range-info {
    display: none;
}

/* Perbaikan untuk tabel */
.table-responsive {
    margin-top: 20px;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 0 10px rgba(0,0,0,0.05);
}

.excel-style tr:nth-child(even) {
    background-color: #f8fafc;
}

.excel-style tr:hover {
    background-color: #f0f7ff;
}

.excel-style .kode {
    font-family: monospace;
    font-weight: 500;
}

.excel-style .ada {
    color: #10b981;
    font-weight: 600;
}

.excel-style .tidak {
    color: #ef4444;
    font-weight: 600;
}

.status-ada {
    background-color: #d1fae5;
    color: #065f46;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 600;
}

.status-tidak {
    background-color: #fee2e2;
    color: #991b1b;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 600;
}

.status-unknown {
    background-color: #e5e7eb;
    color: #374151;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 600;
}

.kecepatan {
    font-family: 'Courier New', monospace;
    font-size: 13px;
    font-weight: 600;
    color: #1e40af;
    background: #dbeafe;
    padding: 4px 8px;
    border-radius: 4px;
    text-align: center;
}

/* Responsif untuk mobile */
@media (max-width: 768px) {
    .pagination-container {
        flex-direction: column;
        gap: 15px;
    }
    
    .pagination-controls {
        flex-direction: column;
        gap: 10px;
    }
    
    .pagination-buttons {
        order: 2;
    }
    
    .jump-controls {
        order: 1;
    }
    
    .excel-style th,
    .excel-style td {
        padding: 6px 8px;
        font-size: 12px;
    }
}

/* Hapus range-controls yang lama */
.range-controls {
    display: none;
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
            <div class="page-header fade-in no-print">
                <div class="header-title">
                    <h1><i class="fas fa-chart-bar"></i> Laporan Statistik</h1>
                    <p>Analisis dan statistik data ketersediaan sinyal berdasarkan desa</p>
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

            <!-- Loading Indicator -->
            <div class="loading" id="loading">
                <div class="loading-spinner"></div>
                <p>Membuat PDF, harap tunggu...</p>
            </div>

            <div class="container fade-in">
                <!-- Actions -->
                <div class="actions no-print">
                    <div class="btn-group">
                        <button onclick="exportToPDF()" class="action-btn">
                            <i class="fas fa-file-pdf"></i> Export PDF
                        </button>
                        <button onclick="exportToExcel()" class="action-btn btn-excel">
                            <i class="fas fa-file-excel"></i> Export Excel
                        </button>
                        <button onclick="clearFilters()" class="action-btn btn-reset">
                            <i class="fas fa-refresh"></i> Reset Filter
                        </button>
                        <button onclick="window.print()" class="action-btn btn-print">
                            <i class="fas fa-print"></i> Print
                        </button>
                    </div>
                </div>

                <!-- Header Report -->
                <div class="report-header">
                    <h1 class="report-title">LAPORAN STATISTIK DESA BLANKSPOT</h1>
                    <p class="report-subtitle">Sistem Pemetaan Desa Blankspot di Aceh</p>
                </div>

                <!-- Filter Area -->
                <div class="filters no-print">
                    <div class="filter-group">
                        <label><i class="fas fa-map-marker-alt"></i> Provinsi:</label>
                        <select id="provinsi"><option value="">-- Semua Provinsi --</option></select>
                    </div>
                    <div class="filter-group">
                        <label><i class="fas fa-city"></i> Kota:</label>
                        <select id="kota"><option value="">-- Semua Kota --</option></select>
                    </div>
                    <div class="filter-group">
                        <label><i class="fas fa-building"></i> Kecamatan:</label>
                        <select id="kecamatan"><option value="">-- Semua Kecamatan --</option></select>
                    </div>
                    <div class="filter-group">
                        <label><i class="fas fa-home"></i> Desa:</label>
                        <select id="desa"><option value="">-- Semua Desa --</option></select>
                    </div>
                </div>

                <!-- Controls untuk Range Data - DISEMBUNYIKAN -->
                <div class="range-controls no-print" style="display: none;">
                    <div class="range-group">
                        <label><i class="fas fa-list"></i> Tampilkan data:</label>
                        <select id="recordsPerPage" onchange="changeRecordsPerPage()">
                            <option value="10">10</option>
                            <option value="25">25</option>
                            <option value="50" selected>50</option>
                            <option value="100">100</option>
                            <option value="250">250</option>
                            <option value="500">500</option>
                            <option value="1000">1000</option>
                            <option value="0">Semua</option>
                        </select>
                        <span>entri per halaman</span>
                    </div>
                    <div class="range-group">
                        <label><i class="fas fa-arrow-right"></i> Lompat ke halaman:</label>
                        <div class="jump-controls">
                            <input type="number" id="jumpToPage" min="1" value="1">
                            <button onclick="jumpToPage()" class="pagination-btn">Lompat</button>
                        </div>
                    </div>
                    <div class="range-info" id="rangeInfo">
                        Menampilkan 1-50 dari 0 data
                    </div>
                </div>

                <!-- Info Filter Aktif -->
                <div class="filter-info no-print">
                    <strong><i class="fas fa-filter"></i> Filter Aktif:</strong>
                    <div class="filter-path" id="filterText">
                        <span class="filter-all">SEMUA WILAYAH INDONESIA</span>
                    </div>
                </div>

                <!-- Tabel Data -->
                <div class="table-responsive">
                    <table id="dataTable" class="excel-style">
                        <thead id="tableHead">
                            <!-- Header akan diisi JavaScript -->
                        </thead>
                        <tbody id="tableBody">
                            <!-- Data akan diisi Ajax -->
                        </tbody>
                        <tfoot id="tableFooter">
                            <!-- Total akan diisi JavaScript -->
                        </tfoot>
                    </table>
                </div>

                <!-- Pagination - DIPINDAH KE BAWAH TABEL dan MENGGABUNGKAN INFO DATA -->
                <div class="pagination-container no-print" id="paginationContainer">
                    <!-- Pagination akan diisi JavaScript -->
                </div>
            </div>
        </div>
    </div>

<script>
// Deklarasi variabel global
const provinsi = document.getElementById('provinsi');
const kota = document.getElementById('kota');
const kecamatan = document.getElementById('kecamatan');
const desa = document.getElementById('desa');
const tableHead = document.getElementById('tableHead');
const tableBody = document.getElementById('tableBody');
const tableFooter = document.getElementById('tableFooter');
const filterText = document.getElementById('filterText');
const loading = document.getElementById('loading');
const recordsPerPageSelect = document.getElementById('recordsPerPage');
const rangeInfo = document.getElementById('rangeInfo');
const jumpToPageInput = document.getElementById('jumpToPage');
const paginationContainer = document.getElementById('paginationContainer');

// Variabel untuk pagination
let currentPage = 1;
let recordsPerPage = 50; // Default 50 data per halaman
let allData = [];

// Inisialisasi jsPDF
const { jsPDF } = window.jspdf;

// Fungsi untuk mendeteksi perangkat mobile
function isMobileDevice() {
    return (typeof window.orientation !== "undefined") || (navigator.userAgent.indexOf('IEMobile') !== -1);
}

// Fungsi untuk menyesuaikan records per page di mobile
function adjustForMobile() {
    if (isMobileDevice() && window.innerWidth < 768) {
        // Kurangi records per page default untuk mobile
        if (recordsPerPageSelect.value === '50') {
            recordsPerPageSelect.value = '10';
            recordsPerPage = 10;
        }
    }
}

// Fungsi untuk menampilkan error
function showError(message) {
    console.error('Error:', message);
    // Buat notifikasi error yang lebih baik
    const errorDiv = document.createElement('div');
    errorDiv.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: #dc2626;
        color: white;
        padding: 15px 20px;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        z-index: 10000;
        animation: slideInRight 0.3s ease;
        max-width: 400px;
    `;
    errorDiv.innerHTML = `<i class="fas fa-exclamation-triangle"></i> ${message}`;
    document.body.appendChild(errorDiv);
    
    setTimeout(() => {
        errorDiv.style.animation = 'slideOutRight 0.3s ease';
        setTimeout(() => errorDiv.remove(), 300);
    }, 5000);
}

// Fungsi untuk menangani response fetch dengan error handling yang baik
async function handleFetchResponse(response) {
    if (!response.ok) {
        const errorText = await response.text();
        throw new Error(`Server error: ${response.status} - ${errorText.substring(0, 200)}`);
    }
    
    // Cek content type untuk memastikan ini JSON
    const contentType = response.headers.get('content-type');
    if (!contentType || !contentType.includes('application/json')) {
        const text = await response.text();
        throw new Error(`Expected JSON but got: ${contentType}. Response: ${text.substring(0, 200)}`);
    }
    
    return response.json();
}

// Fungsi untuk export ke PDF dengan TOTAL yang di-merge
async function exportToPDF() {
    loading.classList.add('show');
    
    try {
        // Catat aktivitas export PDF
        await catatAktivitasPDF();
        
        // SELALU gunakan portrait untuk tampilan yang lebih rapi dan banyak data
        const pdf = new jsPDF('p', 'mm', 'a4');
        
        // Data untuk PDF
        const p = provinsi.value;
        const k = kota.value;
        const kc = kecamatan.value;
        const d = desa.value;
        
        // Header PDF - SEMUA DITENGAH
        const pageWidth = pdf.internal.pageSize.getWidth();
        
        const title = "LAPORAN STATISTIK BLANKSPOT";
        const subtitle = "Sistem Pemetaan Desa Blankspot di Aceh";
        const filterInfo = getFilterTextForPDF();
        const dateInfo = `Dicetak pada: ${new Date().toLocaleDateString('id-ID')} ${new Date().toLocaleTimeString('id-ID')}`;
        
        // Set judul - SEMUA DITENGAH dengan font yang optimal
        pdf.setFontSize(16);
        pdf.setFont(undefined, 'bold');
        pdf.text(title, pageWidth / 2, 20, { align: 'center' });
        
        pdf.setFontSize(10);
        pdf.setFont(undefined, 'normal');
        pdf.text(subtitle, pageWidth / 2, 28, { align: 'center' });
        
        pdf.setFontSize(9);
        pdf.text(filterInfo, pageWidth / 2, 35, { align: 'center' });
        pdf.text(dateInfo, pageWidth / 2, 42, { align: 'center' });
        
        // Tentukan header tabel berdasarkan filter yang aktif
        let headers, columnStyles, tableData;
        let totalColspan = 0; // Untuk menentukan berapa kolom yang akan di-merge
        
        if (d) {
            // Level Desa → detail lokasi dengan kolom lengkap
            headers = ['No', 'Kode Lokasi', 'Nama Lokasi', 'Koordinat', 'Keterangan', 'Status Sinyal', 'Kecepatan Sinyal'];
            columnStyles = {
                0: { cellWidth: 10, halign: 'center' },
                1: { cellWidth: 25, halign: 'center' },
                2: { cellWidth: 40, halign: 'left' },
                3: { cellWidth: 35, halign: 'center' },
                4: { cellWidth: 40, halign: 'left' },
                5: { cellWidth: 20, halign: 'center' },
                6: { cellWidth: 22, halign: 'center' }
            };
            totalColspan = 3; // Untuk level desa, TOTAL akan merge 3 kolom pertama
            
        } else {
            // Untuk semua level di atas desa, gunakan struktur berdasarkan desa
            if (kc) {
                // Level Kecamatan → tampil desa
                headers = ['No', 'Kode Desa', 'Nama Desa', 'Jumlah Lokasi', 'Lokasi Ada Sinyal', 'Lokasi Blankspot', 'Persentase'];
                columnStyles = {
                    0: { cellWidth: 15, halign: 'center' },
                    1: { cellWidth: 25, halign: 'center' },
                    2: { cellWidth: 40, halign: 'left' },
                    3: { cellWidth: 20, halign: 'center' },
                    4: { cellWidth: 20, halign: 'center' },
                    5: { cellWidth: 20, halign: 'center' },
                    6: { cellWidth: 22, halign: 'center' }
                };
                totalColspan = 3; // Untuk level kecamatan, TOTAL akan merge 3 kolom pertama
                
            } else if (k) {
                // Level Kota → tampil kecamatan
                headers = ['No', 'Kode Kecamatan', 'Nama Kecamatan', 'Jumlah Desa', 'Desa Ada Sinyal', 'Desa Blankspot', 'Persentase'];
                columnStyles = {
                    0: { cellWidth: 15, halign: 'center' },
                    1: { cellWidth: 25, halign: 'center' },
                    2: { cellWidth: 40, halign: 'left' },
                    3: { cellWidth: 20, halign: 'center' },
                    4: { cellWidth: 20, halign: 'center' },
                    5: { cellWidth: 20, halign: 'center' },
                    6: { cellWidth: 22, halign: 'center' }
                };
                totalColspan = 3; // Untuk level kota, TOTAL akan merge 3 kolom pertama
                
            } else if (p) {
                // Level Provinsi → tampil kota
                headers = ['No', 'Kode Kota', 'Nama Kota', 'Jumlah Kecamatan', 'Jumlah Desa', 'Desa Ada Sinyal', 'Desa Blankspot', 'Persentase'];
                columnStyles = {
                    0: { cellWidth: 15, halign: 'center' },
                    1: { cellWidth: 25, halign: 'center' },
                    2: { cellWidth: 40, halign: 'left' },
                    3: { cellWidth: 25, halign: 'center' },
                    4: { cellWidth: 20, halign: 'center' },
                    5: { cellWidth: 20, halign: 'center' },
                    6: { cellWidth: 20, halign: 'center' },
                    7: { cellWidth: 22, halign: 'center' }
                };
                totalColspan = 3; // Untuk level provinsi, TOTAL akan merge 3 kolom pertama
                
            } else {
                // Level Nasional → tampil provinsi
                headers = ['No', 'Kode Provinsi', 'Nama Provinsi', 'Jumlah Kota', 'Jumlah Kecamatan', 'Jumlah Desa', 'Desa Ada Sinyal', 'Desa Blankspot', 'Persentase'];
                columnStyles = {
                    0: { cellWidth: 10, halign: 'center' },
                    1: { cellWidth: 20, halign: 'center' },
                    2: { cellWidth: 30, halign: 'left' },
                    3: { cellWidth: 20, halign: 'center' },
                    4: { cellWidth: 25, halign: 'center' },
                    5: { cellWidth: 20, halign: 'center' },
                    6: { cellWidth: 20, halign: 'center' },
                    7: { cellWidth: 20, halign: 'center' },
                    8: { cellWidth: 22, halign: 'center' }
                };
                totalColspan = 3; // Untuk level nasional, TOTAL akan merge 3 kolom pertama
            }
        }
        
        // Siapkan data untuk tabel
        if (d) {
            // Format data untuk level desa (detail lokasi)
            tableData = allData.map((row, index) => {
                const statusSinyal = row.ketersediaan_sinyal === 'Yes' ? 'Ada' : 
                                   row.ketersediaan_sinyal === 'No' ? 'Tidak Ada' : 'Tidak Diketahui';
                const kecepatanSinyal = row.kecepatan_sinyal > 0 ? 
                    `${row.kecepatan_sinyal} Mbps` : '-';
                
                return [
                    (index + 1).toString(),
                    row.kode_lokasi || '-',
                    row.nama_tempat || 'Nama Tidak Tersedia',
                    row.koordinat || '-',
                    row.keterangan || '-',
                    statusSinyal,
                    kecepatanSinyal
                ];
            });
        } else {
            // Format data untuk level di atas desa (statistik berdasarkan desa)
            tableData = allData.map((row, index) => {
                const totalDesa = parseInt(row.total_desa) || 0;
                const desaAdaSinyal = parseInt(row.desa_ada_sinyal) || 0;
                const desaBlankspot = parseInt(row.desa_blankspot) || 0;
                const persentase = totalDesa > 0 ? ((desaAdaSinyal / totalDesa) * 100).toFixed(1) : 0;
                
                // Sesuaikan dengan level yang ditampilkan
                if (kc) {
                    // Level Kecamatan - tampilkan data desa
                    const jumlahLokasi = parseInt(row.jumlah_lokasi) || 0;
                    const lokasiAdaSinyal = parseInt(row.lokasi_ada_sinyal) || 0;
                    const lokasiBlankspot = parseInt(row.lokasi_blankspot) || 0;
                    const persentaseLokasi = jumlahLokasi > 0 ? ((lokasiAdaSinyal / jumlahLokasi) * 100).toFixed(1) : 0;
                    
                    return [
                        (index + 1).toString(),
                        row.kode_wilayah || '-',
                        row.nama || 'Nama Tidak Tersedia',
                        jumlahLokasi.toLocaleString(),
                        lokasiAdaSinyal.toLocaleString(),
                        lokasiBlankspot.toLocaleString(),
                        persentaseLokasi + '%'
                    ];
                } else if (k) {
                    // Level Kota - tampilkan data kecamatan
                    return [
                        (index + 1).toString(),
                        row.kode_wilayah || '-',
                        row.nama || 'Nama Tidak Tersedia',
                        totalDesa.toLocaleString(),
                        desaAdaSinyal.toLocaleString(),
                        desaBlankspot.toLocaleString(),
                        persentase + '%'
                    ];
                } else if (p) {
                    // Level Provinsi - tampilkan data kota
                    const jumlahKecamatan = parseInt(row.jumlah_kecamatan) || 0;
                    return [
                        (index + 1).toString(),
                        row.kode_wilayah || '-',
                        row.nama || 'Nama Tidak Tersedia',
                        jumlahKecamatan.toLocaleString(),
                        totalDesa.toLocaleString(),
                        desaAdaSinyal.toLocaleString(),
                        desaBlankspot.toLocaleString(),
                        persentase + '%'
                    ];
                } else {
                    // Level Nasional - tampilkan data provinsi
                    const jumlahKota = parseInt(row.jumlah_kota) || 0;
                    const jumlahKecamatan = parseInt(row.jumlah_kecamatan) || 0;
                    return [
                        (index + 1).toString(),
                        row.kode_wilayah || '-',
                        row.nama || 'Nama Tidak Tersedia',
                        jumlahKota.toLocaleString(),
                        jumlahKecamatan.toLocaleString(),
                        totalDesa.toLocaleString(),
                        desaAdaSinyal.toLocaleString(),
                        desaBlankspot.toLocaleString(),
                        persentase + '%'
                    ];
                }
            });
            
            // Tambahkan total row untuk statistik dengan MERGE CELLS
            if (!d && allData.length > 0) {
                const totals = calculateTotals(allData);
                
                // Buat array untuk row TOTAL dengan cell yang di-merge
                let totalRow = [];
                
                if (kc) {
                    // Level Kecamatan
                    const totalPersentase = totals.totalLokasi > 0 ? ((totals.totalLokasiAda / totals.totalLokasi) * 100).toFixed(1) : 0;
                    totalRow = [
                        { content: 'TOTAL', colSpan: totalColspan, styles: { halign: 'center', fontStyle: 'bold' } },
                        totals.totalLokasi.toLocaleString(),
                        totals.totalLokasiAda.toLocaleString(),
                        totals.totalLokasiBlankspot.toLocaleString(),
                        totalPersentase + '%'
                    ];
                } else if (k) {
                    // Level Kota
                    totalRow = [
                        { content: 'TOTAL', colSpan: totalColspan, styles: { halign: 'center', fontStyle: 'bold' } },
                        totals.totalDesa.toLocaleString(),
                        totals.totalDesaAda.toLocaleString(),
                        totals.totalDesaBlankspot.toLocaleString(),
                        totals.totalPersentase + '%'
                    ];
                } else if (p) {
                    // Level Provinsi
                    totalRow = [
                        { content: 'TOTAL', colSpan: totalColspan, styles: { halign: 'center', fontStyle: 'bold' } },
                        totals.totalKecamatan.toLocaleString(),
                        totals.totalDesa.toLocaleString(),
                        totals.totalDesaAda.toLocaleString(),
                        totals.totalDesaBlankspot.toLocaleString(),
                        totals.totalPersentase + '%'
                    ];
                } else {
                    // Level Nasional
                    totalRow = [
                        { content: 'TOTAL', colSpan: totalColspan, styles: { halign: 'center', fontStyle: 'bold' } },
                        totals.totalKota.toLocaleString(),
                        totals.totalKecamatan.toLocaleString(),
                        totals.totalDesa.toLocaleString(),
                        totals.totalDesaAda.toLocaleString(),
                        totals.totalDesaBlankspot.toLocaleString(),
                        totals.totalPersentase + '%'
                    ];
                }
                
                // Tambahkan row TOTAL ke tableData
                tableData.push(totalRow);
            }
        }
        
        const tableConfig = {
            startY: 48,
            head: [headers],
            body: tableData,
            theme: 'grid',
            headStyles: {
                fillColor: false, 
                textColor: 0,     
                fontStyle: 'bold',
                halign: 'center',
                fontSize: 8,
                cellPadding: 3,
                lineWidth: 0.1,
                lineColor: 0
            },
            bodyStyles: {
                halign: 'center',
                fontSize: 7,
                cellPadding: 2,
                minCellHeight: 7,
                fillColor: false, 
                textColor: 0,     
                lineWidth: 0.1,
                lineColor: 0
            },
            styles: {
                fontSize: 7,
                cellPadding: 2,
                overflow: 'linebreak',
                textColor: 0, 
                lineWidth: 0.1,
                lineColor: 0  
            },
            columnStyles: columnStyles,
            margin: { 
                top: 48,
                left: 10,
                right: 10
            },
            tableWidth: 'wrap',
            didDrawPage: function(data) {
                // Footer pada setiap halaman - DITENGAH
                pdf.setFontSize(7);
                pdf.setTextColor(0); 
                pdf.text(`Halaman ${data.pageNumber} - Laporan Statistik Blankspot`, 
                        pageWidth / 2, 
                        pdf.internal.pageSize.getHeight() - 10, 
                        { align: 'center' });
            },
            // Custom styling untuk row TOTAL
            willDrawCell: function(data) {
                // Jika ini adalah row terakhir (TOTAL) dan bukan level desa
                if (!d && data.row.index === tableData.length - 1) {
                    // Beri background yang berbeda untuk row TOTAL
                    data.doc.setFillColor(240, 240, 240);
                    data.doc.rect(data.cell.x, data.cell.y, data.cell.width, data.cell.height, 'F');
                    
                    // Teks bold untuk TOTAL
                    data.doc.setFont(undefined, 'bold');
                    data.doc.setTextColor(0);
                }
            },
            // Optimasi untuk banyak data
            pageBreak: 'auto',
            rowPageBreak: 'avoid',
            showHead: 'everyPage'
        };
        
        pdf.autoTable(tableConfig);
        
        // Simpan PDF dengan nama file yang sesuai
        let fileName = 'Laporan_Statistik_Lokasi';
        if (d) {
            const desaName = desa.options[desa.selectedIndex].textContent.replace(/[^a-zA-Z0-9]/g, '_');
            fileName += '_Desa_' + desaName;
        } else if (kc) {
            const kecName = kecamatan.options[kecamatan.selectedIndex].textContent.replace(/[^a-zA-Z0-9]/g, '_');
            fileName += '_Kecamatan_' + kecName;
        } else if (k) {
            const kotaName = kota.options[kota.selectedIndex].textContent.replace(/[^a-zA-Z0-9]/g, '_');
            fileName += '_Kota_' + kotaName;
        } else if (p) {
            const provName = provinsi.options[provinsi.selectedIndex].textContent.replace(/[^a-zA-Z0-9]/g, '_');
            fileName += '_Provinsi_' + provName;
        }
        fileName += '_' + new Date().toISOString().slice(0,10) + '.pdf';
        
        pdf.save(fileName);
        
    } catch (error) {
        console.error('Error generating PDF:', error);
        showError('Terjadi kesalahan saat membuat PDF: ' + error.message);
    } finally {
        loading.classList.remove('show');
    }
}

// Fungsi untuk export ke Excel
function exportToExcel() {
    loading.classList.add('show');
    
    try {
        const p = provinsi.value;
        const k = kota.value;
        const kc = kecamatan.value;
        const d = desa.value;
        
        // Redirect ke export_excel.php dengan parameter filter
        window.location.href = `export_excel.php?provinsi=${p}&kota=${k}&kecamatan=${kc}&desa=${d}`;
        
    } catch (error) {
        console.error('Error exporting to Excel:', error);
        showError('Terjadi kesalahan saat export Excel: ' + error.message);
    } finally {
        setTimeout(() => {
            loading.classList.remove('show');
        }, 2000);
    }
}

// Fungsi untuk print dengan tampilan yang rapi
function setupPrint() {
    // Tambahkan header print sebelum mencetak
    const beforePrint = () => {
        // Tambahkan info filter di header
        const printHeader = document.createElement('div');
        printHeader.style.cssText = `
            text-align: center;
            margin-bottom: 15px;
            padding: 10px;
            border-bottom: 1px solid #000;
            font-size: 12px;
        `;
        printHeader.innerHTML = `
            <div><strong>${getFilterTextForPDF()}</strong></div>
            <div>Dicetak pada: ${new Date().toLocaleDateString('id-ID')} ${new Date().toLocaleTimeString('id-ID')}</div>
        `;
        
        const container = document.querySelector('.container');
        container.insertBefore(printHeader, container.firstChild);
    };
    
    const afterPrint = () => {
        // Hapus header print setelah selesai
        const printHeader = document.querySelector('.container div:first-child');
        if (printHeader && printHeader.style.textAlign === 'center') {
            printHeader.remove();
        }
    };
    
    // Event listeners untuk print
    window.addEventListener('beforeprint', beforePrint);
    window.addEventListener('afterprint', afterPrint);
}

// Fungsi untuk menghitung total (hanya untuk statistik)
function calculateTotals(data) {
    let totalKota = 0;
    let totalKecamatan = 0;
    let totalDesa = 0;
    let totalDesaAda = 0;
    let totalDesaBlankspot = 0;
    let totalLokasi = 0;
    let totalLokasiAda = 0;
    let totalLokasiBlankspot = 0;
    
    data.forEach(row => {
        totalKota += parseInt(row.jumlah_kota) || 0;
        totalKecamatan += parseInt(row.jumlah_kecamatan) || 0;
        totalDesa += parseInt(row.total_desa) || 0;
        totalDesaAda += parseInt(row.desa_ada_sinyal) || 0;
        totalDesaBlankspot += parseInt(row.desa_blankspot) || 0;
        totalLokasi += parseInt(row.jumlah_lokasi) || 0;
        totalLokasiAda += parseInt(row.lokasi_ada_sinyal) || 0;
        totalLokasiBlankspot += parseInt(row.lokasi_blankspot) || 0;
    });
    
    const totalPersentase = totalDesa > 0 ? ((totalDesaAda / totalDesa) * 100).toFixed(1) : 0;
    
    return {
        totalKota,
        totalKecamatan,
        totalDesa,
        totalDesaAda,
        totalDesaBlankspot,
        totalLokasi,
        totalLokasiAda,
        totalLokasiBlankspot,
        totalPersentase
    };
}

// Fungsi untuk mendapatkan teks filter untuk PDF
function getFilterTextForPDF() {
    const p = provinsi.value;
    const k = kota.value;
    const kc = kecamatan.value;
    const d = desa.value;
    
    let filterText = 'Filter: ';
    
    if (d) {
        filterText += `Desa ${desa.options[desa.selectedIndex].textContent}`;
    } else if (kc) {
        filterText += `Kecamatan ${kecamatan.options[kecamatan.selectedIndex].textContent}`;
    } else if (k) {
        filterText += `Kota ${kota.options[kota.selectedIndex].textContent}`;
    } else if (p) {
        filterText += `Provinsi ${provinsi.options[provinsi.selectedIndex].textContent}`;
    } else {
        filterText += 'Semua Wilayah Indonesia';
    }
    
    return filterText;
}

// Fungsi untuk mencatat aktivitas export PDF
async function catatAktivitasPDF() {
    const p = provinsi.value;
    const k = kota.value;
    const kc = kecamatan.value;
    const d = desa.value;

    const filters = {
        provinsi: p,
        kota: k,
        kecamatan: kc,
        desa: d,
        timestamp: new Date().toISOString()
    };

    try {
        const response = await fetch('catat_cetak.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                filters: filters,
                jenis: 'pdf',
                nama_laporan: 'Export PDF Laporan ' + new Date().toLocaleDateString('id-ID')
            })
        });
        
        // Gunakan error handling yang sama
        await handleFetchResponse(response);
        console.log('Aktivitas PDF tercatat');
    } catch (error) {
        console.error('Error mencatat PDF:', error);
        // Tidak perlu alert di sini, karena ini hanya logging
    }
}

// Fungsi untuk mengubah jumlah data per halaman
function changeRecordsPerPage() {
    const newValue = parseInt(recordsPerPageSelect.value);
    recordsPerPage = newValue === 0 ? allData.length : newValue;
    currentPage = 1;
    displayPage(currentPage, allData);
}

// Fungsi untuk lompat ke halaman tertentu
function jumpToPage() {
    const page = parseInt(jumpToPageInput.value);
    const totalPages = Math.ceil(allData.length / recordsPerPage);
    
    if (page >= 1 && page <= totalPages) {
        currentPage = page;
        displayPage(currentPage, allData);
    } else {
        showError(`Halaman harus antara 1 dan ${totalPages}`);
    }
}

// Load dropdown dinamis dengan error handling yang diperbaiki
function loadDropdown(level) {
    let url = `report_data.php?type=${level}`;
    
    if(level === 'kota') url += `&provinsi=${provinsi.value}`;
    else if(level === 'kecamatan') url += `&provinsi=${provinsi.value}&kota=${kota.value}`;
    else if(level === 'desa') url += `&provinsi=${provinsi.value}&kota=${kota.value}&kecamatan=${kecamatan.value}`;

    fetch(url)
    .then(handleFetchResponse)
    .then(data => {
        let sel;
        if(level === 'provinsi') sel = provinsi;
        else if(level === 'kota') sel = kota;
        else if(level === 'kecamatan') sel = kecamatan;
        else sel = desa;

        sel.innerHTML = '<option value="">-- Semua --</option>';
        
        if (data && data.length > 0) {
            data.forEach(d => {
                const opt = document.createElement('option');
                opt.value = d.kode_wilayah;
                opt.textContent = d.nama;
                sel.appendChild(opt);
            });
        }
    })
    .catch(err => {
        console.error('Error loading dropdown:', err);
        // Reset dropdown jika error
        if(level === 'kota') kota.innerHTML = '<option value="">-- Semua --</option>';
        else if(level === 'kecamatan') kecamatan.innerHTML = '<option value="">-- Semua --</option>';
        else if(level === 'desa') desa.innerHTML = '<option value="">-- Semua --</option>';
        
        // Tampilkan error yang lebih informatif
        showError(`Gagal memuat data ${level}: ${err.message}`);
    });
}

// Update info filter dengan format yang lebih jelas
function updateFilterInfo() {
    const p = provinsi.value;
    const k = kota.value;
    const kc = kecamatan.value;
    const d = desa.value;

    let filterItems = [];
    
    if (p) {
        const selectedProvinsiOption = provinsi.options[provinsi.selectedIndex];
        filterItems.push(`<span class="filter-item">${selectedProvinsiOption.textContent}</span>`);
    }
    if (k) {
        const selectedKotaOption = kota.options[kota.selectedIndex];
        filterItems.push(`<span class="filter-item">${selectedKotaOption.textContent}</span>`);
    }
    if (kc) {
        const selectedKecamatanOption = kecamatan.options[kecamatan.selectedIndex];
        filterItems.push(`<span class="filter-item">${selectedKecamatanOption.textContent}</span>`);
    }
    if (d) {
        const selectedDesaOption = desa.options[desa.selectedIndex];
        filterItems.push(`<span class="filter-item">${selectedDesaOption.textContent}</span>`);
    }
    
    if (filterItems.length === 0) {
        filterText.innerHTML = '<span class="filter-all">SEMUA WILAYAH INDONESIA</span>';
    } else {
        let filterHTML = '';
        filterItems.forEach((item, index) => {
            filterHTML += item;
            if (index < filterItems.length - 1) {
                filterHTML += '<span class="arrow">→</span>';
            }
        });
        filterText.innerHTML = filterHTML;
    }
}

// Update header tabel sesuai level
function updateHeader(){
    const isDesaLevel = desa.value;
    
    if (isDesaLevel) {
        // Untuk level desa, ubah struktur tabel menjadi detail lokasi
        tableHead.innerHTML = `
            <tr>
                <th>No</th>
                <th>Kode Lokasi</th>
                <th>Nama Lokasi</th>
                <th>Koordinat</th>
                <th>Keterangan</th>
                <th>Status Sinyal</th>
                <th>Kecepatan Sinyal</th>
            </tr>
        `;
    } else {
        // Untuk level di atas desa, tampilkan struktur statistik berdasarkan desa
        if (kecamatan.value) {
            // Level Kecamatan - tampilkan data desa
            tableHead.innerHTML = `
                <tr>
                    <th>No</th>
                    <th>Kode Desa</th>
                    <th>Nama Desa</th>
                    <th>Jumlah Lokasi</th>
                    <th>Lokasi Ada Sinyal</th>
                    <th>Lokasi Blankspot</th>
                    <th>Persentase</th>
                </tr>
            `;
        } else if (kota.value) {
            // Level Kota - tampilkan data kecamatan
            tableHead.innerHTML = `
                <tr>
                    <th>No</th>
                    <th>Kode Kecamatan</th>
                    <th>Nama Kecamatan</th>
                    <th>Jumlah Desa</th>
                    <th>Desa Ada Sinyal</th>
                    <th>Desa Blankspot</th>
                    <th>Persentase</th>
                </tr>
            `;
        } else if (provinsi.value) {
            // Level Provinsi - tampilkan data kota
            tableHead.innerHTML = `
                <tr>
                    <th>No</th>
                    <th>Kode Kota</th>
                    <th>Nama Kota</th>
                    <th>Jumlah Kecamatan</th>
                    <th>Jumlah Desa</th>
                    <th>Desa Ada Sinyal</th>
                    <th>Desa Blankspot</th>
                    <th>Persentase</th>
                </tr>
            `;
        } else {
            // Level Nasional - tampilkan data provinsi
            tableHead.innerHTML = `
                <tr>
                    <th>No</th>
                    <th>Kode Provinsi</th>
                    <th>Nama Provinsi</th>
                    <th>Jumlah Kota</th>
                    <th>Jumlah Kecamatan</th>
                    <th>Jumlah Desa</th>
                    <th>Desa Ada Sinyal</th>
                    <th>Desa Blankspot</th>
                    <th>Persentase</th>
                </tr>
            `;
        }
    }
}

// Fungsi untuk menampilkan data detail lokasi (level desa)
function displayLokasiDetail(pageData, startIndex) {
    tableBody.innerHTML = '';
    
    pageData.forEach((row, i) => {
        const actualIndex = startIndex + i;
        const statusSinyal = row.ketersediaan_sinyal === 'Yes' ? 
            '<span class="status-ada">Ada </span>' : 
            row.ketersediaan_sinyal === 'No' ? 
            '<span class="status-tidak">Tidak Ada </span>' : 
            '<span class="status-unknown">Tidak Diketahui</span>';
        
        // Format kecepatan sinyal
        const kecepatanSinyal = row.kecepatan_sinyal > 0 ? 
            `${row.kecepatan_sinyal} Mbps` : 
            '-';
        
        tableBody.innerHTML += `
            <tr>
                <td>${actualIndex + 1}</td>
                <td class="kode">${row.kode_lokasi || '-'}</td>
                <td>${row.nama_tempat || 'Nama Tidak Tersedia'}</td>
                <td class="koordinat">${row.koordinat || '-'}</td>
                <td class="keterangan">${row.keterangan || '-'}</td>
                <td>${statusSinyal}</td>
                <td class="kecepatan">${kecepatanSinyal}</td>
            </tr>
        `;
    });
    
    // Untuk detail lokasi, tidak perlu footer total
    tableFooter.innerHTML = '';
}

// Fungsi untuk menampilkan data statistik berdasarkan desa
function displayStatistikDesa(pageData, startIndex) {
    tableBody.innerHTML = '';
    
    let totalKota = 0;
    let totalKecamatan = 0;
    let totalDesa = 0;
    let totalDesaAda = 0;
    let totalDesaBlankspot = 0;
    let totalLokasi = 0;
    let totalLokasiAda = 0;
    let totalLokasiBlankspot = 0;
    
    pageData.forEach((row, i) => {
        const actualIndex = startIndex + i;
        const totalDesaRow = parseInt(row.total_desa) || 0;
        const desaAdaSinyal = parseInt(row.desa_ada_sinyal) || 0;
        const desaBlankspot = parseInt(row.desa_blankspot) || 0;
        
        // Akumulasi total
        totalKota += parseInt(row.jumlah_kota) || 0;
        totalKecamatan += parseInt(row.jumlah_kecamatan) || 0;
        totalDesa += totalDesaRow;
        totalDesaAda += desaAdaSinyal;
        totalDesaBlankspot += desaBlankspot;
        totalLokasi += parseInt(row.jumlah_lokasi) || 0;
        totalLokasiAda += parseInt(row.lokasi_ada_sinyal) || 0;
        totalLokasiBlankspot += parseInt(row.lokasi_blankspot) || 0;
        
        // Tampilkan data sesuai level
        if (kecamatan.value) {
            // Level Kecamatan - tampilkan data desa
            const jumlahLokasi = parseInt(row.jumlah_lokasi) || 0;
            const lokasiAdaSinyal = parseInt(row.lokasi_ada_sinyal) || 0;
            const lokasiBlankspot = parseInt(row.lokasi_blankspot) || 0;
            const persentase = jumlahLokasi > 0 ? ((lokasiAdaSinyal / jumlahLokasi) * 100).toFixed(1) : 0;
            
            tableBody.innerHTML += `
                <tr>
                    <td>${actualIndex + 1}</td>
                    <td class="kode">${row.kode_wilayah || '-'}</td>
                    <td>${row.nama || 'Nama Tidak Tersedia'}</td>
                    <td>${jumlahLokasi.toLocaleString()}</td>
                    <td class="ada">${lokasiAdaSinyal.toLocaleString()}</td>
                    <td class="tidak">${lokasiBlankspot.toLocaleString()}</td>
                    <td><strong>${persentase}%</strong></td>
                </tr>
            `;
        } else if (kota.value) {
            // Level Kota - tampilkan data kecamatan
            const persentase = totalDesaRow > 0 ? ((desaAdaSinyal / totalDesaRow) * 100).toFixed(1) : 0;
            
            tableBody.innerHTML += `
                <tr>
                    <td>${actualIndex + 1}</td>
                    <td class="kode">${row.kode_wilayah || '-'}</td>
                    <td>${row.nama || 'Nama Tidak Tersedia'}</td>
                    <td>${totalDesaRow.toLocaleString()}</td>
                    <td class="ada">${desaAdaSinyal.toLocaleString()}</td>
                    <td class="tidak">${desaBlankspot.toLocaleString()}</td>
                    <td><strong>${persentase}%</strong></td>
                </tr>
            `;
        } else if (provinsi.value) {
            // Level Provinsi - tampilkan data kota
            const jumlahKecamatan = parseInt(row.jumlah_kecamatan) || 0;
            const persentase = totalDesaRow > 0 ? ((desaAdaSinyal / totalDesaRow) * 100).toFixed(1) : 0;
            
            tableBody.innerHTML += `
                <tr>
                    <td>${actualIndex + 1}</td>
                    <td class="kode">${row.kode_wilayah || '-'}</td>
                    <td>${row.nama || 'Nama Tidak Tersedia'}</td>
                    <td>${jumlahKecamatan.toLocaleString()}</td>
                    <td>${totalDesaRow.toLocaleString()}</td>
                    <td class="ada">${desaAdaSinyal.toLocaleString()}</td>
                    <td class="tidak">${desaBlankspot.toLocaleString()}</td>
                    <td><strong>${persentase}%</strong></td>
                </tr>
            `;
        } else {
            // Level Nasional - tampilkan data provinsi
            const jumlahKota = parseInt(row.jumlah_kota) || 0;
            const jumlahKecamatan = parseInt(row.jumlah_kecamatan) || 0;
            const persentase = totalDesaRow > 0 ? ((desaAdaSinyal / totalDesaRow) * 100).toFixed(1) : 0;
            
            tableBody.innerHTML += `
                <tr>
                    <td>${actualIndex + 1}</td>
                    <td class="kode">${row.kode_wilayah || '-'}</td>
                    <td>${row.nama || 'Nama Tidak Tersedia'}</td>
                    <td>${jumlahKota.toLocaleString()}</td>
                    <td>${jumlahKecamatan.toLocaleString()}</td>
                    <td>${totalDesaRow.toLocaleString()}</td>
                    <td class="ada">${desaAdaSinyal.toLocaleString()}</td>
                    <td class="tidak">${desaBlankspot.toLocaleString()}</td>
                    <td><strong>${persentase}%</strong></td>
                </tr>
            `;
        }
    });
    
    // Update footer dengan total
    if (kecamatan.value) {
        const totalPersentase = totalLokasi > 0 ? ((totalLokasiAda / totalLokasi) * 100).toFixed(1) : 0;
        tableFooter.innerHTML = `
            <tr style="background: #f0f7ff; font-weight: 600;">
                <td colspan="3">TOTAL</td>
                <td>${totalLokasi.toLocaleString()}</td>
                <td>${totalLokasiAda.toLocaleString()}</td>
                <td>${totalLokasiBlankspot.toLocaleString()}</td>
                <td>${totalPersentase}%</td>
            </tr>
        `;
    } else if (kota.value) {
        const totalPersentase = totalDesa > 0 ? ((totalDesaAda / totalDesa) * 100).toFixed(1) : 0;
        tableFooter.innerHTML = `
            <tr style="background: #f0f7ff; font-weight: 600;">
                <td colspan="3">TOTAL</td>
                <td>${totalDesa.toLocaleString()}</td>
                <td>${totalDesaAda.toLocaleString()}</td>
                <td>${totalDesaBlankspot.toLocaleString()}</td>
                <td>${totalPersentase}%</td>
            </tr>
        `;
    } else if (provinsi.value) {
        const totalPersentase = totalDesa > 0 ? ((totalDesaAda / totalDesa) * 100).toFixed(1) : 0;
        tableFooter.innerHTML = `
            <tr style="background: #f0f7ff; font-weight: 600;">
                <td colspan="3">TOTAL</td>
                <td>${totalKecamatan.toLocaleString()}</td>
                <td>${totalDesa.toLocaleString()}</td>
                <td>${totalDesaAda.toLocaleString()}</td>
                <td>${totalDesaBlankspot.toLocaleString()}</td>
                <td>${totalPersentase}%</td>
            </tr>
        `;
    } else {
        const totalPersentase = totalDesa > 0 ? ((totalDesaAda / totalDesa) * 100).toFixed(1) : 0;
        tableFooter.innerHTML = `
            <tr style="background: #f0f7ff; font-weight: 600;">
                <td colspan="3">TOTAL</td>
                <td>${totalKota.toLocaleString()}</td>
                <td>${totalKecamatan.toLocaleString()}</td>
                <td>${totalDesa.toLocaleString()}</td>
                <td>${totalDesaAda.toLocaleString()}</td>
                <td>${totalDesaBlankspot.toLocaleString()}</td>
                <td>${totalPersentase}%</td>
            </tr>
        `;
    }
}

// Fungsi untuk menampilkan data dengan pagination
function displayPage(page, data) {
    const startIndex = (page - 1) * recordsPerPage;
    const endIndex = recordsPerPage === 0 ? data.length : Math.min(startIndex + recordsPerPage, data.length);
    const pageData = data.slice(startIndex, endIndex);
    
    tableBody.innerHTML = '';
    
    if (pageData.length === 0) {
        const colspan = desa.value ? 7 : (kecamatan.value ? 7 : (kota.value ? 7 : (provinsi.value ? 8 : 9)));
        tableBody.innerHTML = `<tr><td colspan="${colspan}" style="text-align:center; padding: 30px; color: #64748b;">Tidak ada data yang sesuai dengan filter</td></tr>`;
        paginationContainer.innerHTML = '';
        return;
    }
    
    const isDesaLevel = desa.value;
    
    if (isDesaLevel) {
        // TAMPILAN DETAIL LOKASI untuk level desa
        displayLokasiDetail(pageData, startIndex);
    } else {
        // TAMPILAN STATISTIK berdasarkan desa untuk level di atas desa
        displayStatistikDesa(pageData, startIndex);
    }
    
    // Update input lompat ke halaman
    jumpToPageInput.value = page;
    jumpToPageInput.max = Math.ceil(data.length / (recordsPerPage || 1));
    
    // Update info pagination - SEKARANG MENGGABUNGKAN INFO DATA
    updatePaginationInfo(data.length, page);
}

// Fungsi untuk update info pagination - DIPERBAIKI UNTUK MENGGABUNGKAN INFO DATA
function updatePaginationInfo(totalRecords, currentPage) {
    const totalPages = Math.ceil(totalRecords / (recordsPerPage || 1));
    const startRecord = ((currentPage - 1) * recordsPerPage) + 1;
    const endRecord = Math.min(currentPage * recordsPerPage, totalRecords);
    
    let paginationHTML = `
        <div class="pagination-info">
            Menampilkan ${startRecord} - ${endRecord} dari ${totalRecords.toLocaleString()} data
        </div>
        <div class="pagination-controls">
            <div class="pagination-buttons">
                <button class="pagination-btn" onclick="changePage(${currentPage - 1})" ${currentPage === 1 ? 'disabled' : ''}>
                    <i class="fas fa-chevron-left"></i> Sebelumnya
                </button>
                <span class="pagination-page">Halaman ${currentPage} dari ${totalPages}</span>
                <button class="pagination-btn" onclick="changePage(${currentPage + 1})" ${currentPage === totalPages ? 'disabled' : ''}>
                    Berikutnya <i class="fas fa-chevron-right"></i>
                </button>
            </div>
            <div class="jump-controls">
                <input type="number" id="jumpToPage" min="1" max="${totalPages}" value="${currentPage}">
                <button onclick="jumpToPage()" class="pagination-btn">Lompat</button>
            </div>
        </div>
    `;
    
    paginationContainer.innerHTML = paginationHTML;
}

// Fungsi untuk ganti halaman
function changePage(page) {
    const totalPages = Math.ceil(allData.length / (recordsPerPage || 1));
    if (page < 1 || page > totalPages) return;
    
    currentPage = page;
    displayPage(currentPage, allData);
}

// Load tabel statistik dengan error handling yang lebih baik
function loadTable(){
    // Sesuaikan untuk mobile
    adjustForMobile();
    
    const p = provinsi.value;
    const k = kota.value;
    const kc = kecamatan.value;
    const d = desa.value;

    const url = `report_data.php?type=statistik&provinsi=${p}&kota=${k}&kecamatan=${kc}&desa=${d}`;

    // Tampilkan loading
    loading.classList.add('show');

    fetch(url)
    .then(handleFetchResponse)
    .then(data => {
        allData = data;
        currentPage = 1;
        
        if(data.error){
            tableBody.innerHTML = `<tr><td colspan="9" style="text-align:center; color:red;">Error: ${data.error}</td></tr>`;
            return;
        }
        
        if(!data || data.length === 0){
            const colspan = d ? 7 : (kc ? 7 : (k ? 7 : (p ? 8 : 9)));
            tableBody.innerHTML = `<tr><td colspan="${colspan}" style="text-align:center; padding: 30px; color: #64748b;">Tidak ada data yang sesuai dengan filter</td></tr>`;
            paginationContainer.innerHTML = '';
        } else {
            // Update header terlebih dahulu
            updateHeader();
            // Kemudian tampilkan data
            displayPage(currentPage, data);
        }
        
        updateFilterInfo();
    })
    .catch(err => {
        console.error('Fetch error:', err);
        tableBody.innerHTML = '<tr><td colspan="9" style="text-align:center; color:red;">Error loading data: ' + err.message + '</td></tr>';
        showError('Gagal memuat data statistik: ' + err.message);
    })
    .finally(() => {
        // Sembunyikan loading
        loading.classList.remove('show');
    });
}

// Reset dropdown yang lebih rendah
function resetLowerDropdowns(startFrom) {
    if (startFrom === 'provinsi') {
        kota.innerHTML = '<option value="">-- Semua Kota --</option>';
        kecamatan.innerHTML = '<option value="">-- Semua Kecamatan --</option>';
        desa.innerHTML = '<option value="">-- Semua Desa --</option>';
    } else if (startFrom === 'kota') {
        kecamatan.innerHTML = '<option value="">-- Semua Kecamatan --</option>';
        desa.innerHTML = '<option value="">-- Semua Desa --</option>';
    } else if (startFrom === 'kecamatan') {
        desa.innerHTML = '<option value="">-- Semua Desa --</option>';
    }
}

// Clear semua filter
function clearFilters() {
    provinsi.value = '';
    kota.innerHTML = '<option value="">-- Semua Kota --</option>';
    kecamatan.innerHTML = '<option value="">-- Semua Kecamatan --</option>';
    desa.innerHTML = '<option value="">-- Semua Desa --</option>';
    recordsPerPageSelect.value = '50';
    recordsPerPage = 50;
    loadTable();
}

// Event dropdown
provinsi.addEventListener('change', () => {
    resetLowerDropdowns('provinsi');
    if (provinsi.value) {
        loadDropdown('kota');
    }
    loadTable();
});

kota.addEventListener('change', () => {
    resetLowerDropdowns('kota');
    if (kota.value) {
        loadDropdown('kecamatan');
    }
    loadTable();
});

kecamatan.addEventListener('change', () => {
    resetLowerDropdowns('kecamatan');
    if (kecamatan.value) {
        loadDropdown('desa');
    }
    loadTable();
});

desa.addEventListener('change', () => {
    loadTable();
});

// Panggil adjustForMobile saat load dan resize
window.addEventListener('load', adjustForMobile);
window.addEventListener('resize', adjustForMobile);

// Setup print functionality
window.addEventListener('load', setupPrint);

// Init
loadDropdown('provinsi');
loadTable();

// Tambahkan CSS untuk animasi notifikasi
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
    .koordinat {
        font-family: monospace;
        font-size: 13px;
    }
    .keterangan {
        max-width: 200px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    @media (max-width: 768px) {
        .keterangan {
            max-width: 120px;
        }
    }
`;
document.head.appendChild(style);
</script>
</body>
</html>