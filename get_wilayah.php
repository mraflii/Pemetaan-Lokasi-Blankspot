<?php
include "config/db.php";

$level = $_GET['level'] ?? '';
$parent = $_GET['parent'] ?? '';

if(!$level){
    echo json_encode([]);
    exit;
}

$sql = "SELECT kode_wilayah, nama FROM wilayah WHERE level='".$conn->real_escape_string($level)."'";
if($parent != ''){
    $sql .= " AND parent_kode='".$conn->real_escape_string($parent)."'";
}
$sql .= " ORDER BY nama ASC";

$result = $conn->query($sql);

$data = [];
if($result){
    while($row = $result->fetch_assoc()){
        $data[] = $row;
    }
}
echo json_encode($data);
