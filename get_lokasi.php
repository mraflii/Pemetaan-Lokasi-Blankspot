<?php
include "config/db.php";

// Ambil filter dari query string
$provinsi = $_GET['provinsi'] ?? '';
$kota = $_GET['kota'] ?? '';
$kecamatan = $_GET['kecamatan'] ?? '';
$desa = $_GET['desa'] ?? '';
$filterSinyal = $_GET['filterSinyal'] ?? '';

// Bangun query dinamis
$sql = "SELECT * FROM lokasi WHERE 1=1";

if($provinsi) $sql .= " AND provinsi='$provinsi'";
if($kota) $sql .= " AND kota='$kota'";
if($kecamatan) $sql .= " AND kecamatan='$kecamatan'";
if($desa) $sql .= " AND desa='$desa'";
if($filterSinyal=='Yes') $sql .= " AND ketersediaan_sinyal='Yes'";
elseif($filterSinyal=='No') $sql .= " AND ketersediaan_sinyal='No'";

$res = $conn->query($sql);
$data = [];
while($row = $res->fetch_assoc()){
    $data[] = [
        "kode_lokasi" => $row["kode_lokasi"],
        "nama_tempat" => $row["nama_tempat"],
        "koordinat" => $row["koordinat"],
        "keterangan" => $row["keterangan"],
        "ketersediaan_sinyal" => $row["ketersediaan_sinyal"],
        "kecepatan_sinyal" => $row["kecepatan_sinyal"]
    ];
}

header('Content-Type: application/json');
echo json_encode($data);
