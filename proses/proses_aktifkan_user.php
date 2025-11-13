<?php
session_start();
include "../config/db.php";

// Cek apakah user sudah login dan memiliki akses Super Admin atau Admin
if (!isset($_SESSION['user_id']) || ($_SESSION['peran'] !== 'Super Admin' && $_SESSION['peran'] !== 'Admin')) {
    $_SESSION['message'] = "Anda tidak memiliki akses untuk mengubah status user!";
    $_SESSION['message_type'] = 'error';
    header('Location: ../manage_users.php');
    exit();
}

// Cek apakah parameter id dan action ada
if (!isset($_GET['id']) || !isset($_GET['action'])) {
    $_SESSION['message'] = "Parameter tidak lengkap!";
    $_SESSION['message_type'] = 'error';
    header('Location: ../manage_users.php');
    exit();
}

$user_id = intval($_GET['id']);
$action = $_GET['action'];
$current_user_id = $_SESSION['user_id'];
$current_user_role = $_SESSION['peran'];

// Validasi action
if (!in_array($action, ['activate', 'deactivate'])) {
    $_SESSION['message'] = "Aksi tidak valid!";
    $_SESSION['message_type'] = 'error';
    header('Location: ../manage_users.php');
    exit();
}

// Cek apakah user mencoba mengubah status dirinya sendiri
if ($user_id == $current_user_id) {
    $_SESSION['message'] = "Tidak dapat mengubah status akun sendiri!";
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
    // Admin hanya bisa mengubah status user biasa (bukan Admin atau Super Admin)
    if ($user['peran'] === 'Admin' || $user['peran'] === 'Super Admin') {
        $_SESSION['message'] = "Admin tidak dapat mengubah status Admin atau Super Admin!";
        $_SESSION['message_type'] = 'error';
        header('Location: ../manage_users.php');
        exit();
    }
}

// Update status user
$status_aktif = ($action === 'activate') ? 1 : 0;
$update_stmt = $conn->prepare("UPDATE users SET status_aktif = ? WHERE id = ?");
$update_stmt->bind_param("ii", $status_aktif, $user_id);

if ($update_stmt->execute()) {
    $status_text = ($action === 'activate') ? 'diaktifkan' : 'dinonaktifkan';
    $_SESSION['message'] = "User <strong>{$user['username']}</strong> berhasil $status_text!";
    $_SESSION['message_type'] = 'success';
} else {
    $_SESSION['message'] = "Gagal mengubah status user! Error: " . $update_stmt->error;
    $_SESSION['message_type'] = 'error';
}

$update_stmt->close();
header('Location: ../manage_users.php');
exit();
?>