<?php
/*
 * ==========================================================
 * LAPORAN & RIWAYAT TRANSAKSI
 * Point of Sale - PHP Native
 * ==========================================================
 *
 * Fitur:
 * - Filter tanggal (dari - sampai)
 * - Cari berdasarkan no faktur
 * - Ringkasan: total transaksi, total penjualan, rata-rata
 * - Tabel daftar transaksi
 * - Expand detail barang per transaksi (JavaScript toggle)
 * - Tombol cetak struk langsung
 * ==========================================================
 */

require_once 'koneksi.php';

// --- Ambil parameter filter ---
$tgl_mulai   = isset($_GET['tgl_mulai'])   ? esc($_GET['tgl_mulai'])   : date('Y-m-d');
$tgl_selesai = isset($_GET['tgl_selesai']) ? esc($_GET['tgl_selesai']) : date('Y-m-d');
$cari        = isset($_GET['cari'])          ? esc($_GET['cari'])       : '';

// --- Bangun WHERE clause ---
$where = "WHERE DATE(t.tanggal) BETWEEN '$tgl_mulai' AND '$tgl_selesai'";
if (!empty($cari)) {
    $where .= " AND t.no_faktur LIKE '%$cari%'";
}

// --- Ambil data transaksi ---
$transaksiList = query("
    SELECT t.*,
           (SELECT COUNT(*) FROM detail_transaksi d WHERE d.no_faktur = t.no_faktur) as jumlah_item
    FROM transaksi t
    $where
    ORDER BY t.tanggal DESC
");

// --- Ambil detail barang untuk semua transaksi (effisien, 2 query total) ---
$detailByFaktur = [];
if (!empty($transaksiList)) {
    $fakturs = array_column($transaksiList, 'no_faktur');
    $fakturList = "'" . implode("','", array_map('esc', $fakturs)) . "'";

    $allDetails = query("
        SELECT d.*, p.nama_produk
        FROM detail_transaksi d
        JOIN produk p ON d.id_produk = p.id
        WHERE d.no_faktur IN ($fakturList)
        ORDER BY d.id ASC
    ");

    foreach ($allDetails as $d) {
        $detailByFaktur[$d['no_faktur']][] = $d;
    }
}

// --- Hitung ringkasan ---
$totalTransaksi = count($transaksiList);
$totalPendapatan = 0;
$totalNominalUang = 0;
$totalKembalian = 0;

foreach ($transaksiList as $t) {
    $totalPendapatan += (int) $t['total_bayar'];
    $totalNominalUang += (int) $t['nominal_uang'];
    $totalKembalian += (int) $t['kembalian'];
}

$rataRata = $totalTransaksi > 0 ? round($totalPendapatan / $totalTransaksi) : 0;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Transaksi - Toko Maju Jaya</title>
    <style>
        /* ==========================================================
           RESET & BASE (sama dengan index.php)
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
           HEADER (konsisten dengan index.php)
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

        .header-right {
            display: flex;
            align-items: center;
            gap: 16px;
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
            font-size: 13px;
        }

        /* ==========================================================
           MAIN CONTAINER
           ========================================================== */
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }

        /* ==========================================================
           CARD
           ========================================================== */
        .card {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.06);
            overflow: hidden;
            margin-bottom: 20px;
        }

        .card-header {
            padding: 14px 20px;
            font-weight: 600;
            font-size: 15px;
            border-bottom: 1px solid #f0f0f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #fafbfc;
        }

        .card-body {
            padding: 20px;
        }

        /* ==========================================================
           STATS CARDS
           ========================================================== */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 14px;
            margin-bottom: 20px;
        }

        .stat-card {
            background: #fff;
            border-radius: 12px;
            padding: 18px 20px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.06);
        }

        .stat-card .stat-label {
            font-size: 12px;
            color: #888;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
        }

        .stat-card .stat-value {
            font-size: 22px;
            font-weight: 700;
        }

        .stat-card .stat-sub {
            font-size: 12px;
            color: #999;
            margin-top: 2px;
        }

        .stat-card.stat-total {
            border-left: 4px solid #1a1a2e;
        }

        .stat-card.stat-count {
            border-left: 4px solid #2d89ef;
        }

        .stat-card.stat-rata {
            border-left: 4px solid #00a86b;
        }

        .stat-card.stat-kembali {
            border-left: 4px solid #f39c12;
        }

        /* ==========================================================
           FILTER FORM
           ========================================================== */
        .filter-form {
            display: flex;
            flex-wrap: wrap;
            align-items: flex-end;
            gap: 12px;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .filter-group label {
            font-size: 12px;
            font-weight: 600;
            color: #666;
        }

        .filter-group input {
            padding: 9px 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            outline: none;
            transition: border-color 0.2s;
            min-width: 140px;
        }

        .filter-group input:focus {
            border-color: #1a1a2e;
        }

        .filter-actions {
            display: flex;
            gap: 8px;
            align-items: flex-end;
            padding-bottom: 1px;
        }

        .btn {
            padding: 9px 18px;
            border: none;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn-primary {
            background: #1a1a2e;
            color: #fff;
        }

        .btn-primary:hover {
            background: #16213e;
            transform: translateY(-1px);
            box-shadow: 0 3px 10px rgba(26, 26, 46, 0.25);
        }

        .btn-secondary {
            background: #f0f0f0;
            color: #555;
        }

        .btn-secondary:hover {
            background: #e0e0e0;
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 12px;
        }

        .btn-print {
            background: #2d89ef;
            color: #fff;
        }

        .btn-print:hover {
            background: #1a73d9;
        }

        .btn-detail {
            background: #f0f0f0;
            color: #555;
        }

        .btn-detail:hover {
            background: #e0e0e0;
        }

        .btn-detail.active {
            background: #1a1a2e;
            color: #fff;
        }

        /* ==========================================================
           TABLE
           ========================================================== */
        .table-wrap {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        thead th {
            background: #f8f9fb;
            padding: 10px 12px;
            text-align: left;
            font-weight: 600;
            font-size: 12px;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #eee;
            white-space: nowrap;
        }

        tbody td {
            padding: 10px 12px;
            border-bottom: 1px solid #f0f0f0;
            vertical-align: middle;
        }

        tbody tr:hover {
            background: #fafbfc;
        }

        tbody tr:last-child td {
            border-bottom: none;
        }

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        .faktur-link {
            color: #1a1a2e;
            font-weight: 600;
            text-decoration: none;
        }

        .faktur-link:hover {
            text-decoration: underline;
        }

        .badge-item-count {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #eef2ff;
            color: #1a1a2e;
            font-size: 11px;
            font-weight: 600;
            min-width: 20px;
            height: 20px;
            border-radius: 10px;
            padding: 0 6px;
        }

        /* ==========================================================
           DETAIL BARANG (expandable)
           ========================================================== */
        .detail-row {
            display: none;
            background: #f8f9fb;
        }

        .detail-row.open {
            display: table-row;
        }

        .detail-row td {
            padding: 0;
        }

        .detail-inner {
            padding: 12px 12px 12px 48px;
            border-bottom: 1px solid #e0e0e0;
        }

        .detail-inner table {
            font-size: 12px;
            max-width: 500px;
        }

        .detail-inner th {
            background: transparent;
            padding: 4px 8px;
            font-size: 11px;
            color: #888;
            border-bottom: 1px dashed #ddd;
            text-transform: uppercase;
        }

        .detail-inner td {
            padding: 4px 8px;
            border: none;
            background: transparent !important;
        }

        .detail-inner tr:last-child td {
            border-bottom: none;
        }

        /* ==========================================================
           EMPTY STATE
           ========================================================== */
        .empty-state {
            text-align: center;
            padding: 50px 20px;
            color: #aaa;
        }

        .empty-state .icon {
            font-size: 48px;
            margin-bottom: 10px;
        }

        .empty-state p {
            font-size: 14px;
        }

        /* ==========================================================
           RESPONSIVE
           ========================================================== */
        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                text-align: center;
                gap: 8px;
                padding: 12px 16px;
            }

            .header-right {
                flex-wrap: wrap;
                justify-content: center;
            }

            .container {
                padding: 12px;
            }

            .filter-form {
                flex-direction: column;
                align-items: stretch;
            }

            .filter-group input {
                width: 100%;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .detail-inner {
                padding-left: 12px;
            }
        }

        /* ==========================================================
           ANIMATIONS
           ========================================================== */
        @keyframes slideDown {
            from { opacity: 0; max-height: 0; }
            to   { opacity: 1; max-height: 500px; }
        }

        .detail-row.open .detail-inner {
            animation: slideDown 0.25s ease;
        }

        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(10px); }
            to   { opacity: 1; transform: translateY(0); }
        }

            .stat-card {
            animation: fadeInUp 0.3s ease;
        }
    </style>
