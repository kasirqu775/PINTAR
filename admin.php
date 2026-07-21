<?php
ob_start();
session_start();
require 'koneksi.php';

// -------------------------------------------------------------
// PROTEKSI STRUKTUR: HANYA USERNAME 'SU_admin' YANG BOLEH MASUK
// -------------------------------------------------------------
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php?err=unauthorized");
    exit;
}

$user_id = $_SESSION['user_id'];
$query   = mysqli_query($koneksi, "SELECT * FROM users WHERE id = '$user_id'");
$user    = mysqli_fetch_assoc($query);

// TOLA AKSES jika user tidak ditemukan, BUKAN role 'admin', ATAU username BUKAN 'SU_admin'
if (!$user || $user['role'] !== 'admin' || $user['username'] !== 'SU_admin') {
    session_destroy();
    header("Location: login.php?err=unauthorized");
    exit;
}

// -------------------------------------------------------------
// LOGIKA APPROVAL & PERPANJANGAN MASA AKTIF MEMBER
// -------------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['approve_user'])) {
    $target_user_id = intval($_POST['user_id']);
    $durasi         = $_POST['durasi'];

    // Hitung tanggal kedaluwarsa berdasarkan pilihan
    switch ($durasi) {
        case '1_hari':
            $expired_at = date('Y-m-d H:i:s', strtotime('+1 day'));
            break;
        case '1_bulan':
            $expired_at = date('Y-m-d H:i:s', strtotime('+1 month'));
            break;
        case '3_bulan':
            $expired_at = date('Y-m-d H:i:s', strtotime('+3 months'));
            break;
        case '6_bulan':
            $expired_at = date('Y-m-d H:i:s', strtotime('+6 months'));
            break;
        case '1_tahun':
            $expired_at = date('Y-m-d H:i:s', strtotime('+1 year'));
            break;
        default:
            $expired_at = date('Y-m-d H:i:s', strtotime('+1 day'));
    }

    $update = "UPDATE users SET status = 'active', expired_at = '$expired_at' WHERE id = $target_user_id";
    mysqli_query($koneksi, $update);
}

// Ambil data seluruh pengguna (role 'user')
$result = mysqli_query($koneksi, "SELECT * FROM users WHERE role = 'user' ORDER BY id DESC");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Pengelolaan Super Admin</title>
    <link rel="stylesheet" href="style.css">
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
</head>
<body class="bg-slate-100 font-sans text-slate-800">

    <div class="max-w-6xl mx-auto my-8 p-6 bg-white rounded-2xl shadow-sm border border-slate-200">
        <!-- Header Panel Super Admin -->
        <div class="flex flex-wrap justify-between items-center mb-6 border-b pb-4 gap-4">
            <div>
                <div class="flex items-center space-x-2">
                    <span class="bg-indigo-600 text-white text-xs font-bold px-2.5 py-1 rounded-md uppercase">Super Admin Only</span>
                    <h2 class="text-xl font-extrabold text-slate-900">Panel Persetujuan Member</h2>
                </div>
                <p class="text-xs text-slate-500 mt-1">
                    Logged in as: <strong class="text-indigo-700"><?= htmlspecialchars($user['username']); ?></strong> (<?= htmlspecialchars($user['nama_lengkap']); ?>)
                </p>
            </div>
            <a href="logout.php" class="bg-rose-600 hover:bg-rose-700 text-white font-bold text-xs px-4 py-2.5 rounded-xl shadow transition">
                Keluar (Logout) 🚪
            </a>
        </div>

        <!-- Tabel User Baru Pendaftar -->
        <div class="overflow-x-auto rounded-xl border border-slate-200">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-900 text-slate-200 text-xs uppercase tracking-wider">
                        <th class="p-3.5">ID</th>
                        <th class="p-3.5">Nama Lengkap</th>
                        <th class="p-3.5">Nama Usaha / Toko</th>
                        <th class="p-3.5">Username</th>
                        <th class="p-3.5 text-center">Status</th>
                        <th class="p-3.5 text-center">Masa Aktif S.D</th>
                        <th class="p-3.5 text-center">Aksi Approval</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 text-sm font-medium">
                    <?php if (mysqli_num_rows($result) > 0): ?>
                        <?php while ($row = mysqli_fetch_assoc($result)): ?>
                        <tr class="hover:bg-slate-50 transition">
                            <td class="p-3.5 font-bold font-mono text-slate-900"><?= $row['id']; ?></td>
                            <td class="p-3.5 font-semibold text-slate-900"><?= htmlspecialchars($row['nama_lengkap']); ?></td>
                            <td class="p-3.5 font-bold text-indigo-700"><?= htmlspecialchars($row['nama_usaha']); ?></td>
                            <td class="p-3.5 font-mono text-slate-600"><?= htmlspecialchars($row['username']); ?></td>
                            <td class="p-3.5 text-center">
                                <span class="text-xs font-bold px-2.5 py-1 rounded-full <?= $row['status'] === 'active' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'; ?>">
                                    <?= strtoupper($row['status']); ?>
                                </span>
                            </td>
                            <td class="p-3.5 text-center font-mono text-xs text-slate-600">
                                <?= ($row['expired_at'] && $row['expired_at'] !== '0000-00-00 00:00:00') ? date('d-m-Y H:i', strtotime($row['expired_at'])) : '-'; ?>
                            </td>
                            <td class="p-3.5 text-center">
                                <form action="" method="POST" class="flex justify-center items-center gap-2">
                                    <input type="hidden" name="user_id" value="<?= $row['id']; ?>">
                                    <select name="durasi" required class="text-xs p-2 border border-slate-300 rounded-lg bg-white font-medium">
                                        <option value="1_hari">1 Hari</option>
                                        <option value="1_bulan" selected>1 Bulan</option>
                                        <option value="3_bulan">3 Bulan</option>
                                        <option value="6_bulan">6 Bulan</option>
                                        <option value="1_tahun">1 Tahun</option>
                                    </select>
                                    <button type="submit" name="approve_user" class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs px-3 py-2 rounded-lg transition shadow">
                                        Setujui
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center text-slate-400 p-6 text-xs">Belum ada pendaftar baru yang membutuhkan persetujuan.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</body>
</html>
