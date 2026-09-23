<!-- DIBUAT OTOMATIS dari PRD.md oleh Alat/PecahPrd.py. JANGAN DIEDIT LANGSUNG: ubah PRD.md lalu jalankan ulang skrip. -->

### P-06 · Legal & Template Komunikasi

**Tujuan:** Dokumen hukum dan semua pesan ke tenant/pelanggan dikelola terpusat, berversi, dan tercatat persetujuannya.
**Aktor:** Konten & Legal.

**Dokumen legal:** Syarat & Ketentuan, Kebijakan Privasi, **Perjanjian Pemrosesan Data** (UU PDP: {{APP}} bertindak sebagai *pemroses* data pelanggan milik tenant, tenant sebagai *pengendali*), SLA per paket.
- Setiap dokumen punya versi dan tanggal berlaku.
- Saat registrasi (F-00), calon tenant menyetujui versi yang berlaku. Versi baru yang **materiil** diumumkan minimal 30 hari sebelum berlaku, lalu Owner diminta menyetujui saat login berikutnya.
- Persetujuan disimpan: tenant, pengguna, versi, waktu, IP.

**Template komunikasi:** email, WhatsApp, push, dan notifikasi in-app per peristiwa (verifikasi, OTP, tagihan, dunning, struk digital, pengingat piutang, booking, approval), dalam Bahasa Indonesia & Inggris, dengan variabel (`{NamaUsaha}`, `{TotalTagihan}`, dll.) dan pratinjau. Template WA menampilkan status persetujuan dari BSP (`MenungguPersetujuan`, `Disetujui`, `Ditolak`).

**Help center:** artikel bantuan, video, dan tautan kontekstual dari halaman aplikasi.

**Aturan Bisnis:**
- BR-P06.1 Versi dokumen legal yang sudah terbit tidak bisa diubah.
- BR-P06.2 Registrasi tenant ditolak jika belum ada S&K dan Kebijakan Privasi berstatus terbit (prasyarat F-00). "Terbit" berarti ada versi terbit yang tanggal berlakunya sudah tiba.
- BR-P06.3 Versi baru hanya bisa terbit dengan `BerlakuMulai` hari ini atau nanti (WIB) dan lebih lambat dari versi terbit sebelumnya. Versi **materiil** yang menggantikan versi sebelumnya wajib `BerlakuMulai` ≥ tanggal terbit + 30 hari. Versi pertama suatu jenis boleh berlaku hari itu juga.
- BR-P06.4 Satu jenis dokumen hanya punya satu draf pada satu waktu, dan draf hanya terlihat oleh penyusun (Konten & Legal, Super Admin). Status tampilan dihitung dari tanggal: *Terjadwal* (terbit, belum berlaku), *Berlaku* (versi terbit terakhir yang tanggalnya sudah tiba), *Digantikan*. Hanya draf yang boleh dihapus.
- BR-P06.5 Pencatatan `PersetujuanDokumenLegal` (tenant, pengguna, versi, waktu, IP), pengumuman versi materiil ke Owner, dan permintaan persetujuan ulang saat login dibangun bersama F-00, karena membutuhkan tabel tenant. P-06 menyediakan kueri versi yang berlaku dan pemeriksaan prasyarat registrasi.
