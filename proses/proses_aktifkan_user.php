<?php
session_start();
include "../config/db.php";

// Cek apakah user sudah login dan memiliki akses Super Admin
if (!isset($_SESSION['user_id']) || $_SESSION['peran'] !== 'Super Admin') {
    header('Location: ../login.php');
    exit();
}

// Cek apakah parameter id dan action ada
if (!isset($_GET['id']) || !isset($_GET['action'])) {
    header('Location: ../manage_users.php');
    exit();
}

$user_id = intval($_GET['id']);
$action = $_GET['action'];

// Validasi action
if (!in_array($action, ['activate', 'deactivate'])) {
    header('Location: ../manage_users.php');
    exit();
}

// Cek apakah user mencoba mengubah status dirinya sendiri
if ($user_id == $_SESSION['user_id']) {
    $_SESSION['message'] = "Tidak dapat mengubah status akun sendiri!";
    $_SESSION['message_type'] = 'error';
    header('Location: ../manage_users.php');
    exit();
}

// Ambil data user untuk mendapatkan username
$stmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
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

// Update status user
$status_aktif = ($action === 'activate') ? 1 : 0;
$update_stmt = $conn->prepare("UPDATE users SET status_aktif = ? WHERE id = ?");
$update_stmt->bind_param("ii", $status_aktif, $user_id);

if ($update_stmt->execute()) {
    $status_text = ($action === 'activate') ? 'diaktifkan' : 'dinonaktifkan';
    $_SESSION['message'] = "User <strong>{$user['username']}</strong> berhasil $status_text!";
    $_SESSION['message_type'] = 'success';
} else {
    $_SESSION['message'] = "Gagal mengubah status user!";
    $_SESSION['message_type'] = 'error';
}

$update_stmt->close();
header('Location: ../manage_users.php');
exit();
?>