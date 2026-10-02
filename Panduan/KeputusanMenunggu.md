# Keputusan yang menunggu pemilik produk

Satu-satunya daftar keputusan yang perlu dijawab pemilik produk. Agent menambah butir di sini setiap kali menemukan
hal yang tidak boleh diputuskan sendiri, lalu tetap melanjutkan pekerjaan lain. Jawab di butir masing-masing; butir
yang sudah dijawab dipindah ke bagian **Sudah diputuskan** beserta versi PRD yang menerapkannya.

Format tiap butir: pertanyaan, pilihan yang tersedia, usulan agent, dan apa yang tertahan sampai dijawab.

## Produk & fitur

| # | Keputusan | Pilihan | Usulan agent | Yang tertahan |
|---|---|---|---|---|
| K1 | **Gabung pelanggan ganda**: saldo deposit, piutang terbuka, poin, saldo sesi, dan riwayat belanja pelanggan yang digabung | (a) semua dipindahkan ke pelanggan tujuan lewat dokumen pemindahan (jurnal deposit seimbang, mutasi poin/sesi bertipe Gabung); (b) gabung ditolak selama salah satu masih punya saldo/piutang terbuka | (b) dulu (aman, tanpa jurnal baru), (a) menyusul | Fitur gabung pelanggan (F-16a) |
| K2 | **Bonus isi deposit** (mis. isi Rp 500.000 dapat Rp 25.000) | ada / tidak | Ada, opsional per tenant; bonus dicatat Dr Beban Promosi / Cr Deposit Pelanggan | F-16d bonus deposit |
| K3 | **Kedaluwarsa saldo deposit** | tidak ada / ada (berapa bulan, dan sisa diakui sebagai pendapatan lain) | Tidak ada (deposit = titipan uang pelanggan; menghanguskan berisiko keluhan konsumen) | F-16d kedaluwarsa deposit |
| K4 | **Gift card / voucher bernilai** dijual ke publik: boleh dipakai di semua outlet? ada kedaluwarsa? bisa diuangkan? | — | Semua outlet, kedaluwarsa opsional per kartu, tidak bisa diuangkan; akuntansi sama dengan deposit (Pendapatan Diterima Dimuka) | F-16d gift card |
| K5 | **Membership berbayar berbasis waktu** (iuran bulanan/tahunan): keuntungannya apa saja (harga tier, diskon, poin berlipat) dan pengakuan pendapatannya | — | Iuran = Pendapatan Diterima Dimuka diakui rata per bulan; keuntungan = tier khusus selama aktif | F-16d membership |
| K6 | **Refund di sisi gerbang pembayaran** (QRIS/VA) saat retur/void: otomatis lewat API gerbang atau manual | otomatis / manual + catat | Manual + catat dulu (tidak semua gerbang punya API refund QRIS) | Sisa F-08 |
| K7 | Sub-merchant gerbang pembayaran di bawah platform | sekarang / nanti | Nanti | — |
| K8 | **Geofence absensi**: butuh aplikasi staf (HP pribadi) atau cukup lokasi perangkat kasir | aplikasi staf baru / lokasi perangkat kasir | Tunda sampai ada aplikasi staf | Geofence absensi F-18 |
| K9 | **PPh 21 & BPJS di rekap gaji** (EMP-07): hitung otomatis (TER PMK 168/2023) atau hanya kolom potongan manual | otomatis / manual | Manual dulu (kolom potongan PPh 21 & BPJS tercatat terpisah), otomatis setelah divalidasi konsultan pajak | Payroll penuh |

## Situs, domain & merek

| # | Keputusan | Usulan agent |
|---|---|---|
| K10 | `robots.txt` & `sitemap.xml` masuk pengecualian konvensi URL? (sekarang `/peta-situs` + `robots.txt` statis) | Ya, keduanya nama standar web |
| K11 | Tautan struk digital/QR tetap di `dashboard.payou.id/s/…` atau pindah ke `payou.id` | Pindah ke `payou.id` (lebih pendek di struk) |
| K12 | Pengalihan `www.payou.id` → `payou.id` | Diatur di hPanel |
| K13 | Nama subdomain konsol `console.` vs `consol.` | Samakan nilai `PENGELOLA_DOMAIN` |
| K14 | Isi pemasaran (teks, foto, testimoni nyata) | Diisi pemilik dari konsol |
| K15 | Lisensi repo (`composer.json` menyebut MIT padahal produk komersial) | Ganti ke proprietary |

## Infrastruktur & di luar kode

| # | Keputusan / tindakan | Catatan |
|---|---|---|
| K16 | Proteksi branch `main` + jadikan default | Pengaturan GitHub pemilik repo |
| K17 | MySQL 8 atau MariaDB 11.8 di produksi | Lihat job CI `backend-mariadb` |
| K18 | Backup DB & berkas + uji restore berkala | — |
| K19 | Kunci unggah Android + Play App Signing | — |
| K20 | Isi `SITUS_KUNCI_SIDIK` di produksi | — |
| K21 | Naikkan CSP dari Report-Only ke penegak; nyalakan `TENANT_TOLAK_ID_BERBEDA=true` | Setelah log bersih beberapa minggu |
| K22 | Validasi ekspor XML Coretax ke aplikasi resmi (impor satu faktur) | Butuh akun Coretax pemilik usaha |
| K23 | **Kampanye pesan CRM-07 (v3.44)**: daftarkan templat pemasaran WhatsApp ke Meta (4 variabel: toko, nama, isi, tautan berhenti) lalu isi `NamaTemplatPromosi` di konsol Integrasi; tinjau batas agent (maks. 5.000 penerima/kampanye, 25 pesan/menit, jam tenang 08.00–21.00, fitur tanpa kunci paket khusus) | Batas & kunci paket bisa diubah bila pemilik menghendaki kampanye jadi fitur berbayar |
| K24 | **Mitra & referral P-12 (v3.50)**: (a) besaran komisi bawaan mitra reseller & referral, (b) perlakuan pajak komisi — PPh 21 bukan pegawai (mitra perorangan) / PPh 23 (mitra badan), tarif & bukti potong, dan apakah PAYOU menanggung atau memotong, (c) bentuk imbalan referral (kredit langganan vs uang tunai) | Agent: persen komisi diisi per mitra di konsol (bawaan 0), potongan pajak dicatat manual per pencairan oleh Keuangan sampai pemilik & konsultan pajak memutuskan; referral sementara dibayar sebagai komisi uang |

## Sudah diputuskan

_(kosong)_
