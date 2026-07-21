<?php
ob_start();
session_start();
require 'koneksi.php';

// -------------------------------------------------------------
// PROTEKSI SESSION & ROLE UNTUK DASHBOARD POS
// -------------------------------------------------------------
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php?err=unauthorized");
    exit;
}

$user_id = $_SESSION['user_id'];
$query   = mysqli_query($koneksi, "SELECT * FROM users WHERE id = '$user_id'");
$user    = mysqli_fetch_assoc($query);

if (!$user) {
    session_destroy();
    header("Location: login.php?err=unauthorized");
    exit;
}

// Jika Superadmin mencoba membuka dashboard, kembalikan ke admin.php
if ($user['role'] === 'superadmin') {
    header("Location: admin.php");
    exit;
}

// Cek Status Aktif & Masa Aktif
if ($user['status'] !== 'active') {
    session_destroy();
    header("Location: login.php?err=pending");
    exit;
}

if (!empty($user['expired_at']) && $user['expired_at'] !== '0000-00-00 00:00:00') {
    $time_expired = strtotime($user['expired_at']);
    if ($time_expired !== false && $time_expired < time()) {
        session_destroy();
        header("Location: login.php?err=expired");
        exit;
    }
}

$nama_lengkap     = $user['nama_lengkap'];
$nama_usaha       = $user['nama_usaha'];
$role_user        = $user['role'];
$admin_expired_at = $user['expired_at'];

// -------------------------------------------------------------
// LOGIKA ABSENSI OTOMATIS (SISTEM RECORD 1X PER HARI)
// -------------------------------------------------------------
$tgl_sekarang   = date('Y-m-d');
$waktu_sekarang = date('H:i:s');

// Cek apakah user sudah absen hari ini
$cek_absensi = mysqli_query($koneksi, "SELECT id FROM absensi WHERE user_id = '$user_id' AND tanggal = '$tgl_sekarang'");
if ($cek_absensi && mysqli_num_rows($cek_absensi) == 0) {
    $nama_lengkap_esc = mysqli_real_escape_string($koneksi, $nama_lengkap);
    $nama_usaha_esc   = mysqli_real_escape_string($koneksi, $nama_usaha);
    $role_esc         = mysqli_real_escape_string($koneksi, $role_user);

    // Rekam absensi baru hanya pada login/akses pertama hari ini
    mysqli_query($koneksi, "INSERT INTO absensi (user_id, nama_lengkap, nama_usaha, role, tanggal, waktu_masuk) 
                            VALUES ('$user_id', '$nama_lengkap_esc', '$nama_usaha_esc', '$role_esc', '$tgl_sekarang', '$waktu_sekarang')");
}

// Ambil Riwayat Absensi Toko Ini
$nama_usaha_esc = mysqli_real_escape_string($koneksi, $nama_usaha);
$q_absensi      = mysqli_query($koneksi, "SELECT * FROM absensi WHERE nama_usaha = '$nama_usaha_esc' ORDER BY id DESC LIMIT 100");
$list_absensi   = [];
if ($q_absensi) {
    while ($row_a = mysqli_fetch_assoc($q_absensi)) {
        $list_absensi[] = $row_a;
    }
}

// -------------------------------------------------------------
// LOGIKA ADMIN: FITUR TAMBAH & HAPUS KASIR TOKO
// -------------------------------------------------------------
$pesan_kasir = '';
$tipe_pesan_kasir = '';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action_tambah_kasir'])) {
    if ($role_user === 'admin') {
        $kasir_nama     = mysqli_real_escape_string($koneksi, trim($_POST['kasir_nama'] ?? ''));
        $kasir_username = mysqli_real_escape_string($koneksi, trim($_POST['kasir_username'] ?? ''));
        $kasir_password = trim($_POST['kasir_password'] ?? '');

        if (empty($kasir_nama) || empty($kasir_username) || empty($kasir_password)) {
            $pesan_kasir = "Semua bidang formulir wajib diisi!";
            $tipe_pesan_kasir = "error";
        } else {
            $cek_u = mysqli_query($koneksi, "SELECT id FROM users WHERE username = '$kasir_username'");
            if ($cek_u && mysqli_num_rows($cek_u) > 0) {
                $pesan_kasir = "Username '$kasir_username' sudah digunakan, pilih username lain!";
                $tipe_pesan_kasir = "error";
            } else {
                $pass_hash = password_hash($kasir_password, PASSWORD_BCRYPT);
                $exp_value = (!empty($admin_expired_at) && $admin_expired_at !== '0000-00-00 00:00:00') ? "'$admin_expired_at'" : "NULL";

                $sql_insert = "INSERT INTO users (nama_lengkap, nama_usaha, username, password, role, status, expired_at) 
                               VALUES ('$kasir_nama', '$nama_usaha_esc', '$kasir_username', '$pass_hash', 'user', 'active', $exp_value)";
                
                if (mysqli_query($koneksi, $sql_insert)) {
                    $pesan_kasir = "Berhasil menambahkan kasir/user baru: $kasir_nama!";
                    $tipe_pesan_kasir = "success";
                } else {
                    $pesan_kasir = "Gagal menambah kasir: " . mysqli_error($koneksi);
                    $tipe_pesan_kasir = "error";
                }
            }
        }
    }
}

// Logika Hapus Kasir
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action_hapus_kasir'])) {
    if ($role_user === 'admin') {
        $id_kasir = intval($_POST['id_kasir']);
        mysqli_query($koneksi, "DELETE FROM users WHERE id = $id_kasir AND nama_usaha = '$nama_usaha_esc' AND role = 'user'");
        $pesan_kasir = "Akun kasir berhasil dihapus dari sistem.";
        $tipe_pesan_kasir = "success";
    }
}

