<?php
/*
 * ==========================================================
 * ⚠️ APLIKASI SUDAH BERPINDAH KE FIREBASE FIRESTORE
 * ==========================================================
 *
 * Mulai sekarang, aplikasi ini menggunakan:
 *   🔥 Firebase Firestore (NoSQL Cloud Database)
 *   📄 HTML + JavaScript (no PHP backend)
 *
 ✅ File yang aktif (gunakan langsung di browser):
 *
 *   index.html         → Dashboard utama
 *   kasir.html         → Halaman kasir (transaksi)
 *   laporan.html       → Laporan & riwayat transaksi
 *   data_produk.html   → Manajemen produk (CRUD)
 *   cetak_struk.html   → Cetak struk belanja
 *   js/firebase-config.js → Konfigurasi Firebase
 *
 * 🛑 File PHP TIDAK DIGUNAKAN LAGI (ditinggalkan):
 *   - index.php   → Ganti dengan index.html
 *   - laporan.php → Ganti dengan laporan.html
 *   - data_produk.php → Ganti dengan data_produk.html
 *   - cetak_struk.php → Ganti dengan cetak_struk.html
 *
 * ==========================================================
 * 🔥 CARA SETUP FIREBASE:
 * ==========================================================
 *
 * 1. Buka https://console.firebase.google.com
 * 2. Klik "Add project" → beri nama (e.g. "pos-kasir") → Create
 * 3. Setelah jadi, klik ikon Web (</>) → Register app
 * 4. Copy firebaseConfig → Paste ke js/firebase-config.js
 * 5. Buka menu Build → Firestore Database → Create database
 * 6. Pilih "Start in test mode" → Next → Done
 * 7. Buka index.html di browser → Selesai! 🚀
 *
 * ==========================================================
 * 💡 UNTUK MENJALANKAN APLIKASI:
 * ==========================================================
 *
 * cukup buka file index.html langsung di browser.
 * Atau gunakan Live Server (VS Code extension) agar lebih nyaman.
 *
 * ==========================================================
 */

// Koneksi MySQL sudah tidak digunakan.
// Silakan matikan Apache & MySQL di XAMPP jika tidak diperlukan lagi.
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Migrasi Firebase - Toko Maju Jaya</title>
    <style>
        body {
            font-family: 'Segoe UI', sans-serif;
            background: #1a1a2e;
            color: #fff;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            padding: 20px;
        }
        .card {
            background: #fff;
            color: #2d3436;
            border-radius: 16px;
            padding: 32px;
            max-width: 600px;
            width: 100%;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
        }
        .card h1 { font-size: 24px; margin-bottom: 6px; }
        .card .sub { color: #888; font-size: 14px; margin-bottom: 20px; }
        .card .icon { font-size: 48px; text-align: center; margin-bottom: 12px; }
        .step {
            background: #f8f9fb;
            border-radius: 10px;
            padding: 12px 16px;
            margin-bottom: 10px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
        }
        .step .num {
            width: 28px; height: 28px;
            background: #1a1a2e; color: #fff;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-weight: 700; font-size: 14px;
            flex-shrink: 0;
        }
        .step .desc { font-size: 13px; line-height: 1.5; }
        .step .desc strong { color: #1a1a2e; }
        .btn {
            display: inline-block;
            margin-top: 16px;
            padding: 12px 24px;
            background: #1a1a2e;
            color: #fff;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            transition: background 0.2s;
        }
        .btn:hover { background: #16213e; }
        .warning {
            background: #fff3cd;
            color: #856404;
            padding: 10px 14px;
            border-radius: 8px;
            font-size: 13px;
            margin-top: 14px;
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon">🔥</div>
        <h1 style="text-align:center;">Aplikasi Berpindah ke Firebase</h1>
        <p class="sub" style="text-align:center;">
            Aplikasi POS sekarang menggunakan <strong>Firebase Firestore</strong> — tidak perlu XAMPP/MySQL lagi!
        </p>

        <div class="step">
            <span class="num">1</span>
            <div class="desc"><strong>Buka halaman utama</strong><br>
            Buka file <code>index.html</code> langsung di browser.<br>
            Atau gunakan Live Server (VS Code) untuk pengalaman lebih baik.</div>
        </div>
        <div class="step">
            <span class="num">2</span>
            <div class="desc"><strong>Setup Firebase (1x saja)</strong><br>
            Buka <code>js/firebase-config.js</code> dan ikuti petunjuk setup di dalamnya.</div>
        </div>
        <div class="step">
            <span class="num">3</span>
            <div class="desc"><strong>Selesai! 🚀</strong><br>
            Setelah Firebase terkonfigurasi, semua halaman akan langsung terhubung ke database cloud.</div>
        </div>

        <div style="text-align:center;">
            <a href="index.html" class="btn">🚀 Buka Dashboard</a>
        </div>

        <div class="warning">
            ⚠️ File PHP (index.php, laporan.php, dll.) sudah tidak digunakan lagi, tapi tetap disimpan sebagai referensi.
        </div>
    </div>
</body>
</html>