</head>
<body>

<!-- ============================================================
     HEADER (konsisten dengan index.php)
     ============================================================ -->
<header class="header">
    <div class="header-left">
        <span class="logo">🏪</span>
        <div>
            <div class="nama-toko">TOKO MAJU JAYA</div>
        </div>
    </div>
    <div class="header-right">
        <a href="index.php">🏠 Kasir</a>
        <a href="laporan.php" class="active">📊 Laporan</a>
        <a href="data_produk.php">📦 Produk</a>
        <span class="tanggal">📅 <?= date('d/m/Y H:i') ?></span>
    </div>
</header>

<!-- ============================================================
     MAIN CONTENT
     ============================================================ -->
<div class="container">

    <!-- ======== RINGKASAN ======== -->
    <div class="stats-grid">
        <div class="stat-card stat-count">
            <div class="stat-label">Jumlah Transaksi</div>
            <div class="stat-value"><?= $totalTransaksi ?></div>
            <div class="stat-sub"><?= date('d/m/Y', strtotime($tgl_mulai)) ?> - <?= date('d/m/Y', strtotime($tgl_selesai)) ?></div>
        </div>
        <div class="stat-card stat-total">
            <div class="stat-label">Total Penjualan</div>
            <div class="stat-value">Rp <?= rupiah($totalPendapatan) ?></div>
            <div class="stat-sub">Pendapatan kotor</div>
        </div>
        <div class="stat-card stat-rata">
            <div class="stat-label">Rata-rata Transaksi</div>
            <div class="stat-value">Rp <?= rupiah($rataRata) ?></div>
            <div class="stat-sub">Per transaksi</div>
        </div>
        <div class="stat-card stat-kembali">
            <div class="stat-label">Total Kembalian</div>
            <div class="stat-value">Rp <?= rupiah($totalKembalian) ?></div>
            <div class="stat-sub">Uang kembali ke pelanggan</div>
        </div>
    </div>

    <!-- ======== FILTER ======== -->
    <div class="card">
        <div class="card-header">
            <span>🔍 Filter Laporan</span>
        </div>
        <div class="card-body">
            <form method="GET" action="laporan.php" class="filter-form">
                <div class="filter-group">
                    <label>Dari Tanggal</label>
                    <input type="date" name="tgl_mulai" value="<?= htmlspecialchars($tgl_mulai) ?>">
                </div>
                <div class="filter-group">
                    <label>Sampai Tanggal</label>
                    <input type="date" name="tgl_selesai" value="<?= htmlspecialchars($tgl_selesai) ?>">
                </div>
                <div class="filter-group">
                    <label>Cari Faktur</label>
                    <input type="text" name="cari" placeholder="No. faktur..."
                           value="<?= htmlspecialchars($cari) ?>">
                </div>
                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary">🔍 Tampilkan</button>
                    <a href="laporan.php" class="btn btn-secondary">↻ Reset</a>
                </div>
            </form>
        </div>
    </div>

    <!-- ======== TABEL TRANSAKSI ======== -->
    <div class="card">
        <div class="card-header">
            <span>📋 Daftar Transaksi</span>
            <span style="font-size:12px;color:#888;font-weight:400;">
                <?= $totalTransaksi ?> transaksi ditemukan
            </span>
        </div>
        <div class="card-body" style="padding:0;">

            <?php if (empty($transaksiList)): ?>
                <div class="empty-state">
                    <div class="icon">📭</div>
                    <p>Tidak ada transaksi pada periode ini.</p>
                    <p style="font-size:12px;color:#ccc;margin-top:4px;">Coba ubah rentang tanggal atau reset filter.</p>
                </div>
            <?php else: ?>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th style="width:32px;"></th>
                                <th>No. Faktur</th>
                                <th>Tanggal</th>
                                <th style="width:40px;">Item</th>
                                <th class="text-right">Total Bayar</th>
                                <th class="text-right">Nominal</th>
                                <th class="text-right">Kembalian</th>
                                <th class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($transaksiList as $i => $t):
                                $no_faktur = htmlspecialchars($t['no_faktur']);
                                $tanggal   = date('d/m/Y H:i', strtotime($t['tanggal']));
                                $totalBayar = (int) $t['total_bayar'];
                                $nominal    = (int) $t['nominal_uang'];
                                $kembalian  = (int) $t['kembalian'];
                                $jmlItem    = (int) $t['jumlah_item'];
                                $detailKey  = 'detail-' . $i;
                            ?>
                            <tr>
                                <td class="text-center">
                                    <button class="btn btn-sm btn-detail"
                                            onclick="toggleDetail('<?= $detailKey ?>', this)"
                                            title="Lihat detail barang">
                                        ▶
                                    </button>
                                </td>
                                <td>
                                    <a href="cetak_struk.php?faktur=<?= urlencode($t['no_faktur']) ?>"
                                       class="faktur-link"
                                       target="_blank">
                                        <?= $no_faktur ?>
                                    </a>
                                </td>
                                <td style="font-size:12px;color:#666;"><?= $tanggal ?></td>
                                <td class="text-center">
                                    <span class="badge-item-count"><?= $jmlItem ?></span>
                                </td>
                                <td class="text-right" style="font-weight:600;">
                                    Rp <?= rupiah($totalBayar) ?>
                                </td>
                                <td class="text-right" style="color:#2e7d32;">
                                    Rp <?= rupiah($nominal) ?>
                                </td>
                                <td class="text-right" style="color:#e67e22;">
                                    Rp <?= rupiah($kembalian) ?>
                                </td>
                                <td class="text-center">
                                    <a href="cetak_struk.php?faktur=<?= urlencode($t['no_faktur']) ?>"
                                       class="btn btn-sm btn-print"
                                       target="_blank">
                                        🖨 Cetak
                                    </a>
                                </td>
                            </tr>

                            <!-- Detail Barang (hidden by default, toggle dengan JS) -->
                            <tr class="detail-row" id="<?= $detailKey ?>">
                                <td colspan="8">
                                    <div class="detail-inner">
                                        <?php if (isset($detailByFaktur[$t['no_faktur']])): ?>
                                            <table>
                                                <thead>
                                                    <tr>
                                                        <th style="width:60%;">Nama Barang</th>
                                                        <th style="width:15%;">Qty</th>
                                                        <th style="width:25%;" class="text-right">Subtotal</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                <?php foreach ($detailByFaktur[$t['no_faktur']] as $d): ?>
                                                    <tr>
                                                        <td><?= htmlspecialchars($d['nama_produk']) ?></td>
                                                        <td><?= (int) $d['jumlah'] ?> x Rp <?= rupiah((int) ($d['subtotal'] / $d['jumlah'])) ?></td>
                                                        <td class="text-right">Rp <?= rupiah((int) $d['subtotal']) ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                                </tbody>
                                                <tfoot>
                                                    <tr>
                                                        <td colspan="2" style="text-align:right;font-weight:600;border-top:1px solid #ddd;padding-top:6px;">
                                                            Total
                                                        </td>
                                                        <td class="text-right" style="font-weight:700;border-top:1px solid #ddd;padding-top:6px;">
                                                            Rp <?= rupiah($totalBayar) ?>
                                                        </td>
                                                    </tr>
                                                </tfoot>
                                            </table>
                                        <?php else: ?>
                                            <em style="color:#999;">Detail tidak tersedia.</em>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

        </div>
    </div>

</div>

<!-- ============================================================
     JAVASCRIPT
     ============================================================ -->
<script>
// ==========================================================
// TOGGLE DETAIL BARANG
// ==========================================================
function toggleDetail(id, btn) {
    var row = document.getElementById(id);
    if (!row) return;

    var isOpen = row.classList.contains('open');

    // Tutup semua detail yang terbuka (opsional: bisa dihapus jika mau multi-open)
    var allRows = document.querySelectorAll('.detail-row.open');
    var allBtns = document.querySelectorAll('.btn-detail.active');
    allRows.forEach(function(r) { r.classList.remove('open'); });
    allBtns.forEach(function(b) { b.classList.remove('active'); b.textContent = '▶'; });

    // Buka yang diklik
    if (!isOpen) {
        row.classList.add('open');
        btn.classList.add('active');
        btn.textContent = '▼';
    }
}
</script>

</body>
</html>
