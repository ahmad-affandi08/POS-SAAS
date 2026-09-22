<!-- DIBUAT OTOMATIS dari PRD.md oleh Alat/PecahPrd.py. JANGAN DIEDIT LANGSUNG: ubah PRD.md lalu jalankan ulang skrip. -->

### P-05 · Konfigurasi Integrasi Platform

**Tujuan:** Semua layanan pihak ketiga milik platform terhubung, aman, dan terpantau.
**Aktor:** Teknis, Super Admin.

| Integrasi | Milik | Keterangan |
|---|---|---|
| Payment gateway untuk **tagihan langganan** | Platform | Akun merchant {{APP}} sendiri (P-08) |
| Payment gateway untuk **transaksi tenant** | Tenant | Kredensial per tenant, diisi tenant di back-office. Pengelola hanya mengatur daftar gateway yang didukung |
| Email transaksional (SMTP/layanan email) | Platform | Verifikasi, tagihan, notifikasi |
| WhatsApp BSP | Platform | Nomor pengirim, **status persetujuan template pesan** dari Meta |
| FCM (push Android & iOS) | Platform | Service account, sertifikat APNs |
| Penyimpanan objek | Platform | File installer, lampiran besar, backup |
| CAPTCHA, Sentry, uptime monitor | Platform | Anti-spam, error tracking, pemantauan |

**Langkah:** input kredensial (langsung terenkripsi) → **tes koneksi** → aktifkan per lingkungan (staging/produksi) → pemantauan berkala (P-11) → **rotasi kunci** terjadwal.

**Aturan Bisnis:**
- BR-P05.1 Kredensial tidak pernah ditampilkan ulang secara utuh (hanya 4 karakter terakhir).
- BR-P05.2 Perubahan kredensial produksi hanya oleh Super Admin/Teknis, wajib alasan, tercatat di audit.
- BR-P05.3 Kegagalan tes koneksi berkala memicu alert ke Teknis dan banner status di Platform Pengelola.
