-- ============================================================
-- DATABASE: pos_kasir
-- Aplikasi Point of Sale (Kasir) PHP Native
-- XAMPP phpMyAdmin Compatible
-- ============================================================

CREATE DATABASE IF NOT EXISTS pos_kasir;
USE pos_kasir;

-- ------------------------------------------------------------
-- Tabel produk
-- Menyimpan daftar barang yang dijual
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS produk (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    nama_produk VARCHAR(100) NOT NULL,
    harga INT(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- ------------------------------------------------------------
-- Tabel transaksi
-- Menyimpan data transaksi utama (header)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS transaksi (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    no_faktur VARCHAR(30) NOT NULL UNIQUE,
    tanggal DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    total_bayar INT(11) NOT NULL DEFAULT 0,
    nominal_uang INT(11) NOT NULL DEFAULT 0,
    kembalian INT(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- ------------------------------------------------------------
-- Tabel detail_transaksi
-- Menyimpan rincian barang yang dibeli per transaksi
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS detail_transaksi (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    no_faktur VARCHAR(30) NOT NULL,
    id_produk INT(11) NOT NULL,
    jumlah INT(11) NOT NULL DEFAULT 1,
    subtotal INT(11) NOT NULL DEFAULT 0,
    FOREIGN KEY (no_faktur) REFERENCES transaksi(no_faktur) ON DELETE CASCADE,
    FOREIGN KEY (id_produk) REFERENCES produk(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- ============================================================
-- DATA CONTOH (Opsional)
-- ============================================================

INSERT INTO produk (nama_produk, harga) VALUES
    ('Aqua Gelas', 1000),
    ('Indomie Goreng', 3500),
    ('Kopi Kapal Api', 2000),
    ('Teh Botol Sosro', 5000),
    ('Roti Tawar', 12000),
    ('Susu Ultra 250ml', 6500),
    ('Biskuit Roma Kelapa', 7000),
    ('Sabun Lifebuoy', 4000);
