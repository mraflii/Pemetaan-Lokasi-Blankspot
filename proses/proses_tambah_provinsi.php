<?php
include "../config/db.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $provinsi = $_POST['provinsi'] ?? null;

    if ($provinsi) {
        $kodeProv = $conn->real_escape_string($provinsi['kode']);
        $namaProv = $conn->real_escape_string($provinsi['nama']);

        // Cek apakah sudah ada provinsi dengan kode yang sama
        $cek = $conn->query("SELECT 1 FROM wilayah WHERE kode_wilayah='$kodeProv' AND level='provinsi'");

        if ($cek && $cek->num_rows == 0) {
            // Insert Provinsi
            $sql = "INSERT INTO wilayah (kode_wilayah, nama, level, parent_kode)
                    VALUES ('$kodeProv', '$namaProv', 'provinsi', NULL)";
            if ($conn->query($sql)) {
                echo "<script>
                    alert('Provinsi berhasil ditambahkan!');
                    window.location.href='../hasil_pemetaan.php';
                </script>";
            } else {
                echo "<script>
                    alert('Gagal menambahkan provinsi: " . $conn->error . "');
                    window.location.href='../create_provinsi.php';
                </script>";
            }
        } else {
            echo "<script>
                alert('Kode provinsi sudah ada!');
                window.location.href='../create_provinsi.php';
            </script>";
        }
    } else {
        echo "<script>
            alert('Data tidak valid!');
            window.location.href='../create_provinsi.php';
        </script>";
    }
} else {
    echo "Akses tidak valid.";
}
