<?php
ob_start();
session_start();
require 'koneksi.php';

$pesan = '';

if (isset($_GET['err'])) {
    if ($_GET['err'] === 'pending') {
        $pesan = "⚠️ Akun Anda belum disetujui Super Admin. Silakan hubungi pengelola!";
    } elseif ($_GET['err'] === 'expired') {
        $pesan = "⏰ Masa aktif akun Anda telah habis. Silakan perpanjang langganan!";
    } elseif ($_GET['err'] === 'unauthorized') {
        $pesan = "🔒 Silakan login terlebih dahulu untuk mengakses sistem.";
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = mysqli_real_escape_string($koneksi, trim($_POST['username'] ?? ''));
    $password = trim($_POST['password'] ?? '');

    if (empty($username) || empty($password)) {
        $pesan = "Username dan Password wajib diisi!";
    } else {
        $query = mysqli_query($koneksi, "SELECT * FROM users WHERE username = '$username'");
        
        if ($query && mysqli_num_rows($query) === 1) {
            $user = mysqli_fetch_assoc($query);

            if (password_verify($password, $user['password'])) {
                
                // 1. JIKA ROLE SUPERADMIN (LANGSUNG KE ADMIN.PHP)
                if ($user['role'] === 'superadmin') {
                    $_SESSION['user_id']      = $user['id'];
                    $_SESSION['nama_lengkap'] = $user['nama_lengkap'];
                    $_SESSION['nama_usaha']   = $user['nama_usaha'];
                    $_SESSION['role']         = $user['role'];

                    header("Location: admin.php");
                    exit;
                }

                // 2. JIKA ROLE ADMIN DAN USER (CEK STATUS & MASA AKTIF)
                if ($user['status'] === 'pending') {
                    $pesan = "⚠️ Akun Anda masih PENDING! Menunggu persetujuan Super Admin.";
                } elseif ($user['status'] === 'rejected') {
                    $pesan = "🚫 Pendaftaran akun Anda ditolak oleh Super Admin.";
                } elseif (!empty($user['expired_at']) && $user['expired_at'] !== '0000-00-00 00:00:00' && strtotime($user['expired_at']) < time()) {
                    $pesan = "⏰ Masa aktif akun Anda telah habis pada " . date('d-m-Y H:i', strtotime($user['expired_at']));
                } else {
                    $_SESSION['user_id']      = $user['id'];
                    $_SESSION['nama_lengkap'] = $user['nama_lengkap'];
                    $_SESSION['nama_usaha']   = $user['nama_usaha'];
                    $_SESSION['role']         = $user['role'];

                    header("Location: dashboard.php");
                    exit;
                }

            } else {
                $pesan = "❌ Password yang Anda masukkan salah!";
            }
        } else {
            $pesan = "❌ Username '$username' tidak ditemukan!";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Masuk ke Akun POS</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
</head>
<body class="bg-slate-900 font-sans text-slate-800 flex items-center justify-center min-h-screen p-4">
    <div class="bg-white p-8 rounded-2xl shadow-2xl max-w-md w-full border border-slate-700">
        <div class="text-center mb-6">
            <h2 class="text-2xl font-black text-slate-900">POS SYSTEM LOGIN</h2>
            <p class="text-xs text-slate-500 mt-1">Masukkan kredensial akun Anda</p>
        </div>

        <?php if ($pesan): ?>
            <div class="mb-4 p-3.5 bg-rose-100 border border-rose-200 text-rose-800 text-xs font-bold rounded-xl">
                <?= $pesan; ?>
            </div>
        <?php endif; ?>

        <form action="" method="POST" class="space-y-4">
            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Username</label>
                <input type="text" name="username" required class="w-full px-4 py-2.5 border border-slate-300 rounded-xl text-sm font-mono focus:ring-2 focus:ring-indigo-600 outline-none" placeholder="Masukkan username">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Password</label>
                <input type="password" name="password" required class="w-full px-4 py-2.5 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-indigo-600 outline-none" placeholder="••••••••">
            </div>
            <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3 rounded-xl transition shadow-lg text-sm">
                Masuk Sistem
            </button>
        </form>

        <div class="mt-6 text-center text-xs text-slate-500 space-y-3">
            <div>
                Daftar usaha baru? <a href="register.php" class="text-indigo-600 font-bold hover:underline">Registrasi Akun Admin</a>
            </div>
            <div class="pt-3 border-t border-slate-200 text-slate-600 leading-relaxed">
                Jika terkendala login silahkan hubungi administrator untuk bantuan <br>
                <a href="https://wa.me/6285890166697" target="_blank" class="inline-flex items-center gap-1 font-extrabold text-emerald-600 hover:text-emerald-700 hover:underline mt-1 bg-emerald-50 px-3 py-1.5 rounded-lg border border-emerald-200">
                    🟢 WA 085890166697
                </a>
            </div>
        </div>
    </div>
</body>
</html>