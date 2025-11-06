<?php
include "config/db.php";

header('Content-Type: application/json');

$prefix = $_GET['prefix'] ?? '';
if(!$prefix){
    echo json_encode(['success' => false, 'message' => 'Prefix kode tidak diberikan']);
    exit;
}

// Cari kode lokasi terakhir dengan prefix ini
$sql = "SELECT kode_lokasi FROM lokasi WHERE kode_lokasi LIKE ? ORDER BY kode_lokasi DESC LIMIT 1";
$stmt = $conn->prepare($sql);
$like_param = $prefix . '.%';
$stmt->bind_param("s", $like_param);
$stmt->execute();
$result = $stmt->get_result();

if($result->num_rows > 0){
    $row = $result->fetch_assoc();
    $last_kode = $row['kode_lokasi']; // misal '11.72.01.2010.05'
    $parts = explode('.', $last_kode);
    $last_number = intval(end($parts));
    $new_number = $last_number + 1;
} else {
    $new_number = 1;
}

$new_number_str = str_pad($new_number, 2, '0', STR_PAD_LEFT);
$kode_lokasi = $prefix . '.' . $new_number_str;

echo json_encode(['success' => true, 'kode_lokasi' => $kode_lokasi]);