<!-- DIBUAT OTOMATIS dari PRD.md oleh Alat/PecahPrd.py. JANGAN DIEDIT LANGSUNG: ubah PRD.md lalu jalankan ulang skrip. -->

### F-02 · Setup Organisasi

**Tujuan:** Struktur usaha tergambar di sistem: Tenant → Brand (opsional) → Outlet → Gudang/Lokasi → Perangkat → User.

**Entitas & hubungan:**
```
Tenant 1─* Brand 1─* Outlet 1─* Warehouse(Location)
                         1─* Device (kasir/KDS/self-order kiosk)
                         1─* Table Area 1─* Table   (F&B)
Tenant 1─* User *─* Outlet (penugasan) + Role per outlet
```

**Langkah:**
1. Tambah outlet (nama, kode 3–5 huruf untuk penomoran dokumen, alamat, zona waktu, template sektor, jam operasional).
2. Tambah gudang/lokasi stok per outlet (default: 1 lokasi "Toko"). Bisa tambah "Gudang Belakang", "Dapur", "Bar".
3. Undang user via email/WA dengan role & outlet yang ditugaskan.
4. Kasir mendapat **PIN 6 digit** untuk login cepat di perangkat kasir bersama.
5. **Aktivasi perangkat**: di back-office, admin membuat perangkat (tipe: Kasir / KDS / Gudang / Pelayan) dan mendapat **kode aktivasi 8 karakter + QR** (berlaku 15 menit). Di aplikasi Flutter, pengguna memindai QR atau mengetik kode. Server mengembalikan **device token** (disimpan di secure storage) dan kode perangkat `Perangkat.Kode` (misal `JKT1-K02`) untuk penomoran offline. Satu instalasi aplikasi = satu perangkat terdaftar.

**Aturan Bisnis:**
- BR-02.1 Jumlah outlet, perangkat, dan user dibatasi paket langganan.
- BR-02.2 Kode outlet unik per tenant dan **tidak bisa diubah** setelah ada transaksi.
- BR-02.3 Perangkat yang dicabut (revoke) langsung ditolak saat sinkron, tetapi transaksi offline yang sudah dibuat sebelum revoke **tetap diterima** (dengan flag review).
- BR-02.4 Setiap outlet wajib punya minimal 1 lokasi stok dan 1 akun kas (Kas Outlet).
