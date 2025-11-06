<?php
include "config/db.php";

// Ambil parameter
$level = $_GET['level'] ?? '';
$parent = $_GET['parent_kode'] ?? '';

header('Content-Type: application/json; charset=utf-8');

if(!$level || !$parent){
    echo json_encode([]);
    exit;
}

$stmt = $conn->prepare("SELECT kode_wilayah, nama 
                        FROM wilayah 
                        WHERE level=? AND parent_kode=? 
                        ORDER BY nama ASC");
if(!$stmt){
    echo json_encode([]);
    exit;
}

$stmt->bind_param("ss", $level, $parent);
$stmt->execute();
$res = $stmt->get_result();

$data = [];
while($row = $res->fetch_assoc()){
    $data[] = $row;
}

echo json_encode($data);
