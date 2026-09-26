<?php

declare(strict_types=1);

namespace App\Domain\Situs\Layanan;

/**
 * Isi awal situs pemasaran (D-21), dipakai sampai konsol menerbitkan halamannya sendiri dan sebagai draf awal saat
 * halaman dibuat di konsol. Teks menerangkan fitur yang benar-benar ada (tanpa testimoni atau angka karangan).
 */
final class KontenSitusBawaan
{
    /**
     * @return array<string, array{Judul: string, JudulSeo: string, DeskripsiSeo: string, Bagian: list<array<string, mixed>>}>
     */
    public static function AmbilHalaman(): array
    {
        $ctaDaftar = [
            'Jenis' => 'Cta',
            'Judul' => 'Siap merapikan kasir dan pembukuan usaha Anda?',
            'Teks' => 'Daftar gratis dalam beberapa menit. Tidak perlu kartu kredit.',
            'TombolUtama' => ['Label' => 'Coba gratis sekarang', 'Tautan' => '@daftar'],
            'TombolKedua' => ['Label' => 'Tanya lewat WhatsApp', 'Tautan' => '@whatsapp'],
        ];

        return [
            'beranda' => [
                'Judul' => 'Beranda',
                'JudulSeo' => 'PAYOU · Aplikasi Kasir yang Tetap Jalan Walau Offline',
                'DeskripsiSeo' => 'Aplikasi kasir (POS) untuk kafe, resto, toko, salon, dan laundry: tetap jalan saat offline, stok & HPP otomatis, pajak PPN/PBJT, promo, loyalti, dan laporan keuangan.',
                'Bagian' => [
                    [
                        'Jenis' => 'Hero',
                        'Label' => 'Aplikasi kasir & pembukuan',
                        'Judul' => 'Kasir yang tetap jalan walau internet putus',
                        'Subjudul' => "Catat penjualan, kelola stok, hitung pajak, dan baca laporan keuangan dari satu aplikasi.\nSemua tersinkron otomatis saat kembali online.",
                        'TombolUtama' => ['Label' => 'Coba gratis', 'Tautan' => '@daftar'],
                        'TombolKedua' => ['Label' => 'Lihat fitur', 'Tautan' => '/fitur'],
                        'Catatan' => 'Gratis selamanya untuk usaha mikro. Tanpa kartu kredit.',
                    ],
                    [
                        'Jenis' => 'Keunggulan',
                        'Judul' => 'Semua yang dibutuhkan kasir dan pemilik usaha',
                        'Subjudul' => 'Dibuat untuk cara berjualan di Indonesia.',
                        'Kolom' => '3',
                        'Item' => [
                            ['Ikon' => 'WifiOff', 'Judul' => 'Tetap jalan saat offline', 'Teks' => 'Transaksi, struk, dan laci kas tetap berjalan. Data terkirim otomatis saat internet kembali.'],
                            ['Ikon' => 'Boxes', 'Judul' => 'Stok & HPP otomatis', 'Teks' => 'Stok berkurang setiap penjualan, HPP rata-rata dihitung dari pembelian, opname dan transfer antar-gudang.'],
                            ['Ikon' => 'Percent', 'Judul' => 'Pajak sesuai aturan', 'Teks' => 'PPN dan PBJT dihitung dari tarif berlaku, termasuk harga termasuk pajak dan biaya layanan.'],
                            ['Ikon' => 'Tag', 'Judul' => 'Promo & voucher', 'Teks' => 'Diskon otomatis, beli X gratis Y, happy hour, voucher dan kode promo, poin berlipat.'],
                            ['Ikon' => 'Users', 'Judul' => 'Pelanggan & loyalti', 'Teks' => 'Tier member, poin, deposit, piutang dengan limit kredit, dan struk digital lewat WhatsApp.'],
                            ['Ikon' => 'ChartColumn', 'Judul' => 'Laporan & pembukuan', 'Teks' => 'Jurnal otomatis, laba rugi, neraca, arus kas, dan tutup buku bulanan tanpa input ulang.'],
                        ],
                    ],
                    [
                        'Jenis' => 'Sektor',
                        'Judul' => 'Cocok untuk berbagai jenis usaha',
                        'Item' => [
                            ['Ikon' => 'Coffee', 'Nama' => 'Kafe & Resto', 'Teks' => 'Denah meja, pesanan ke dapur, self-order QR, pisah tagihan.', 'Tautan' => '/solusi/kafe-resto'],
                            ['Ikon' => 'Store', 'Nama' => 'Toko & Retail', 'Teks' => 'Pemindai barcode, varian, harga grosir, stok multi-gudang.', 'Tautan' => '/solusi/toko-retail'],
                            ['Ikon' => 'Scissors', 'Nama' => 'Salon & Jasa', 'Teks' => 'Komisi per staf, deposit pelanggan, jadwal & absensi karyawan.', 'Tautan' => '/solusi/jasa'],
                            ['Ikon' => 'WashingMachine', 'Nama' => 'Laundry', 'Teks' => 'Pre-order dengan uang muka, pelanggan & piutang, struk digital.', 'Tautan' => '/solusi/jasa'],
                        ],
                    ],
                    [
                        'Jenis' => 'GambarTeks',
                        'Label' => 'Satu sistem',
                        'Judul' => 'Kasir, dapur, dan pemilik selalu melihat data yang sama',
                        'Teks' => 'Aplikasi kasir berjalan di Android, iPad, dan Windows. Pemilik memantau penjualan dari aplikasi Pemilik atau back-office di browser.',
                        'Poin' => [
                            ['Teks' => 'Printer struk Bluetooth, LAN, USB, dan printer bawaan Sunmi/iMin'],
                            ['Teks' => 'Layar dapur (KDS) dan tiket per stasiun'],
                            ['Teks' => 'Buka & tutup shift dengan hitung kas'],
                            ['Teks' => 'Hak akses per peran dan PIN penyetuju'],
                        ],
                        'PosisiGambar' => 'Kanan',
                        'Tombol' => ['Label' => 'Lihat semua fitur', 'Tautan' => '/fitur'],
                    ],
                    [
                        'Jenis' => 'Harga',
                        'Judul' => 'Harga jelas, mulai dari gratis',
                        'Subjudul' => 'Pilih paket sesuai ukuran usaha. Naik atau turun paket kapan saja.',
                        'TampilkanTahunan' => true,
                        'TeksTombol' => 'Mulai',
                    ],
                    [
                        'Jenis' => 'Faq',
                        'Judul' => 'Pertanyaan yang sering diajukan',
                        'Item' => [
                            ['Pertanyaan' => 'Apakah PAYOU bisa dipakai tanpa internet?', 'Jawaban' => 'Bisa. Aplikasi kasir menyimpan transaksi di perangkat dan mengirimnya otomatis saat internet kembali.'],
                            ['Pertanyaan' => 'Perangkat apa yang didukung?', 'Jawaban' => 'Android, iPhone/iPad, dan Windows. Daftar printer dan perangkat yang sudah diuji ada di halaman Perangkat kompatibel.'],
                            ['Pertanyaan' => 'Apakah data usaha saya aman?', 'Jawaban' => 'Data tersimpan di server dengan cadangan rutin, akses dibatasi per peran, dan kami bertindak sebagai pemroses data sesuai UU Perlindungan Data Pribadi.'],
                            ['Pertanyaan' => 'Bagaimana cara berlangganan?', 'Jawaban' => 'Daftar gratis, lalu pilih paket dari menu Langganan di back-office. Pembayaran lewat transfer bank.'],
                        ],
                    ],
                    $ctaDaftar,
                ],
            ],
            'fitur' => [
                'Judul' => 'Fitur',
                'JudulSeo' => 'Fitur Aplikasi Kasir PAYOU',
                'DeskripsiSeo' => 'Fitur lengkap PAYOU: kasir offline, stok & HPP, pajak, promo, loyalti, deposit, piutang, karyawan & komisi, dapur, self-order QR, dan laporan keuangan.',
                'Bagian' => [
                    [
                        'Jenis' => 'Hero',
                        'Judul' => 'Fitur lengkap tanpa ribet',
                        'Subjudul' => 'Mulai dari kasir, lalu aktifkan fitur lain saat usaha berkembang.',
                        'TombolUtama' => ['Label' => 'Coba gratis', 'Tautan' => '@daftar'],
                    ],
                    [
                        'Jenis' => 'Keunggulan',
                        'Judul' => 'Penjualan',
                        'Kolom' => '3',
                        'Item' => [
                            ['Ikon' => 'Receipt', 'Judul' => 'Kasir cepat', 'Teks' => 'Cari produk, pemindai barcode, varian & pilihan tambahan, diskon dengan persetujuan PIN.'],
                            ['Ikon' => 'CreditCard', 'Judul' => 'Semua metode bayar', 'Teks' => 'Tunai, QRIS statis & dinamis, EDC, transfer, e-wallet, tempo, deposit, dan bayar terpisah.'],
                            ['Ikon' => 'RefreshCw', 'Judul' => 'Void & retur', 'Teks' => 'Pembatalan dan retur tercatat rapi dengan jurnal pembalik, stok kembali otomatis.'],
                            ['Ikon' => 'MessageCircle', 'Judul' => 'Struk digital', 'Teks' => 'Kirim struk lewat WhatsApp atau email, dan QR struk di kertas.'],
                            ['Ikon' => 'ChefHat', 'Judul' => 'Meja & dapur', 'Teks' => 'Denah meja, pesanan terbuka, tiket dapur per stasiun, layar dapur (KDS).'],
                            ['Ikon' => 'QrCode', 'Judul' => 'Self-order QR', 'Teks' => 'Tamu memesan dari HP lewat QR di meja, pesanan masuk ke kasir.'],
                        ],
                    ],
                    [
                        'Jenis' => 'Keunggulan',
                        'Judul' => 'Stok & pembelian',
                        'Kolom' => '3',
                        'Item' => [
                            ['Ikon' => 'Package', 'Judul' => 'Stok multi-gudang', 'Teks' => 'Stok per gudang, transfer, opname, dan penyesuaian dengan riwayat lengkap.'],
                            ['Ikon' => 'Truck', 'Judul' => 'Pembelian & hutang', 'Teks' => 'Pesanan pembelian, penerimaan barang, faktur pemasok, pembayaran, dan retur.'],
                            ['Ikon' => 'Calculator', 'Judul' => 'HPP otomatis', 'Teks' => 'HPP rata-rata bergerak dihitung dari setiap penerimaan barang.'],
                        ],
                    ],
                    [
                        'Jenis' => 'Keunggulan',
                        'Judul' => 'Pelanggan, karyawan & keuangan',
                        'Kolom' => '3',
                        'Item' => [
                            ['Ikon' => 'Gift', 'Judul' => 'Loyalti & promo', 'Teks' => 'Tier, poin, voucher, promo otomatis, promo ulang tahun, dan promo metode bayar.'],
                            ['Ikon' => 'Wallet', 'Judul' => 'Deposit & piutang', 'Teks' => 'Saldo deposit pelanggan, penjualan tempo dengan limit kredit, pelunasan.'],
                            ['Ikon' => 'Clock', 'Judul' => 'Karyawan', 'Teks' => 'Jadwal, absensi dengan swafoto, komisi per staf, kasbon, dan rekap gaji.'],
                            ['Ikon' => 'Landmark', 'Judul' => 'Pembukuan otomatis', 'Teks' => 'Jurnal dari setiap transaksi, laba rugi, neraca, arus kas, dan tutup buku.'],
                            ['Ikon' => 'FileText', 'Judul' => 'Laporan', 'Teks' => 'Penjualan, pajak, stok, promo, dan shift, bisa diekspor.'],
                            ['Ikon' => 'Smartphone', 'Judul' => 'Aplikasi pemilik', 'Teks' => 'Pantau penjualan dan shift semua outlet dari HP.'],
                        ],
                    ],
                    $ctaDaftar,
                ],
            ],
            'harga' => [
                'Judul' => 'Harga',
                'JudulSeo' => 'Harga Paket Aplikasi Kasir PAYOU',
                'DeskripsiSeo' => 'Paket PAYOU mulai dari gratis. Bandingkan batas outlet, perangkat, pengguna, dan fitur tiap paket.',
                'Bagian' => [
                    [
                        'Jenis' => 'Harga',
                        'Judul' => 'Pilih paket yang pas',
                        'Subjudul' => 'Harga per bulan untuk satu usaha. Bayar tahunan lebih hemat.',
                        'TampilkanTahunan' => true,
                        'TeksTombol' => 'Mulai',
                        'CatatanKaki' => 'Harga belum termasuk add-on (outlet atau perangkat tambahan, kuota WhatsApp, self-order QR).',
                    ],
                    [
                        'Jenis' => 'Faq',
                        'Judul' => 'Tentang langganan',
                        'Item' => [
                            ['Pertanyaan' => 'Apakah ada masa uji coba?', 'Jawaban' => 'Ada. Paket berbayar bisa dicoba gratis selama masa trial, lalu otomatis turun ke paket Gratis bila tidak diperpanjang.'],
                            ['Pertanyaan' => 'Bagaimana cara membayar?', 'Jawaban' => 'Buat tagihan dari menu Langganan di back-office, transfer ke rekening yang tertera, lalu unggah bukti transfer.'],
                            ['Pertanyaan' => 'Bisakah pindah paket?', 'Jawaban' => 'Bisa naik atau turun paket kapan saja dari menu Langganan.'],
                        ],
                    ],
                    $ctaDaftar,
                ],
            ],
            'solusi/kafe-resto' => [
                'Judul' => 'Kafe & Resto',
                'JudulSeo' => 'Aplikasi Kasir Kafe & Resto · PAYOU',
                'DeskripsiSeo' => 'Aplikasi kasir kafe dan resto: denah meja, pesanan ke dapur, self-order QR, pisah tagihan, pajak PBJT dan biaya layanan.',
                'Bagian' => [
                    [
                        'Jenis' => 'Hero',
                        'Label' => 'Kafe & Resto',
                        'Judul' => 'Dari meja ke dapur tanpa kertas tercecer',
                        'Subjudul' => 'Pesanan dicatat pelayan, langsung muncul di dapur, dibayar di kasir dengan pajak dan biaya layanan yang benar.',
                        'TombolUtama' => ['Label' => 'Coba gratis', 'Tautan' => '@daftar'],
                    ],
                    [
                        'Jenis' => 'Keunggulan',
                        'Kolom' => '3',
                        'Item' => [
                            ['Ikon' => 'UtensilsCrossed', 'Judul' => 'Denah meja', 'Teks' => 'Status meja, pindah & gabung meja, pisah tagihan.'],
                            ['Ikon' => 'ChefHat', 'Judul' => 'Dapur', 'Teks' => 'Tiket per stasiun di printer atau layar dapur (KDS).'],
                            ['Ikon' => 'QrCode', 'Judul' => 'Self-order QR', 'Teks' => 'Tamu memesan sendiri dari HP dengan perkiraan total.'],
                            ['Ikon' => 'Smartphone', 'Judul' => 'Mode pelayan', 'Teks' => 'Pelayan mencatat pesanan dari HP tanpa membuka shift.'],
                            ['Ikon' => 'Percent', 'Judul' => 'PBJT & biaya layanan', 'Teks' => 'Dihitung otomatis dari tarif yang berlaku di kota Anda.'],
                            ['Ikon' => 'Boxes', 'Judul' => 'Resep & bahan baku', 'Teks' => 'Stok bahan berkurang sesuai komposisi menu.'],
                        ],
                    ],
                    $ctaDaftar,
                ],
            ],
            'solusi/toko-retail' => [
                'Judul' => 'Toko & Retail',
                'JudulSeo' => 'Aplikasi Kasir Toko & Retail · PAYOU',
                'DeskripsiSeo' => 'Aplikasi kasir toko dan minimarket: pemindai barcode, varian, harga grosir & member, stok multi-gudang, pembelian dan hutang pemasok.',
                'Bagian' => [
                    [
                        'Jenis' => 'Hero',
                        'Label' => 'Toko & Retail',
                        'Judul' => 'Stok selalu cocok dengan rak',
                        'Subjudul' => 'Scan barcode, jual cepat, dan pantau stok serta HPP setiap barang.',
                        'TombolUtama' => ['Label' => 'Coba gratis', 'Tautan' => '@daftar'],
                    ],
                    [
                        'Jenis' => 'Keunggulan',
                        'Kolom' => '3',
                        'Item' => [
                            ['Ikon' => 'ShoppingBasket', 'Judul' => 'Kasir cepat', 'Teks' => 'Pemindai barcode tanpa klik, varian, dan satuan jual.'],
                            ['Ikon' => 'Tag', 'Judul' => 'Harga bertingkat', 'Teks' => 'Harga member, reseller, dan grosir per tier pelanggan.'],
                            ['Ikon' => 'Warehouse', 'Judul' => 'Multi-gudang', 'Teks' => 'Transfer stok, opname, dan penyesuaian.'],
                            ['Ikon' => 'Truck', 'Judul' => 'Pembelian', 'Teks' => 'PO, penerimaan barang, faktur, hutang, dan retur pemasok.'],
                            ['Ikon' => 'HandCoins', 'Judul' => 'Tempo & piutang', 'Teks' => 'Jual tempo dengan limit kredit dan pelunasan.'],
                            ['Ikon' => 'ChartLine', 'Judul' => 'Laporan stok', 'Teks' => 'Kartu stok, nilai persediaan, dan barang terlaris.'],
                        ],
                    ],
                    $ctaDaftar,
                ],
            ],
            'solusi/jasa' => [
                'Judul' => 'Salon, Laundry & Jasa',
                'JudulSeo' => 'Aplikasi Kasir Salon, Laundry & Jasa · PAYOU',
                'DeskripsiSeo' => 'Aplikasi kasir usaha jasa: komisi per staf, deposit pelanggan, pre-order dengan uang muka, jadwal & absensi karyawan.',
                'Bagian' => [
                    [
                        'Jenis' => 'Hero',
                        'Label' => 'Salon, Laundry & Jasa',
                        'Judul' => 'Komisi staf dan saldo pelanggan tercatat otomatis',
                        'Subjudul' => 'Catat siapa yang melayani, terima deposit dan uang muka, lalu hitung komisi dan gaji tanpa rekap manual.',
                        'TombolUtama' => ['Label' => 'Coba gratis', 'Tautan' => '@daftar'],
                    ],
                    [
                        'Jenis' => 'Keunggulan',
                        'Kolom' => '3',
                        'Item' => [
                            ['Ikon' => 'Users', 'Judul' => 'Komisi per staf', 'Teks' => 'Aturan komisi per produk atau kategori, dibagi ke beberapa staf.'],
                            ['Ikon' => 'Wallet', 'Judul' => 'Deposit pelanggan', 'Teks' => 'Isi saldo di kasir, pakai untuk membayar kunjungan berikutnya.'],
                            ['Ikon' => 'ClipboardList', 'Judul' => 'Pre-order & uang muka', 'Teks' => 'Terima pesanan dengan DP, ambil dan lunasi kemudian.'],
                            ['Ikon' => 'Clock', 'Judul' => 'Jadwal & absensi', 'Teks' => 'Absen dengan PIN dan swafoto di perangkat kasir.'],
                            ['Ikon' => 'HandCoins', 'Judul' => 'Kasbon & gaji', 'Teks' => 'Kasbon dipotong otomatis di rekap gaji bulanan.'],
                            ['Ikon' => 'MessageCircle', 'Judul' => 'Struk digital', 'Teks' => 'Kirim struk dan pengingat lewat WhatsApp.'],
                        ],
                    ],
                    $ctaDaftar,
                ],
            ],
            'kontak' => [
                'Judul' => 'Kontak',
                'JudulSeo' => 'Hubungi PAYOU',
                'DeskripsiSeo' => 'Hubungi tim PAYOU lewat WhatsApp atau email untuk demo, pertanyaan harga, dan bantuan.',
                'Bagian' => [
                    [
                        'Jenis' => 'Kontak',
                        'Judul' => 'Hubungi kami',
                        'Subjudul' => 'Tim kami siap membantu demo, pertanyaan paket, dan pemasangan perangkat.',
                    ],
                    [
                        'Jenis' => 'UnduhAplikasi',
                        'Judul' => 'Unduh aplikasi PAYOU',
                        'Subjudul' => 'Aplikasi kasir untuk Android, iPhone/iPad, dan Windows.',
                    ],
                ],
            ],
            'tentang' => [
                'Judul' => 'Tentang kami',
                'JudulSeo' => 'Tentang PAYOU',
                'DeskripsiSeo' => 'PAYOU adalah aplikasi kasir dan pembukuan untuk usaha di Indonesia.',
                'Bagian' => [
                    [
                        'Jenis' => 'TeksBebas',
                        'Judul' => 'Tentang PAYOU',
                        'Isi' => "PAYOU dibuat untuk pemilik usaha di Indonesia yang ingin berjualan dengan tenang: kasir tetap jalan saat internet putus, stok dan pajak dihitung benar, dan laporan keuangan tersedia tanpa input ulang.\n\n## Yang kami pegang\n- Data transaksi tidak pernah diedit diam-diam; koreksi selalu lewat dokumen pembalik.\n- Pajak mengikuti tarif yang berlaku.\n- Data pelanggan Anda dilindungi sesuai UU Perlindungan Data Pribadi.",
                    ],
                    $ctaDaftar,
                ],
            ],
        ];
    }
}
