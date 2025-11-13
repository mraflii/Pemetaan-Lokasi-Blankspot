<?php
include "config/db.php";
header('Content-Type: application/json');

// Ambil parameter
$level = $_GET['level'] ?? '';
$parent = $_GET['parent'] ?? '';

// Validasi parameter
if(!$level){
    echo json_encode(['error' => 'Parameter level diperlukan']);
    exit;
}

// Validasi level yang diizinkan
$allowed_levels = ['provinsi', 'kota', 'kecamatan', 'desa'];
if(!in_array($level, $allowed_levels)){
    echo json_encode(['error' => 'Level tidak valid']);
    exit;
}

// Bangun query dengan prepared statement untuk keamanan
$sql = "SELECT kode_wilayah, nama FROM wilayah WHERE level = ?";
$params = [$level];

if($parent != ''){
    $sql .= " AND parent_kode = ?";
    $params[] = $parent;
}

$sql .= " ORDER BY nama ASC";

// Gunakan prepared statement
$stmt = $conn->prepare($sql);

if($stmt){
    // Bind parameters
    if(count($params) == 1){
        $stmt->bind_param("s", $params[0]);
    }elseif(count($params) == 2){
        $stmt->bind_param("ss", $params[0], $params[1]);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $data = [];
    while($row = $result->fetch_assoc()){
        $data[] = [
            'kode_wilayah' => $row['kode_wilayah'],
            'nama' => $row['nama']
        ];
    }
    
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'data' => $data,
        'total' => count($data)
    ]);
}else{
    echo json_encode(['error' => 'Gagal mempersiapkan query']);
}

$conn->close();
?>