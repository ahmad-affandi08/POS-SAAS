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
- BR-P06.2 Registrasi tenant ditolak jika belum ada S&K dan Kebijakan Privasi berstatus terbit (prasyarat F-00).
