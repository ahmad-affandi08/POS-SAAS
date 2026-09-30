<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F-17 bagian 3 (prasyarat yang flow-nya namai sendiri): **ongkir yang ditagih ke pembeli**.
 *
 * Sampai sekarang `Penjualan` tidak punya tempat untuk biaya kirim, sehingga pesanan kirim berongkir **ditolak di
 * kasir** — pilihan yang benar, karena menagihnya dengan total yang kurang akan membuat uang muka pelanggan lebih besar
 * daripada penjualannya. Ini yang membuka gembok itu, sekaligus prasyarat promo **gratis ongkir** (F-16c) yang selama
 * ini tertahan karena tidak ada angka ongkir untuk didiskon.
 *
 * Bentuknya meniru **biaya layanan** (`PersenBiayaLayanan`, `PendapatanBiayaLayanan`), bukan pola baru: sama-sama
 * pungutan di luar harga barang, punya akun pendapatan sendiri, dan ikut dasar pengenaan pajak sesuai konfigurasi.
 * Bedanya ongkir **nominal**, bukan persen, karena ia datang dari tarif kurir/jarak — bukan dari nilai keranjang.
 *
 * `DiskonKirim` dipisah dari `BiayaKirim` supaya gratis ongkir **terlihat**: kalau ongkir langsung ditulis nol, laporan
 * tidak bisa membedakan "tidak ada ongkir" dari "ongkir digratiskan", dan biaya kurir yang tetap dibayar toko jadi
 * kehilangan pasangan pendapatannya. Keduanya di-snapshot per baris (`PenjualanDetail.BiayaKirim`) karena pajaknya
 * dihitung **per baris**: kode pajak boleh berbeda antar baris, jadi ongkir dialokasikan sebanding netto seperti biaya
 * layanan.
 *
 * `KenaBiayaKirim` per baris kelompok pajak adalah **bendera independen**, bukan case baru di `DasarPengenaanPajak`.
 * Menambah case berarti empat kombinasi (dengan/tanpa layanan × dengan/tanpa kirim) pada enum yang nilainya tersimpan di
 * DB dan dibaca Dart `KlienApi`, FE, serta template sektor. Dua bendera yang berdiri sendiri lebih kecil risikonya dan
 * lebih jujur: keduanya memang pertanyaan terpisah. Bawaannya `false` sehingga PB1 tidak berubah; PPN disetel `true` di
 * template sektor sesuai keputusan pemilik produk (ongkir ikut konfigurasi per jenis pajak).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('Penjualan', function (Blueprint $tabel): void {
            $tabel->decimal('BiayaKirim', 18, 2)->default(0)->after('PersenBiayaLayanan');
            $tabel->decimal('DiskonKirim', 18, 2)->default(0)->after('BiayaKirim');
        });

        Schema::table('PenjualanDetail', function (Blueprint $tabel): void {
            // Bagian ongkir netto (BiayaKirim − DiskonKirim) yang dialokasikan ke baris ini; dasar pajaknya.
            $tabel->decimal('BiayaKirim', 18, 2)->default(0)->after('BiayaLayanan');
        });

        Schema::table('KelompokPajakDetail', function (Blueprint $tabel): void {
            $tabel->boolean('KenaBiayaKirim')->default(false)->after('DasarPengenaan');
        });

        Schema::table('PenjualanPajak', function (Blueprint $tabel): void {
            // Snapshot: konfigurasi pajak boleh berubah kemudian, sedangkan jurnal yang sudah diposting harus tetap
            // bisa dijelaskan dengan aturan yang berlaku saat dokumennya dibuat.
            $tabel->boolean('KenaBiayaKirim')->default(false)->after('DasarPengenaan');
        });
    }

    public function down(): void
    {
        Schema::table('PenjualanPajak', fn (Blueprint $tabel) => $tabel->dropColumn('KenaBiayaKirim'));
        Schema::table('KelompokPajakDetail', fn (Blueprint $tabel) => $tabel->dropColumn('KenaBiayaKirim'));
        Schema::table('PenjualanDetail', fn (Blueprint $tabel) => $tabel->dropColumn('BiayaKirim'));
        Schema::table('Penjualan', fn (Blueprint $tabel) => $tabel->dropColumn(['BiayaKirim', 'DiskonKirim']));
    }
};
