<?php
include "config/db.php";
session_start();

// Ambil parameter level dan kode parent
$level = isset($_GET['level']) ? $_GET['level'] : 'provinsi';
$parent_kode = isset($_GET['parent_kode']) ? $_GET['parent_kode'] : '';
$searchKeyword = isset($_GET['search']) ? $_GET['search'] : '';

// Query data berdasarkan level
$tableData = [];
$tableTitle = '';
$breadcrumbs = [];
$currentParentData = []; // Untuk menyimpan data parent saat ini

if ($level == 'provinsi') {
    $tableTitle = 'Data Provinsi';
    $query = "SELECT * FROM wilayah WHERE level = 'provinsi' ORDER BY nama ASC";
    $result = $conn->query($query);
    while ($row = $result->fetch_assoc()) {
        $tableData[] = $row;
    }
    
    // Breadcrumb
    $breadcrumbs[] = ['name' => 'Provinsi', 'url' => 'hasil_pemetaan.php?level=provinsi'];
    
} elseif ($level == 'kota' && $parent_kode) {
    $tableTitle = 'Data Kabupaten/Kota';
    
    // Dapatkan info parent (provinsi)
    $parentQuery = $conn->query("SELECT * FROM wilayah WHERE kode_wilayah = '$parent_kode'");
    $currentParentData = $parentQuery->fetch_assoc();
    
    $query = "SELECT * FROM wilayah WHERE level = 'kota' AND parent_kode = '$parent_kode' ORDER BY nama ASC";
    $result = $conn->query($query);
    while ($row = $result->fetch_assoc()) {
        $tableData[] = $row;
    }
    
    // Breadcrumb
    $breadcrumbs[] = ['name' => 'Provinsi', 'url' => 'hasil_pemetaan.php?level=provinsi'];
    $breadcrumbs[] = ['name' => $currentParentData['nama'], 'url' => '#'];
    
} elseif ($level == 'kecamatan' && $parent_kode) {
    $tableTitle = 'Data Kecamatan';
    
    // Dapatkan info parent (kota) dan grandparent (provinsi)
    $parentQuery = $conn->query("SELECT * FROM wilayah WHERE kode_wilayah = '$parent_kode'");
    $currentParentData = $parentQuery->fetch_assoc();
    
    $grandParentQuery = $conn->query("SELECT * FROM wilayah WHERE kode_wilayah = '{$currentParentData['parent_kode']}'");
    $grandParentData = $grandParentQuery->fetch_assoc();
    
    $query = "SELECT * FROM wilayah WHERE level = 'kecamatan' AND parent_kode = '$parent_kode' ORDER BY nama ASC";
    $result = $conn->query($query);
    while ($row = $result->fetch_assoc()) {
        $tableData[] = $row;
    }
    
    // Breadcrumb
    $breadcrumbs[] = ['name' => 'Provinsi', 'url' => 'hasil_pemetaan.php?level=provinsi'];
    $breadcrumbs[] = ['name' => $grandParentData['nama'], 'url' => "hasil_pemetaan.php?level=kota&parent_kode={$grandParentData['kode_wilayah']}"];
    $breadcrumbs[] = ['name' => $currentParentData['nama'], 'url' => '#'];
    
} elseif ($level == 'desa' && $parent_kode) {
    $tableTitle = 'Data Desa';
    
    // Dapatkan info parent (kecamatan), grandparent (kota), dan great-grandparent (provinsi)
    $parentQuery = $conn->query("SELECT * FROM wilayah WHERE kode_wilayah = '$parent_kode'");
    $currentParentData = $parentQuery->fetch_assoc();
    
    $grandParentQuery = $conn->query("SELECT * FROM wilayah WHERE kode_wilayah = '{$currentParentData['parent_kode']}'");
    $grandParentData = $grandParentQuery->fetch_assoc();
    
    $greatGrandParentQuery = $conn->query("SELECT * FROM wilayah WHERE kode_wilayah = '{$grandParentData['parent_kode']}'");
    $greatGrandParentData = $greatGrandParentQuery->fetch_assoc();
    
    $query = "SELECT * FROM wilayah WHERE level = 'desa' AND parent_kode = '$parent_kode' ORDER BY nama ASC";
    $result = $conn->query($query);
    while ($row = $result->fetch_assoc()) {
        $tableData[] = $row;
    }
    
    // Breadcrumb
    $breadcrumbs[] = ['name' => 'Provinsi', 'url' => 'hasil_pemetaan.php?level=provinsi'];
    $breadcrumbs[] = ['name' => $greatGrandParentData['nama'], 'url' => "hasil_pemetaan.php?level=kota&parent_kode={$greatGrandParentData['kode_wilayah']}"];
    $breadcrumbs[] = ['name' => $grandParentData['nama'], 'url' => "hasil_pemetaan.php?level=kecamatan&parent_kode={$grandParentData['kode_wilayah']}"];
    $breadcrumbs[] = ['name' => $currentParentData['nama'], 'url' => '#'];
    
} elseif ($level == 'lokasi' && $parent_kode) {
    $tableTitle = 'Data Lokasi Blankspot';
    
    // Dapatkan info parent (desa), grandparent (kecamatan), great-grandparent (kota), dan great-great-grandparent (provinsi)
    $parentQuery = $conn->query("SELECT * FROM wilayah WHERE kode_wilayah = '$parent_kode'");
    $currentParentData = $parentQuery->fetch_assoc();
    
    $grandParentQuery = $conn->query("SELECT * FROM wilayah WHERE kode_wilayah = '{$currentParentData['parent_kode']}'");
    $grandParentData = $grandParentQuery->fetch_assoc();
    
    $greatGrandParentQuery = $conn->query("SELECT * FROM wilayah WHERE kode_wilayah = '{$grandParentData['parent_kode']}'");
    $greatGrandParentData = $greatGrandParentQuery->fetch_assoc();
    
    $greatGreatGrandParentQuery = $conn->query("SELECT * FROM wilayah WHERE kode_wilayah = '{$greatGrandParentData['parent_kode']}'");
    $greatGreatGrandParentData = $greatGreatGrandParentQuery->fetch_assoc();
    
    $query = "SELECT * FROM lokasi WHERE kode_wilayah = '$parent_kode'";
    if ($searchKeyword != '') {
        $searchKeyword = $conn->real_escape_string($searchKeyword);
        $query .= " AND (nama_tempat LIKE '%$searchKeyword%' 
                        OR keterangan LIKE '%$searchKeyword%' 
                        OR koordinat LIKE '%$searchKeyword%'
                        OR kode_lokasi LIKE '%$searchKeyword%')";
    }
    $query .= " ORDER BY created_at DESC";
    
    $result = $conn->query($query);
    while ($row = $result->fetch_assoc()) {
        $tableData[] = $row;
    }
    
    // Breadcrumb
    $breadcrumbs[] = ['name' => 'Provinsi', 'url' => 'hasil_pemetaan.php?level=provinsi'];
    $breadcrumbs[] = ['name' => $greatGreatGrandParentData['nama'], 'url' => "hasil_pemetaan.php?level=kota&parent_kode={$greatGreatGrandParentData['kode_wilayah']}"];
    $breadcrumbs[] = ['name' => $greatGrandParentData['nama'], 'url' => "hasil_pemetaan.php?level=kecamatan&parent_kode={$greatGrandParentData['kode_wilayah']}"];
    $breadcrumbs[] = ['name' => $grandParentData['nama'], 'url' => "hasil_pemetaan.php?level=desa&parent_kode={$grandParentData['kode_wilayah']}"];
    $breadcrumbs[] = ['name' => $currentParentData['nama'], 'url' => '#'];
}

