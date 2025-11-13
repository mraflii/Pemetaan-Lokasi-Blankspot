<?php
session_start();
include "../config/db.php";

// Cek apakah user sudah login dan memiliki akses Super Admin atau Admin
if (!isset($_SESSION['user_id']) || ($_SESSION['peran'] !== 'Super Admin' && $_SESSION['peran'] !== 'Admin')) {
    $_SESSION['message'] = "Anda tidak memiliki akses untuk menghapus user!";
    $_SESSION['message_type'] = 'error';
    header('Location: ../manage_users.php');
    exit();
}

// Cek apakah parameter id ada
if (!isset($_GET['id'])) {
    $_SESSION['message'] = "ID user tidak valid!";
    $_SESSION['message_type'] = 'error';
    header('Location: ../manage_users.php');
    exit();
}

$user_id = intval($_GET['id']);
$current_user_id = $_SESSION['user_id'];
$current_user_role = $_SESSION['peran'];

// Cek apakah user mencoba menghapus dirinya sendiri
if ($user_id == $current_user_id) {
    $_SESSION['message'] = "Tidak dapat menghapus akun sendiri!";
    $_SESSION['message_type'] = 'error';
    header('Location: ../manage_users.php');
    exit();
}

// Ambil data user untuk mendapatkan username dan peran
$stmt = $conn->prepare("SELECT username, peran FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['message'] = "User tidak ditemukan!";
    $_SESSION['message_type'] = 'error';
    header('Location: ../manage_users.php');
    exit();
}

$user = $result->fetch_assoc();
$stmt->close();

// Validasi hak akses berdasarkan role
if ($current_user_role === 'Admin') {
    // Admin hanya bisa menghapus user biasa (bukan Admin atau Super Admin)
    if ($user['peran'] === 'Admin' || $user['peran'] === 'Super Admin') {
        $_SESSION['message'] = "Admin tidak dapat menghapus Admin atau Super Admin!";
        $_SESSION['message_type'] = 'error';
        header('Location: ../manage_users.php');
        exit();
    }
}

// Hapus user
$delete_stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
$delete_stmt->bind_param("i", $user_id);

if ($delete_stmt->execute()) {
    $_SESSION['message'] = "User <strong>{$user['username']}</strong> berhasil dihapus!";
    $_SESSION['message_type'] = 'success';
} else {
    $_SESSION['message'] = "Gagal menghapus user! Error: " . $delete_stmt->error;
    $_SESSION['message_type'] = 'error';
}

$delete_stmt->close();
header('Location: ../manage_users.php');
exit();
?>