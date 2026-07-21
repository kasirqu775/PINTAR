<?php
session_start();
require 'koneksi.php';

$pesan = '';
$tipe_pesan = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nama_lengkap = mysqli_real_escape_string($koneksi, trim($_POST['nama_lengkap'] ?? ''));
    $nama_usaha   = mysqli_real_escape_string($koneksi, trim($_POST['nama_usaha'] ?? ''));
    $username     = mysqli_real_escape_string($koneksi, trim($_POST['username'] ?? ''));
    $password     = trim($_POST['password'] ?? '');

    // Validasi input kosong
    if (empty($nama_lengkap) || empty($nama_usaha) || empty($username) || empty($password)) {
        $pesan = "Semua bidang formulir wajib diisi!";
        $tipe_pesan = "error";
    } else {
        // Cek ketersediaan username dengan proteksi error query
        $cek_username = mysqli_query($koneksi, "SELECT id FROM users WHERE username = '$username'");
        
        if (!$cek_username) {
            $pesan = "Terjadi kesalahan database: " . mysqli_error($koneksi) . ". Pastikan tabel 'users' sudah dibuat.";
            $tipe_pesan = "error";
        } elseif (mysqli_num_rows($cek_username) > 0) {
            $pesan = "Username sudah digunakan, silakan pilih username lain.";
            $tipe_pesan = "error";
        } else {
            // Encrypt password menggunakan BCRYPT
            $password_hashed = password_hash($password, PASSWORD_BCRYPT);

            $query = "INSERT INTO users (nama_lengkap, nama_usaha, username, password, status) 
                      VALUES ('$nama_lengkap', '$nama_usaha', '$username', '$password_hashed', 'pending')";

            if (mysqli_query($koneksi, $query)) {
                $pesan = "Pendaftaran berhasil! Akun Anda sedang menunggu persetujuan Admin.";
                $tipe_pesan = "success";
            } else {
                $pesan = "Gagal mendaftar: " . mysqli_error($koneksi);
                $tipe_pesan = "error";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrasi Akun Usaha</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="auth-container">
        <div class="auth-header">
            <h2>Buat Akun Baru</h2>
            <p>Daftarkan bisnis Anda untuk mulai menggunakan platform</p>
        </div>

        <?php if ($pesan): ?>
            <div class="alert alert-<?= $tipe_pesan; ?>"><?= $pesan; ?></div>
        <?php endif; ?>

        <form action="" method="POST">
            <div class="form-group">
                <label for="nama_lengkap">Nama Lengkap</label>
                <input type="text" id="nama_lengkap" name="nama_lengkap" required placeholder="Contoh: Budi Santoso">
            </div>
            <div class="form-group">
                <label for="nama_usaha">Nama Usaha</label>
                <input type="text" id="nama_usaha" name="nama_usaha" required placeholder="Contoh: Toko Maju Jaya">
            </div>
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" required placeholder="Pilih username">
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required placeholder="••••••••">
            </div>
            <button type="submit" class="btn-submit">Daftar Sekarang</button>
        </form>

        <div class="auth-footer">
            Sudah punya akun? <a href="login.php">Masuk di sini</a> | <a href="index.php">Beranda</a>
        </div>
    </div>
</body>
</html>