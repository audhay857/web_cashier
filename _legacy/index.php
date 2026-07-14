<?php
/*
 * ==========================================================
 * HALAMAN UTAMA KASIR (POS)
 * Point of Sale - PHP Native
 * ==========================================================
 *
 * Fitur:
 * - Menampilkan daftar produk dari database
 * - Pencarian produk (real-time)
 * - Keranjang belanja (session-based)
 * - Input nominal bayar & hitung kembalian otomatis
 * - Proses transaksi → redirect ke cetak_struk.php
 * ==========================================================
 */

session_start();
require_once 'koneksi.php';

// --- Inisialisasi keranjang ---
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// --- Proses Aksi ---
$aksi = isset($_POST['aksi']) ? $_POST['aksi'] : '';

if ($aksi === 'tambah') {
    // Tambah item ke keranjang
    $id     = (int) $_POST['id_produk'];
    $jumlah = max(1, (int) ($_POST['jumlah'] ?? 1));

    // Ambil data produk dari DB
    $produk = query_row("SELECT * FROM produk WHERE id = $id");
    if ($produk) {
        if (isset($_SESSION['cart'][$id])) {
            // Jika sudah ada, tambah jumlahnya
            $_SESSION['cart'][$id]['jumlah'] += $jumlah;
        } else {
            // Baru                $_SESSION['cart'][$id] = [
                'id'     => $produk['id'],
                'nama'   => $produk['nama_produk'],
                'harga'  => $produk['harga'],
                'jumlah' => $jumlah,
            ];
        }

        // Notifikasi sukses
        $_SESSION['success'] = '✓ ' . $produk['nama_produk'] . ' ditambahkan!';
    }
    redirectBack();

} elseif ($aksi === 'update_qty') {
    // Update jumlah item di keranjang
    $id     = (int) $_POST['id_produk'];
    $jumlah = max(0, (int) ($_POST['jumlah'] ?? 0));

    if ($jumlah > 0 && isset($_SESSION['cart'][$id])) {
        $_SESSION['cart'][$id]['jumlah'] = $jumlah;
    } else {
        unset($_SESSION['cart'][$id]);
    }
    redirectBack();

} elseif ($aksi === 'hapus') {
    // Hapus item dari keranjang
    $id = (int) ($_POST['id_produk'] ?? 0);
    unset($_SESSION['cart'][$id]);
    redirectBack();

} elseif ($aksi === 'clear') {
    // Kosongkan keranjang
    $_SESSION['cart'] = [];
    redirectBack();

} elseif ($aksi === 'bayar') {
    // Proses pembayaran
    prosesBayar();
}

/**
 * Redirect kembali ke halaman utama (mencegah resubmit form)
 */
function redirectBack()
{
    header('Location: index.php');
    exit;
}

/**
 * Proses transaksi: simpan ke database & redirect ke cetak struk
 */
