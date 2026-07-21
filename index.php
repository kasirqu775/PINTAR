<?php
session_start();

// Cek apakah user sudah login
$is_logged_in = isset($_SESSION['user_id']);
$user_role    = $_SESSION['role'] ?? '';
$nama_user    = $_SESSION['nama_lengkap'] ?? '';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistem Multi-User Usaha - Kelola Bisnis Anda Lebih Praktis</title>
    <link rel="stylesheet" href="style.css">
    <style>
        /* Penyesuaian khusus Tampilan Landing Page (Index) */
        body {
            display: block; /* Mengabaikan flex center bawaan style.css agar halaman bisa di-scroll */
            background-color: #f8fafc;
        }

        /* Navbar */
        .navbar {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            position: sticky;
            top: 0;
            z-index: 100;
            border-bottom: 1px solid rgba(229, 231, 235, 0.8);
            padding: 16px 8%;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .brand-logo {
            font-size: 1.4rem;
            font-weight: 800;
            background: linear-gradient(135deg, #4f46e5 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            text-decoration: none;
        }

        .nav-buttons {
            display: flex;
            gap: 12px;
            align-items: center;
        }

        .btn-secondary {
            padding: 10px 20px;
            border-radius: 8px;
            color: #4f46e5;
            border: 1.5px solid #4f46e5;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s ease;
        }

        .btn-secondary:hover {
            background: #eef2ff;
        }

        .btn-primary {
            padding: 10px 20px;
            border-radius: 8px;
            background: #4f46e5;
            color: white;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s ease;
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3);
        }

        .btn-primary:hover {
            background: #4338ca;
            transform: translateY(-1px);
        }

        /* Hero Section */
        .hero {
            background: var(--bg-gradient);
            color: white;
            padding: 100px 8% 120px 8%;
            text-align: center;
            clip-path: polygon(0 0, 100% 0, 100% 90%, 0 100%);
        }

        .hero h1 {
            font-size: 3rem;
            font-weight: 800;
            margin-bottom: 20px;
            line-height: 1.2;
        }

        .hero p {
            font-size: 1.2rem;
            max-width: 680px;
            margin: 0 auto 35px auto;
            opacity: 0.9;
            line-height: 1.6;
        }

        .hero-cta {
            display: flex;
            justify-content: center;
            gap: 16px;
        }

        .btn-hero-white {
            background: white;
            color: #4f46e5;
            padding: 14px 32px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 1rem;
            text-decoration: none;
            box-shadow: 0 10px 20px rgba(0,0,0,0.15);
            transition: all 0.2s;
        }

        .btn-hero-white:hover {
            transform: translateY(-2px);
            background: #f8fafc;
        }

        /* Fitur Section */
        .section-container {
            padding: 80px 8%;
            max-width: 1200px;
            margin: 0 auto;
        }

        .section-title {
            text-align: center;
            margin-bottom: 50px;
        }

        .section-title h2 {
            font-size: 2rem;
            color: #1f2937;
            margin-bottom: 10px;
        }

        .section-title p {
            color: #6b7280;
        }

        .grid-3 {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 30px;
        }

        .card-feature {
            background: white;
            padding: 30px;
            border-radius: 16px;
            border: 1px solid #e5e7eb;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
        }

        .card-feature:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
        }

        .feature-icon {
            width: 50px;
            height: 50px;
            background: #eef2ff;
            color: #4f46e5;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            font-weight: bold;
            margin-bottom: 20px;
        }

        .card-feature h3 {
            font-size: 1.25rem;
            color: #1f2937;
            margin-bottom: 12px;
        }

        .card-feature p {
            color: #6b7280;
            font-size: 0.95rem;
            line-height: 1.5;
        }

        /* Paket Masa Aktif */
        .plans-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 20px;
            margin-top: 30px;
        }

        .plan-box {
            background: white;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            padding: 24px 16px;
            text-align: center;
            transition: all 0.2s;
        }

        .plan-box:hover {
            border-color: #4f46e5;
            background: #f8fafc;
        }

        .plan-box .duration {
            font-size: 1.25rem;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 6px;
        }

        .plan-box .desc {
            font-size: 0.85rem;
            color: #6b7280;
        }

        /* Footer */
        footer {
            background: #111827;
            color: #9ca3af;
            padding: 40px 8%;
            text-align: center;
            font-size: 0.9rem;
        }

        footer a {
            color: #818cf8;
            text-decoration: none;
        }

        @media (max-width: 768px) {
            .hero h1 { font-size: 2rem; }
            .hero { padding: 60px 5% 80px 5%; }
            .navbar { padding: 16px 5%; }
        }
    </style>
