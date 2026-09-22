<!-- DIBUAT OTOMATIS dari PRD.md oleh Alat/PecahPrd.py. JANGAN DIEDIT LANGSUNG: ubah PRD.md lalu jalankan ulang skrip. -->

### P-07 · Siklus Hidup Tenant

**Tujuan:** Tim melihat kondisi setiap tenant secara utuh dan mengambil tindakan yang tepat dan terkendali.
**Aktor:** Dukungan, Mitra & Penjualan, Keuangan, Super Admin.

**Tampilan 360° tenant:** profil usaha, sektor & versi template, paket & add-on, pemakaian vs batas, outlet & perangkat (platform, versi aplikasi, outbox tertunda), riwayat tagihan & pembayaran, tiket dukungan, riwayat akses dukungan, mitra perujuk, catatan internal, dan **skor kesehatan**.

**State Machine `Langganan.Status`** (sama dengan F-00): `Trial → Aktif → Tertunggak → Ditangguhkan → Berhenti`, cabang `Gratis`. Tenant juga bisa diberi penanda `Uji`, `Demo`, atau `Internal` (dikecualikan dari metrik bisnis & tagihan).

**Tindakan pengelola:**

| Tindakan | Peran | Syarat |
|---|---|---|
| Perpanjang trial | Dukungan, Penjualan | Maks 2 kali, alasan wajib |
| Override batas/fitur sementara | Dukungan, Super Admin | Wajib tanggal berakhir & alasan. Berakhir otomatis |
| Ganti paket manual | Keuangan | Proration otomatis |
| Tangguhkan manual | Super Admin | Alasan wajib (penipuan, penyalahgunaan, permintaan hukum). Owner diberi notifikasi |
| Aktifkan kembali | Keuangan, Super Admin | Tagihan lunas atau keputusan tertulis |
| Catatan internal | Semua peran | Tidak terlihat tenant |
| Permintaan penghapusan data (UU PDP) | Super Admin | Lihat langkah di bawah |

**Skor kesehatan** (dihitung harian): hari aktif 14 hari terakhir, tren transaksi, jumlah fitur utama yang dipakai, perangkat yang lama offline, tiket terbuka, tagihan telat. Hasil: **Sehat / Perlu Perhatian / Berisiko**. Skor memicu tugas otomatis, misalnya tenant baru yang belum bertransaksi 48 jam setelah daftar masuk daftar "hubungi untuk bantuan onboarding".

**Langkah permintaan penghapusan data:** verifikasi identitas Owner → tenant mengunduh export lengkap → masa tunggu 14 hari (bisa dibatalkan) → penghapusan data pribadi & anonimisasi (data transaksi dipertahankan sesuai kewajiban retensi pajak dengan identitas dianonimkan) → bukti penghapusan dikirim ke Owner.

**Aturan Bisnis:**
- BR-P07.1 Pengelola tidak pernah menghapus dokumen transaksi tenant.
- BR-P07.2 Transaksi offline yang dibuat sebelum penangguhan tetap diterima saat sinkron.
- BR-P07.3 Semua tindakan pada tabel di atas tercatat di `LogAuditPengelola` dan terlihat di riwayat tenant.
