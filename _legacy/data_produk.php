<?php
/*
 * ==========================================================
 * MANAJEMEN PRODUK
 * Point of Sale - PHP Native
 * ==========================================================
 *
 * Fitur:
 * - Lihat daftar produk dalam tabel
 * - Tambah produk baru
 * - Edit produk (nama & harga)
 * - Hapus produk
 * - Cari / filter produk
 * ==========================================================
 */

require_once 'koneksi.php';

// --- Proses Aksi CRUD ---
$aksi = isset($_POST['aksi']) ? $_POST['aksi'] : '';

if ($aksi === 'tambah') {
    $nama  = esc($_POST['nama_produk'] ?? '');
    $harga = (int) str_replace('.', '', $_POST['harga'] ?? '0');

    if (!empty($nama) && $harga > 0) {
        $sql = "INSERT INTO produk (nama_produk, harga) VALUES ('$nama', $harga)";
        if ($koneksi->query($sql)) {
            $msg = 'success|✓ Produk "' . htmlspecialchars($nama) . '" berhasil ditambahkan!';
        } else {
            $msg = 'error|Gagal menambah produk: ' . $koneksi->error;
        }
    } else {
        $msg = 'error|Nama produk tidak boleh kosong dan harga harus lebih dari 0!';
    }
    redirectWithMsg($msg);

} elseif ($aksi === 'edit') {
    $id    = (int) ($_POST['id'] ?? 0);
    $nama  = esc($_POST['nama_produk'] ?? '');
    $harga = (int) str_replace('.', '', $_POST['harga'] ?? '0');

    if ($id > 0 && !empty($nama) && $harga > 0) {
        $sql = "UPDATE produk SET nama_produk = '$nama', harga = $harga WHERE id = $id";
        if ($koneksi->query($sql)) {
            $msg = 'success|✓ Produk "' . htmlspecialchars($nama) . '" berhasil diperbarui!';
        } else {
            $msg = 'error|Gagal mengupdate produk: ' . $koneksi->error;
        }
    } else {
        $msg = 'error|Data produk tidak valid!';
    }
    redirectWithMsg($msg);

} elseif ($aksi === 'hapus') {
    $id   = (int) ($_POST['id'] ?? 0);
    $nama = esc($_POST['nama_produk'] ?? '');

    if ($id > 0) {
        $sql = "DELETE FROM produk WHERE id = $id";
        if ($koneksi->query($sql)) {
            $msg = 'success|✓ Produk "' . htmlspecialchars($nama) . '" berhasil dihapus!';
        } else {
            $msg = 'error|Gagal menghapus produk: ' . $koneksi->error;
        }
    } else {
        $msg = 'error|ID produk tidak valid!';
    }
    redirectWithMsg($msg);
}

/**
 * Redirect dengan pesan notifikasi via URL parameter
 */
function redirectWithMsg($msg)
{
    header('Location: data_produk.php?msg=' . urlencode($msg));
    exit;
}

// --- Baca pesan notifikasi dari URL ---
$notifType  = '';
$notifText  = '';
if (isset($_GET['msg'])) {
    $parts = explode('|', $_GET['msg'], 2);
    if (count($parts) === 2) {
        $notifType = $parts[0];
        $notifText = htmlspecialchars($parts[1]);
    }
}

// --- Ambil parameter pencarian ---
$keyword = isset($_GET['cari']) ? esc($_GET['cari']) : '';

