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
| K28 | **Retur tanpa struk** (pelanggan tidak membawa struk/nomor transaksi). PRD hanya mengizinkan retur dari struk asal (F-09). Tanpa struk, harga & keabsahan barang tidak bisa dipastikan sehingga rawan penipuan | (a) tidak boleh (seperti sekarang); (b) boleh dengan PIN supervisor, nilai = harga jual saat ini, refund hanya sebagai tukar barang/deposit (tanpa uang tunai), batas nominal per hari; (c) boleh penuh | (a) dulu; bila diperlukan, (b) | Retur tanpa struk (K-11) |
| K27 | **Enkripsi basis data lokal kasir untuk semua perangkat** (v3.57). PRD lama mewajibkannya hanya untuk HP pribadi & paket Bisnis; agent menyalakannya untuk semua perangkat (satu jalur kode, tidak ada sakelar yang bisa lupa dinyalakan). Biaya: ukuran aplikasi bertambah ±1 MB per arsitektur, buka basis data sedikit lebih lambat | (a) semua perangkat; (b) hanya yang diwajibkan PRD | (a), sudah berjalan; cukup diketahui | Enkripsi DB lokal K-7 |
| K26 | **Format nomor antrian** bila satu outlet punya lebih dari satu perangkat kasir (sekarang urut harian per perangkat, jadi dua kasir bisa sama-sama memanggil "042") | (a) tetap per perangkat; (b) awalan huruf per perangkat (A-042, B-017) diatur di back-office; (c) urut per outlet dari server (wajib online) | (b): tetap jalan offline, tidak tabrakan | Format nomor antrian multi-kasir (v3.52) |
| K25 | **Sektor Apotek & Bengkel**: dijual sekarang atau belum? Keduanya butuh modul baru (apotek: golongan obat, resep, peran Apoteker, racikan; bengkel: kendaraan, work order, mekanik) | (a) bangun sekarang; (b) sembunyikan template sektornya sampai siap | (b) dulu, kerjakan setelah celah kasir K-1…K-22 (`DaftarKekurangan.md` bagian K) | Template sektor Apotek/Bengkel |

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
| K29 | **Pelaporan galat pihak ketiga (K-21, v3.73)**: pakai Sentry SaaS (server di luar negeri → transfer data pribadi lintas negara, UU PDP Pasal 56, perlu DPA & penilaian), Sentry self-hosted di server PAYOU, atau cukup kanal mandiri | Agent: kanal mandiri dulu — aplikasi kasir menyimpan log lokal tersaring PII dan mengirim galat ke `POST /api/pos/v1/perangkat/galat` → log harian `galat-perangkat` (30 hari). Sentry dipasang setelah pemilik memilih & mengisi DSN |

## Sudah diputuskan

| # | Keputusan | Jawaban pemilik | Diterapkan |
|---|---|---|---|
| K30 | Modul Salesman: (a) nomor HP & alamat pelanggan di aplikasi salesman, (b) semua pelanggan vs penugasan, (c) izin salesman berubah setelah offline | "Berikan yang terbaik" (2 Okt 2026) → (a) nomor HP **penuh + alamat** (salesman perlu menghubungi & mendatangi toko; hanya untuk izin `salesman.kunjungan`, DB lokal terenkripsi K-7); (b) semua pelanggan aktif, penugasan menyusul bila diminta; (c) cukup log audit, draf tetap dikonfirmasi back-office | PRD v3.86 |
| K31 | Izin lokasi di semua versi Android & perilaku perangkat berjenis Salesman | "Kerjakan yang terbaik" (2 Okt 2026) → (a) satu aplikasi; izin lokasi hanya saat dipakai, untuk mencatat kunjungan; deklarasi Play Console: lokasi perkiraan & akurat, saat aplikasi dipakai, tujuan "mencatat lokasi kunjungan pelanggan oleh salesman", tidak dibagikan ke pihak ketiga; semua pengguna di perangkat Salesman masuk ruang kerja Salesman (kanvas memakai perangkat Kasir di outlet kanvas) | PRD v3.88 |