</head>
<body>

    <!-- Navigation Bar -->
    <nav class="navbar">
        <a href="index.php" class="brand-logo">🚀 AppUsaha MultiUser</a>
        <div class="nav-buttons">
            <?php if ($is_logged_in): ?>
                <span style="font-size: 0.9rem; color: #4b5563; margin-right: 10px;">Halo, <strong><?= htmlspecialchars($nama_user); ?></strong></span>
                <?php if ($user_role === 'admin'): ?>
                    <a href="admin.php" class="btn-primary">Panel Admin</a>
                <?php else: ?>
                    <a href="dashboard.php" class="btn-primary">Ke Dashboard</a>
                <?php endif; ?>
            <?php else: ?>
                <a href="login.php" class="btn-secondary">Masuk</a>
                <a href="register.php" class="btn-primary">Daftar Sekarang</a>
            <?php endif; ?>
        </div>
    </nav>

    <!-- Hero Section -->
    <header class="hero">
        <h1>Solusi Manajemen Usaha<br>Multi-User Berbasis Keanggotaan</h1>
        <p>Daftarkan bisnis Anda, dapatkan persetujuan lisensi cepat dari admin, dan nikmati akses fleksibel harian, bulanan, hingga tahunan.</p>
        
        <div class="hero-cta">
            <?php if ($is_logged_in): ?>
                <a href="<?= $user_role === 'admin' ? 'admin.php' : 'dashboard.php'; ?>" class="btn-hero-white">Buka Panel Anda</a>
            <?php else: ?>
                <a href="register.php" class="btn-hero-white">Mulai Daftar Gratis</a>
            <?php endif; ?>
        </div>
    </header>

    <!-- Fitur Utama -->
    <section class="section-container">
        <div class="section-title">
            <h2>Keunggulan Platform</h2>
            <p>Sistem terstruktur untuk memastikan keamanan dan fleksibilitas akses pengguna</p>
        </div>

        <div class="grid-3">
            <div class="card-feature">
                <div class="feature-icon">📝</div>
                <h3>Pendaftaran Mudah</h3>
                <p>Cukup daftarkan nama usaha dan identitas Anda untuk mendapatkan akses ke dalam sistem.</p>
            </div>

            <div class="card-feature">
                <div class="feature-icon">🛡️</div>
                <h3>Sistem Approval Admin</h3>
                <p>Akun baru akan diverifikasi secara manual oleh administrator demi menjaga keamanan data usaha Anda.</p>
            </div>

            <div class="card-feature">
                <div class="feature-icon">⏳</div>
                <h3>Masa Aktif Fleksibel</h3>
                <p>Lisensi akses yang terukur presisi mulai dari harian, bulanan, hingga tahunan sesuai kebutuhan bisnis.</p>
            </div>
        </div>
    </section>

    <!-- Pilihan Masa Aktif -->
    <section class="section-container" style="background: #ffffff; border-radius: 24px; margin-bottom: 80px;">
        <div class="section-title">
            <h2>Masa Aktif Akses Lisensi</h2>
            <p>Admin dapat memberikan opsi durasi berlangganan berikut kepada akun pengguna</p>
        </div>

        <div class="plans-grid">
            <div class="plan-box">
                <div class="duration">1 Hari</div>
                <div class="desc">Akses Uji Coba Singkat</div>
            </div>
            <div class="plan-box">
                <div class="duration">1 Bulan</div>
                <div class="desc">Paket Bulanan Standar</div>
            </div>
            <div class="plan-box">
                <div class="duration">3 Bulan</div>
                <div class="desc">Paket Triwulan</div>
            </div>
            <div class="plan-box">
                <div class="duration">6 Bulan</div>
                <div class="desc">Paket Semester</div>
            </div>
            <div class="plan-box">
                <div class="duration">1 Tahun</div>
                <div class="desc">Paket Tahunan Hemat</div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer>
        <p>&copy; <?= date('Y'); ?> <strong>AppUsaha MultiUser</strong>. Seluruh Hak Cipta Dilindungi.</p>
    </footer>

</body>
</html>