function prosesBayar()
{
    global $koneksi;

    $cart  = $_SESSION['cart'];
    $bayar = (int) str_replace('.', '', $_POST['nominal_uang'] ?? '0');

    if (empty($cart)) {
        $_SESSION['error'] = 'Keranjang belanja masih kosong!';
        redirectBack();
    }

    if ($bayar <= 0) {
        $_SESSION['error'] = 'Nominal uang tidak valid!';
        redirectBack();
    }

    // Hitung total
    $total = 0;
    foreach ($cart as $item) {
        $total += $item['harga'] * $item['jumlah'];
    }

    if ($bayar < $total) {
        $_SESSION['error'] = 'Uang tidak mencukupi! Kurang Rp ' . number_format($total - $bayar, 0, ',', '.');
        redirectBack();
    }

    $kembalian = $bayar - $total;

    // Generate no faktur: INV-YYYYMMDD-XXXX
    $today = date('Ymd');
    $last  = query_row("
        SELECT MAX(no_faktur) as last
        FROM transaksi
        WHERE no_faktur LIKE 'INV-$today-%'
    ");

    if ($last && $last['last']) {
        $seq = (int) substr($last['last'], -4) + 1;
    } else {
        $seq = 1;
    }
    $no_faktur = 'INV-' . $today . '-' . str_pad($seq, 4, '0', STR_PAD_LEFT);

    // Mulai transaksi database
    // Aktifkan exception mode agar error query langsung terdeteksi
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $koneksi->begin_transaction();

    try {
        // Simpan transaksi
        $sqlTransaksi = "
            INSERT INTO transaksi (no_faktur, tanggal, total_bayar, nominal_uang, kembalian)
            VALUES (
                '" . esc($no_faktur) . "',
                NOW(),
                $total,
                $bayar,
                $kembalian
            )
        ";
        if (!$koneksi->query($sqlTransaksi)) {
            throw new Exception($koneksi->error);
        }

        // Simpan detail transaksi
        foreach ($cart as $item) {
            $subtotal = $item['harga'] * $item['jumlah'];
            $sqlDetail = "
                INSERT INTO detail_transaksi (no_faktur, id_produk, jumlah, subtotal)
                VALUES (
                    '" . esc($no_faktur) . "',
                    {$item['id']},
                    {$item['jumlah']},
                    $subtotal
                )
            ";
            if (!$koneksi->query($sqlDetail)) {
                throw new Exception($koneksi->error);
            }
        }

        $koneksi->commit();

        // Kosongkan keranjang
        $_SESSION['cart'] = [];

        // Redirect ke halaman cetak struk
        header("Location: cetak_struk.php?faktur=" . urlencode($no_faktur));
        exit;

    } catch (Exception $e) {
        $koneksi->rollback();
        $_SESSION['error'] = 'Gagal memproses transaksi: ' . $e->getMessage();
        redirectBack();
    } finally {
        // Kembalikan ke mode default
        mysqli_report(MYSQLI_REPORT_ERROR);
    }
}

// --- Ambil daftar produk ---
$keyword = isset($_GET['cari']) ? esc($_GET['cari']) : '';
if (!empty($keyword)) {
    $produkList = query("
        SELECT * FROM produk
        WHERE nama_produk LIKE '%$keyword%'
        ORDER BY nama_produk ASC
    ");
} else {
    $produkList = query("SELECT * FROM produk ORDER BY nama_produk ASC");
}

// --- Hitung total keranjang ---
$cartTotal = 0;
foreach ($_SESSION['cart'] as $item) {
    $cartTotal += $item['harga'] * $item['jumlah'];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>POS - Toko Maju Jaya</title>
    <style>
        /* ==========================================================
           RESET & BASE
           ========================================================== */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #eef0f5;
            color: #2d3436;
            min-height: 100vh;
        }

        /* ==========================================================
           HEADER
           ========================================================== */
        .header {
            background: linear-gradient(135deg, #1a1a2e, #16213e);
            color: #fff;
            padding: 14px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .header-left .logo {
            font-size: 22px;
        }

        .header-left .nama-toko {
            font-size: 18px;
            font-weight: 700;
            letter-spacing: 0.5px;
        }

        .header-left .kasir-info {
            font-size: 12px;
            color: rgba(255,255,255,0.7);
            margin-top: 2px;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 16px;
            font-size: 13px;
            color: rgba(255,255,255,0.85);
        }

        .header-right a {
            color: rgba(255,255,255,0.85);
            text-decoration: none;
            font-size: 13px;
            padding: 6px 14px;
            border-radius: 6px;
            transition: background 0.2s;
        }

        .header-right a:hover {
            background: rgba(255,255,255,0.1);
        }

        .header-right a.active {
            background: rgba(255,255,255,0.15);
            font-weight: 600;
        }

        .header-right .tanggal {
            background: rgba(255,255,255,0.1);
            padding: 4px 12px;
            border-radius: 6px;
        }

        /* ==========================================================
           LAYOUT UTAMA (Flex)
           ========================================================== */
        .main-layout {
            display: flex;
            gap: 20px;
            padding: 20px;
            max-width: 1400px;
            margin: 0 auto;
            min-height: calc(100vh - 66px);
        }

        /* --- Panel Kiri: Daftar Produk --- */
        .panel-produk {
            flex: 1;
            min-width: 0;
        }

        /* --- Panel Kanan: Keranjang --- */
        .panel-keranjang {
            width: 380px;
            flex-shrink: 0;
        }

        /* ==========================================================
           CARD / PANEL
           ========================================================== */
        .card {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.06);
            overflow: hidden;
        }

        .card-header {
            padding: 14px 18px;
            font-weight: 600;
            font-size: 15px;
            border-bottom: 1px solid #f0f0f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #fafbfc;
        }

        .card-body {
            padding: 16px;
        }

        /* ==========================================================
           SEARCH BAR
           ========================================================== */
        .search-box {
            display: flex;
            gap: 8px;
            margin-bottom: 14px;
        }

        .search-box input {
            flex: 1;
            padding: 9px 14px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            outline: none;
            transition: border-color 0.2s;
        }

        .search-box input:focus {
            border-color: #1a1a2e;
        }

        .search-box button {
            padding: 9px 16px;
            background: #1a1a2e;
            color: #fff;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            transition: background 0.2s;
        }

        .search-box button:hover {
            background: #16213e;
        }

        /* ==========================================================
           GRID PRODUK
           ========================================================== */
        .produk-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
            gap: 10px;
        }

        .produk-item {
            background: #f8f9fb;
            border: 1px solid #eee;
            border-radius: 10px;
            padding: 14px 12px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s ease;
            user-select: none;
        }

        .produk-item:hover {
            background: #eef2ff;
            border-color: #1a1a2e;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(26, 26, 46, 0.12);
        }

        .produk-item:active {
            transform: translateY(0);
        }

        .produk-item .produk-icon {
            font-size: 28px;
            margin-bottom: 6px;
        }

        .produk-item .produk-nama {
            font-size: 13px;
            font-weight: 600;
            color: #2d3436;
            margin-bottom: 4px;
            line-height: 1.3;
        }

        .produk-item .produk-harga {
            font-size: 14px;
            color: #1a1a2e;
            font-weight: 700;
        }

        .produk-item .produk-qty-input {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            margin-top: 8px;
        }

        .produk-item .produk-qty-input input {
            width: 48px;
            padding: 4px 6px;
            text-align: center;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 13px;
            font-weight: 600;
        }

        /* ==========================================================
           KERANJANG
           ========================================================== */
        .cart-count {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #e74c3c;
            color: #fff;
            font-size: 11px;
            font-weight: 700;
            min-width: 22px;
            height: 22px;
            border-radius: 11px;
            padding: 0 6px;
        }

        .cart-items {
            max-height: 340px;
            overflow-y: auto;
            margin-bottom: 10px;
        }

        .cart-items::-webkit-scrollbar {
            width: 4px;
        }

        .cart-items::-webkit-scrollbar-thumb {
            background: #ccc;
            border-radius: 4px;
        }

        .cart-item {
            display: flex;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid #f0f0f0;
            gap: 8px;
        }

        .cart-item:last-child {
            border-bottom: none;
        }

        .cart-item-info {
            flex: 1;
            min-width: 0;
        }

        .cart-item-nama {
            font-size: 13px;
            font-weight: 600;
            color: #2d3436;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .cart-item-harga {
            font-size: 11px;
            color: #888;
        }

        .cart-item-qty {
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .cart-item-qty button {
            width: 26px;
            height: 26px;
            border: 1px solid #ddd;
            background: #f8f9fb;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.2s;
        }

        .cart-item-qty button:hover {
            background: #eef2ff;
            border-color: #1a1a2e;
        }

        .cart-item-qty .qty-value {
            font-size: 14px;
            font-weight: 700;
            min-width: 24px;
            text-align: center;
        }

        .cart-item-subtotal {
            font-size: 13px;
            font-weight: 700;
            color: #1a1a2e;
            min-width: 70px;
            text-align: right;
        }

        .cart-item-hapus {
            background: none;
            border: none;
            color: #e74c3c;
            cursor: pointer;
            font-size: 16px;
            padding: 2px 4px;
            border-radius: 4px;
            transition: background 0.2s;
        }

        .cart-item-hapus:hover {
            background: #fde8e8;
        }

        /* --- Ringkasan Pembayaran --- */
        .cart-summary {
            border-top: 2px dashed #ddd;
            padding-top: 12px;
            margin-top: 4px;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 4px 0;
            font-size: 14px;
        }

        .summary-row.total {
            font-size: 18px;
            font-weight: 700;
            color: #1a1a2e;
            padding: 8px 0;
            border-top: 1px solid #eee;
            margin-top: 4px;
        }

        .summary-row .label {
            color: #666;
        }

        .summary-row .value {
            font-weight: 600;
        }

        .summary-row.total .value {
            font-size: 20px;
            color: #e74c3c;
        }

        /* --- Form Bayar --- */
        .bayar-group {
            margin-top: 10px;
        }

        .bayar-group label {
            font-size: 13px;
            font-weight: 600;
            color: #555;
            display: block;
            margin-bottom: 4px;
        }

        .bayar-input-wrap {
            display: flex;
            align-items: center;
            border: 2px solid #ddd;
            border-radius: 8px;
            overflow: hidden;
            transition: border-color 0.2s;
        }

        .bayar-input-wrap:focus-within {
            border-color: #1a1a2e;
        }

        .bayar-input-wrap .rp-prefix {
            padding: 10px 12px;
            background: #f5f5f5;
            font-weight: 700;
            font-size: 15px;
            color: #555;
            border-right: 1px solid #ddd;
        }

        .bayar-input-wrap input {
            flex: 1;
            padding: 10px 12px;
            border: none;
            font-size: 16px;
            font-weight: 600;
            outline: none;
        }

        .kembali-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 12px;
            margin-top: 8px;
            background: #e8f5e9;
            border-radius: 8px;
            font-size: 15px;
        }

        .kembali-row .label {
            color: #2e7d32;
            font-weight: 600;
        }

        .kembali-row .value {
            font-size: 18px;
            font-weight: 700;
            color: #1b5e20;
        }

        .kembali-row.minus {
            background: #fde8e8;
        }

        .kembali-row.minus .label {
            color: #c62828;
        }

        .kembali-row.minus .value {
            color: #c62828;
        }

        .btn-bayar {
            width: 100%;
            padding: 13px;
            margin-top: 12px;
            background: linear-gradient(135deg, #1a1a2e, #16213e);
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: transform 0.15s, box-shadow 0.2s;
            letter-spacing: 0.5px;
        }

        .btn-bayar:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 16px rgba(26, 26, 46, 0.3);
        }

        .btn-bayar:active {
            transform: translateY(0);
        }

        .btn-bayar:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }

        .btn-clear {
            display: inline-block;
            padding: 5px 12px;
            background: none;
            border: 1px solid #e74c3c;
            color: #e74c3c;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 600;
            transition: all 0.2s;
        }

        .btn-clear:hover {
            background: #e74c3c;
            color: #fff;
        }

        /* --- Cart Empty --- */
        .cart-empty {
            text-align: center;
            padding: 30px 16px;
            color: #aaa;
        }

        .cart-empty .icon {
            font-size: 40px;
            margin-bottom: 8px;
        }

        .cart-empty p {
            font-size: 13px;
        }

        /* ==========================================================
           NOTIFIKASI
           ========================================================== */
        .notif {
            padding: 12px 18px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 500;
            margin-bottom: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .notif.error {
            background: #fde8e8;
            color: #c62828;
            border: 1px solid #ffcdd2;
        }

        .notif.success {
            background: #e8f5e9;
            color: #1b5e20;
            border: 1px solid #c8e6c9;
        }

        /* ==========================================================
           RESPONSIVE
           ========================================================== */
        @media (max-width: 960px) {
            .main-layout {
                flex-direction: column;
            }

            .panel-keranjang {
                width: 100%;
            }
        }

        @media (max-width: 600px) {
            .header {
                flex-direction: column;
                text-align: center;
                gap: 8px;
                padding: 12px 16px;
            }

            .main-layout {
                padding: 12px;
            }

            .produk-grid {
                grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
            }
        }

        /* ==========================================================
           ANIMASI
           ========================================================== */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(6px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .cart-item {
            animation: fadeIn 0.2s ease;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50%       { transform: scale(1.05); }
        }

        .produk-item:active .produk-nama {
            animation: pulse 0.15s ease;
        }
    </style>
</head>
<body>

<!-- ============================================================
     HEADER
     ============================================================ -->
<header class="header">
    <div class="header-left">
        <span class="logo">🏪</span>
        <div>
            <div class="nama-toko">TOKO MAJU JAYA</div>
            <div class="kasir-info">👤 Kasir: Admin</div>
        </div>
    </div>
    <div class="header-right">
        <a href="index.php" class="active">🏠 Kasir</a>
        <a href="laporan.php">📊 Laporan</a>
        <a href="data_produk.php">📦 Produk</a>
        <span class="tanggal">📅 <?= date('d/m/Y H:i') ?></span>
    </div>
</header>

<!-- ============================================================
     MAIN LAYOUT
     ============================================================ -->
<div class="main-layout">

    <!-- ========== PANEL KIRI: PRODUK ========== -->
    <div class="panel-produk">

        <!-- Notifikasi -->
        <?php if (isset($_SESSION['error'])): ?>
            <div class="notif error">
                <span>⚠️</span> <?= htmlspecialchars($_SESSION['error']) ?>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="notif success">
                <span>✅</span> <?= htmlspecialchars($_SESSION['success']) ?>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <!-- Card Produk -->
        <div class="card">
            <div class="card-header">
                <span>📦 Daftar Produk</span>
                <span style="font-size:12px;color:#888;font-weight:400;">
                    <?= count($produkList) ?> item
                </span>
            </div>
            <div class="card-body">

                <!-- Search -->
                <form method="GET" action="index.php" class="search-box">
                    <input type="text" name="cari" placeholder="Cari produk..."
                           value="<?= htmlspecialchars($keyword) ?>"
                           autocomplete="off">
                    <button type="submit">🔍 Cari</button>
                    <?php if (!empty($keyword)): ?>
                        <a href="index.php" style="display:inline-flex;align-items:center;padding:9px 12px;background:#eee;border-radius:8px;color:#666;text-decoration:none;font-size:13px;">
                            ✕ Reset
                        </a>
                    <?php endif; ?>
                </form>

                <!-- Grid Produk -->
                <?php if (empty($produkList)): ?>
                    <div style="text-align:center;padding:30px 0;color:#aaa;">
                        <p style="font-size:40px;margin-bottom:6px;">📭</p>
                        <p>Produk tidak ditemukan.</p>
                    </div>
                <?php else: ?>
                    <div class="produk-grid">
                        <?php foreach ($produkList as $p): ?>
                            <?php
                                $icons = ['📦','🍪','🥤','🍞','🧴','🍫','🥫','🧃'];
                                $icon = $icons[$p['id'] % count($icons)];
                            ?>
                            <div class="produk-item"
                                 onclick="tambahKeKeranjang(<?= $p['id'] ?>, '<?= htmlspecialchars($p['nama_produk'], ENT_QUOTES) ?>', <?= $p['harga'] ?>)">
                                <div class="produk-icon"><?= $icon ?></div>
                                <div class="produk-nama"><?= htmlspecialchars($p['nama_produk']) ?></div>
                                <div class="produk-harga">Rp <?= rupiah($p['harga']) ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

            </div>
        </div>
    </div>

    <!-- ========== PANEL KANAN: KERANJANG ========== -->
    <div class="panel-keranjang">
        <div class="card" style="position:sticky;top:86px;">
            <div class="card-header">
                <span>
                    🛒 Keranjang
                    <?php if (count($_SESSION['cart']) > 0): ?>
                        <span class="cart-count"><?= count($_SESSION['cart']) ?></span>
                    <?php endif; ?>
                </span>
                <?php if (count($_SESSION['cart']) > 0): ?>
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="aksi" value="clear">
                        <button type="submit" class="btn-clear">✕ Kosongkan</button>
                    </form>
                <?php endif; ?>
            </div>

            <div class="card-body">

                <!-- Daftar Item di Keranjang -->
                <?php if (empty($_SESSION['cart'])): ?>
                    <div class="cart-empty">
                        <div class="icon">🛒</div>
                        <p>Belum ada barang</p>
                        <p style="font-size:11px;color:#ccc;">Klik produk untuk menambah</p>
                    </div>
                <?php else: ?>
                    <div class="cart-items">
                        <?php foreach ($_SESSION['cart'] as $item):
                            $subtotal = $item['harga'] * $item['jumlah'];
                        ?>
                        <div class="cart-item" data-id="<?= $item['id'] ?>">
                            <div class="cart-item-info">
                                <div class="cart-item-nama"><?= htmlspecialchars($item['nama']) ?></div>
                                <div class="cart-item-harga">Rp <?= rupiah($item['harga']) ?></div>
                            </div>
                            <form method="POST" class="cart-item-qty" style="display:flex;align-items:center;gap:4px;">
                                <input type="hidden" name="aksi" value="update_qty">
                                <input type="hidden" name="id_produk" value="<?= $item['id'] ?>">
                                <button type="button" onclick="ubahQty(this, -1)">−</button>
                                <input type="hidden" name="jumlah" class="qty-hidden" value="<?= $item['jumlah'] ?>">
                                <span class="qty-value"><?= $item['jumlah'] ?></span>
                                <button type="button" onclick="ubahQty(this, 1)">+</button>
                            </form>
                            <div class="cart-item-subtotal">Rp <?= rupiah($subtotal) ?></div>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="aksi" value="hapus">
                                <input type="hidden" name="id_produk" value="<?= $item['id'] ?>">
                                <button type="submit" class="cart-item-hapus" title="Hapus">✕</button>
                            </form>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Ringkasan -->
                    <div class="cart-summary">
                        <div class="summary-row total">
                            <span class="label">Total</span>
                            <span class="value">Rp <?= rupiah($cartTotal) ?></span>
                        </div>

                        <!-- Form Bayar -->
                        <form method="POST" onsubmit="return validasiBayar()">
                            <input type="hidden" name="aksi" value="bayar">

                            <div class="bayar-group">
                                <label>💵 Nominal Uang</label>
                                <div class="bayar-input-wrap">
                                    <span class="rp-prefix">Rp</span>
                                    <input type="text" id="nominal_uang" name="nominal_uang"
                                           placeholder="0"
                                           oninput="formatRupiahInput(this); hitungKembalian();"
                                           autocomplete="off">
                                </div>
                            </div>

                            <div id="kembaliContainer">
                                <div class="kembali-row">
                                    <span class="label">Kembalian</span>
                                    <span class="value" id="kembaliText">Rp 0</span>
                                </div>
                            </div>

                            <button type="submit" class="btn-bayar" id="btnBayar">
                                💳 BAYAR
                            </button>
                        </form>
                    </div>
                <?php endif; ?>

            </div>
        </div>
    </div>

</div>

<!-- ============================================================
     JAVASCRIPT
     ============================================================ -->
<script>
// ==========================================================
// TAMBAH KE KERANJANG (via form POST)
// ==========================================================
function tambahKeKeranjang(id, nama, harga) {
    var form = document.createElement('form');
    form.method = 'POST';
    form.action = 'index.php';

    var inputAksi = document.createElement('input');
    inputAksi.type = 'hidden';
    inputAksi.name = 'aksi';
    inputAksi.value = 'tambah';
    form.appendChild(inputAksi);

    var inputId = document.createElement('input');
    inputId.type = 'hidden';
    inputId.name = 'id_produk';
    inputId.value = id;
    form.appendChild(inputId);

    var inputJumlah = document.createElement('input');
    inputJumlah.type = 'hidden';
    inputJumlah.name = 'jumlah';
    inputJumlah.value = 1;
    form.appendChild(inputJumlah);

    document.body.appendChild(form);
    form.submit();
}

// ==========================================================
// UBAH QUANTITY DI KERANJANG
// ==========================================================
function ubahQty(btn, delta) {
    var form = btn.closest('form');
    var hidden = form.querySelector('.qty-hidden');
    var display = form.querySelector('.qty-value');
    var current = parseInt(hidden.value) || 1;
    var baru = current + delta;

    if (baru < 1) {
        // Jika qty < 1, hapus item
        var hapusForm = btn.closest('.cart-item').querySelector('form:last-of-type');
        if (hapusForm) hapusForm.submit();
        return;
    }

    hidden.value = baru;
    display.textContent = baru;
    form.submit();
}

// ==========================================================
// FORMAT RUPIAH INPUT (saat mengetik)
// ==========================================================
function formatRupiahInput(input) {
    var value = input.value.replace(/[^0-9]/g, '');
    if (value === '') {
        input.value = '';
        return;
    }
    var num = parseInt(value);
    if (isNaN(num)) {
        input.value = '';
        return;
    }
    input.value = num.toLocaleString('id-ID');
}

// ==========================================================
// HITUNG KEMBALIAN REAL-TIME
// ==========================================================
function hitungKembalian() {
    var totalElement = document.querySelector('.summary-row.total .value');
    var bayarInput = document.getElementById('nominal_uang');
    var kembaliText = document.getElementById('kembaliText');
    var kembaliContainer = document.getElementById('kembaliContainer');
    var btnBayar = document.getElementById('btnBayar');

    if (!totalElement || !bayarInput || !kembaliText || !kembaliContainer) return;

    // Ambil total dari teks (format: Rp 1.000)
    var totalStr = totalElement.textContent.trim().replace(/[^0-9]/g, '');
    var total = parseInt(totalStr) || 0;

    // Ambil nominal bayar
    var bayarStr = bayarInput.value.replace(/[^0-9]/g, '');
    var bayar = parseInt(bayarStr) || 0;

    var kembali = bayar - total;

    if (bayar > 0 && bayar >= total) {
        kembaliContainer.innerHTML =
            '<div class="kembali-row">' +
                '<span class="label">Kembalian</span>' +
                '<span class="value" id="kembaliText">Rp ' + kembali.toLocaleString('id-ID') + '</span>' +
            '</div>';
        btnBayar.disabled = false;
    } else if (bayar > 0 && bayar < total) {
        var kurang = total - bayar;
        kembaliContainer.innerHTML =
            '<div class="kembali-row minus">' +
                '<span class="label">⚠️ Kurang</span>' +
                '<span class="value" id="kembaliText">Rp ' + kurang.toLocaleString('id-ID') + '</span>' +
            '</div>';
        btnBayar.disabled = true;
    } else {
        kembaliContainer.innerHTML =
            '<div class="kembali-row">' +
                '<span class="label">Kembalian</span>' +
                '<span class="value" id="kembaliText">Rp 0</span>' +
            '</div>';
        btnBayar.disabled = false;
    }
}

// ==========================================================
// VALIDASI SEBELUM BAYAR
// ==========================================================
function validasiBayar() {
    var totalElement = document.querySelector('.summary-row.total .value');
    var bayarInput = document.getElementById('nominal_uang');

    var totalStr = totalElement.textContent.trim().replace(/[^0-9]/g, '');
    var total = parseInt(totalStr) || 0;

    var bayarStr = bayarInput.value.replace(/[^0-9]/g, '');
    var bayar = parseInt(bayarStr) || 0;

    if (total === 0) {
        alert('Keranjang masih kosong!');
        return false;
    }

    if (bayar <= 0) {
        alert('Masukkan nominal uang terlebih dahulu!');
        return false;
    }

    if (bayar < total) {
        alert('Uang tidak mencukupi! Kurang Rp ' + (total - bayar).toLocaleString('id-ID'));
        return false;
    }

    return confirm('Proses pembayaran?\n\nTotal: Rp ' + total.toLocaleString('id-ID') +
                   '\nBayar: Rp ' + bayar.toLocaleString('id-ID') +
                   '\nKembali: Rp ' + (bayar - total).toLocaleString('id-ID'));
}

// ==========================================================
// INISIALISASI
// ==========================================================
document.addEventListener('DOMContentLoaded', function() {
    // Hitung kembalian saat halaman dimuat (jika ada nilai)
    hitungKembalian();
});
</script>

</body>
</html>
