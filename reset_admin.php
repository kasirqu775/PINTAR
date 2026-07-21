<?php
require 'koneksi.php';

// Kredensial Baru Admin
$username       = 'SU_admin';
$password_plain = 'juara2026';

// Generate Hash Password BCRYPT
$password_hashed = password_hash($password_plain, PASSWORD_BCRYPT);

// Hapus akun admin lama
mysqli_query($koneksi, "DELETE FROM users WHERE role = 'admin' OR username = '$username'");

// Masukkan akun Super Admin Baru
$query = "INSERT INTO users (nama_lengkap, nama_usaha, username, password, role, status) 
          VALUES ('Super Admin', 'Pusat Admin', '$username', '$password_hashed', 'admin', 'active')";

if (mysqli_query($koneksi, $query)) {
    echo "<div style='font-family:sans-serif; padding:40px; text-align:center;'>";
    echo "<h2 style='color:#059669;'>✅ Akun Admin Berhasil Diperbarui!</h2>";
    echo "<p style='margin-top:10px;'>Gunakan data login baru berikut:</p>";
    echo "<div style='background:#f3f4f6; display:inline-block; padding:15px 30px; border-radius:8px; margin:15px 0; text-align:left;'>";
    echo "<b>Username:</b> SU_admin<br>";
    echo "<b>Password:</b> juara2026";
    echo "</div><br>";
    echo "<a href='login.php' style='padding:12px 24px; background:#4f46e5; color:white; text-decoration:none; border-radius:8px; font-weight:bold;'>Masuk ke Halaman Login</a>";
    echo "</div>";
} else {
    echo "<h2 style='color:red;'>Gagal memperbarui admin: " . mysqli_error($koneksi) . "</h2>";
}
?>