// --- Ambil daftar produk ---
if (!empty($keyword)) {
    $produkList = query("
        SELECT * FROM produk
        WHERE nama_produk LIKE '%$keyword%'
        ORDER BY id ASC
    ");
} else {
    $produkList = query("SELECT * FROM produk ORDER BY id ASC");
}

// --- Hitung total produk ---
$totalProduk = count($produkList);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Produk - Toko Maju Jaya</title>
    <style>
        /* ==========================================================
           RESET & BASE (konsisten dengan halaman lain)
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
           HEADER (konsisten)
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
            max-width: 1100px;
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
           FORM TAMBAH PRODUK
           ========================================================== */
        .form-produk {
            display: flex;
            flex-wrap: wrap;
            align-items: flex-end;
            gap: 12px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 4px;
            flex: 1;
            min-width: 140px;
        }

        .form-group label {
            font-size: 12px;
            font-weight: 600;
            color: #666;
        }

        .form-group input {
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            outline: none;
            transition: border-color 0.2s;
        }

        .form-group input:focus {
            border-color: #1a1a2e;
        }

        .form-group .harga-wrap {
            display: flex;
            align-items: center;
            border: 1px solid #ddd;
            border-radius: 8px;
            overflow: hidden;
            transition: border-color 0.2s;
        }

        .form-group .harga-wrap:focus-within {
            border-color: #1a1a2e;
        }

        .form-group .harga-wrap .rp-prefix {
            padding: 10px 12px;
            background: #f5f5f5;
            font-weight: 700;
            font-size: 14px;
            color: #555;
            border-right: 1px solid #ddd;
        }

        .form-group .harga-wrap input {
            flex: 1;
            border: none;
            border-radius: 0;
            padding: 10px 12px;
        }

        .form-actions {
            display: flex;
            gap: 8px;
            align-items: flex-end;
            padding-bottom: 1px;
        }

        /* ==========================================================
           BUTTONS
           ========================================================== */
        .btn {
            padding: 10px 20px;
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
            white-space: nowrap;
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

        .btn-success {
            background: #00a86b;
            color: #fff;
        }

        .btn-success:hover {
            background: #008f5a;
            transform: translateY(-1px);
        }

        .btn-danger {
            background: #e74c3c;
            color: #fff;
        }

        .btn-danger:hover {
            background: #c0392b;
        }

        .btn-warning {
            background: #f39c12;
            color: #fff;
        }

        .btn-warning:hover {
            background: #d68910;
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

        /* ==========================================================
           SEARCH
           ========================================================== */
        .search-box {
            display: flex;
            gap: 8px;
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
           TABLE PRODUK
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

        .text-center {
            text-align: center;
        }

        .text-right {
            text-align: right;
        }

        .no-produk {
            text-align: center;
            padding: 30px 16px;
            color: #aaa;
        }

        .no-produk .icon {
            font-size: 40px;
            margin-bottom: 6px;
        }

        .no-produk p {
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
           MODAL FORM EDIT
           ========================================================== */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.4);
            z-index: 1000;
            justify-content: center;
            align-items: center;
            animation: fadeIn 0.2s ease;
        }

        .modal-overlay.open {
            display: flex;
        }

        .modal-box {
            background: #fff;
            border-radius: 14px;
            padding: 24px;
            width: 90%;
            max-width: 420px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            animation: slideUp 0.25s ease;
        }

        .modal-box h3 {
            font-size: 17px;
            margin-bottom: 16px;
            color: #1a1a2e;
        }

        .modal-actions {
            display: flex;
            gap: 10px;
            margin-top: 16px;
            justify-content: flex-end;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to   { opacity: 1; }
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(20px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        /* ==========================================================
           KONFIRMASI HAPUS
           ========================================================== */
        .delete-form {
            display: inline;
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

            .form-produk {
                flex-direction: column;
            }

            .form-group {
                min-width: 100%;
            }
        }
    </style>
</head>
<body>

<!-- ============================================================
     HEADER (konsisten dengan halaman lain)
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
        <a href="laporan.php">📊 Laporan</a>
        <a href="data_produk.php" class="active">📦 Produk</a>
        <span class="tanggal">📅 <?= date('d/m/Y H:i') ?></span>
    </div>
</header>

<!-- ============================================================
     MAIN CONTENT
     ============================================================ -->
<div class="container">

    <!-- Notifikasi -->
    <?php if (!empty($notifText)): ?>
        <div class="notif <?= $notifType === 'success' ? 'success' : 'error' ?>">
            <span><?= $notifType === 'success' ? '✅' : '⚠️' ?></span>
            <?= $notifText ?>
        </div>
    <?php endif; ?>

    <!-- ======== FORM TAMBAH PRODUK ======== -->
    <div class="card">
        <div class="card-header">
            <span>➕ Tambah Produk Baru</span>
        </div>
        <div class="card-body">
            <form method="POST" action="data_produk.php" class="form-produk" onsubmit="return validasiTambah()">
                <input type="hidden" name="aksi" value="tambah">

                <div class="form-group">
                    <label>Nama Produk</label>
                    <input type="text" name="nama_produk" id="inputNama"
                           placeholder="Masukkan nama produk..."
                           required autocomplete="off">
                </div>

                <div class="form-group">
                    <label>Harga (Rp)</label>
                    <div class="harga-wrap">
                        <span class="rp-prefix">Rp</span>
                        <input type="text" name="harga" id="inputHarga"
                               placeholder="0"
                               oninput="formatRupiah(this)"
                               required autocomplete="off">
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-success">➕ Simpan</button>
                    <button type="reset" class="btn btn-secondary">↻ Reset</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ======== TABEL PRODUK ======== -->
    <div class="card">
        <div class="card-header">
            <span>📦 Daftar Produk</span>
            <span style="font-size:12px;color:#888;font-weight:400;">
                <?= $totalProduk ?> produk
            </span>
        </div>
        <div class="card-body">

            <!-- Search -->
            <form method="GET" action="data_produk.php" class="search-box" style="margin-bottom:14px;">
                <input type="text" name="cari" placeholder="Cari produk..."
                       value="<?= htmlspecialchars($keyword) ?>"
                       autocomplete="off">
                <button type="submit">🔍 Cari</button>
                <?php if (!empty($keyword)): ?>
                    <a href="data_produk.php" class="btn btn-secondary">✕ Reset</a>
                <?php endif; ?>
            </form>

            <!-- Table -->
            <?php if (empty($produkList)): ?>
                <div class="no-produk">
                    <div class="icon">📭</div>
                    <p>Tidak ada produk ditemukan.</p>
                </div>
            <?php else: ?>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th style="width:60px;">ID</th>
                                <th>Nama Produk</th>
                                <th class="text-right" style="width:160px;">Harga</th>
                                <th class="text-center" style="width:160px;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($produkList as $p): ?>
                            <tr>
                                <td style="color:#888;font-size:12px;">#<?= (int) $p['id'] ?></td>
                                <td style="font-weight:600;"><?= htmlspecialchars($p['nama_produk']) ?></td>
                                <td class="text-right">Rp <?= rupiah((int) $p['harga']) ?></td>
                                <td class="text-center">
                                    <button class="btn btn-sm btn-warning"
                                            onclick="openEdit(<?= $p['id'] ?>, '<?= htmlspecialchars($p['nama_produk'], ENT_QUOTES) ?>', <?= (int) $p['harga'] ?>)">
                                        ✏️ Edit
                                    </button>
                                    <form method="POST" class="delete-form"
                                          onsubmit="return confirm('Hapus produk ini?')">
                                        <input type="hidden" name="aksi" value="hapus">
                                        <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                        <input type="hidden" name="nama_produk" value="<?= htmlspecialchars($p['nama_produk'], ENT_QUOTES) ?>">
                                        <button type="submit" class="btn btn-sm btn-danger">🗑 Hapus</button>
                                    </form>
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
     MODAL EDIT PRODUK
     ============================================================ -->
<div class="modal-overlay" id="modalEdit">
    <div class="modal-box">
        <h3>✏️ Edit Produk</h3>
        <form method="POST" action="data_produk.php" onsubmit="return validasiEdit()">
            <input type="hidden" name="aksi" value="edit">
            <input type="hidden" name="id" id="editId" value="">

            <div class="form-group" style="margin-bottom:12px;">
                <label>Nama Produk</label>
                <input type="text" name="nama_produk" id="editNama"
                       placeholder="Nama produk..." required>
            </div>

            <div class="form-group">
                <label>Harga (Rp)</label>
                <div class="harga-wrap">
                    <span class="rp-prefix">Rp</span>
                    <input type="text" name="harga" id="editHarga"
                           placeholder="0"
                           oninput="formatRupiah(this)"
                           required>
                </div>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="closeEdit()">Batal</button>
                <button type="submit" class="btn btn-primary">💾 Simpan</button>
            </div>
        </form>
    </div>
</div>

<!-- ============================================================
     JAVASCRIPT
     ============================================================ -->
<script>
// ==========================================================
// FORMAT RUPIAH INPUT
// ==========================================================
function formatRupiah(input) {
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
// MODAL EDIT
// ==========================================================
function openEdit(id, nama, harga) {
    document.getElementById('editId').value = id;
    document.getElementById('editNama').value = nama;
    document.getElementById('editHarga').value = harga.toLocaleString('id-ID');
    document.getElementById('modalEdit').classList.add('open');
}

function closeEdit() {
    document.getElementById('modalEdit').classList.remove('open');
}

// Tutup modal jika klik di luar kotak modal
document.getElementById('modalEdit').addEventListener('click', function(e) {
    if (e.target === this) closeEdit();
});

// Tutup modal dengan tombol Escape
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeEdit();
});

// ==========================================================
// VALIDASI FORM
// ==========================================================
function validasiTambah() {
    var nama = document.getElementById('inputNama').value.trim();
    var harga = document.getElementById('inputHarga').value.replace(/[^0-9]/g, '');
    if (nama === '') {
        alert('Nama produk tidak boleh kosong!');
        return false;
    }
    if (harga === '' || parseInt(harga) <= 0) {
        alert('Harga harus diisi dengan benar!');
        return false;
    }
    return true;
}

function validasiEdit() {
    var nama = document.getElementById('editNama').value.trim();
    var harga = document.getElementById('editHarga').value.replace(/[^0-9]/g, '');
    if (nama === '') {
        alert('Nama produk tidak boleh kosong!');
        return false;
    }
    if (harga === '' || parseInt(harga) <= 0) {
        alert('Harga harus diisi dengan benar!');
        return false;
    }
    return true;
}
</script>

</body>
</html>