// Hitung total data
$totalData = count($tableData);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Hasil Pemetaan Lokasi Blankspot</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css"/>
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
        --gradient-primary: linear-gradient(135deg, #4361ee 0%, #3a0ca3 100%);
        --gradient-success: linear-gradient(135deg, #10b981 0%, #059669 100%);
        --gradient-warning: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
        --gradient-danger: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
        --gradient-purple: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
        --gradient-table: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
    }

    /* Sidebar */
    .sidebar {
        width: 280px;
        background: white;
        box-shadow: 2px 0 20px rgba(0,0,0,0.1);
        padding: 20px 0;
        position: fixed;
        height: 100vh;
        overflow-y: auto;
        z-index: 1000;
    }

    /* Main Content */
    .main-content {
        flex: 1;
        margin-left: 280px;
        padding: 30px;
        width: calc(100% - 280px);
    }

    /* Header */
    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 30px;
        background: white;
        padding: 25px;
        border-radius: 15px;
        box-shadow: 0 5px 15px rgba(0,0,0,0.08);
    }

    .header-title h1 {
        font-size: 1.8rem;
        font-weight: 800;
        background: var(--gradient-primary);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
        margin-bottom: 5px;
    }

    .header-title p {
        color: #6c757d;
        font-size: 1rem;
    }

    .header-actions {
        display: flex;
        gap: 10px;
    }

    .btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 12px 20px;
        border: none;
        border-radius: 10px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
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

    /* Breadcrumb */
    .breadcrumb {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 20px;
        padding: 15px 20px;
        background: white;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }

    .breadcrumb-item {
        display: flex;
        align-items: center;
        gap: 8px;
        color: #6c757d;
        text-decoration: none;
        font-size: 14px;
        transition: color 0.3s ease;
    }

    .breadcrumb-item:hover {
        color: var(--primary);
    }

    .breadcrumb-item.active {
        color: var(--primary);
        font-weight: 600;
    }

    .breadcrumb-separator {
        color: #6c757d;
    }

    /* Action Buttons Section - Tampil di semua level kecuali lokasi */
    .action-buttons-section {
        background: white;
        padding: 20px;
        border-radius: 15px;
        box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        margin-bottom: 20px;
    }

    .action-buttons-container {
        display: flex;
        gap: 15px;
        flex-wrap: wrap;
        justify-content: center;
    }

    .action-btn {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 12px 20px;
        border: none;
        border-radius: 10px;
        text-decoration: none;
        font-weight: 600;
        transition: all 0.3s ease;
        color: white;
        font-size: 14px;
    }

    .action-btn i {
        font-size: 1.1rem;
    }

    .action-btn.batch { background: var(--gradient-purple); }
    .action-btn.add { background: var(--gradient-success); }
    .action-btn.location { background: var(--gradient-primary); }
    .action-btn.edit { background: var(--warning); }
    .action-btn.delete { background: var(--gradient-danger); }

    .action-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(0,0,0,0.2);
    }

    /* Search Section */
    .search-section {
        background: white;
        padding: 20px;
        border-radius: 15px;
        box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        margin-bottom: 20px;
    }

    .search-box {
        display: flex;
        gap: 10px;
        align-items: center;
    }

    .search-input {
        flex: 1;
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
    }

    .search-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(67, 97, 238, 0.3);
    }

    /* Table Container - DESAIN TABEL BARU DENGAN GARIS */
    .table-section {
        background: white;
        border-radius: 15px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        overflow: hidden;
        margin-bottom: 30px;
    }

    .table-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 20px 25px;
        background: var(--gradient-table);
        color: white;
    }

    .table-header h2 {
        margin: 0;
        font-size: 1.4rem;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .table-info {
        font-size: 14px;
        background: rgba(255,255,255,0.2);
        padding: 6px 12px;
        border-radius: 20px;
        font-weight: 600;
    }

    .table-container {
        overflow-x: auto;
        padding: 0;
    }

    /* TABEL DENGAN GARIS YANG JELAS */
    table {
        width: 100%;
        border-collapse: collapse;
        font-size: 14px;
        border: 2px solid #e9ecef;
        border-radius: 10px;
        overflow: hidden;
        color: #000000; /* Default text color hitam */
    }

    /* Header tabel dengan garis */
    th {
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        color: #000000; /* Header hitam */
        font-weight: 800; /* Extra bold untuk header */
        text-transform: uppercase;
        font-size: 12px;
        letter-spacing: 0.5px;
        padding: 16px 15px;
        border-bottom: 3px solid #dee2e6;
        border-right: 1px solid #dee2e6;
        position: relative;
        text-align: left;
    }

    /* Hapus border dari kolom terakhir */
    th:last-child {
        border-right: none;
    }

    th:after {
        content: '';
        position: absolute;
        bottom: -3px;
        left: 0;
        width: 100%;
        height: 3px;
        background: var(--gradient-primary);
        transform: scaleX(0);
        transition: transform 0.3s ease;
    }

    th:hover:after {
        transform: scaleX(1);
    }

    /* Cell tabel dengan garis */
    td {
        padding: 16px 15px;
        border-bottom: 2px solid #f1f3f4;
        border-right: 1px solid #f1f3f4;
        transition: all 0.2s ease;
        vertical-align: middle;
        color: #000000; /* Pastikan semua td berwarna hitam */
        font-weight: 500; /* Sedikit tebal untuk konten biasa */
    }

    /* Hapus border dari kolom terakhir */
    td:last-child {
        border-right: none;
    }

    /* Baris terakhir tanpa border bottom */
    tr:last-child td {
        border-bottom: none;
    }

    /* Hover effect untuk baris */
    tr {
        transition: all 0.3s ease;
        position: relative;
    }

    tr:hover {
        background: linear-gradient(135deg, #f8faff 0%, #f0f4ff 100%);
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(67, 97, 238, 0.1);
    }

    /* Alternating row colors - lebih jelas */
    tr:nth-child(even) {
        background-color: #fafbfc;
    }

    tr:nth-child(even):hover {
        background: linear-gradient(135deg, #f0f4ff 0%, #e6eeff 100%);
    }

    /* Garis pemisah yang lebih jelas antara baris */
    tr:not(:last-child) {
        border-bottom: 2px solid #f1f3f4;
    }

    /* Styling untuk kolom No */
    .no-cell {
        font-weight: 700;
        color: #000000;
        text-align: center;
        background: #f8f9fa;
        padding: 8px 12px;
        border-radius: 8px;
        display: inline-block;
        font-size: 14px;
        border: 1px solid #e9ecef;
        min-width: 50px;
    }

    /* Kode wilayah styling - DIUBAH: HITAM DAN TEBAL */
    .kode-cell {
        font-family: 'Monaco', 'Consolas', monospace;
        font-weight: 800; /* Lebih tebal */
        color: #000000; /* Hitam pekat */
        background: #f8f9fa; /* Background lebih soft */
        padding: 8px 12px;
        border-radius: 8px;
        display: inline-block;
        font-size: 16px;
        border: 1px solid #e9ecef;
    }

    /* Nama wilayah styling - DIUBAH: HITAM DAN TEBAL */
    .nama-cell {
        font-weight: 700; /* Lebih tebal */
        color: #000000; /* Hitam pekat */
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 8px 0;
    }

    .nama-cell i {
        color: var(--primary);
        font-size: 16px;
        background: #f0f4ff;
        padding: 8px;
        border-radius: 8px;
    }

    /* Action Buttons */
    .action-buttons {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }

    .btn-view, .btn-edit, .btn-delete {
        color: white;
        border: none;
        padding: 8px 14px;
        border-radius: 8px;
        cursor: pointer;
        text-decoration: none;
        font-size: 12px;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        transition: all 0.3s ease;
        font-weight: 600;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        border: 1px solid transparent;
    }

    .btn-view {
        background: var(--gradient-primary);
        border-color: #3a56e4;
    }

    .btn-edit {
        background: var(--warning);
        border-color: #e6a707;
    }

    .btn-delete {
        background: var(--gradient-danger);
        border-color: #dc2626;
    }

    .btn-view:hover, .btn-edit:hover, .btn-delete:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        filter: brightness(1.1);
    }

    /* Badge */
    .badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
        border: 1px solid;
    }

    .badge.success {
        background: var(--success);
        color: white;
        border-color: #0da271;
    }

    .badge.danger {
        background: var(--danger);
        color: white;
        border-color: #dc2626;
    }

    .badge.info {
        background: var(--info);
        color: white;
        border-color: #2563eb;
    }

    /* Lokasi Card */
    .lokasi-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
        gap: 20px;
        padding: 20px;
    }

    .lokasi-card {
        background: #fff;
        border: 1px solid #e9ecef;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 3px 10px rgba(0,0,0,0.08);
        transition: 0.3s;
        position: relative;
    }

    .lokasi-card:hover {
        box-shadow: 0 6px 20px rgba(0,0,0,0.12);
        transform: translateY(-3px);
    }

    .lokasi-card-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 15px;
        background: #f8f9fa;
        border-bottom: 1px solid #e9ecef;
    }

    .lokasi-card-header h4 {
        margin: 0;
        font-size: 1.1rem;
        color: #000000; /* Hitam untuk judul card */
        font-weight: 700; /* Tebal untuk judul card */
    }

    .lokasi-kode {
        font-size: 0.8rem;
        color: #000000; /* Hitam untuk kode lokasi */
        background: white;
        padding: 4px 8px;
        border-radius: 6px;
        border: 1px solid #e9ecef;
        font-family: 'Monaco', 'Consolas', monospace;
        font-weight: 600; /* Sedikit tebal */
    }

    .lokasi-card-body {
        padding: 15px;
    }

    .lokasi-card-body p {
        margin: 8px 0;
        font-size: 0.9rem;
        line-height: 1.4;
        display: flex;
        align-items: flex-start;
        gap: 8px;
        color: #000000; /* Hitam untuk teks card */
    }

    .lokasi-card-body b {
        min-width: 120px;
        color: #000000; /* Hitam untuk label bold */
        font-weight: 600;
    }

    .lokasi-actions {
        padding: 12px 15px;
        background: #f8f9fa;
        border-top: 1px solid #e9ecef;
        display: flex;
        gap: 8px;
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
        font-family: 'Monaco', 'Consolas', monospace;
        border: 1px solid #d6e6ff;
    }

    .koordinat-card:hover {
        background: #d6e6ff;
        transform: translateY(-1px);
    }

    /* Timestamp info */
    .timestamp-info {
        background: #f8f9fa;
        padding: 4px 8px;
        border-radius: 6px;
        font-family: 'Monaco', 'Consolas', monospace;
        font-size: 0.75rem;
        color: #000000; /* Hitam untuk timestamp */
        border: 1px solid #e9ecef;
        font-weight: 500;
    }

    /* Empty State */
    .empty-state {
        text-align: center;
        padding: 60px 20px;
        background: white;
        border-radius: 15px;
        box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        margin: 20px 0;
    }

    .empty-state i {
        font-size: 4rem;
        margin-bottom: 20px;
        opacity: 0.3;
        color: #6c757d;
    }

    .empty-state h3 {
        font-size: 1.5rem;
        margin-bottom: 15px;
        color: #000000; /* Hitam untuk judul empty state */
        font-weight: 700;
    }

    .empty-state p {
        font-size: 1rem;
        opacity: 0.7;
        margin-bottom: 25px;
        color: #000000; /* Hitam untuk teks empty state */
    }

    /* Modal Styles */
    .modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.5);
        z-index: 1000;
        align-items: center;
        justify-content: center;
    }

    .modal-content {
        background: white;
        border-radius: 12px;
        width: 90%;
        max-width: 500px;
        max-height: 90vh;
        overflow-y: auto;
        box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        border: 2px solid #e9ecef;
    }

    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 20px;
        border-bottom: 1px solid #e9ecef;
        background: var(--gradient-primary);
        color: white;
        border-radius: 12px 12px 0 0;
    }

    .modal-header h3 {
        margin: 0;
        font-size: 1.3rem;
    }

    .close-btn {
        background: none;
        border: none;
        font-size: 1.5rem;
        color: white;
        cursor: pointer;
        padding: 0;
        width: 30px;
        height: 30px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .modal-body {
        padding: 20px;
    }

    .modal-footer {
        padding: 15px 20px;
        border-top: 1px solid #e9ecef;
        display: flex;
        gap: 10px;
        justify-content: flex-end;
    }

    .form-group {
        margin-bottom: 20px;
    }

    .form-group label {
        display: block;
        margin-bottom: 8px;
        font-weight: 600;
        color: #000000; /* Hitam untuk label form */
    }

    .form-control {
        width: 100%;
        padding: 12px;
        border: 2px solid #e9ecef;
        border-radius: 8px;
        font-size: 14px;
        transition: border-color 0.3s ease;
        background: #f8f9fa;
        color: #000000; /* Hitam untuk input text */
        font-weight: 500;
    }

    .form-control:focus {
        outline: none;
        border-color: var(--primary);
        background: white;
        box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.1);
    }

    .form-control:read-only {
        background-color: #f8f9fa;
        color: #6c757d;
    }

    .btn-secondary {
        background: #6c757d;
        color: white;
        border: none;
        padding: 10px 20px;
        border-radius: 8px;
        cursor: pointer;
        font-size: 14px;
        border: 1px solid #5a6268;
    }

    .btn-primary {
        background: var(--gradient-primary);
        color: white;
        border: none;
        padding: 10px 20px;
        border-radius: 8px;
        cursor: pointer;
        font-size: 14px;
        border: 1px solid #3a56e4;
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
        padding: 20px;
        border-radius: 15px;
        width: 100%;
        max-width: 600px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        max-height: 90vh;
        overflow-y: auto;
        border: 2px solid #e9ecef;
    }

    #map {
        width: 100%;
        height: 400px;
        border-radius: 10px;
        border: 2px solid #e9ecef;
    }

    /* Responsive */
    @media (max-width: 768px) {
        .app-container {
            flex-direction: column;
        }
        
        .sidebar {
            width: 100%;
            height: auto;
            position: relative;
            transform: none;
        }
        
        .main-content {
            margin-left: 0;
            width: 100%;
            padding: 20px 15px;
        }
        
        .page-header {
            flex-direction: column;
            gap: 15px;
            text-align: center;
        }
        
        .breadcrumb {
            flex-wrap: wrap;
        }
        
        .action-buttons-container {
            flex-direction: column;
        }
        
        .search-box {
            flex-direction: column;
        }
        
        .table-header {
            flex-direction: column;
            gap: 10px;
            text-align: center;
        }
        
        .lokasi-grid {
            grid-template-columns: 1fr;
        }
        
        th, td {
            padding: 12px 8px;
            font-size: 14px;
        }
        
        .action-buttons {
            flex-direction: column;
        }
        
        table {
            font-size: 13px;
        }
        
        /* Untuk tabel di mobile, buat garis lebih tipis */
        th, td {
            border-right: 1px solid #dee2e6;
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

    /* Loading animation for table */
    .table-loading {
        position: relative;
        overflow: hidden;
    }

    .table-loading::after {
        content: '';
        position: absolute;
        top: 0;
        right: 0;
        bottom: 0;
        left: 0;
        transform: translateX(-100%);
        background: linear-gradient(90deg, transparent, rgba(255,255,255,0.4), transparent);
        animation: shimmer 1.5s infinite;
    }

    @keyframes shimmer {
        100% {
            transform: translateX(100%);
        }
    }

    /* Table row animation */
    .table-row-animation {
        animation: slideIn 0.5s ease-out;
    }

    @keyframes slideIn {
        from {
            opacity: 0;
            transform: translateX(-20px);
        }
        to {
            opacity: 1;
            transform: translateX(0);
        }
    }

    /* Border radius untuk tabel */
    table {
        border-radius: 10px;
    }

    /* Garis luar tabel yang lebih jelas */
    .table-container {
        border: 2px solid #e9ecef;
        border-radius: 10px;
        margin: 0;
    }

    /* Efek hover untuk seluruh tabel */
    .table-container:hover {
        box-shadow: 0 8px 25px rgba(0,0,0,0.1);
    }

    /* Garis pemisah header dan body */
    thead {
        border-bottom: 3px solid #dee2e6;
    }

    /* Styling untuk kolom aksi */
    td:last-child {
        background: linear-gradient(135deg, #fafbfc 0%, #f8f9fa 100%);
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

        <!-- Breadcrumb -->
        <div class="breadcrumb fade-in">
            <a href="hasil_pemetaan.php?level=provinsi" class="breadcrumb-item">
                <i class="fas fa-home"></i> Home
            </a>
            <?php foreach ($breadcrumbs as $index => $crumb): ?>
                <span class="breadcrumb-separator"><i class="fas fa-chevron-right"></i></span>
                <?php if ($index == count($breadcrumbs) - 1): ?>
                    <span class="breadcrumb-item active"><?= $crumb['name'] ?></span>
                <?php else: ?>
                    <a href="<?= $crumb['url'] ?>" class="breadcrumb-item"><?= $crumb['name'] ?></a>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>

        <!-- Action Buttons Section - Tampil di semua level kecuali lokasi -->
        <?php if ($level != 'lokasi'): ?>
        <div class="action-buttons-section fade-in">
            <div class="action-buttons-container">
                <?php 
                // SELALU gunakan kode provinsi sebagai parent, tidak peduli level mana kita sekarang
                $kode_provinsi = '';
                
                if ($level == 'provinsi') {
                    // Jika sudah di level provinsi, gunakan kode provinsi saat ini
                    $kode_provinsi = !empty($tableData[0]['kode_wilayah']) ? $tableData[0]['kode_wilayah'] : '';
                } elseif (in_array($level, ['kota', 'kecamatan', 'desa']) && $parent_kode) {
                    // Untuk level lainnya, cari kode provinsi dengan menelusuri parent
                    $current_kode = $parent_kode;
                    
                    // Telusuri ke atas sampai menemukan provinsi
                    while ($current_kode) {
                        $query = $conn->query("SELECT * FROM wilayah WHERE kode_wilayah = '$current_kode'");
                        if ($query->num_rows > 0) {
                            $data = $query->fetch_assoc();
                            if ($data['level'] == 'provinsi') {
                                $kode_provinsi = $data['kode_wilayah'];
                                break;
                            }
                            $current_kode = $data['parent_kode'];
                        } else {
                            break;
                        }
                    }
                }
                ?>
                
                <?php if ($kode_provinsi): ?>
                    <a href="create_wilayah_sekaligus.php?parent=<?= $kode_provinsi ?>" class="action-btn batch">
                        <i class="fas fa-layer-group"></i>
                        <span>Tambah Wilayah Sekaligus</span>
                    </a>
                    <a href="create_lokasi.php?parent_kode=<?= $parent_kode ?>" class="action-btn location">
                        <i class="fas fa-map-pin"></i>
                        <span>Tambah Lokasi</span>
                    </a>
                <?php else: ?>
                    <a href="create_wilayah_sekaligus.php" class="action-btn batch">
                        <i class="fas fa-layer-group"></i>
                        <span>Tambah Wilayah Sekaligus</span>
                    </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Table/Lokasi Section -->
        <div class="table-section fade-in">
            <div class="table-header">
                <h2><i class="fas fa-table"></i> <?= $tableTitle ?></h2>
                <div class="table-info">
                    <i class="fas fa-database"></i> Total: <strong><?= $totalData ?></strong> data
                </div>
            </div>

            <?php if ($totalData > 0): ?>
                <?php if ($level == 'lokasi'): ?>
                    <!-- Tampilan Grid untuk Lokasi -->
                    <div class="lokasi-grid">
                        <?php foreach ($tableData as $index => $data): ?>
                            <div class="lokasi-card">
                                <div class="lokasi-card-header">
                                    <h4><?= htmlspecialchars($data['nama_tempat']) ?></h4>
                                    <span class="lokasi-kode"><?= $data['kode_lokasi'] ?></span>
                                </div>
                                <div class="lokasi-card-body">
                                    <p><b><i class="fas fa-info-circle"></i> Keterangan:</b> <?= htmlspecialchars($data['keterangan']) ?></p>
                                    <p><b><i class="fas fa-signal"></i> Sinyal:</b> 
                                        <?php if ($data['ketersediaan_sinyal'] === "Yes"): ?>
                                            <span class="badge success"><i class="fas fa-signal"></i> Ada Sinyal</span>
                                        <?php else: ?>
                                            <span class="badge danger"><i class="fas fa-signal-slash"></i> Tidak Ada Sinyal</span>
                                        <?php endif; ?>
                                    </p>
                                    <p><b><i class="fas fa-tachometer-alt"></i> Kecepatan:</b> <?= $data['kecepatan_sinyal'] ?> Mbps</p>
                                    <p><b><i class="fas fa-map-pin"></i> Koordinat:</b> 
                                        <a href="#" class="lihat-peta koordinat-card" data-koordinat="<?= htmlspecialchars($data['koordinat']) ?>">
                                            <i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($data['koordinat']) ?>
                                        </a>
                                    </p>
                                    <p><b><i class="fas fa-calendar-plus"></i> Ditambahkan:</b> 
                                        <span class="timestamp-info"><?= date('d/m/Y H:i', strtotime($data['created_at'])) ?></span>
                                    </p>
                                    <p><b><i class="fas fa-calendar-check"></i> Terakhir Update:</b> 
                                        <span class="timestamp-info"><?= date('d/m/Y H:i', strtotime($data['updated_at'])) ?></span>
                                    </p>
                                </div>
                                <div class="lokasi-actions">
                                    <a href="edit_lokasi.php?kode_lokasi=<?= $data['kode_lokasi'] ?>" class="btn-edit">
                                        <i class="fas fa-edit"></i> Edit
                                    </a>
                                    <a href="proses/proses_hapus_semua.php?kode_lokasi=<?= $data['kode_lokasi'] ?>" class="btn-delete" onclick="return confirm('Yakin ingin hapus data ini?')">
                                        <i class="fas fa-trash-alt"></i> Hapus
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <!-- Tampilan Table untuk Wilayah - TABEL DENGAN GARIS YANG JELAS -->
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th><i class="fas fa-list-ol"></i> No</th>
                                    <?php if ($level == 'provinsi'): ?>
                                        <th><i class="fas fa-hashtag"></i> Kode Provinsi</th>
                                        <th><i class="fas fa-map"></i> Nama Provinsi</th>
                                    <?php elseif ($level == 'kota'): ?>
                                        <th><i class="fas fa-hashtag"></i> Kode Kabupaten/Kota</th>
                                        <th><i class="fas fa-city"></i> Nama Kabupaten/Kota</th>
                                    <?php elseif ($level == 'kecamatan'): ?>
                                        <th><i class="fas fa-hashtag"></i> Kode Kecamatan</th>
                                        <th><i class="fas fa-building"></i> Nama Kecamatan</th>
                                    <?php elseif ($level == 'desa'): ?>
                                        <th><i class="fas fa-hashtag"></i> Kode Desa</th>
                                        <th><i class="fas fa-home"></i> Nama Desa</th>
                                    <?php endif; ?>
                                    <th><i class="fas fa-cogs"></i> Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tableData as $index => $data): ?>
                                    <tr class="table-row-animation" style="animation-delay: <?= $index * 0.05 ?>s;">
                                        <td>
                                            <span class="no-cell">
                                                <?= $index + 1 ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="kode-cell">
                                                <i class="fas fa-fingerprint"></i> <?= $data['kode_wilayah'] ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="nama-cell">
                                                <?php if ($level == 'provinsi'): ?>
                                                    <i class="fas fa-map-marked-alt"></i>
                                                <?php elseif ($level == 'kota'): ?>
                                                    <i class="fas fa-city"></i>
                                                <?php elseif ($level == 'kecamatan'): ?>
                                                    <i class="fas fa-building"></i>
                                                <?php elseif ($level == 'desa'): ?>
                                                    <i class="fas fa-home"></i>
                                                <?php endif; ?>
                                                <?= htmlspecialchars($data['nama']) ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <?php if ($level == 'provinsi'): ?>
                                                    <a href="hasil_pemetaan.php?level=kota&parent_kode=<?= $data['kode_wilayah'] ?>" class="btn-view">
                                                        <i class="fas fa-arrow-right"></i> Lihat Kabupaten/Kota
                                                    </a>
                                                <?php elseif ($level == 'kota'): ?>
                                                    <a href="hasil_pemetaan.php?level=kecamatan&parent_kode=<?= $data['kode_wilayah'] ?>" class="btn-view">
                                                        <i class="fas fa-arrow-right"></i> Lihat Kecamatan
                                                    </a>
                                                <?php elseif ($level == 'kecamatan'): ?>
                                                    <a href="hasil_pemetaan.php?level=desa&parent_kode=<?= $data['kode_wilayah'] ?>" class="btn-view">
                                                        <i class="fas fa-arrow-right"></i> Lihat Desa
                                                    </a>
                                                <?php elseif ($level == 'desa'): ?>
                                                    <a href="hasil_pemetaan.php?level=lokasi&parent_kode=<?= $data['kode_wilayah'] ?>" class="btn-view">
                                                        <i class="fas fa-map-marker-alt"></i> Lihat Lokasi
                                                    </a>
                                                <?php endif; ?>
                                                
                                                <!-- Tombol Edit dan Hapus untuk setiap row -->
                                                <button type="button" class="btn-edit" onclick="openEditModal('<?= $data['kode_wilayah'] ?>')">
                                                    <i class="fas fa-edit"></i> Edit
                                                </button>
                                                <a href="proses/proses_hapus_wilayah.php?kode_wilayah=<?= $data['kode_wilayah'] ?>" class="btn-delete" onclick="return confirm('Yakin ingin hapus wilayah <?= htmlspecialchars($data['nama']) ?>?')">
                                                    <i class="fas fa-trash-alt"></i> Hapus
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <h3>Tidak ada data ditemukan</h3>
                    <p><?= $searchKeyword != '' ? 'Coba ubah kata kunci pencarian' : 'Data belum tersedia untuk level ini' ?></p>
                    <?php if ($level != 'provinsi'): ?>
                        <a href="hasil_pemetaan.php?level=provinsi" class="btn btn-primary">
                            <i class="fas fa-arrow-left"></i> Kembali ke Provinsi
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
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

<!-- Modal Edit Wilayah -->
<div id="editModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-edit"></i> Edit Wilayah</h3>
            <button type="button" class="close-btn" onclick="closeEditModal()">&times;</button>
        </div>
        <div class="modal-body">
            <form id="editForm" method="post" action="proses/proses_edit_wilayah.php">
                <input type="hidden" name="kode_lama" id="editKodeLama">
                <input type="hidden" name="provinsi" id="editProvinsi">
                
                <div class="form-group">
                    <label for="editKode"><i class="fas fa-code"></i> Kode Wilayah</label>
                    <input type="text" id="editKode" name="kode_baru" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="editNama"><i class="fas fa-tag"></i> Nama Wilayah</label>
                    <input type="text" id="editNama" name="nama" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="editLevel"><i class="fas fa-layer-group"></i> Level</label>
                    <input type="text" id="editLevel" class="form-control" readonly>
                </div>
                
                <div class="form-group" id="parentGroup">
                    <label for="editParent"><i class="fas fa-sitemap"></i> Parent</label>
                    <input type="text" id="editParent" class="form-control" readonly>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeEditModal()">
                <i class="fas fa-times"></i> Batal
            </button>
            <button type="button" class="btn btn-primary" id="saveEditButton">
                <i class="fas fa-save"></i> Simpan Perubahan
            </button>
        </div>
    </div>
</div>

<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
<script>
// Debug info
console.log('✅ Script loaded successfully');

// ========== MODAL PETA ==========
const modal = document.getElementById('mapModal');
const closeBtn = document.querySelector('.close-btn');
let map, marker;

// Event klik koordinat
document.querySelectorAll('.lihat-peta').forEach(link => {
    link.addEventListener('click', function(e){
        e.preventDefault();
        const coords = this.getAttribute('data-koordinat').split(',');
        const lat = parseFloat(coords[0]);
        const lon = parseFloat(coords[1]);

        modal.style.display = 'flex';

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

// Tombol close peta
closeBtn.addEventListener('click', ()=> modal.style.display='none');
window.addEventListener('click', e => { if(e.target == modal){ modal.style.display='none'; } });

// ========== MODAL EDIT WILAYAH ==========
function openEditModal(kodeWilayah) {
    console.log('🔧 Opening edit modal for:', kodeWilayah);
    
    // Tampilkan modal
    document.getElementById('editModal').style.display = 'flex';
    document.getElementById('editKodeLama').value = kodeWilayah;
    
    // Reset form
    document.getElementById('editKode').value = '';
    document.getElementById('editNama').value = '';
    
    // Ambil data wilayah via AJAX
    fetch(`get_wilayah_data.php?kode_wilayah=${kodeWilayah}`)
        .then(response => response.json())
        .then(data => {
            console.log('📦 Data received:', data);
            if (data.success) {
                document.getElementById('editKode').value = data.kode_wilayah;
                document.getElementById('editNama').value = data.nama;
                document.getElementById('editLevel').value = data.level;
                document.getElementById('editProvinsi').value = data.provinsi_kode;
                
                // Tampilkan parent jika ada
                const parentGroup = document.getElementById('parentGroup');
                const editParent = document.getElementById('editParent');
                if (data.parent_nama) {
                    parentGroup.style.display = 'block';
                    editParent.value = data.parent_nama;
                } else {
                    parentGroup.style.display = 'none';
                }
            } else {
                alert('❌ Gagal memuat data wilayah');
                closeEditModal();
            }
        })
        .catch(error => {
            console.error('❌ Error:', error);
            alert('❌ Terjadi kesalahan saat memuat data');
            closeEditModal();
        });
}

function closeEditModal() {
    document.getElementById('editModal').style.display = 'none';
}

// Close modal edit ketika klik di luar
window.addEventListener('click', function(event) {
    const modal = document.getElementById('editModal');
    if (event.target === modal) {
        closeEditModal();
    }
});

// ========== FIXED FORM SUBMISSION ==========
function handleSaveButton() {
    console.log('🎯 Tombol Simpan diklik!');
    
    // Cari tombol dengan selector yang lebih spesifik
    const submitBtn = document.querySelector('#editModal .btn-primary');
    console.log('🔍 Tombol ditemukan:', submitBtn);
    
    if (!submitBtn) {
        console.error('❌ Tombol tidak ditemukan!');
        alert('Error: Tombol tidak ditemukan');
        return;
    }
    
    // Ambil data dari form
    const kodeLama = document.getElementById('editKodeLama').value;
    const kodeBaru = document.getElementById('editKode').value;
    const nama = document.getElementById('editNama').value;
    const provinsi = document.getElementById('editProvinsi').value;
    
    console.log('📤 Data to send:', {
        kode_lama: kodeLama,
        kode_baru: kodeBaru,
        nama: nama,
        provinsi: provinsi
    });
    
    // Validasi
    if (!kodeLama || !kodeBaru || !nama) {
        alert('❌ Semua field harus diisi!');
        return;
    }
    
    // Tampilkan loading state - PASTIKAN tombol ada
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Menyimpan...';
    submitBtn.disabled = true;
    
    // Prepare form data
    const formData = new FormData();
    formData.append('kode_lama', kodeLama);
    formData.append('kode_baru', kodeBaru);
    formData.append('nama', nama);
    formData.append('provinsi', provinsi);
    
    // Send request
    fetch('proses/proses_edit_wilayah.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        console.log('📡 Response status:', response.status);
        return response.json();
    })
    .then(data => {
        console.log('📨 Response data:', data);
        
        if (data.success) {
            alert('✅ ' + data.message);
            closeEditModal();
            // Refresh page after 1 second
            setTimeout(() => {
                location.reload();
            }, 1000);
        } else {
            alert('❌ ' + data.message);
            // Reset button
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        }
    })
    .catch(error => {
        console.error('❌ Fetch error:', error);
        alert('❌ Gagal mengirim data: ' + error.message);
        // Reset button
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    });
}

// ========== INITIALIZATION ==========
// Pasang event listener dengan cara yang sederhana
document.addEventListener('DOMContentLoaded', function() {
    console.log('✅ DOM fully loaded');
    
    // Cari tombol submit di modal edit
    const saveButton = document.querySelector('#editModal .btn-primary');
    console.log('🔍 Save button found:', saveButton);
    
    if (saveButton) {
        console.log('✅ Attaching click event to save button...');
        saveButton.addEventListener('click', handleSaveButton);
    } else {
        console.log('⚠️ Save button not found yet, will retry...');
    }
});

// Backup: Coba pasang event listener lagi setelah modal terbuka
function setupSaveButton() {
    const saveButton = document.querySelector('#editModal .btn-primary');
    if (saveButton && !saveButton.hasAttribute('data-listener-attached')) {
        console.log('✅ Setting up save button listener...');
        saveButton.setAttribute('data-listener-attached', 'true');
        saveButton.addEventListener('click', handleSaveButton);
    }
}

// Panggil setup ketika modal dibuka (override openEditModal)
const originalOpenEditModal = openEditModal;
openEditModal = function(kodeWilayah) {
    originalOpenEditModal(kodeWilayah);
    // Setup save button setelah modal terbuka
    setTimeout(setupSaveButton, 100);
};

// ========== GLOBAL FUNCTION EXPORTS ==========
window.openEditModal = openEditModal;
window.closeEditModal = closeEditModal;
window.handleSaveButton = handleSaveButton;

console.log('🚀 All functions initialized');
</script>
</body>
</html>