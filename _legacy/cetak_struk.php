<?php
/*
 * ==========================================================
 * CETAK STRUK BELANJA
 * Point of Sale - PHP Native
 * ==========================================================
 *
 * Dipanggil dengan: cetak_struk.php?faktur=NO_FAKTUR
 * Contoh: cetak_struk.php?faktur=INV-2024-0001
 *
 * Printer: Epson TM-U220 (kertas 76mm / 3 inch)
 * Metode : window.print() bawaan browser
 * Font   : Courier New (monospace)
 * ==========================================================
 */

require_once 'koneksi.php';

// --- Ambil parameter faktur ---
$no_faktur = isset($_GET['faktur']) ? esc($_GET['faktur']) : '';

if (empty($no_faktur)) {
    die('<h2>Parameter faktur tidak ditemukan.</h2>');
}

// --- Ambil data transaksi ---
$transaksi = query_row("
    SELECT * FROM transaksi
    WHERE no_faktur = '$no_faktur'
");

if (!$transaksi) {
    die("<h2>Transaksi dengan faktur '$no_faktur' tidak ditemukan.</h2>");
}

// --- Ambil detail barang ---
$detail = query("
    SELECT
        d.id_produk,
        d.jumlah,
        d.subtotal,
        p.nama_produk,
        p.harga
    FROM detail_transaksi d
    JOIN produk p ON d.id_produk = p.id
    WHERE d.no_faktur = '$no_faktur'
    ORDER BY d.id ASC
");

// --- Format tanggal ---
$tanggal = date('d/m/Y H:i:s', strtotime($transaksi['tanggal']));

// --- Konfigurasi lebar karakter (monospace) ---
// Kertas 76mm dengan font 10pt Courier New ~ 40 karakter per baris
define('LEBAR', 40);

/**
 * Membuat garis putus-putus selebar LEBAR karakter
 */
function garis($char = '-')
{
    return str_repeat($char, LEBAR);
}

/**
 * Ratakan teks ke kiri dalam batas LEBAR karakter
 */
function kiri($teks, $length = LEBAR)
{
    return str_pad($teks, $length, ' ', STR_PAD_RIGHT);
}

/**
 * Ratakan teks ke kanan dalam batas LEBAR karakter
 */
function kanan($teks, $length = LEBAR)
{
    return str_pad($teks, $length, ' ', STR_PAD_LEFT);
}

/**
 * Teks dengan posisi kiri dan kanan (sejajar)
 * Contoh: Nama Barang            Rp 5.000
 */
function kiri_kanan($kiri, $kanan, $length = LEBAR)
{
    $isi = $kiri . $kanan;
    if (strlen($isi) > $length) {
        // Potong teks kiri jika terlalu panjang
        $maxKiri = $length - strlen($kanan) - 1;
        $kiri = substr($kiri, 0, $maxKiri);
    }
    return str_pad($kiri, $length - strlen($kanan), ' ', STR_PAD_RIGHT) . $kanan;
}

/**
 * Format rupiah tanpa desimal
 */
function rp($angka)
{
    return 'Rp ' . number_format($angka, 0, ',', '.');
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Struk Belanja - <?= htmlspecialchars($no_faktur) ?></title>
    <style>
        /* ==========================================================
           CSS KHUSUS EPSON TM-U220 (Kertas 76mm)
           ========================================================== */

        /* Reset total */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Courier New', 'Courier', monospace;
            font-size: 10pt;
            line-height: 1.3;
            color: #000;
            background: #fff;
            width: 76mm;
            margin: 0 auto;
            padding: 0;
        }

        .struk {
            width: 72mm;  /* Lebar cetak efektif ~72mm dari 76mm */
            margin: 0 auto;
            padding: 2mm 0;
        }

        /* --- Kop / Header Toko --- */
        .header-toko {
            text-align: center;
            margin-bottom: 4px;
        }

        .header-toko .nama-toko {
            font-size: 14pt;
            font-weight: bold;
            text-transform: uppercase;
        }

        .header-toko .alamat {
            font-size: 9pt;
        }

        .header-toko .telp {
            font-size: 9pt;
        }

        /* --- Garis Pemisah --- */
        .garis {
            font-size: 10pt;
            letter-spacing: 0;
            white-space: nowrap;
            margin: 2px 0;
        }

        /* --- Info Faktur --- */
        .info-faktur {
            font-size: 10pt;
            margin: 3px 0;
        }

        .info-faktur table {
            width: 100%;
            font-family: 'Courier New', monospace;
            font-size: 10pt;
            border-collapse: collapse;
        }

        .info-faktur td {
            padding: 0;
            vertical-align: top;
        }

        .info-faktur .label {
            width: 12mm;
        }

        /* --- Tabel Belanja --- */
        .tabel-belanja {
            width: 100%;
            font-family: 'Courier New', monospace;
            font-size: 10pt;
            border-collapse: collapse;
            margin: 2px 0;
        }

        .tabel-belanja th {
            text-align: left;
            font-weight: bold;
            padding: 1px 0;
            border-bottom: 1px dashed #000;
        }

        .tabel-belanja td {
            padding: 1px 0;
            vertical-align: top;
        }

        .tabel-belanja .col-nama {
            text-align: left;
        }

        .tabel-belanja .col-qty {
            text-align: center;
            width: 12mm;
        }

        .tabel-belanja .col-harga {
            text-align: right;
            width: 22mm;
        }

        .tabel-belanja .col-subtotal {
            text-align: right;
            width: 22mm;
        }

        /* --- Ringkasan Pembayaran --- */
        .ringkasan {
            width: 100%;
            font-family: 'Courier New', monospace;
            font-size: 10pt;
            border-collapse: collapse;
            margin: 2px 0;
        }

        .ringkasan td {
            padding: 1px 0;
            vertical-align: top;
        }

        .ringkasan .label {
            text-align: left;
            width: 50mm;
        }

        .ringkasan .nilai {
            text-align: right;
        }

        .ringkasan .total-bayar {
            font-weight: bold;
            font-size: 12pt;
        }

        .ringkasan .total-bayar .nilai {
            font-size: 12pt;
        }

        /* --- Footer --- */
        .footer {
            text-align: center;
            margin-top: 6px;
        }

        .footer .terima-kasih {
            font-size: 11pt;
            font-weight: bold;
            margin-bottom: 2px;
        }

        .footer .pesan {
            font-size: 9pt;
        }

        /* --- Status & Info Cetak --- */
        .info-cetak {
            font-family: sans-serif;
            font-size: 9pt;
            color: #666;
            text-align: center;
            padding: 10px;
        }

        @media print {
            @page {
                size: 76mm auto;   /* Lebar 76mm, tinggi menyesuaikan */
                margin: 0;          /* Margin nol agar pas */
            }

            * {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            body {
                width: 76mm;
                margin: 0;
                padding: 0;
                background: #fff;
            }

            .struk {
                width: 72mm;
                margin: 0 auto;
                padding: 0;
            }

            .info-cetak {
                display: none !important;
            }

            /* Pastikan tidak ada page break di tengah */
            .struk {
                page-break-after: avoid;
                page-break-inside: avoid;
            }
        }

        @media screen {
            body {
                background: #f5f5f5;
                padding: 20px 0;
            }

            .struk {
                background: #fff;
                padding: 4mm 3mm;
                box-shadow: 0 2px 12px rgba(0, 0, 0, 0.15);
                border-radius: 2px;
            }
        }
    </style>
</head>
<body>

<div class="struk">

    <!-- ======== HEADER TOKO ======== -->
    <div class="header-toko">
        <div class="nama-toko">TOKO MAJU JAYA</div>
        <div class="alamat">Jl. Merdeka No. 123, Jakarta</div>
        <div class="telp">Telp: (021) 1234-5678</div>
    </div>

    <!-- ======== GARIS ======== -->
    <div class="garis"><?= garis('=') ?></div>

    <!-- ======== INFO FAKTUR ======== -->
    <div class="info-faktur">
        <table>
            <tr>
                <td class="label">Faktur</td>
                <td>: <?= htmlspecialchars($no_faktur) ?></td>
            </tr>
            <tr>
                <td class="label">Tanggal</td>
                <td>: <?= htmlspecialchars($tanggal) ?></td>
            </tr>
        </table>
    </div>

    <!-- Kasir info (statis, bisa disesuaikan) -->
    <div style="font-size:9pt;">Kasir : Admin</div>

    <!-- ======== GARIS ======== -->
    <div class="garis"><?= garis('-') ?></div>

    <!-- ======== TABEL BARANG ======== -->
    <table class="tabel-belanja">
        <thead>
            <tr>
                <th class="col-nama">Nama Barang</th>
                <th class="col-qty">Qty</th>
                <th class="col-harga">Harga</th>
                <th class="col-subtotal">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($detail)): ?>
                <tr>
                    <td colspan="4" style="text-align:center; padding:6px 0;">
                        <em>Tidak ada barang</em>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($detail as $item): ?>
                <?php
                    // Potong nama barang jika terlalu panjang
                    $nama = $item['nama_produk'];
                    $qty  = (int) $item['jumlah'];
                    $harga = rp($item['harga']);
                    $subtotal = rp($item['subtotal']);
                ?>
                <tr>
                    <td class="col-nama"><?= htmlspecialchars($nama) ?></td>
                    <td class="col-qty"><?= $qty ?></td>
                    <td class="col-harga"><?= $harga ?></td>
                    <td class="col-subtotal"><?= $subtotal ?></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- ======== GARIS ======== -->
    <div class="garis"><?= garis('-') ?></div>

    <!-- ======== RINGKASAN PEMBAYARAN ======== -->
    <table class="ringkasan">
        <tr>
            <td class="label">Total Belanja</td>
            <td class="nilai"><?= rp($transaksi['total_bayar']) ?></td>
        </tr>
        <tr>
            <td class="label">Tunai</td>
            <td class="nilai"><?= rp($transaksi['nominal_uang']) ?></td>
        </tr>
        <tr>
            <td class="label">Kembalian</td>
            <td class="nilai"><?= rp($transaksi['kembalian']) ?></td>
        </tr>
    </table>

    <!-- ======== GARIS ======== -->
    <div class="garis"><?= garis('=') ?></div>

    <!-- ======== FOOTER ======== -->
    <div class="footer">
        <div class="terima-kasih">TERIMA KASIH</div>
        <div class="pesan">Selamat Belanja Kembali</div>
        <div class="pesan">Barang yang sudah dibeli tidak dapat</div>
        <div class="pesan">ditukar atau dikembalikan</div>
    </div>

</div>

<!-- ======== PESAN CETAK (hanya tampil di layar) ======== -->
<div class="info-cetak">
    <p style="margin-top: 16px; font-size: 10pt; color: #333;">
        <strong>⏳ Mencetak struk...</strong>
    </p>
    <p>Jika dialog cetak tidak muncul, tekan <strong>Ctrl + P</strong></p>
    <p style="margin-top: 12px;">
        <button onclick="window.print()"
                style="padding: 8px 24px; font-size: 11pt;
                       background: #2d89ef; color: #fff;
                       border: none; border-radius: 4px;
                       cursor: pointer;">
            🖨 Cetak Sekarang
        </button>
    </p>
    <p style="margin-top: 8px;">
        <a href="javascript:void(0)" onclick="window.close()"
           style="color: #888; font-size: 9pt;">Tutup halaman</a>
    </p>
</div>

<script>
    // ==========================================================
    // Auto Print — window.print() begitu halaman selesai dimuat
    // ==========================================================

    window.addEventListener('load', function() {
        // Beri jeda 500ms agar semua konten termuat sempurna
        setTimeout(function() {
            window.print();
        }, 500);
    });

    // Optional: Setelah dialog print ditutup (di-cancel atau selesai),
    // fokus kembali ke halaman sebelumnya
    window.addEventListener('afterprint', function() {
        // Bisa diarahkan ke halaman tertentu
        // window.location.href = 'index.php';
    });
</script>

</body>
</html>
