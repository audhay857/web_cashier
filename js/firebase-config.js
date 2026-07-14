/**
 * ==========================================================
 * FIREBASE CONFIGURATION — Point of Sale
 * ==========================================================
 *
 * 🔥 CARA SETUP FIREBASE (lakukan SEKALI):
 *
 * 1. Buka https://console.firebase.google.com
 * 2. Klik "Add project", beri nama (e.g. "pos-kasir")
 * 3. Matikan Google Analytics (opsional) → Create project
 * 4. Setelah jadi, klik ikon Web (</>) untuk register app
 * 5. Beri nama (e.g. "POS-Kasir-Web") → Register
 * 6. Akan muncul firebaseConfig — COPY dan paste di bawah!
 * 7. Buka menu Build > Firestore Database > Create database
 * 8. Pilih "Start in test mode" → Next → Done
 * 9. Selesai! Aplikasi siap digunakan.
 *
 * ⚠️ Untuk production, perketat security rules Firestore!
 * * ✅ Semua query sudah tanpa ORDER BY — tidak perlu composite index.
 * Firestore auto-index untuk single-field sudah cukup.
 *
 * ========================================================== */

// 🔧 ISI KONFIGURASI FIREBASE ANDA DI BAWAH INI 🔧
// For Firebase JS SDK v7.20.0 and later, measurementId is optional
const firebaseConfig = {
  apiKey: "AIzaSyD01UTjUE82tYj1YVORmkpk3xw_8UkRun8",
  authDomain: "web-cashier-6fc3a.firebaseapp.com",
  databaseURL: "https://web-cashier-6fc3a-default-rtdb.asia-southeast1.firebasedatabase.app",
  projectId: "web-cashier-6fc3a",
  storageBucket: "web-cashier-6fc3a.firebasestorage.app",
  messagingSenderId: "329058928006",
  appId: "1:329058928006:web:94f7a492cb19a2328a9807",
  measurementId: "G-LSHX419VL0"
};
// ==========================================================

// Inisialisasi Firebase
firebase.initializeApp(firebaseConfig);

// Inisialisasi Firestore
const db = firebase.firestore();

// Gunakan Timestamp Firestore
const Timestamp = firebase.firestore.Timestamp;

// ==========================================================
// HELPER FUNCTIONS
// ==========================================================

/**
 * Format angka ke Rupiah (contoh: 15000 → "15.000")
 */
function rupiah(angka) {
    return Number(angka).toLocaleString('id-ID');
}

/**
 * Format tanggal Firestore ke string (dd/mm/YYYY HH:ii)
 */
function formatTanggal(tanggal) {
    if (!tanggal) return '-';
    if (tanggal.toDate) {
        // Firestore Timestamp
        tanggal = tanggal.toDate();
    } else if (typeof tanggal === 'string') {
        tanggal = new Date(tanggal);
    }
    const d = tanggal.getDate().toString().padStart(2, '0');
    const m = (tanggal.getMonth() + 1).toString().padStart(2, '0');
    const Y = tanggal.getFullYear();
    const H = tanggal.getHours().toString().padStart(2, '0');
    const i = tanggal.getMinutes().toString().padStart(2, '0');
    return `${d}/${m}/${Y} ${H}:${i}`;
}

/**
 * Generate nomor faktur: INV-YYYYMMDD-XXXX
 * XXXX = auto increment based on existing faktur hari ini
 */
async function generateNoFaktur() {
    const today = new Date();
    const yyyy = today.getFullYear();
    const mm = (today.getMonth() + 1).toString().padStart(2, '0');
    const dd = today.getDate().toString().padStart(2, '0');
    const dateStr = `${yyyy}${mm}${dd}`;

    // Cari faktur terakhir hari ini (tanpa orderBy biar gaperlu composite index)
    const snapshot = await db.collection('transaksi')
        .where('no_faktur', '>=', `INV-${dateStr}-`)
        .get();

    let maxSeq = 0;
    snapshot.forEach(doc => {
        const faktur = doc.data().no_faktur || '';
        const parts = faktur.split('-');
        if (parts.length === 3) {
            const seq = parseInt(parts[2]);
            if (!isNaN(seq) && seq > maxSeq) maxSeq = seq;
        }
    });

    return `INV-${dateStr}-${(maxSeq + 1).toString().padStart(4, '0')}`;
}