// Ambil Daftar Kasir
$list_kasir = [];
if ($role_user === 'admin') {
    $q_kasir = mysqli_query($koneksi, "SELECT * FROM users WHERE nama_usaha = '$nama_usaha_esc' AND role = 'user' ORDER BY id DESC");
    if ($q_kasir) {
        while ($row_k = mysqli_fetch_assoc($q_kasir)) {
            $list_kasir[] = $row_k;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>POS System - <?= htmlspecialchars($nama_usaha); ?></title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <style>
        .page { display: none; }
        .active { display: block; }
        @media print {
            body * { visibility: hidden; }
            #area-cetak-struk, #area-cetak-struk * { visibility: visible; }
            #area-cetak-struk { position: absolute; left: 0; top: 0; width: 58mm; font-family: monospace; font-size: 10px; whitespace: pre-wrap; }
        }
    </style>
</head>
<body class="bg-slate-100 font-sans text-slate-800 antialiased">

    <!-- DASHBOARD UTAMA -->
    <div id="page-dashboard" class="page active min-h-screen flex flex-col">
        
        <!-- Elegant Midnight Header / Navbar -->
        <nav class="bg-slate-900 text-white px-6 py-4 flex flex-wrap justify-between items-center shadow-lg border-b border-slate-800 gap-4">
            <div class="flex items-center space-x-4">
                <div class="bg-indigo-600 text-white p-2 rounded-lg font-black text-lg tracking-wider shadow">POS</div>
                <div>
                    <span class="text-xl font-extrabold tracking-wider text-amber-400 block uppercase"><?= htmlspecialchars($nama_usaha); ?></span>
                    <span class="text-[10px] text-slate-400 tracking-widest font-mono uppercase">System Integrated V2.0</span>
                </div>
            </div>

            <!-- HARI, TANGGAL & JAM BERJALAN (WAKTU PERANGKAT) -->
            <div class="hidden lg:flex flex-col items-center bg-slate-800/90 px-4 py-1.5 rounded-xl border border-slate-700 shadow-inner">
                <span class="text-[9px] font-bold uppercase text-slate-400 tracking-widest">Waktu Sistem Perangkat</span>
                <span id="realtime-clock" class="text-xs font-mono font-extrabold text-amber-400">Loading Clock...</span>
            </div>

            <div class="flex items-center space-x-4">
                <div class="bg-slate-800/80 px-4 py-1.5 rounded-xl border border-slate-700 text-right">
                    <p id="user-display-name" class="font-bold text-slate-100 text-sm"><?= htmlspecialchars($nama_lengkap); ?></p>
                    <p id="user-display-role" class="text-[10px] font-mono tracking-wider uppercase text-amber-400 font-semibold"><?= htmlspecialchars($role_user); ?></p>
                </div>
                <button onclick="handleLogout()" class="bg-rose-600 hover:bg-rose-700 text-white px-4 py-2 rounded-xl text-xs font-bold transition-all shadow-md hover:shadow-rose-900/30">
                    Keluar 🚪
                </button>
            </div>
        </nav>

        <!-- Menu Navigasi / Tab -->
        <div class="bg-white shadow-sm flex border-b border-slate-200 overflow-x-auto px-4 space-x-2">
            <button onclick="switchTab('transaksi')" id="btn-tab-transaksi" class="px-5 py-3.5 font-bold text-sm border-b-2 border-indigo-600 text-indigo-700 bg-indigo-50/50 rounded-t-lg shrink-0 transition-all">🛒 Mesin Kasir (POS)</button>
            <button onclick="switchTab('riwayat')" id="btn-tab-riwayat" class="px-5 py-3.5 font-semibold text-sm text-slate-600 hover:text-slate-900 shrink-0 transition-all">📜 Riwayat Transaksi</button>
            <button onclick="switchTab('laporan')" id="btn-tab-laporan" class="px-5 py-3.5 font-semibold text-sm text-slate-600 hover:text-slate-900 shrink-0 transition-all">📈 Laporan Omzet</button>
            <button onclick="switchTab('barang')" id="btn-tab-barang" class="px-5 py-3.5 font-semibold text-sm text-slate-600 hover:text-slate-900 shrink-0 transition-all">📦 Logistik Barang</button>
            <button onclick="switchTab('member')" id="btn-tab-member" class="px-5 py-3.5 font-semibold text-sm text-slate-600 hover:text-slate-900 shrink-0 transition-all">👑 Database Member</button>
            <button onclick="switchTab('opname')" id="btn-tab-opname" class="px-5 py-3.5 font-semibold text-sm text-slate-600 hover:text-slate-900 shrink-0 transition-all">🔍 Stock Opname</button>
            
            <!-- TAB ABSENSI KASIR -->
            <button onclick="switchTab('absensi')" id="btn-tab-absensi" class="px-5 py-3.5 font-semibold text-sm text-slate-600 hover:text-slate-900 shrink-0 transition-all">📅 Absensi Kasir</button>

            <!-- TAB KHUSUS ADMIN KELOLA KASIR -->
            <button onclick="switchTab('users')" id="btn-tab-users" class="px-5 py-3.5 font-bold text-sm text-indigo-700 shrink-0 view-admin-only bg-indigo-50/80 rounded-t-lg border-b-2 border-indigo-600">👥 Kelola Kasir</button>
        </div>

        <!-- Konten Utama -->
        <main class="flex-1 p-6">
            
            <!-- TAB 1: MESIN KASIR (POS) -->
            <div id="tab-transaksi" class="tab-content grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div class="lg:col-span-2 bg-white p-6 rounded-2xl shadow-sm border border-slate-200 space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1">Pencarian Barang</label>
                            <input type="text" id="pos-search" oninput="cariBarangPOS()" class="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-indigo-600 font-medium text-slate-900" placeholder="Ketik Nama / Scan Barcode...">
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1">CRM Member (No HP)</label>
                            <div class="flex space-x-2">
                                <input type="text" id="pos-member-input" class="flex-1 px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-indigo-600 font-mono font-bold text-slate-900" placeholder="Contoh: 0812...">
                                <button onclick="terapkanMemberPOS()" class="bg-indigo-600 text-white px-5 rounded-xl font-bold text-xs hover:bg-indigo-700 shadow-md">Cek Member</button>
                            </div>
                        </div>
                    </div>
                    
                    <div id="pos-search-results" class="bg-slate-50 border border-slate-200 rounded-xl max-h-48 overflow-y-auto hidden divide-y"></div>
                    
                    <!-- Status Member Box -->
                    <div id="pos-member-status-box" class="bg-indigo-900 text-white p-4 rounded-xl shadow-inner hidden space-y-3 border border-indigo-700">
                        <div class="flex flex-wrap justify-between items-center gap-2">
                            <span id="pos-member-info-text" class="font-bold text-sm text-amber-300"></span>
                            <div class="flex gap-2">
                                <span id="pos-member-poin-text" class="bg-amber-500 text-slate-950 font-mono text-xs px-2.5 py-1 rounded-md font-extrabold"></span>
                                <span id="pos-member-piutang-text" class="bg-rose-600 text-white font-mono text-xs px-2.5 py-1 rounded-md font-bold"></span>
                            </div>
                        </div>
                        <div class="bg-slate-800 p-3 rounded-lg border border-slate-700 flex items-center space-x-3">
                            <input type="checkbox" id="pos-use-discount-checkbox" onchange="renderKeranjang()" checked class="w-5 h-5 cursor-pointer accent-indigo-500">
                            <label for="pos-use-discount-checkbox" class="text-xs font-bold text-slate-200 cursor-pointer select-none">
                                Berikan skema potongan diskon khusus member untuk belanjaan ini
                            </label>
                        </div>
                    </div>

                    <!-- Tabel Keranjang -->
                    <div class="overflow-x-auto rounded-xl border border-slate-200">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-slate-900 text-slate-200 uppercase text-xs tracking-wider">
                                    <th class="p-3.5">Detail Barang</th>
                                    <th class="p-3.5 text-center">Harga Jual</th>
                                    <th class="p-3.5 text-center w-28">Qty</th>
                                    <th class="p-3.5 text-right">Subtotal</th>
                                    <th class="p-3.5 text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody id="cart-table-body" class="divide-y divide-slate-200 font-medium text-sm"></tbody>
                        </table>
                    </div>
                </div>

                <!-- Card Total & Pembayaran -->
                <div class="bg-slate-900 text-white p-6 rounded-2xl shadow-xl flex flex-col justify-between border border-slate-800">
                    <div>
                        <span class="text-xs font-bold uppercase tracking-widest text-slate-400 block mb-1">Total Tagihan Belanja</span>
                        <h1 id="cart-total-display" class="text-4xl font-black font-mono text-amber-400 tracking-tight mb-2">Rp 0</h1>
                        <p id="cart-discount-display" class="text-xs font-bold text-rose-400 mb-2 bg-rose-950/60 p-2 rounded-lg border border-rose-800 hidden"></p>
                        <p id="cart-poin-estimate-display" class="text-xs font-bold text-amber-300 mb-4 bg-slate-800 inline-block px-3 py-1.5 rounded-lg border border-slate-700 hidden"></p>
                        
                        <div class="space-y-4 pt-2">
                            <!-- Pilihan Metode Pembayaran -->
                            <div>
                                <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-2">Metode Bayar</label>
                                <div class="grid grid-cols-3 gap-2">
                                    <button onclick="setMetodeBayar('CASH')" id="btn-pay-cash" class="py-2.5 rounded-xl font-bold border-2 border-emerald-500 bg-emerald-600 text-white transition text-xs shadow-md">💵 TUNAI</button>
                                    <button onclick="setMetodeBayar('QRIS')" id="btn-pay-qris" class="py-2.5 rounded-xl font-bold border-2 border-slate-700 bg-slate-800 text-slate-300 hover:bg-slate-700 transition text-xs shadow-md">📱 QRIS</button>
                                    <button onclick="setMetodeBayar('DEBT')" id="btn-pay-debt" class="py-2.5 rounded-xl font-bold border-2 border-slate-700 bg-slate-800 text-slate-300 hover:bg-slate-700 transition text-xs shadow-md">📌 BON</button>
                                </div>
                            </div>

                            <!-- Input Uang Diterima -->
                            <div id="container-input-pembayaran">
                                <label id="label-nominal-bayar" class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-1">Uang Diterima (Cash)</label>
                                <input type="number" id="pos-bayar" oninput="hitungKembalian()" class="w-full text-2xl px-4 py-2.5 bg-slate-800 border border-slate-700 rounded-xl font-mono font-bold text-amber-400 focus:ring-2 focus:ring-amber-400 outline-none" placeholder="0">
                                
                                <div class="bg-slate-800 p-4 rounded-xl border border-slate-700 mt-3">
                                    <p id="label-kembalian-status" class="text-xs font-bold text-slate-400 uppercase tracking-wider">Kembalian Tunai</p>
                                    <h2 id="pos-kembalian-display" class="text-2xl font-black font-mono text-emerald-400 mt-0.5">Rp 0</h2>
                                </div>
                            </div>

                            <!-- Detail QRIS -->
                            <div id="container-display-qris" class="bg-slate-800 p-4 rounded-xl border border-slate-700 hidden space-y-2 text-sm">
                                <p class="text-xs font-bold text-amber-400 uppercase tracking-wider border-b border-slate-700 pb-1">Detail Tagihan QRIS</p>
                                <div class="flex justify-between text-slate-300"><span class="text-xs">Subtotal</span><span class="font-mono font-bold" id="qris-val-net">Rp 0</span></div>
                                <div class="flex justify-between text-rose-400 font-medium hidden" id="qris-val-disc-row"><span class="text-xs">Diskon</span><span class="font-mono font-bold" id="qris-val-disc">-Rp 0</span></div>
                                <div class="flex justify-between items-center pt-2 border-t border-slate-700"><span class="text-xs font-bold text-white">Nominal QRIS</span><span class="text-lg font-black font-mono text-indigo-400" id="qris-val-total">Rp 0</span></div>
                            </div>

                            <!-- Detail Piutang -->
                            <div id="container-display-debt" class="bg-slate-800 p-4 rounded-xl border border-slate-700 hidden space-y-2 text-sm">
                                <p class="text-xs font-bold text-rose-400 uppercase tracking-wider border-b border-slate-700 pb-1">Detail Piutang Member</p>
                                <div class="flex justify-between text-slate-300"><span class="text-xs">Limit Kredit</span><span class="font-mono font-bold" id="debt-val-limit">Rp 0</span></div>
                                <div class="flex justify-between text-rose-400"><span class="text-xs">Belanja Ini</span><span class="font-mono font-bold" id="debt-val-belanja">Rp 0</span></div>
                                <div class="flex justify-between items-center pt-2 border-t border-slate-700"><span class="text-xs font-bold text-white">Sisa Limit</span><span class="text-lg font-black font-mono text-slate-200" id="debt-val-sisa-limit">Rp 0</span></div>
                            </div>

                        </div>
                    </div>
                    
                    <!-- Tombol Eksekusi -->
                    <div class="mt-6 space-y-2">
                        <button onclick="prosesTransaksiSpesifik('CASH')" id="btn-submit-cash" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white py-4 rounded-xl text-base font-extrabold tracking-wider shadow-lg transition">
                            PROSES BAYAR TUNAI 💵
                        </button>
                        <button onclick="prosesTransaksiSpesifik('QRIS')" id="btn-submit-qris" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white py-4 rounded-xl text-base font-extrabold tracking-wider shadow-lg transition hidden">
                            KONFIRMASI BAYAR QRIS 📱
                        </button>
                        <button onclick="prosesTransaksiSpesifik('DEBT')" id="btn-submit-debt" class="w-full bg-rose-600 hover:bg-rose-700 text-white py-4 rounded-xl text-base font-extrabold tracking-wider shadow-lg transition hidden">
                            PROSES BON PIUTANG 📌
                        </button>
                    </div>
                </div>
            </div>

            <!-- TAB 2: RIWAYAT TRANSAKSI -->
            <div id="tab-riwayat" class="tab-content hidden space-y-6">
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <div class="lg:col-span-2 bg-white p-6 rounded-2xl shadow-sm border border-slate-200">
                        <h3 class="text-base font-bold text-slate-900 mb-4 border-b pb-2">Daftar Transaksi Selesai</h3>
                        <div class="overflow-x-auto rounded-xl border border-slate-200">
                            <table class="w-full text-left border-collapse">
                                <thead>
                                    <tr class="bg-slate-900 text-slate-200 text-xs uppercase tracking-wider">
                                        <th class="p-3">ID Transaksi</th>
                                        <th class="p-3">Waktu</th>
                                        <th class="p-3">Metode</th>
                                        <th class="p-3">Pelanggan</th>
                                        <th class="p-3 text-right">Total Akhir</th>
                                        <th class="p-3 text-center">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody id="history-table-body" class="divide-y divide-slate-200 text-sm font-medium"></tbody>
                            </table>
                        </div>
                    </div>
                    <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200 flex flex-col justify-between">
                        <div>
                            <h3 class="text-base font-bold text-slate-900 mb-4 border-b pb-2">Preview Struk Kasir</h3>
                            <div id="area-cetak-struk" class="bg-slate-900 text-emerald-400 p-4 rounded-xl font-mono text-xs shadow-inner whitespace-pre-wrap leading-tight border border-slate-800">Pilih transaksi untuk melihat nota.</div>
                        </div>
                        <button onclick="window.print()" class="w-full mt-4 bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3 rounded-xl transition shadow-md text-sm">
                            🖨️ CETAK STRUK THERMAL
                        </button>
                    </div>
                </div>
            </div>

            <!-- TAB 3: LAPORAN KEUANGAN -->
            <div id="tab-laporan" class="tab-content hidden space-y-6">
                <div class="border-b pb-4 flex justify-between items-center bg-white p-6 rounded-2xl shadow-sm border border-slate-200">
                    <div>
                        <h2 class="text-xl font-extrabold text-slate-900">Panel Akumulasi Keuangan</h2>
                        <p class="text-xs text-slate-500 mt-1">Data rekapitulasi real-time omzet penjualan kasir berdasarkan metode bayar & CRM.</p>
                    </div>
                    <button onclick="hitungUlangLaporanKeuangan()" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2.5 rounded-xl text-xs font-bold shadow">🔄 REFRESH DATA STATISTIK</button>
                </div>

                <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
                    <!-- Harian -->
                    <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200 flex flex-col justify-between">
                        <div class="space-y-4">
                            <div class="bg-slate-900 p-3.5 rounded-xl text-white border border-slate-800">
                                <h4 class="font-bold text-[10px] uppercase tracking-widest text-slate-400">Periode Akumulasi</h4>
                                <h2 class="text-base font-black text-amber-400">📅 HARI INI (24 JAM)</h2>
                            </div>
                            <div class="space-y-2">
                                <div class="flex justify-between bg-slate-50 p-3 rounded-xl border border-slate-200">
                                    <span class="text-xs text-slate-600 font-bold uppercase">Total Omzet</span>
                                    <span class="font-black font-mono text-slate-900 text-base" id="rep-hari-total">Rp 0</span>
                                </div>
                                <div class="grid grid-cols-3 gap-1.5 text-xs pt-1">
                                    <div class="bg-emerald-50 p-2 rounded-lg border border-emerald-200 text-center">
                                        <p class="text-slate-500 font-bold text-[9px] mb-1">TUNAI</p>
                                        <p class="font-mono font-bold text-emerald-700 text-xs" id="rep-hari-cash">Rp 0</p>
                                    </div>
                                    <div class="bg-indigo-50 p-2 rounded-lg border border-indigo-200 text-center">
                                        <p class="text-slate-500 font-bold text-[9px] mb-1">QRIS</p>
                                        <p class="font-mono font-bold text-indigo-700 text-xs" id="rep-hari-qris">Rp 0</p>
                                    </div>
                                    <div class="bg-rose-50 p-2 rounded-lg border border-rose-200 text-center">
                                        <p class="text-slate-500 font-bold text-[9px] mb-1">BON</p>
                                        <p class="font-mono font-bold text-rose-700 text-xs" id="rep-hari-debt">Rp 0</p>
                                    </div>
                                </div>
                                <div class="grid grid-cols-2 gap-2 text-xs pt-1">
                                    <div class="bg-purple-50 p-2.5 rounded-lg border border-purple-200">
                                        <p class="text-slate-500 font-bold text-[10px] mb-0.5">BELANJA MEMBER</p>
                                        <p class="font-mono font-bold text-purple-700 text-sm" id="rep-hari-member">Rp 0</p>
                                    </div>
                                    <div class="bg-slate-100 p-2.5 rounded-lg border border-slate-200">
                                        <p class="text-slate-500 font-bold text-[10px] mb-0.5">NON-MEMBER</p>
                                        <p class="font-mono font-bold text-slate-700 text-sm" id="rep-hari-umum">Rp 0</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <button onclick="cetakLaporanThermal('hari')" class="w-full mt-6 bg-slate-900 hover:bg-slate-800 text-white font-bold py-2.5 rounded-xl text-xs transition">🖨️ CETAK LAPORAN HARIAN</button>
                    </div>

                    <!-- Mingguan -->
                    <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200 flex flex-col justify-between">
                        <div class="space-y-4">
                            <div class="bg-slate-900 p-3.5 rounded-xl text-white border border-slate-800">
                                <h4 class="font-bold text-[10px] uppercase tracking-widest text-slate-400">Periode Akumulasi</h4>
                                <h2 class="text-base font-black text-amber-400">📊 7 HARI TERAKHIR</h2>
                            </div>
                            <div class="space-y-2">
                                <div class="flex justify-between bg-slate-50 p-3 rounded-xl border border-slate-200">
                                    <span class="text-xs text-slate-600 font-bold uppercase">Total Omzet</span>
                                    <span class="font-black font-mono text-slate-900 text-base" id="rep-minggu-total">Rp 0</span>
                                </div>
                                <div class="grid grid-cols-3 gap-1.5 text-xs pt-1">
                                    <div class="bg-emerald-50 p-2 rounded-lg border border-emerald-200 text-center">
                                        <p class="text-slate-500 font-bold text-[9px] mb-1">TUNAI</p>
                                        <p class="font-mono font-bold text-emerald-700 text-xs" id="rep-minggu-cash">Rp 0</p>
                                    </div>
                                    <div class="bg-indigo-50 p-2 rounded-lg border border-indigo-200 text-center">
                                        <p class="text-slate-500 font-bold text-[9px] mb-1">QRIS</p>
                                        <p class="font-mono font-bold text-indigo-700 text-xs" id="rep-minggu-qris">Rp 0</p>
                                    </div>
                                    <div class="bg-rose-50 p-2 rounded-lg border border-rose-200 text-center">
                                        <p class="text-slate-500 font-bold text-[9px] mb-1">BON</p>
                                        <p class="font-mono font-bold text-rose-700 text-xs" id="rep-minggu-debt">Rp 0</p>
                                    </div>
                                </div>
                                <div class="grid grid-cols-2 gap-2 text-xs pt-1">
                                    <div class="bg-purple-50 p-2.5 rounded-lg border border-purple-200">
                                        <p class="text-slate-500 font-bold text-[10px] mb-0.5">BELANJA MEMBER</p>
                                        <p class="font-mono font-bold text-purple-700 text-sm" id="rep-minggu-member">Rp 0</p>
                                    </div>
                                    <div class="bg-slate-100 p-2.5 rounded-lg border border-slate-200">
                                        <p class="text-slate-500 font-bold text-[10px] mb-0.5">NON-MEMBER</p>
                                        <p class="font-mono font-bold text-slate-700 text-sm" id="rep-minggu-umum">Rp 0</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <button onclick="cetakLaporanThermal('minggu')" class="w-full mt-6 bg-slate-900 hover:bg-slate-800 text-white font-bold py-2.5 rounded-xl text-xs transition">🖨️ CETAK LAPORAN MINGGUAN</button>
                    </div>

                    <!-- Bulanan -->
                    <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200 flex flex-col justify-between">
                        <div class="space-y-4">
                            <div class="bg-slate-900 p-3.5 rounded-xl text-white border border-slate-800">
                                <h4 class="font-bold text-[10px] uppercase tracking-widest text-slate-400">Periode Akumulasi</h4>
                                <h2 class="text-base font-black text-amber-400">📈 30 HARI TERAKHIR</h2>
                            </div>
                            <div class="space-y-2">
                                <div class="flex justify-between bg-slate-50 p-3 rounded-xl border border-slate-200">
                                    <span class="text-xs text-slate-600 font-bold uppercase">Total Omzet</span>
                                    <span class="font-black font-mono text-slate-900 text-base" id="rep-bulan-total">Rp 0</span>
                                </div>
                                <div class="grid grid-cols-3 gap-1.5 text-xs pt-1">
                                    <div class="bg-emerald-50 p-2 rounded-lg border border-emerald-200 text-center">
                                        <p class="text-slate-500 font-bold text-[9px] mb-1">TUNAI</p>
                                        <p class="font-mono font-bold text-emerald-700 text-xs" id="rep-bulan-cash">Rp 0</p>
                                    </div>
                                    <div class="bg-indigo-50 p-2 rounded-lg border border-indigo-200 text-center">
                                        <p class="text-slate-500 font-bold text-[9px] mb-1">QRIS</p>
                                        <p class="font-mono font-bold text-indigo-700 text-xs" id="rep-bulan-qris">Rp 0</p>
                                    </div>
                                    <div class="bg-rose-50 p-2 rounded-lg border border-rose-200 text-center">
                                        <p class="text-slate-500 font-bold text-[9px] mb-1">BON</p>
                                        <p class="font-mono font-bold text-rose-700 text-xs" id="rep-bulan-debt">Rp 0</p>
                                    </div>
                                </div>
                                <div class="grid grid-cols-2 gap-2 text-xs pt-1">
                                    <div class="bg-purple-50 p-2.5 rounded-lg border border-purple-200">
                                        <p class="text-slate-500 font-bold text-[10px] mb-0.5">BELANJA MEMBER</p>
                                        <p class="font-mono font-bold text-purple-700 text-sm" id="rep-bulan-member">Rp 0</p>
                                    </div>
                                    <div class="bg-slate-100 p-2.5 rounded-lg border border-slate-200">
                                        <p class="text-slate-500 font-bold text-[10px] mb-0.5">NON-MEMBER</p>
                                        <p class="font-mono font-bold text-slate-700 text-sm" id="rep-bulan-umum">Rp 0</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <button onclick="cetakLaporanThermal('bulan')" class="w-full mt-6 bg-slate-900 hover:bg-slate-800 text-white font-bold py-2.5 rounded-xl text-xs transition">🖨️ CETAK LAPORAN BULANAN</button>
                    </div>

                </div>
            </div>

            <!-- TAB 4: MASTER BARANG -->
            <div id="tab-barang" class="tab-content hidden">
                <div class="bg-slate-900 text-white p-4 rounded-2xl shadow mb-6 flex flex-wrap justify-between items-center gap-4 view-admin-only">
                    <div>
                        <h4 class="font-bold text-amber-400 text-sm">Database Tools (CSV Backup/Restore)</h4>
                        <p class="text-xs text-slate-300">Ekspor atau impor master data produk ritel secara otomatis.</p>
                    </div>
                    <div class="flex items-center space-x-3">
                        <button onclick="exportDatabaseBarang()" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-xl text-xs font-bold shadow">📤 EXPORT CSV</button>
                        <label class="bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded-xl text-xs font-bold cursor-pointer shadow">
                            📥 IMPORT CSV
                            <input type="file" id="import-file-csv" onchange="importDatabaseBarang(event)" class="hidden" accept=".csv">
                        </label>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200">
                        <h3 class="text-base font-bold text-slate-900 mb-4 border-b pb-2" id="title-form-barang">Form Logistik Barang</h3>
                        <div class="space-y-4">
                            <div>
                                <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Kode Barcode</label>
                                <input type="text" id="prod-kode" onblur="autoFillBarangLama()" class="w-full px-3.5 py-2.5 border border-slate-300 rounded-xl font-mono font-bold text-slate-900" placeholder="1001">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Nama Barang</label>
                                <input type="text" id="prod-nama" class="w-full px-3.5 py-2.5 border border-slate-300 rounded-xl text-slate-900 font-medium">
                            </div>
                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Kategori</label>
                                    <input type="text" id="prod-kategori" class="w-full px-3.5 py-2.5 border border-slate-300 rounded-xl text-slate-900">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Susunan Rak</label>
                                    <input type="text" id="prod-rak" class="w-full px-3.5 py-2.5 border border-slate-300 rounded-xl text-slate-900">
                                </div>
                            </div>

                            <div id="form-harga-admin-only" class="space-y-4 view-admin-only">
                                <div class="grid grid-cols-2 gap-2">
                                    <div>
                                        <label class="block text-xs font-bold text-slate-600 uppercase mb-1">HPP (Modal)</label>
                                        <input type="number" id="prod-hpp" oninput="hitungMarginDanHarga('hpp')" class="w-full px-3.5 py-2.5 border border-slate-300 rounded-xl font-mono text-slate-900">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Margin (%)</label>
                                        <input type="number" id="prod-margin" oninput="hitungMarginDanHarga('margin')" class="w-full px-3.5 py-2.5 border border-slate-300 rounded-xl font-mono text-slate-900">
                                    </div>
                                </div>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-indigo-700 uppercase mb-1">Harga Jual Terbaru (Rp)</label>
                                <input type="number" id="prod-harga" class="w-full px-3.5 py-2.5 border-2 border-indigo-400 rounded-xl font-mono font-bold text-indigo-900 text-lg">
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Jumlah Qty Masuk (+)</label>
                                <input type="number" id="prod-stok" class="w-full px-3.5 py-2.5 border border-slate-300 rounded-xl font-mono text-slate-900" placeholder="0">
                            </div>
                            <button onclick="simpanBarang()" class="w-full bg-indigo-600 text-white py-3 rounded-xl font-bold hover:bg-indigo-700 transition shadow-md text-sm">Simpan Data Barang</button>
                        </div>
                    </div>

                    <div class="lg:col-span-2 bg-white p-6 rounded-2xl shadow-sm border border-slate-200">
                        <h3 class="text-base font-bold text-slate-900 mb-4 border-b pb-2">Master Data Inventori</h3>
                        <div class="overflow-x-auto rounded-xl border border-slate-200">
                            <table class="w-full text-left border-collapse">
                                <thead>
                                    <tr class="bg-slate-900 text-slate-200 text-xs uppercase tracking-wider">
                                        <th class="p-3">Kode</th>
                                        <th class="p-3">Nama Produk</th>
                                        <th class="p-3">Rak</th>
                                        <th class="p-3 text-right view-admin-only">HPP</th>
                                        <th class="p-3 text-right">Harga Jual</th>
                                        <th class="p-3 text-center">Promo</th>
                                        <th class="p-3 text-center bg-slate-800">Stok</th>
                                    </tr>
                                </thead>
                                <tbody id="inventory-table-body" class="divide-y divide-slate-200 text-sm font-medium"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TAB 5: DATABASE MEMBER -->
            <div id="tab-member" class="tab-content hidden space-y-6">
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <div class="space-y-6">
                        <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200">
                            <h3 class="text-base font-bold text-slate-900 mb-4 border-b pb-2">⚙️ Parameter Loyalty & Diskon</h3>
                            <div class="space-y-4">
                                <div class="view-admin-only">
                                    <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Tipe Diskon Member</label>
                                    <select id="promo-tipe" class="w-full px-3 py-2 border border-slate-300 rounded-xl font-bold bg-white text-slate-800">
                                        <option value="persen">Persentase (%)</option>
                                        <option value="nominal">Potongan Tetap (Rp)</option>
                                    </select>
                                </div>
                                <div class="view-admin-only">
                                    <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Nilai Potongan</label>
                                    <input type="number" id="promo-value" class="w-full px-3 py-2 border border-slate-300 rounded-xl font-mono font-bold text-rose-600" placeholder="5">
                                </div>
                                <hr class="my-2 border-slate-200">
                                <div class="view-admin-only">
                                    <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Minimal Belanja per 1 Poin (Rp)</label>
                                    <input type="number" id="poin-konversi-rule" class="w-full px-3 py-2 border border-slate-300 rounded-xl font-mono font-bold text-indigo-700" placeholder="10000">
                                </div>
                                <button onclick="simpanAturanDiskonDanPoin()" class="w-full bg-slate-900 text-white py-2.5 rounded-xl font-bold text-xs shadow view-admin-only">UPDATE PARAMETER PROMO</button>
                                <div class="bg-slate-50 p-3 rounded-xl border border-slate-200 text-xs text-slate-600 space-y-1">
                                    <strong>Status Skema Aktif Toko:</strong>
                                    <p id="status-diskon-aktif" class="text-xs font-bold text-emerald-700">-</p>
                                    <p id="status-poin-aktif" class="text-xs font-bold text-indigo-700">-</p>
                                </div>
                            </div>
                        </div>

                        <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200 view-admin-only">
                            <h3 class="text-base font-bold text-amber-700 mb-4 border-b pb-2">🏷️ Promo Barang Tertentu</h3>
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Pilih Produk</label>
                                    <select id="promo-barang-kode" class="w-full px-3 py-2 border border-slate-300 rounded-xl text-xs bg-white font-medium"></select>
                                </div>
                                <div class="grid grid-cols-2 gap-2">
                                    <div>
                                        <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Tipe Potongan</label>
                                        <select id="promo-barang-tipe" class="w-full px-3 py-2 border border-slate-300 rounded-xl text-xs bg-white">
                                            <option value="persen">Persen (%)</option>
                                            <option value="nominal">Nominal (Rp)</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Nilai Promo</label>
                                        <input type="number" id="promo-barang-nilai" class="w-full px-3 py-2 border border-slate-300 rounded-xl text-xs font-mono font-bold text-amber-600" placeholder="0">
                                    </div>
                                </div>
                                <button onclick="simpanPromoBarang()" class="w-full bg-amber-600 text-white py-2.5 rounded-xl font-bold text-xs shadow hover:bg-amber-700">PASANG PROMO BARANG</button>
                            </div>
                        </div>
                    </div>

                    <div class="lg:col-span-2 bg-white p-6 rounded-2xl shadow-sm border border-slate-200 space-y-6">
                        <div>
                            <h3 class="text-base font-bold text-slate-900 mb-4 border-b pb-2">Registrasi & CRM Member</h3>
                            <div class="flex flex-wrap gap-2 mb-4">
                                <input type="text" id="mem-nama" class="px-3.5 py-2 border border-slate-300 rounded-xl text-sm" placeholder="Nama Member">
                                <input type="text" id="mem-hp" class="px-3.5 py-2 border border-slate-300 rounded-xl text-sm font-mono" placeholder="No HP/Kode (0812...)">
                                <input type="number" id="mem-limit-piutang" class="px-3.5 py-2 border border-slate-300 rounded-xl text-sm font-mono" placeholder="Limit Piutang (Rp)">
                                <button onclick="tambahMember()" class="bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2 rounded-xl font-bold text-xs shadow">Daftarkan Member</button>
                            </div>
                        </div>
                        
                        <div id="crm-piutang-quick-pay" class="bg-rose-50 p-4 border border-rose-200 rounded-xl hidden flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                            <div>
                                <h4 class="font-bold text-rose-950 text-xs" id="piutang-quick-nama">-</h4>
                                <p class="text-[11px] text-rose-700">Form pelunasan cicilan / bon member.</p>
                            </div>
                            <div class="flex gap-2">
                                <input type="number" id="piutang-pay-nominal" class="px-3 py-2 border border-slate-300 rounded-lg text-xs font-mono font-bold" placeholder="Nominal (Rp)">
                                <button onclick="bayarHutangMemberManual()" class="bg-rose-600 text-white font-bold text-xs px-4 py-2 rounded-lg hover:bg-rose-700">BAYAR BON</button>
                            </div>
                        </div>

                        <div class="overflow-x-auto rounded-xl border border-slate-200">
                            <table class="w-full text-left border-collapse">
                                <thead>
                                    <tr class="bg-slate-900 text-slate-200 text-xs uppercase tracking-wider">
                                        <th class="p-3">Nama Pelanggan</th>
                                        <th class="p-3 font-mono">Nomor HP</th>
                                        <th class="p-3 text-center bg-indigo-900">Poin</th>
                                        <th class="p-3 text-center bg-rose-950">Bon Piutang</th>
                                        <th class="p-3 text-center">Limit Kredit</th>
                                        <th class="p-3 text-center">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody id="member-table-body" class="divide-y divide-slate-200 text-sm font-medium"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TAB 6: STOCK OPNAME -->
            <div id="tab-opname" class="tab-content hidden">
                <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200">
                    <div class="flex flex-wrap justify-between items-center mb-4 border-b pb-3 gap-4">
                        <div>
                            <h3 class="text-lg font-bold text-slate-900">Modul Kerja Stock Opname</h3>
                            <p class="text-xs text-slate-500 mt-1" id="opname-instruksi-role">Sistem sedang mendeteksi hak akses...</p>
                        </div>
                        <div class="flex space-x-2">
                            <button onclick="exportDatabaseOpname()" id="btn-export-opname" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-xl font-bold shadow text-xs view-admin-only hidden">
                                📤 EXPORT EXCEL (.XLS)
                            </button>
                            <button onclick="prosesSelesaiOpname()" class="bg-emerald-600 hover:bg-emerald-700 text-white px-5 py-2 rounded-xl font-bold shadow text-xs">
                                SIMPAN FISIK RAK
                            </button>
                        </div>
                    </div>
                    
                    <div class="overflow-x-auto rounded-xl border border-slate-200">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-slate-900 text-slate-200 text-xs uppercase tracking-wider">
                                    <th class="p-3 w-32">Kode</th>
                                    <th class="p-3">Nama Barang</th>
                                    <th class="p-3 text-center bg-indigo-950 view-admin-only">Stok Sistem (A)</th>
                                    <th class="p-3 text-center bg-amber-900 w-44">Stok Fisik Rak (B)</th>
                                    <th class="p-3 text-center view-admin-only bg-slate-950">Selisih (B-A)</th>
                                    <th class="p-3 text-right view-admin-only bg-rose-950">Audit Kerugian</th>
                                </tr>
                            </thead>
                            <tbody id="opname-table-body" class="divide-y divide-slate-200 text-sm font-medium"></tbody>
                            <tfoot id="opname-table-footer" class="view-admin-only"></tfoot>
                        </table>
                    </div>
                </div>
            </div>

            <!-- TAB 7: RIWAYAT ABSENSI KASIR (BARU) -->
            <div id="tab-absensi" class="tab-content hidden space-y-6">
                <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200">
                    <div class="flex flex-wrap justify-between items-center mb-4 border-b pb-3 gap-4">
                        <div>
                            <h3 class="text-lg font-bold text-slate-900">📋 Log Perekaman Absensi Kehadiran Kasir</h3>
                            <p class="text-xs text-slate-500 mt-1">Sistem secara otomatis merekam waktu kehadiran kasir <strong>1 kali per hari</strong> saat pertama kali login.</p>
                        </div>
                        <div class="bg-emerald-50 text-emerald-800 text-xs font-bold px-3 py-1.5 rounded-xl border border-emerald-200">
                            🟢 Perekaman Otomatis Aktif
                        </div>
                    </div>

                    <div class="overflow-x-auto rounded-xl border border-slate-200">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-slate-900 text-slate-200 text-xs uppercase tracking-wider">
                                    <th class="p-3">No</th>
                                    <th class="p-3">Nama Pengguna</th>
                                    <th class="p-3">Toko / Usaha</th>
                                    <th class="p-3 text-center">Role Hak Akses</th>
                                    <th class="p-3 text-center">Tanggal Masuk</th>
                                    <th class="p-3 text-center">Waktu Log Masuk</th>
                                    <th class="p-3 text-center">Status Kehadiran</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200 text-sm font-medium">
                                <?php if (!empty($list_absensi)): ?>
                                    <?php foreach ($list_absensi as $idx => $abs): ?>
                                        <tr class="hover:bg-slate-50 transition <?= $abs['tanggal'] === $tgl_sekarang ? 'bg-indigo-50/30' : ''; ?>">
                                            <td class="p-3 font-bold text-slate-500"><?= $idx + 1; ?></td>
                                            <td class="p-3 font-bold text-slate-900"><?= htmlspecialchars($abs['nama_lengkap']); ?></td>
                                            <td class="p-3 text-slate-600"><?= htmlspecialchars($abs['nama_usaha']); ?></td>
                                            <td class="p-3 text-center">
                                                <span class="text-[10px] font-mono font-bold uppercase px-2 py-0.5 rounded-md <?= $abs['role'] === 'admin' ? 'bg-indigo-100 text-indigo-800' : 'bg-slate-200 text-slate-700'; ?>">
                                                    <?= htmlspecialchars($abs['role']); ?>
                                                </span>
                                            </td>
                                            <td class="p-3 text-center font-mono text-slate-700"><?= date('d-m-Y', strtotime($abs['tanggal'])); ?></td>
                                            <td class="p-3 text-center font-mono font-bold text-indigo-700"><?= htmlspecialchars($abs['waktu_masuk']); ?> WIB</td>
                                            <td class="p-3 text-center">
                                                <span class="bg-emerald-100 text-emerald-800 text-[10px] font-bold px-2.5 py-0.5 rounded-full uppercase">
                                                    ✔ HADIR
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" class="text-center p-6 text-slate-400 text-xs">Belum ada catatan absensi yang terekam.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- TAB 8: KELOLA KASIR / USER (KHUSUS ADMIN) -->
            <div id="tab-users" class="tab-content hidden space-y-6">
                <!-- Info Banner Masa Aktif Kasir -->
                <div class="bg-slate-900 text-white p-4 rounded-2xl border border-slate-800 shadow-sm flex items-center">
                    <span class="text-2xl mr-3">⏰</span>
                    <div>
                        <h4 class="font-bold text-amber-400 text-sm">Masa Aktif Kasir Terintegrasi:</h4>
                        <p class="text-xs text-slate-300 mt-0.5">
                            Setiap akun Kasir/User yang ditambahkan otomatis mengikuti masa aktif akun Admin ini s.d. 
                            <strong><?= (!empty($admin_expired_at) && $admin_expired_at !== '0000-00-00 00:00:00') ? date('d-m-Y H:i', strtotime($admin_expired_at)) : 'Aktif Permanen'; ?></strong>.
                        </p>
                    </div>
                </div>

                <?php if ($pesan_kasir): ?>
                    <div class="p-4 rounded-xl font-bold text-sm shadow-sm <?= $tipe_pesan_kasir === 'success' ? 'bg-emerald-100 text-emerald-800 border border-emerald-200' : 'bg-rose-100 text-rose-800 border border-rose-200'; ?>">
                        <?= $pesan_kasir; ?>
                    </div>
                <?php endif; ?>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <!-- Form Tambah Kasir Baru -->
                    <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200">
                        <h3 class="text-base font-bold text-slate-900 mb-4 border-b pb-2">➕ Tambah Kasir Baru</h3>
                        <form action="" method="POST" class="space-y-4">
                            <input type="hidden" name="action_tambah_kasir" value="1">
                            <div>
                                <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Nama Lengkap Kasir</label>
                                <input type="text" name="kasir_nama" required class="w-full px-3.5 py-2 border border-slate-300 rounded-xl text-sm" placeholder="Contoh: Budi Kasir">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Username Login</label>
                                <input type="text" name="kasir_username" required class="w-full px-3.5 py-2 border border-slate-300 rounded-xl text-sm font-mono" placeholder="kasir1">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Password</label>
                                <input type="password" name="kasir_password" required class="w-full px-3.5 py-2 border border-slate-300 rounded-xl text-sm" placeholder="••••••••">
                            </div>
                            <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2.5 rounded-xl text-xs shadow transition">
                                Simpan Akun Kasir
                            </button>
                        </form>
                    </div>

                    <!-- Tabel Daftar Kasir Toko -->
                    <div class="lg:col-span-2 bg-white p-6 rounded-2xl shadow-sm border border-slate-200">
                        <h3 class="text-base font-bold text-slate-900 mb-4 border-b pb-2">📋 Daftar Kasir <?= htmlspecialchars($nama_usaha); ?></h3>
                        <div class="overflow-x-auto rounded-xl border border-slate-200">
                            <table class="w-full text-left border-collapse">
                                <thead>
                                    <tr class="bg-slate-900 text-slate-200 text-xs uppercase tracking-wider">
                                        <th class="p-3">No</th>
                                        <th class="p-3">Nama Lengkap</th>
                                        <th class="p-3">Username</th>
                                        <th class="p-3 text-center">Status</th>
                                        <th class="p-3 text-center">Masa Aktif Kasir</th>
                                        <th class="p-3 text-center">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-200 text-sm font-medium">
                                    <?php if (!empty($list_kasir)): ?>
                                        <?php foreach ($list_kasir as $no => $k): ?>
                                            <tr>
                                                <td class="p-3 font-bold text-slate-500"><?= $no + 1; ?></td>
                                                <td class="p-3 font-semibold text-slate-900"><?= htmlspecialchars($k['nama_lengkap']); ?></td>
                                                <td class="p-3 font-mono text-indigo-700"><?= htmlspecialchars($k['username']); ?></td>
                                                <td class="p-3 text-center">
                                                    <span class="bg-emerald-100 text-emerald-800 text-[10px] font-bold px-2 py-0.5 rounded-full uppercase">
                                                        <?= htmlspecialchars($k['status']); ?>
                                                    </span>
                                                </td>
                                                <td class="p-3 text-center text-xs font-mono text-slate-600">
                                                    <?= (!empty($k['expired_at']) && $k['expired_at'] !== '0000-00-00 00:00:00') ? date('d-m-Y H:i', strtotime($k['expired_at'])) : 'Permanen'; ?>
                                                </td>
                                                <td class="p-3 text-center">
                                                    <form action="" method="POST" onsubmit="return confirm('Hapus kasir <?= htmlspecialchars($k['nama_lengkap']); ?>?');">
                                                        <input type="hidden" name="action_hapus_kasir" value="1">
                                                        <input type="hidden" name="id_kasir" value="<?= $k['id']; ?>">
                                                        <button type="submit" class="bg-rose-500 hover:bg-rose-600 text-white font-bold text-xs px-2.5 py-1 rounded-lg transition">
                                                            Hapus
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6" class="text-center p-4 text-slate-400 text-xs">Belum ada akun kasir tambahan untuk toko ini.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

        </main>
    </div>

    <!-- LOGIKA JAVASCRIPT POS SYSTEM -->
    <script>
        let currentUser = {
            role: "<?= $role_user; ?>", 
            name: "<?= htmlspecialchars($nama_lengkap); ?>"
        };

        let BARANG_DB = JSON.parse(localStorage.getItem('pos_barang_db')) || [
            { kode: '1001', nama: 'Indomie Goreng Spc', kategori: 'Makanan', rak: 'Rak A-1', hpp: 2800, margin: 25, harga: 3500, stok: 100, promo: { tipe: 'none', value: 0 } },
            { kode: '1002', nama: 'Aqua Botol 600ml', kategori: 'Minuman', rak: 'Chiller-1', hpp: 2000, margin: 50, harga: 3000, stok: 50, promo: { tipe: 'none', value: 0 } }
        ];

        let MEMBER_DB = JSON.parse(localStorage.getItem('pos_member_db')) || [
            { nama: 'Andi Wijaya', hp: '08123456789', join: '14/07/2026', poin: 125, limitPiutang: 500000, sisaPiutang: 150000 }
        ];

        let DISKON_RULE = JSON.parse(localStorage.getItem('pos_diskon_rule')) || { tipe: 'persen', value: 5, minimalBelanjaPoin: 10000 };
        let TRANSAKSI_DB = JSON.parse(localStorage.getItem('pos_transaksi_db')) || [];
        
        let OPNAME_INPUT_TEMPORARY = {};
        let keranjang = [];
        let memberAktifTransaksi = null;
        let memberPilihanCrmPay = null;
        let metodePembayaranAktif = 'CASH';
        let laporanTerakhirDihitung = {}; 

        // LOGIKA JAM BERJALAN MENGIKUTI WAKTU PERANGKAT CLIENT
        function updateRealtimeClock() {
            const now = new Date();
            const hariList = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
            const bulanList = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
            
            const hari = hariList[now.getDay()];
            const tgl = String(now.getDate()).padStart(2, '0');
            const bln = bulanList[now.getMonth()];
            const thn = now.getFullYear();
            
            const jam = String(now.getHours()).padStart(2, '0');
            const min = String(now.getMinutes()).padStart(2, '0');
            const sec = String(now.getSeconds()).padStart(2, '0');
            
            const clockEl = document.getElementById('realtime-clock');
            if(clockEl) {
                clockEl.textContent = `${hari}, ${tgl} ${bln} ${thn} | ${jam}:${min}:${sec} WIB`;
            }
        }
        setInterval(updateRealtimeClock, 1000);

        function handleLogout() {
            if (confirm("Apakah Anda yakin ingin keluar dari sistem kasir?")) {
                window.location.href = 'logout.php';
            }
        }

        document.addEventListener("DOMContentLoaded", function() {
            updateRealtimeClock();
            initDashboard();
        });

        function initDashboard() {
            document.getElementById('user-display-name').textContent = currentUser.name;
            document.getElementById('user-display-role').textContent = currentUser.role;

            if (currentUser.role === 'admin') {
                document.querySelectorAll('.view-admin-only').forEach(el => el.classList.remove('hidden'));
                document.getElementById('title-form-barang').textContent = "Input & Edit Master Barang (Admin)";
                updateDropdownPromoBarang();
            } else {
                document.querySelectorAll('.view-admin-only').forEach(el => el.classList.add('hidden'));
                document.getElementById('title-form-barang').textContent = "Input Logistik Barang Masuk (Kasir)";
            }
            hitungUlangLaporanKeuangan(); 
            saveAndRenderDB();
            renderRuleDiskonTeks();
            setMetodeBayar('CASH');
        }

        function switchTab(tabName) {
            document.querySelectorAll('.tab-content').forEach(el => el.classList.add('hidden'));
            document.getElementById(`tab-${tabName}`).classList.remove('hidden');
            
            document.querySelectorAll('#page-dashboard button').forEach(b => {
                b.className = "px-5 py-3.5 font-semibold text-sm text-slate-600 hover:text-slate-900 shrink-0 transition-all";
            });

            if (tabName === 'users') {
                document.getElementById('btn-tab-users').className = "px-5 py-3.5 font-bold text-sm text-indigo-700 bg-indigo-50 border-b-2 border-indigo-600 shrink-0 view-admin-only";
            } else {
                const btnActive = document.getElementById(`btn-tab-${tabName}`);
                if (btnActive) {
                    btnActive.className = "px-5 py-3.5 font-bold text-sm border-b-2 border-indigo-600 text-indigo-700 bg-indigo-50/50 rounded-t-lg shrink-0 transition-all";
                }
            }
            
            if(tabName === 'riwayat') renderRiwayatTransaksi();
            if(tabName === 'opname') renderStockOpname();
            if(tabName === 'member') renderMemberTable();
            if(tabName === 'laporan') hitungUlangLaporanKeuangan();
        }

        function hitungUlangLaporanKeuangan() {
            let rekap = {
                hari:   { total: 0, cash: 0, qris: 0, debt: 0, member: 0, umum: 0 },
                minggu: { total: 0, cash: 0, qris: 0, debt: 0, member: 0, umum: 0 },
                bulan:  { total: 0, cash: 0, qris: 0, debt: 0, member: 0, umum: 0 }
            };

            const sekarang = new Date();
            const hariIniMulai = new Date(sekarang.getFullYear(), sekarang.getMonth(), sekarang.getDate());

            TRANSAKSI_DB.forEach(t => {
                let partWaktu = t.waktu.split(',');
                let partTgl = partWaktu[0].trim().split('/');
                
                let tglTransaksi = new Date(partTgl[2], partTgl[1] - 1, partTgl[0]);
                if (isNaN(tglTransaksi.getTime())) return;

                let selisihMilidetik = hariIniMulai - tglTransaksi;
                let selisihHari = Math.ceil(selisihMilidetik / (1000 * 60 * 60 * 24));

                let isMember = !t.member.includes("Umum");
                let nilaiOmzet = t.total;

                if (tglTransaksi.toDateString() === sekarang.toDateString()) {
                    rekap.hari.total += nilaiOmzet;
                    if (t.metode === 'CASH') rekap.hari.cash += nilaiOmzet; 
                    else if (t.metode === 'QRIS') rekap.hari.qris += nilaiOmzet;
                    else rekap.hari.debt += nilaiOmzet;
                    if (isMember) rekap.hari.member += nilaiOmzet; else rekap.hari.umum += nilaiOmzet;
                }

                if (selisihHari >= 0 && selisihHari < 7 || tglTransaksi.toDateString() === sekarang.toDateString()) {
                    rekap.minggu.total += nilaiOmzet;
                    if (t.metode === 'CASH') rekap.minggu.cash += nilaiOmzet; 
                    else if (t.metode === 'QRIS') rekap.minggu.qris += nilaiOmzet;
                    else rekap.minggu.debt += nilaiOmzet;
                    if (isMember) rekap.minggu.member += nilaiOmzet; else rekap.minggu.umum += nilaiOmzet;
                }

                if (selisihHari >= 0 && selisihHari < 30 || tglTransaksi.toDateString() === sekarang.toDateString()) {
                    rekap.bulan.total += nilaiOmzet;
                    if (t.metode === 'CASH') rekap.bulan.cash += nilaiOmzet; 
                    else if (t.metode === 'QRIS') rekap.bulan.qris += nilaiOmzet;
                    else rekap.bulan.debt += nilaiOmzet;
                    if (isMember) rekap.bulan.member += nilaiOmzet; else rekap.bulan.umum += nilaiOmzet;
                }
            });

            laporanTerakhirDihitung = rekap;

            const renderDataKeuangan = (prefix, data) => {
                document.getElementById(`rep-${prefix}-total`).textContent  = `Rp ${data.total.toLocaleString('id-ID')}`;
                document.getElementById(`rep-${prefix}-cash`).textContent   = `Rp ${data.cash.toLocaleString('id-ID')}`;
                document.getElementById(`rep-${prefix}-qris`).textContent   = `Rp ${data.qris.toLocaleString('id-ID')}`;
                document.getElementById(`rep-${prefix}-debt`).textContent   = `Rp ${data.debt.toLocaleString('id-ID')}`;
                document.getElementById(`rep-${prefix}-member`).textContent = `Rp ${data.member.toLocaleString('id-ID')}`;
                document.getElementById(`rep-${prefix}-umum`).textContent   = `Rp ${data.umum.toLocaleString('id-ID')}`;
            };

            renderDataKeuangan('hari', rekap.hari);
            renderDataKeuangan('minggu', rekap.minggu);
            renderDataKeuangan('bulan', rekap.bulan);
        }

        function cetakLaporanThermal(periode) {
            const data = laporanTerakhirDihitung[periode];
            if (!data) return alert("Data laporan tidak ditemukan!");

            let namaPeriode = "";
            if(periode === 'hari') namaPeriode = "LAPORAN HARIAN (HARI INI)";
            if(periode === 'minggu') namaPeriode = "LAPORAN MINGGUAN (7 HARI)";
            if(periode === 'bulan') namaPeriode = "LAPORAN BULANAN (30 HARI)";

            let s = `   === <?= strtoupper(htmlspecialchars($nama_usaha)); ?> ===\n`;
            s += `        ${namaPeriode}\n`;
            s += `TANGGAL PRINT : ${new Date().toLocaleString('id-ID')}\n`;
            s += `DICETAK OLEH  : ${currentUser.name}\n`;
            s += `----------------------------------\n`;
            s += `TOTAL OMZET   : Rp ${data.total.toLocaleString('id-ID')}\n\n`;
            s += `SUMBER METODE BAYAR:\n`;
            s += `  - TUNAI     : Rp ${data.cash.toLocaleString('id-ID')}\n`;
            s += `  - QRIS      : Rp ${data.qris.toLocaleString('id-ID')}\n`;
            s += `  - BON/DEBT  : Rp ${data.debt.toLocaleString('id-ID')}\n\n`;
            s += `KLASIFIKASI CRM PELANGGAN:\n`;
            s += `  - MEMBER    : Rp ${data.member.toLocaleString('id-ID')}\n`;
            s += `  - NON-MEMBER: Rp ${data.umum.toLocaleString('id-ID')}\n`;
            s += `----------------------------------\n`;
            s += `     LAPORAN BERHASIL DIVALIDASI\n\n\n\n`;

            document.getElementById('area-cetak-struk').textContent = s;
            window.print();
        }

        function setMetodeBayar(metode) {
            metodePembayaranAktif = metode;
            const btnCash = document.getElementById('btn-pay-cash');
            const btnQris = document.getElementById('btn-pay-qris');
            const btnDebt = document.getElementById('btn-pay-debt');
            
            const labelNominal = document.getElementById('label-nominal-bayar');
            const conQris = document.getElementById('container-display-qris');
            const conDebt = document.getElementById('container-display-debt');
            
            const btnSubmitCash = document.getElementById('btn-submit-cash');
            const btnSubmitQris = document.getElementById('btn-submit-qris');
            const btnSubmitDebt = document.getElementById('btn-submit-debt');

            document.getElementById('pos-bayar').value = '';

            [btnCash, btnQris, btnDebt].forEach(b => {
                b.className = "py-2.5 rounded-xl font-bold border-2 border-slate-700 bg-slate-800 text-slate-300 hover:bg-slate-700 transition text-xs shadow-md";
            });

            conQris.classList.add('hidden');
            conDebt.classList.add('hidden');
            
            [btnSubmitCash, btnSubmitQris, btnSubmitDebt].forEach(b => b.classList.add('hidden'));

            if (metode === 'QRIS') {
                btnQris.className = "py-2.5 rounded-xl font-bold border-2 border-indigo-500 bg-indigo-600 text-white transition text-xs shadow-md";
                labelNominal.textContent = "Nominal Konfirmasi Scan (QRIS)";
                conQris.classList.remove('hidden');
                btnSubmitQris.classList.remove('hidden');
                renderKeuanganQris();
            } else if (metode === 'DEBT') {
                if(!memberAktifTransaksi) {
                    alert("Metode pembayaran BON/HUTANG hanya diperbolehkan untuk Member!");
                    setMetodeBayar('CASH');
                    return;
                }
                btnDebt.className = "py-2.5 rounded-xl font-bold border-2 border-rose-500 bg-rose-600 text-white transition text-xs shadow-md";
                labelNominal.textContent = "Nominal Bon Piutang (Otomatis)";
                conDebt.classList.remove('hidden');
                btnSubmitDebt.classList.remove('hidden');
                renderKeuanganPiutang();
            } else {
                btnCash.className = "py-2.5 rounded-xl font-bold border-2 border-emerald-500 bg-emerald-600 text-white transition text-xs shadow-md";
                labelNominal.textContent = "Nominal Uang Diterima (Cash)";
                btnSubmitCash.classList.remove('hidden');
                hitungKembalian();
            }
        }

        function renderKeuanganQris() {
            let subtotalSemua = 0;
            keranjang.forEach(item => {
                let hargaFinalBarang = item.harga;
                if(item.promo && item.promo.tipe !== 'none' && item.promo.value > 0) {
                    let pot = item.promo.tipe === 'persen' ? item.harga * (item.promo.value/100) : item.promo.value;
                    hargaFinalBarang = Math.max(0, item.harga - pot);
                }
                subtotalSemua += (hargaFinalBarang * item.qty);
            });

            let totalAkhir = subtotalSemua;
            let diskonGlobal = 0;
            const kasirSetujuBeriDiskon = document.getElementById('pos-use-discount-checkbox').checked;

            if (memberAktifTransaksi && subtotalSemua > 0 && kasirSetujuBeriDiskon) {
                diskonGlobal = DISKON_RULE.tipe === 'persen' ? subtotalSemua * (DISKON_RULE.value / 100) : DISKON_RULE.value;
                totalAkhir = Math.max(0, subtotalSemua - diskonGlobal);
            }

            document.getElementById('qris-val-net').textContent = `Rp ${subtotalSemua.toLocaleString('id-ID')}`;
            const discRow = document.getElementById('qris-val-disc-row');
            if(diskonGlobal > 0) {
                discRow.classList.remove('hidden');
                document.getElementById('qris-val-disc').textContent = `-Rp ${diskonGlobal.toLocaleString('id-ID')}`;
            } else {
                discRow.classList.add('hidden');
            }
            document.getElementById('qris-val-total').textContent = `Rp ${totalAkhir.toLocaleString('id-ID')}`;
            
            document.getElementById('pos-bayar').value = totalAkhir;
            hitungKembalian();
        }

        function renderKeuanganPiutang() {
            if(!memberAktifTransaksi) return;
            let subtotalSemua = 0;
            keranjang.forEach(item => {
                let hargaFinalBarang = item.harga;
                if(item.promo && item.promo.tipe !== 'none' && item.promo.value > 0) {
                    let pot = item.promo.tipe === 'persen' ? item.harga * (item.promo.value/100) : item.promo.value;
                    hargaFinalBarang = Math.max(0, item.harga - pot);
                }
                subtotalSemua += (hargaFinalBarang * item.qty);
            });

            let totalAkhir = subtotalSemua;
            const kasirSetujuBeriDiskon = document.getElementById('pos-use-discount-checkbox').checked;

            if (subtotalSemua > 0 && kasirSetujuBeriDiskon) {
                let diskonGlobal = DISKON_RULE.tipe === 'persen' ? subtotalSemua * (DISKON_RULE.value / 100) : DISKON_RULE.value;
                totalAkhir = Math.max(0, subtotalSemua - diskonGlobal);
            }

            let sisaTagihan = memberAktifTransaksi.sisaPiutang || 0;
            let limitMax = memberAktifTransaksi.limitPiutang || 0;
            let sisaLimitSistem = Math.max(0, limitMax - sisaTagihan);

            document.getElementById('debt-val-limit').textContent = `Rp ${sisaLimitSistem.toLocaleString('id-ID')}`;
            document.getElementById('debt-val-belanja').textContent = `Rp ${totalAkhir.toLocaleString('id-ID')}`;
            document.getElementById('debt-val-sisa-limit').textContent = `Rp ${Math.max(0, sisaLimitSistem - totalAkhir).toLocaleString('id-ID')}`;
            
            document.getElementById('pos-bayar').value = totalAkhir;
            hitungKembalian();
        }

        function prosesTransaksiSpesifik(metodeEksekusi) {
            const total = parseFloat(document.getElementById('cart-total-display').textContent.replace(/[^\d]/g, '')) || 0;
            if(keranjang.length === 0) return alert('Keranjang belanja kosong!');

            const bayar = parseFloat(document.getElementById('pos-bayar').value) || 0;
            if(bayar < total && metodeEksekusi !== 'DEBT') {
                return alert(`Gagal! Uang pembayaran [${metodeEksekusi}] kurang.`);
            }

            if(metodeEksekusi === 'DEBT') {
                if(!memberAktifTransaksi) return alert("Pilih member terlebih dahulu untuk bertransaksi hutang/bon!");
                
                let sisaTagihanSekarang = memberAktifTransaksi.sisaPiutang || 0;
                let limitMax = memberAktifTransaksi.limitPiutang || 0;
                let sisaLimitSistem = limitMax - sisaTagihanSekarang;

                if(total > sisaLimitSistem) {
                    return alert(`Gagal! Transaksi Rp ${total.toLocaleString('id-ID')} melebihi sisa limit kredit member (Sisa limit: Rp ${sisaLimitSistem.toLocaleString('id-ID')})`);
                }
            }

            const kembalian = metodeEksekusi === 'DEBT' ? 0 : (bayar - total);

            if (metodeEksekusi === 'QRIS') {
                const konfirmasi = confirm(`[KONFIRMASI QRIS]\nApakah dana Rp ${total.toLocaleString('id-ID')} sudah masuk?`);
                if(!konfirmasi) return;
            }

            keranjang.forEach(item => { 
                const target = BARANG_DB.find(b => b.kode === item.kode); 
                if(target) target.stok -= item.qty; 
            });

            let poinDiperoleh = 0;
            if(memberAktifTransaksi) {
                poinDiperoleh = Math.floor(total / (DISKON_RULE.minimalBelanjaPoin || 10000));
                const idxMem = MEMBER_DB.findIndex(m => m.hp === memberAktifTransaksi.hp);
                if(idxMem > -1) { 
                    MEMBER_DB[idxMem].poin = (MEMBER_DB[idxMem].poin || 0) + poinDiperoleh; 
                    if(metodeEksekusi === 'DEBT') {
                        MEMBER_DB[idxMem].sisaPiutang = (MEMBER_DB[idxMem].sisaPiutang || 0) + total;
                    }
                    localStorage.setItem('pos_member_db', JSON.stringify(MEMBER_DB)); 
                }
            }

            const idTrx = 'TRX-' + Date.now().toString().slice(-6);
            TRANSAKSI_DB.unshift({ 
                id: idTrx, 
                waktu: new Date().toLocaleString('id-ID'), 
                kasir: currentUser.name, 
                metode: metodeEksekusi,
                member: memberAktifTransaksi ? `${memberAktifTransaksi.nama} (+${poinDiperoleh} Pts)` : 'Umum (Non-Member)', 
                items: [...keranjang], 
                total, 
                bayar: metodeEksekusi === 'DEBT' ? 0 : bayar, 
                kembalian 
            });

            localStorage.setItem('pos_transaksi_db', JSON.stringify(TRANSAKSI_DB)); 
            saveAndRenderDB(); 
            tampilkanPreviewStruk(idTrx); 
            
            keranjang = []; 
            memberAktifTransaksi = null; 
            document.getElementById('pos-member-input').value = ''; 
            document.getElementById('pos-member-status-box').classList.add('hidden'); 
            document.getElementById('pos-bayar').value = ''; 
            setMetodeBayar('CASH'); 
            renderKeranjang(); 
            
            alert(`Transaksi [${metodeEksekusi}] Berhasil Diproses!`);
            switchTab('riwayat');
        }

        function autoFillBarangLama() {
            const kode = document.getElementById('prod-kode').value;
            const barang = BARANG_DB.find(b => b.kode === kode);
            if(barang) {
                document.getElementById('prod-nama').value = barang.nama;
                document.getElementById('prod-kategori').value = barang.kategori;
                document.getElementById('prod-rak').value = barang.rak;
                document.getElementById('prod-harga').value = barang.harga;
                if(currentUser.role === 'admin') {
                    document.getElementById('prod-hpp').value = barang.hpp;
                    document.getElementById('prod-margin').value = barang.margin;
                }
                if(currentUser.role !== 'admin') document.getElementById('prod-nama').disabled = true;
            } else {
                document.getElementById('prod-nama').disabled = false;
                if(currentUser.role !== 'admin') {
                    alert('Barang baru wajib didaftarkan oleh Admin terlebih dahulu.');
                    document.getElementById('prod-kode').value = '';
                }
            }
        }

        function simpanBarang() {
            const kode = document.getElementById('prod-kode').value;
            const nama = document.getElementById('prod-nama').value;
            const kategori = document.getElementById('prod-kategori').value;
            const rak = document.getElementById('prod-rak').value;
            const hargaJualTerbaru = parseFloat(document.getElementById('prod-harga').value) || 0;
            const stokInput = parseInt(document.getElementById('prod-stok').value) || 0;

            if(!kode || !nama) return alert('Kode dan Nama Barang Wajib diisi!');
            const indexLama = BARANG_DB.findIndex(b => b.kode === kode);

            if(currentUser.role === 'admin') {
                const hpp = parseFloat(document.getElementById('prod-hpp').value) || 0;
                const margin = parseFloat(document.getElementById('prod-margin').value) || 0;
                const promoLama = indexLama > -1 ? (BARANG_DB[indexLama].promo || { tipe: 'none', value: 0 }) : { tipe: 'none', value: 0 };
                
                if(indexLama > -1) BARANG_DB[indexLama] = { kode, nama, kategori, rak, hpp, margin, harga: hargaJualTerbaru, stok: stokInput, promo: promoLama };
                else BARANG_DB.push({ kode, nama, kategori, rak, hpp, margin, harga: hargaJualTerbaru, stok: stokInput, promo: { tipe: 'none', value: 0 } });
            } else {
                if(indexLama > -1) {
                    BARANG_DB[indexLama].stok += stokInput;
                    BARANG_DB[indexLama].harga = hargaJualTerbaru; 
                    BARANG_DB[indexLama].kategori = kategori;
                    BARANG_DB[indexLama].rak = rak;
                } else { return alert('Kasir dilarang membuat data barang baru!'); }
            }
            saveAndRenderDB();
            if(currentUser.role === 'admin') updateDropdownPromoBarang();
            document.getElementById('prod-nama').disabled = false;
            ['prod-kode', 'prod-nama', 'prod-kategori', 'prod-rak', 'prod-hpp', 'prod-margin', 'prod-harga', 'prod-stok'].forEach(id => {
                const el = document.getElementById(id); if(el) el.value = '';
            });
            alert('Data Ritel Disimpan!');
        }

        function hitungMarginDanHarga(trigger) {
            let hpp = parseFloat(document.getElementById('prod-hpp').value) || 0;
            let margin = parseFloat(document.getElementById('prod-margin').value) || 0;
            if (trigger === 'hpp' || trigger === 'margin') {
                let harga = hpp + (hpp * (margin / 100)); document.getElementById('prod-harga').value = Math.round(harga);
            }
        }

        function saveAndRenderDB() {
            localStorage.setItem('pos_barang_db', JSON.stringify(BARANG_DB));
            const invBody = document.getElementById('inventory-table-body'); invBody.innerHTML = '';
            BARANG_DB.forEach(b => {
                let textPromo = `<span class="text-slate-400 text-xs">Normal</span>`;
                if(b.promo && b.promo.tipe !== 'none' && b.promo.value > 0) {
                    textPromo = b.promo.tipe === 'persen' 
                        ? `<span class="bg-rose-600 text-white font-bold text-[10px] px-2 py-0.5 rounded-full">Disc ${b.promo.value}%</span>`
                        : `<span class="bg-rose-600 text-white font-bold text-[10px] px-2 py-0.5 rounded-full">Pot. Rp ${b.promo.value.toLocaleString('id-ID')}</span>`;
                }
                invBody.innerHTML += `<tr class="hover:bg-slate-50 transition">
                    <td class="p-3 font-mono font-bold text-slate-900">${b.kode}</td>
                    <td class="p-3"><strong>${b.nama}</strong><span class="text-xs text-slate-400 block">${b.kategori || '-'}</span></td>
                    <td class="p-3"><span class="bg-slate-100 border border-slate-200 text-xs px-2 py-0.5 rounded font-semibold text-slate-700">${b.rak || '-'}</span></td>
                    <td class="p-3 text-right font-mono view-admin-only ${currentUser && currentUser.role !== 'admin' ? 'hidden' : ''}">Rp ${b.hpp.toLocaleString('id-ID')}</td>
                    <td class="p-3 text-right font-mono font-bold text-indigo-700">Rp ${b.harga.toLocaleString('id-ID')}</td>
                    <td class="p-3 text-center">${textPromo}</td>
                    <td class="p-3 text-center font-mono font-bold bg-slate-50">${b.stok} Pcs</td>
                </tr>`;
            });
        }

        function updateDropdownPromoBarang() {
            const selectEl = document.getElementById('promo-barang-kode');
            if(!selectEl) return;
            selectEl.innerHTML = '';
            BARANG_DB.forEach(b => { selectEl.innerHTML += `<option value="${b.kode}">[${b.kode}] ${b.nama}</option>`; });
        }

        function simpanPromoBarang() {
            const kode = document.getElementById('promo-barang-kode').value;
            const tipe = document.getElementById('promo-barang-tipe').value;
            const value = parseFloat(document.getElementById('promo-barang-nilai').value) || 0;
            const idx = BARANG_DB.findIndex(b => b.kode === kode);
            if(idx > -1) {
                BARANG_DB[idx].promo = { tipe: value > 0 ? tipe : 'none', value: value };
                saveAndRenderDB();
                alert(`Promo khusus untuk barang ${BARANG_DB[idx].nama} diterapkan!`);
                document.getElementById('promo-barang-nilai').value = '0';
            }
        }

        function renderRuleDiskonTeks() {
            document.getElementById('status-diskon-aktif').textContent = DISKON_RULE.tipe === 'persen' ? `Diskon Member: ${DISKON_RULE.value}%` : `Diskon Member: Rp ${DISKON_RULE.value.toLocaleString('id-ID')}`;
            document.getElementById('status-poin-aktif').textContent = `Reward: Kelipatan Rp ${(DISKON_RULE.minimalBelanjaPoin || 10000).toLocaleString('id-ID')} = +1 Poin`;
        }

        function simpanAturanDiskonDanPoin() {
            const tipe = document.getElementById('promo-tipe').value;
            const value = parseFloat(document.getElementById('promo-value').value) || 0;
            const minimalBelanjaPoin = parseFloat(document.getElementById('poin-konversi-rule').value) || 10000;
            DISKON_RULE = { tipe, value, minimalBelanjaPoin };
            localStorage.setItem('pos_diskon_rule', JSON.stringify(DISKON_RULE));
            renderRuleDiskonTeks();
            alert('Parameter Program Diskon & Poin Berhasil Diperbarui!');
        }

        function tambahMember() {
            const nama = document.getElementById('mem-nama').value;
            const hp = document.getElementById('mem-hp').value.trim();
            const limit = parseFloat(document.getElementById('mem-limit-piutang').value) || 0;
            
            if(!nama || !hp) return alert('Lengkapi data member!');
            MEMBER_DB.push({ 
                nama, 
                hp, 
                join: new Date().toLocaleDateString('id-ID'), 
                poin: 0, 
                limitPiutang: limit, 
                sisaPiutang: 0 
            });
            localStorage.setItem('pos_member_db', JSON.stringify(MEMBER_DB)); 
            renderMemberTable();
            document.getElementById('mem-nama').value = ''; 
            document.getElementById('mem-hp').value = '';
            document.getElementById('mem-limit-piutang').value = '';
        }

        function renderMemberTable() {
            const tbody = document.getElementById('member-table-body'); tbody.innerHTML = '';
            MEMBER_DB.forEach(m => {
                let limitVal = m.limitPiutang || 0;
                let sisaTagihan = m.sisaPiutang || 0;
                
                tbody.innerHTML += `<tr class="hover:bg-slate-50 transition">
                    <td class="p-3 font-semibold text-slate-900">${m.nama}</td>
                    <td class="p-3 font-mono text-slate-600">${m.hp}</td>
                    <td class="p-3 text-center font-mono font-bold text-indigo-700 bg-indigo-50/50">${m.poin || 0} Pts</td>
                    <td class="p-3 text-center font-mono font-bold text-rose-600 bg-rose-50/50">Rp ${sisaTagihan.toLocaleString('id-ID')}</td>
                    <td class="p-3 text-center font-mono text-slate-700">Rp ${limitVal.toLocaleString('id-ID')}</td>
                    <td class="p-3 text-center">
                        <button onclick="pilihCrmQuickPay('${m.hp}')" class="bg-rose-600 hover:bg-rose-700 text-white font-bold px-2.5 py-1 rounded-lg text-xs transition">Bayar Bon</button>
                    </td>
                </tr>`;
            });
        }

        function pilihCrmQuickPay(hp) {
            const member = MEMBER_DB.find(m => m.hp === hp);
            if(!member) return;
            memberPilihanCrmPay = member;
            
            const qpBox = document.getElementById('crm-piutang-quick-pay');
            const qpNama = document.getElementById('piutang-quick-nama');
            
            qpNama.innerHTML = `💵 Pelunasan Bon: <span class="underline">${member.nama} (${member.hp})</span> | Tagihan: <span class="text-rose-600 font-black font-mono">Rp ${member.sisaPiutang.toLocaleString('id-ID')}</span>`;
            document.getElementById('piutang-pay-nominal').value = member.sisaPiutang;
            qpBox.classList.remove('hidden');
        }

        function bayarHutangMemberManual() {
            if(!memberPilihanCrmPay) return;
            const nominalBayar = parseFloat(document.getElementById('piutang-pay-nominal').value) || 0;
            if (nominalBayar <= 0) return alert('Nominal bayar harus lebih dari Rp 0!');
            
            const idx = MEMBER_DB.findIndex(m => m.hp === memberPilihanCrmPay.hp);
            if (idx > -1) {
                let tagihanLama = MEMBER_DB[idx].sisaPiutang || 0;
                if(nominalBayar > tagihanLama) {
                    return alert(`Nominal bayar melebihi sisa hutang member! (Sisa hutang: Rp ${tagihanLama.toLocaleString('id-ID')})`);
                }

                MEMBER_DB[idx].sisaPiutang = tagihanLama - nominalBayar;
                localStorage.setItem('pos_member_db', JSON.stringify(MEMBER_DB));
                
                const idTrx = 'TRX-' + Date.now().toString().slice(-6);
                TRANSAKSI_DB.unshift({ 
                    id: idTrx, 
                    waktu: new Date().toLocaleString('id-ID'), 
                    kasir: currentUser.name, 
                    metode: 'CASH', 
                    member: `[PELUNASAN BON] ${MEMBER_DB[idx].nama}`, 
                    items: [{ nama: `Bayar Cicilan / Pelunasan Bon Ritel`, qty: 1, harga: nominalBayar, rak: 'CRM-SYSTEM' }], 
                    total: nominalBayar, 
                    bayar: nominalBayar, 
                    kembalian: 0 
                });
                
                localStorage.setItem('pos_transaksi_db', JSON.stringify(TRANSAKSI_DB));
                alert(`Pembayaran bon member ${MEMBER_DB[idx].nama} sebesar Rp ${nominalBayar.toLocaleString('id-ID')} Berhasil Disimpan.`);
                
                document.getElementById('crm-piutang-quick-pay').classList.add('hidden');
                memberPilihanCrmPay = null;
                renderMemberTable();
                hitungUlangLaporanKeuangan();
            }
        }

        function terapkanMemberPOS() {
            const hp = document.getElementById('pos-member-input').value.trim();
            const member = MEMBER_DB.find(m => m.hp === hp);
            const boxStatus = document.getElementById('pos-member-status-box');
            
            if(member) {
                memberAktifTransaksi = member;
                document.getElementById('pos-member-info-text').textContent = `Member: ${member.nama} (${member.hp})`;
                document.getElementById('pos-member-poin-text').textContent = `${member.poin || 0} Poin`;
                
                let sisaTagihan = member.sisaPiutang || 0;
                let limitMax = member.limitPiutang || 0;
                document.getElementById('pos-member-piutang-text').textContent = `Sisa Bon: Rp ${sisaTagihan.toLocaleString('id-ID')} / Limit Rp ${limitMax.toLocaleString('id-ID')}`;
                
                boxStatus.classList.remove('hidden');
                alert(`Member ${member.nama} Ditemukan!`);
            } else {
                memberAktifTransaksi = null;
                boxStatus.classList.add('hidden');
                alert('Data nomor member tidak valid!');
            }
            renderKeranjang();
        }

        function cariBarangPOS() {
            const keyword = document.getElementById('pos-search').value.toLowerCase();
            const resultBox = document.getElementById('pos-search-results');
            if(!keyword) { resultBox.classList.add('hidden'); return; }
            const hasil = BARANG_DB.filter(b => b.kode.includes(keyword) || b.nama.toLowerCase().includes(keyword));
            resultBox.innerHTML = '';
            if(hasil.length > 0) {
                resultBox.classList.remove('hidden');
                hasil.forEach(b => {
                    let teksInfoHarga = `Rp ${b.harga.toLocaleString('id-ID')}`;
                    if(b.promo && b.promo.tipe !== 'none' && b.promo.value > 0) {
                        let nominalPotong = b.promo.tipe === 'persen' ? b.harga * (b.promo.value/100) : b.promo.value;
                        let hargaPromo = Math.max(0, b.harga - nominalPotong);
                        teksInfoHarga = `<span class="line-through text-slate-400 text-xs">Rp ${b.harga.toLocaleString('id-ID')}</span> <span class="text-rose-600 font-bold">Rp ${hargaPromo.toLocaleString('id-ID')}</span>`;
                    }
                    resultBox.innerHTML += `<div onclick="tambahKeKeranjang('${b.kode}')" class="p-3 hover:bg-indigo-50 cursor-pointer flex justify-between text-xs font-semibold">
                        <div>[${b.kode}] ${b.nama} <span class="text-slate-400 font-normal">(${b.rak})</span></div>
                        <div class="text-right font-mono">${teksInfoHarga}</div>
                    </div>`;
                });
            }
        }

        function tambahKeKeranjang(kode) {
            const barang = BARANG_DB.find(b => b.kode === kode); if(barang.stok <= 0) return alert('Stok habis!');
            const item = keranjang.find(i => i.kode === kode);
            if(item) item.qty++; else keranjang.push({ ...barang, qty: 1 });
            document.getElementById('pos-search').value = ''; document.getElementById('pos-search-results').classList.add('hidden');
            renderKeranjang();
        }

        function ubahQtyKeranjang(kode, perubahan) {
            const item = keranjang.find(i => i.kode === kode);
            if(item) { item.qty += perubahan; if(item.qty <= 0) keranjang = keranjang.filter(i => i.kode !== kode); }
            renderKeranjang();
        }

        function renderKeranjang() {
            const tbody = document.getElementById('cart-table-body'); tbody.innerHTML = '';
            let subtotalSemua = 0;
            
            keranjang.forEach(item => {
                let hargaFinalBarang = item.harga;
                if(item.promo && item.promo.tipe !== 'none' && item.promo.value > 0) {
                    let pot = item.promo.tipe === 'persen' ? item.harga * (item.promo.value/100) : item.promo.value;
                    hargaFinalBarang = Math.max(0, item.harga - pot);
                }
                let sub = hargaFinalBarang * item.qty; subtotalSemua += sub;
                let renderHargaKolom = `Rp ${item.harga.toLocaleString('id-ID')}`;
                if(hargaFinalBarang < item.harga) {
                    renderHargaKolom = `<span class="line-through text-slate-400 text-xs block">Rp ${item.harga.toLocaleString('id-ID')}</span><span class="text-rose-600 font-bold">Rp ${hargaFinalBarang.toLocaleString('id-ID')}</span>`;
                }
                tbody.innerHTML += `<tr><td class="p-3.5"><strong>${item.nama}</strong><span class="block text-xs text-slate-400">${item.rak}</span></td><td class="p-3.5 text-center font-mono">${renderHargaKolom}</td><td class="p-3.5 text-center"><button onclick="ubahQtyKeranjang('${item.kode}', -1)" class="bg-slate-200 px-2 rounded font-bold hover:bg-slate-300">-</button><span class="font-bold font-mono mx-1.5">${item.qty}</span><button onclick="ubahQtyKeranjang('${item.kode}', 1)" class="bg-slate-200 px-2 rounded font-bold hover:bg-slate-300">+</button></td><td class="p-3.5 text-right font-mono font-bold text-slate-900">Rp ${sub.toLocaleString('id-ID')}</td><td class="p-3.5 text-center"><button onclick="keranjang=keranjang.filter(i=>i.kode!=='${item.kode}');renderKeranjang();" class="text-rose-500 hover:text-rose-700 font-bold">✕</button></td></tr>`;
            });

            let totalAkhir = subtotalSemua; 
            const diskonEl = document.getElementById('cart-discount-display');
            const poinEstEl = document.getElementById('cart-poin-estimate-display');
            const kasirSetujuBeriDiskon = document.getElementById('pos-use-discount-checkbox').checked;

            if (memberAktifTransaksi && subtotalSemua > 0) {
                if(kasirSetujuBeriDiskon) {
                    let pot = DISKON_RULE.tipe === 'persen' ? subtotalSemua * (DISKON_RULE.value / 100) : DISKON_RULE.value;
                    totalAkhir = Math.max(0, subtotalSemua - pot); 
                    diskonEl.textContent = `Diskon Global Member (${DISKON_RULE.value}${DISKON_RULE.tipe === 'persen' ? '%' : 'Rp'}): -Rp ${pot.toLocaleString('id-ID')}`;
                    diskonEl.classList.remove('hidden');
                } else { diskonEl.classList.add('hidden'); }
                let ruleMin = DISKON_RULE.minimalBelanjaPoin || 10000;
                poinEstEl.textContent = `✨ Estimasi Poin Masuk: +${Math.floor(totalAkhir / ruleMin)} Pts`; poinEstEl.classList.remove('hidden');
            } else { diskonEl.classList.add('hidden'); poinEstEl.classList.add('hidden'); }
            
            document.getElementById('cart-total-display').textContent = `Rp ${totalAkhir.toLocaleString('id-ID')}`; 
            
            if (metodePembayaranAktif === 'QRIS') {
                renderKeuanganQris();
            } else if (metodePembayaranAktif === 'DEBT') {
                renderKeuanganPiutang();
            } else {
                hitungKembalian();
            }
        }

        function hitungKembalian() {
            const total = parseFloat(document.getElementById('cart-total-display').textContent.replace(/[^\d]/g, '')) || 0;
            const bayar = parseFloat(document.getElementById('pos-bayar').value) || 0;
            const ke = document.getElementById('pos-kembalian-display');
            if(metodePembayaranAktif === 'DEBT') {
                ke.textContent = `Rp 0 (Masuk Bon)`; 
                ke.className = "text-2xl font-black font-mono text-rose-400";
                return;
            }
            if(bayar >= total) { 
                ke.textContent = `Rp ${(bayar - total).toLocaleString('id-ID')}`; 
                ke.className = "text-2xl font-black font-mono text-emerald-400"; 
            } else { 
                ke.textContent = `Kurang Rp ${(total - bayar).toLocaleString('id-ID')}`; 
                ke.className = "text-2xl font-black font-mono text-rose-400"; 
            }
        }

        function renderRiwayatTransaksi() {
            const tbody = document.getElementById('history-table-body'); tbody.innerHTML = '';
            TRANSAKSI_DB.forEach(t => { 
                let badgeMetode = "";
                if(t.metode === 'QRIS') {
                    badgeMetode = '<span class="bg-indigo-600 text-white text-[10px] px-2 py-0.5 rounded font-bold tracking-wider">QRIS</span>';
                } else if(t.metode === 'DEBT') {
                    badgeMetode = '<span class="bg-rose-600 text-white text-[10px] px-2 py-0.5 rounded font-bold tracking-wider">BON</span>';
                } else {
                    badgeMetode = '<span class="bg-emerald-600 text-white text-[10px] px-2 py-0.5 rounded font-bold tracking-wider">TUNAI</span>';
                }
                tbody.innerHTML += `<tr class="hover:bg-slate-50 transition"><td class="p-3 font-mono font-bold text-slate-900">${t.id}</td><td class="p-3 text-slate-500 font-mono text-xs">${t.waktu}</td><td class="p-3">${badgeMetode}</td><td class="p-3 font-semibold text-slate-800">${t.member}</td><td class="p-3 text-right font-mono font-bold text-slate-900">Rp ${t.total.toLocaleString('id-ID')}</td><td class="p-3 text-center"><button onclick="tampilkanPreviewStruk('${t.id}')" class="bg-slate-100 hover:bg-slate-200 text-slate-800 px-2.5 py-1 rounded-lg text-xs font-bold border border-slate-300">Struk</button></td></tr>`; 
            });
        }

        function tampilkanPreviewStruk(idTrx) {
            const t = TRANSAKSI_DB.find(trx => trx.id === idTrx); if(!t) return;
            let labelMetode = "";
            if (t.metode === 'QRIS') labelMetode = "QRIS DIGITAL";
            else if (t.metode === 'DEBT') labelMetode = "BON PIUTANG";
            else labelMetode = "TUNAI / CASH";

            let s = `   === <?= strtoupper(htmlspecialchars($nama_usaha)); ?> ===\nNOTA    : ${t.id}\nTANGGAL : ${t.waktu}\nMETODE  : ${labelMetode}\nPELANGGAN: ${t.member}\nKASIR   : ${t.kasir}\n----------------------------------\n`;
            t.items.forEach(i => { s += `${i.nama}\n  ${i.qty} x Rp ${i.harga.toLocaleString('id-ID')}`.padEnd(22) + `Rp ${(i.qty*i.harga).toLocaleString('id-ID')}`.padStart(12) + `\n`; });
            s += `----------------------------------\nTOTAL AKHIR   : Rp ${t.total.toLocaleString('id-ID')}\n`;
            if (t.metode === 'DEBT') {
                s += `UANG BON/DEBT : Rp ${t.total.toLocaleString('id-ID')}\nKEMBALIAN     : Rp 0\n`;
            } else {
                s += `BAYAR         : Rp ${t.bayar.toLocaleString('id-ID')}\nKEMBALIAN     : Rp ${t.kembalian.toLocaleString('id-ID')}\n`;
            }
            s += `==================================\n   TERIMA KASIH ATAS KUNJUNGAN ANDA`;
            document.getElementById('area-cetak-struk').textContent = s;
        }

        function renderStockOpname() {
            const tbody = document.getElementById('opname-table-body'); 
            const tfooter = document.getElementById('opname-table-footer');
            tbody.innerHTML = ''; tfooter.innerHTML = '';
            
            const shortcutExport = document.getElementById('btn-export-opname');
            const instruksiEl = document.getElementById('opname-instruksi-role');

            OPNAME_INPUT_TEMPORARY = JSON.parse(localStorage.getItem('pos_opname_audit_db')) || {};

            if(currentUser.role === 'admin') {
                instruksiEl.innerHTML = "🔴 <strong>Audit Supervisor:</strong> Hasil komparasi stok fisik vs sistem.";
                shortcutExport.classList.remove('hidden');
            } else {
                instruksiEl.innerHTML = "🔵 <strong>Hitung Fisik:</strong> Input jumlah produk riil di rak.";
                shortcutExport.classList.add('hidden');
            }

            BARANG_DB.forEach((b, index) => {
                const nilaiFisikAwal = OPNAME_INPUT_TEMPORARY[b.kode] !== undefined ? OPNAME_INPUT_TEMPORARY[b.kode] : b.stok;
                const selisih = nilaiFisikAwal - b.stok; 
                const nilaiRupiahLoss = selisih * b.hpp;

                let rowHTML = `<tr class="hover:bg-slate-50 transition">
                    <td class="p-3 font-mono font-bold text-slate-900">${b.kode}</td>
                    <td class="p-3 font-semibold text-slate-800">${b.nama}</td>
                    <td class="p-3 text-center font-mono font-bold text-indigo-700 bg-indigo-50/50 view-admin-only ${currentUser.role !== 'admin' ? 'hidden' : ''}" id="opname-sistem-${index}">${b.stok}</td>
                    <td class="p-3 bg-amber-50/50">
                        <input type="number" value="${nilaiFisikAwal}" id="input-fisik-${index}" oninput="simpanKalkulasiFisikKontrol(${index}, '${b.kode}', ${b.hpp})" class="w-full text-center font-mono font-bold border-2 border-amber-300 rounded-lg p-1 focus:border-amber-500 bg-white">
                    </td>
                    <td class="p-3 text-center font-mono font-black view-admin-only ${currentUser.role !== 'admin' ? 'hidden' : ''}" id="opname-selisih-${index}">${selisih}</td>
                    <td class="p-3 text-right font-mono font-bold text-slate-900 view-admin-only ${currentUser.role !== 'admin' ? 'hidden' : ''}" id="opname-nilai-${index}">Rp ${nilaiRupiahLoss.toLocaleString('id-ID')}</td>
                </tr>`;

                tbody.innerHTML += rowHTML;
                if(currentUser.role === 'admin') styleKolomAuditOpname(index, selisih);
            });

            hitungGlobalKerugianOpname();
        }

        function simpanKalkulasiFisikKontrol(index, kodeBarang, hpp) {
            const nilaiFisik = parseInt(document.getElementById(`input-fisik-${index}`).value) || 0;
            OPNAME_INPUT_TEMPORARY[kodeBarang] = nilaiFisik;
            if(currentUser.role === 'admin') {
                const stokSistem = parseInt(document.getElementById(`opname-sistem-${index}`).textContent);
                const selisih = nilaiFisik - stokSistem;
                document.getElementById(`opname-selisih-${index}`).textContent = selisih;
                document.getElementById(`opname-nilai-${index}`).textContent = `Rp ${(selisih * hpp).toLocaleString('id-ID')}`;
                styleKolomAuditOpname(index, selisih);
                hitungGlobalKerugianOpname();
            }
        }

        function styleKolomAuditOpname(index, selisih) {
            const s = document.getElementById(`opname-selisih-${index}`); const n = document.getElementById(`opname-nilai-${index}`); if(!s) return;
            if(selisih === 0) { s.className = "p-3 text-center font-mono font-bold text-slate-400 view-admin-only"; n.className = "p-3 text-right font-mono font-bold text-slate-400 view-admin-only"; }
            else if (selisih < 0) { s.className = "p-3 text-center font-mono font-black text-rose-600 bg-rose-50 view-admin-only"; n.className = "p-3 text-right font-mono font-bold text-rose-600 bg-rose-50 view-admin-only"; }
            else { s.className = "p-3 text-center font-mono font-black text-emerald-600 bg-emerald-50 view-admin-only"; n.className = "p-3 text-right font-mono font-bold text-emerald-600 bg-emerald-50 view-admin-only"; }
        }

        function hitungGlobalKerugianOpname() {
            if(currentUser.role !== 'admin') return;
            let totalLossGlobal = 0;
            BARANG_DB.forEach(b => {
                const nilaiFisik = OPNAME_INPUT_TEMPORARY[b.kode] !== undefined ? OPNAME_INPUT_TEMPORARY[b.kode] : b.stok;
                const selisih = nilaiFisik - b.stok; totalLossGlobal += (selisih * b.hpp);
            });
            const fEl = document.getElementById('opname-table-footer');
            let warnaTeks = totalLossGlobal < 0 ? 'text-rose-600 bg-rose-50' : (totalLossGlobal > 0 ? 'text-emerald-600 bg-emerald-50' : 'text-slate-700 bg-slate-100');
            fEl.innerHTML = `<tr class="font-bold border-t-2 border-slate-900 ${warnaTeks}"><td colspan="2" class="p-4 text-left font-bold text-xs">RINGKASAN AUDIT SELISIH FISIK</td><td class="p-4 text-center view-admin-only">-</td><td class="p-4 text-center">-</td><td class="p-4 text-center view-admin-only">-</td><td class="p-4 text-right text-base font-black font-mono view-admin-only">Rp ${totalLossGlobal.toLocaleString('id-ID')}</td></tr>`;
        }

        function prosesSelesaiOpname() {
            const konfirmasiTeks = currentUser.role === 'admin' ? "Finalisasi data audit ini untuk penyesuaian stok sistem?" : "Kirim data fisik rak ke Admin?";
            if(!confirm(konfirmasiTeks)) return;

            let opnameSimpanan = JSON.parse(localStorage.getItem('pos_opname_audit_db')) || {};
            BARANG_DB.forEach((b, index) => {
                const elemenFisik = document.getElementById(`input-fisik-${index}`);
                if(elemenFisik) {
                    const nilaiFisik = parseInt(elemenFisik.value);
                    if(!isNaN(nilaiFisik)) {
                        if(currentUser.role === 'admin') b.stok = nilaiFisik; else opnameSimpanan[b.kode] = nilaiFisik;
                    }
                }
            });

            if(currentUser.role === 'admin') {
                saveAndRenderDB(); localStorage.removeItem('pos_opname_audit_db'); OPNAME_INPUT_TEMPORARY = {}; alert("Audit Sukses! Stok sistem diperbarui."); switchTab('barang');
            } else {
                localStorage.setItem('pos_opname_audit_db', JSON.stringify(opnameSimpanan)); OPNAME_INPUT_TEMPORARY = opnameSimpanan; alert("Data fisik berhasil dikirim!"); switchTab('transaksi');
            }
        }

        function exportDatabaseOpname() {
            if(currentUser.role !== 'admin') return alert('Akses Ditolak!');
            let totalLossGlobal = 0; let tableRows = "";
            BARANG_DB.forEach((b) => {
                const nilaiFisik = OPNAME_INPUT_TEMPORARY[b.kode] !== undefined ? OPNAME_INPUT_TEMPORARY[b.kode] : b.stok;
                const selisih = nilaiFisik - b.stok; const rugiLaba = selisih * b.hpp; totalLossGlobal += rugiLaba;
                tableRows += `<tr><td style="font-family: monospace;">'${b.kode}</td><td>${b.nama}</td><td align="center">${b.stok}</td><td align="center">${nilaiFisik}</td><td align="center">${selisih}</td><td align="right">${b.hpp}</td><td align="right">${rugiLaba}</td></tr>`;
            });

            let excelTemplate = `<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40"><head><meta charset="UTF-8"><!--[if gte mso 9]><xml><x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet><x:Name>Audit Opname</x:Name><x:WorksheetOptions><x:DisplayGridlines/></x:WorksheetOptions></x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook></xml><![endif]--><style>th { background-color: #0F172A; color: #FFFFFF; font-weight: bold; padding: 6px; } td { padding: 4px; border: 0.5px solid #E2E8F0; }</style></head><body><h2>LAPORAN AUDIT STOCK OPNAME</h2><p><b>Tanggal:</b> ${new Date().toLocaleString('id-ID')}</p><table><thead><tr><th>Kode</th><th>Nama Barang</th><th>Sistem</th><th>Fisik</th><th>Selisih</th><th>HPP</th><th>Kerugian/Keuntungan (Rp)</th></tr></thead><tbody>${tableRows}<tr><td colspan="2" height="30" valign="middle"><b>TOTAL SELISIH</b></td><td align="center">-</td><td align="center">-</td><td align="center">-</td><td align="right">-</td><td align="right" style="font-size: 12pt; font-weight: bold;">${totalLossGlobal}</td></tr></tbody></table></body></html>`;

            const blob = new Blob([excelTemplate], { type: "application/vnd.ms-excel" });
            const url = URL.createObjectURL(blob); const link = document.createElement("a"); link.href = url; link.download = `LAPORAN_OPNAME_${new Date().toISOString().slice(0,10)}.xls`; document.body.appendChild(link); link.click(); document.body.removeChild(link); URL.revokeObjectURL(url);
        }

        function exportDatabaseBarang() {
            let csv = "data:text/csv;charset=utf-8,Kode,Nama,Kategori,Rak,HPP,Margin,HargaJual,Stok\n";
            BARANG_DB.forEach(b => { csv += `"${b.kode}","${b.nama}","${b.kategori}","${b.rak}",${b.hpp},${b.margin},${b.harga},${b.stok}\n`; });
            const e = encodeURI(csv); const l = document.createElement("a"); l.setAttribute("href", e); l.setAttribute("download", `BACKUP_POS.csv`); document.body.appendChild(l); l.click(); document.body.removeChild(l);
        }

        function importDatabaseBarang(event) {
            const file = event.target.files[0]; if (!file) return;
            const r = new FileReader(); r.onload = function(e) {
                const lines = e.target.result.split("\n");
                for (let i = 1; i < lines.length; i++) {
                    if (!lines[i].trim()) continue; const cols = lines[i].split(/,(?=(?:(?:[^"]*"){2})*[^"]*$)/);
                    if(cols.length >= 8) {
                        const kode = cols[0].replace(/"/g, '').trim(); const nama = cols[1].replace(/"/g, '').trim(); const kategori = cols[2].replace(/"/g, '').trim(); const rak = cols[3].replace(/"/g, '').trim(); const hpp = parseFloat(cols[4]) || 0; const margin = parseFloat(cols[5]) || 0; const harga = parseFloat(cols[6]) || 0; const stok = parseInt(cols[7]) || 0;
                        const idx = BARANG_DB.findIndex(b => b.kode === kode);
                        if(idx > -1) BARANG_DB[idx] = { kode, nama, kategori, rak, hpp, margin, harga, stok, promo: { tipe: 'none', value: 0 } }; else BARANG_DB.push({ kode, nama, kategori, rak, hpp, margin, harga, stok, promo: { tipe: 'none', value: 0 } });
                    }
                }
                saveAndRenderDB(); alert("Import Berhasil!");
            }; r.readAsText(file);
        }
    </script>
</body>
</html>