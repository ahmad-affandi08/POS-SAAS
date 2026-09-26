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
- BR-P05.3 Kegagalan tes koneksi berkala memicu alert ke Teknis dan banner status di Platform Pengelola. Uji berkala berjalan tiap jam untuk integrasi aktif di lingkungan server itu; alert email dikirim sekali saat status berubah dari berhasil menjadi gagal (bukan tiap jam), ke anggota Teknis aktif (bila tidak ada, ke Super Admin).
- BR-P05.4 Konfigurasi baru atau yang kredensial/pengaturannya berubah berstatus `BelumDiuji` dan tidak bisa diaktifkan sebelum tes koneksi berhasil. Konfigurasi aktif yang diubah langsung nonaktif sampai diuji ulang, sehingga sistem tidak pernah memakai kredensial yang belum terbukti. Perubahan apa pun pada lingkungan Produksi (simpan, aktifkan, nonaktifkan) wajib alasan.
- BR-P05.5 Setiap konfigurasi punya masa rotasi (default 90 hari sejak kredensial terakhir diganti). Lewat masa itu, banner Platform Pengelola mengingatkan Teknis untuk mengganti kunci.
- BR-P05.6 Kredensial hanya didekripsi di server saat dipakai atau diuji; halaman, log audit, dan respons tidak pernah memuatnya. Log audit mencatat nama kolom kredensial yang berubah, bukan nilainya. Mengosongkan kolom kredensial saat menyunting berarti nilai lama dipertahankan.

**Rincian katalog penyedia P-05 (v2.04, atas permintaan pemilik produk):**
- **Pilih penyedia:** setiap jenis integrasi punya satu konfigurasi per lingkungan (Staging/Produksi) dengan satu penyedia terpilih dari katalog `PenyediaIntegrasi`. Formulir konsol menampilkan daftar penyedia, bidang pengaturan & kredensial milik penyedia itu (bidang opsional ditandai), nilai bawaan yang terisi otomatis, dan keterangan penyedia. **Ganti penyedia** membuang kredensial lama (wajib diisi ulang), mengembalikan status ke Belum diuji dan menonaktifkan konfigurasi sampai lolos uji (BR-P05.4); audit mencatat penyedia. Konfigurasi lama (sebelum v2.04) memakai penyedia bawaan jenisnya.
- **Email transaksional (15 penyedia, semua lewat relay SMTP resmi penyedia):** SMTP umum/hosting, Amazon SES (region Jakarta bawaan), Mailgun, Twilio SendGrid, Brevo, Postmark, Resend, Mailjet, Mailtrap Email Sending, Zoho ZeptoMail, Elastic Email, Gmail/Google Workspace (sandi aplikasi), Microsoft 365, Zoho Mail, Hostinger. Satu jalur kirim (mailer SMTP Laravel) dan satu penguji (login SMTP tanpa mengirim).
- **Gerbang pembayaran (QRIS dinamis) — jenis baru:** Midtrans (Core API QRIS, notifikasi SHA512), Xendit (QR Codes API DYNAMIC, token callback), Tripay (closed payment QRIS, HMAC-SHA256), Duitku (API v2, kanal QRIS SP/NQ/GQ/SQ, tanda tangan MD5 sesuai protokol Duitku), iPaymu (direct payment QRIS, HMAC; notifikasi tanpa tanda tangan selalu dikonfirmasi ulang ke iPaymu), DOKU (Checkout — halaman bayar QRIS, tanda tangan HMACSHA256). Mode Sandbox/Produksi per konfigurasi. Uji koneksi memanggil endpoint yang aman (saldo, daftar kanal, atau status pesanan fiktif) tanpa membuat transaksi. Adaptor runtime `App\Domain\Integrasi\GerbangPembayaran` (port `GerbangPembayaran`: `BuatQris`, `CekStatus`, `UraiWebhook`) dibaca dari `config('integrasi.GerbangPembayaran')`.
- **WhatsApp — jenis baru:** **resmi** WhatsApp Cloud API (Meta Graph `/{phone-number-id}/messages`; di luar jendela 24 jam wajib templat yang disetujui Meta, nama templat struk diisi di konsol) dan **tidak resmi** berbasis WhatsApp Web: Fonnte, Wablas (domain server + secret key opsional), StarSender, Watzap. Penyedia tidak resmi ditandai peringatan: murah dan mudah, tetapi nomor bisa diblokir WhatsApp bila mengirim massal; pakai nomor khusus. Adaptor runtime `App\Domain\Integrasi\Whatsapp` (port `PengirimWhatsapp`), nomor Indonesia dirapikan ke format 62.
- **Kerahasiaan:** pesan hasil uji & galat adaptor disaring dari kredensial (BR-P05.6).
- **Catatan regulasi (untuk keputusan pemilik produk):** gerbang pembayaran dikonfigurasi satu akun di tingkat platform. Menerima dana penjualan tenant lewat satu akun merchant platform lalu meneruskannya termasuk kegiatan penyelenggaraan jasa pembayaran yang diatur Bank Indonesia; pilihan yang aman adalah memakai fitur sub-merchant/platform dari penyedia (misal xenPlatform, Midtrans sub-merchant) atau akun gateway milik tenant sendiri. Arsitektur adaptor mendukung keduanya; bagian ini menunggu keputusan.


**Lingkup Fase 0 (PGL-05):** Email (SMTP), CAPTCHA (Cloudflare Turnstile, BR-00.4), penyimpanan objek (S3-compatible). Konfigurasi dengan lingkungan yang sama dengan server (Staging untuk server non-produksi) diterapkan ke aplikasi saat berjalan. Gateway billing (P-08), WhatsApp BSP, FCM, Sentry/uptime, dan daftar gateway tenant ditambahkan bersama flow pemakainya.
