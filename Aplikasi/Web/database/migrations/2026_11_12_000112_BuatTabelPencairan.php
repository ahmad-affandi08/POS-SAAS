<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F-08 BR-08.4 & J-08.1: **pencairan** dana non-tunai ke rekening toko, `PC/{YYYY}/{MM}/{SEQ4}`.
 *
 * Masalah yang diselesaikannya: setiap pembayaran QRIS, kartu/EDC, gerbang, dan ojol mendebet akun kliring metodenya
 * (atau `PiutangPencairan`, BR-08.3) karena uangnya belum ada di rekening. Sampai sekarang tidak ada dokumen yang
 * melunasi akun itu, jadi saldonya hanya bertambah selamanya dan potongan platform (MDR, komisi ojol) tidak pernah
 * masuk laba-rugi — laba terlihat lebih besar daripada kenyataan.
 *
 * Satu pencairan = satu setoran yang benar-benar masuk rekening, untuk **satu metode pembayaran** dan **satu outlet**.
 * Alasannya bukan kenyamanan: akun kliring itu milik metode (`MetodePembayaran.IdAkunKliring`) dan setorannya datang
 * dari satu platform, jadi mencampur metode dalam satu dokumen akan mengkredit akun kliring yang bukan sumbernya.
 *
 * `JumlahBersih` **dimasukkan operator dari mutasi rekening**, bukan dihitung dari persen biaya metode. Persen itu
 * ekspektasi; yang menentukan buku adalah uang yang benar-benar masuk. Selisih `JumlahKotor − JumlahBersih` dibukukan
 * sebagai `BebanBiayaPembayaran` bila positif, dan sebagai `PendapatanLain` bila negatif (platform menyetor lebih dari
 * nilai transaksinya) — beban bernilai negatif di laba-rugi terbaca seperti potongan biaya, padahal yang terjadi
 * adalah kelebihan setor yang belum dijelaskan.
 *
 * **Satu pembayaran hanya boleh dicairkan sekali**, dan penjaganya ada di skema — bukan cuma di aksi. Penandanya tidak
 * bisa ditaruh di `PenjualanPembayaran`: model itu menolak `updating` tanpa syarat (append-only), dan melemahkannya demi
 * fitur ini akan membuka seluruh riwayat pembayaran untuk diubah. Jadi `PencairanDetail` membawa dua kolom untuk
 * pembayaran yang sama:
 * - `IdPenjualanPembayaran` — isi barisnya, tidak pernah berubah (dokumen tetap bisa dibaca setelah dibatalkan);
 * - `IdPembayaranAktif` — salinan **unik** yang semata-mata menahan klaim atas pembayaran itu, dan dikosongkan saat
 *   pencairannya dibatalkan sehingga pembayarannya bisa dicairkan ulang di dokumen lain. MySQL mengizinkan banyak NULL
 *   di indeks unik, jadi dokumen yang dibatalkan tidak saling bertabrakan.
 *
 * Cermin `SuratJalan.IdFakturPenjualan` yang juga dilepas saat fakturnya dibatalkan: yang dilepas tautannya, bukan
 * angka dokumennya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('Pencairan', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->UuidPublik();
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkPencairanIdTenant')->restrictOnDelete();
            $tabel->string('Nomor', 30);
            $tabel->foreignId('IdOutlet')->constrained('Outlet', 'Id', 'FkPencairanIdOutlet')->restrictOnDelete();
            $tabel->foreignId('IdMetodePembayaran')->constrained('MetodePembayaran', 'Id', 'FkPencairanIdMetodePembayaran')->restrictOnDelete();
            // Tanggal uang masuk rekening (tanggal mutasi rekening), bukan tanggal transaksinya.
            $tabel->date('Tanggal');
            // Akun kas/bank yang menerima setoran.
            $tabel->foreignId('IdAkunTujuan')->constrained('Akun', 'Id', 'FkPencairanIdAkunTujuan')->restrictOnDelete();
            // Akun kliring metode saat dokumen dibuat; **boleh kosong**, dan itu bukan kelalaian: metode tanpa akun
            // kliring sendiri dibukukan J-07.1 lewat peran `PiutangPencairan`, jadi pencairannya pun mengkredit peran
            // yang sama supaya yang dilunasi persis akun yang tadi didebet (pemetaan peran per outlet ikut terpakai
            // ulang). Menyimpan Id hasil resolusi di sini justru bisa menyimpang bila pemetaannya berbeda per outlet.
            $tabel->foreignId('IdAkunKliring')->nullable()->constrained('Akun', 'Id', 'FkPencairanIdAkunKliring')->restrictOnDelete();
            $tabel->string('Status', 20);
            // Kotor = Σ pembayaran yang dicairkan; Bersih = yang masuk rekening; Biaya = selisihnya (boleh negatif).
            $tabel->decimal('JumlahKotor', 18, 2)->default(0);
            $tabel->decimal('JumlahBersih', 18, 2)->default(0);
            $tabel->decimal('Biaya', 18, 2)->default(0);
            // Biaya yang diharapkan dari pengaturan metode (persen + biaya tetap) saat dokumen dibuat, untuk
            // dibandingkan dengan `Biaya` yang sebenarnya. Hanya keterangan; tidak pernah masuk jurnal.
            $tabel->decimal('BiayaDiharapkan', 18, 2)->default(0);
            $tabel->string('Referensi', 100)->nullable();
            $tabel->string('Catatan', 500)->nullable();
            $tabel->foreignId('IdJurnal')->nullable()->constrained('Jurnal', 'Id', 'FkPencairanIdJurnal')->restrictOnDelete();
            $tabel->foreignId('IdJurnalPembatalan')->nullable()->constrained('Jurnal', 'Id', 'FkPencairanIdJurnalPembatalan')->restrictOnDelete();
            $tabel->foreignId('DibuatOleh')->nullable()->constrained('Pengguna', 'Id', 'FkPencairanDibuatOleh')->restrictOnDelete();
            $tabel->foreignId('DiubahOleh')->nullable()->constrained('Pengguna', 'Id', 'FkPencairanDiubahOleh')->restrictOnDelete();
            $tabel->foreignId('DibatalkanOleh')->nullable()->constrained('Pengguna', 'Id', 'FkPencairanDibatalkanOleh')->restrictOnDelete();
            $tabel->timestamp('DibatalkanPada')->nullable();
            $tabel->string('AlasanBatal', 255)->nullable();
            $tabel->WaktuStandar();
            $tabel->unique(['IdTenant', 'Nomor'], 'UniqPencairanIdTenantNomor');
            $tabel->index(['IdTenant', 'Status', 'Tanggal'], 'IdxPencairanIdTenantStatusTanggal');
            $tabel->index(['IdTenant', 'IdMetodePembayaran', 'Tanggal'], 'IdxPencairanIdTenantIdMetodeTanggal');
        });

        Schema::create('PencairanDetail', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkPencairanDetailIdTenant')->restrictOnDelete();
            $tabel->foreignId('IdPencairan')->constrained('Pencairan', 'Id', 'FkPencairanDetailIdPencairan')->restrictOnDelete();
            $tabel->unsignedInteger('Urutan');
            $tabel->foreignId('IdPenjualanPembayaran')->constrained('PenjualanPembayaran', 'Id', 'FkPencairanDetailIdPenjualanPembayaran')->restrictOnDelete();
            $tabel->foreignId('IdPenjualan')->constrained('Penjualan', 'Id', 'FkPencairanDetailIdPenjualan')->restrictOnDelete();
            // Snapshot supaya rincian dokumen tetap terbaca tanpa menggabung tabel penjualan.
            $tabel->string('NomorPenjualan', 40);
            $tabel->date('TanggalPenjualan');
            $tabel->decimal('Jumlah', 18, 2);
            $tabel->string('RefEksternal', 100)->nullable();
            // Penahan klaim: sama dengan `IdPenjualanPembayaran` selama pencairannya berlaku, dikosongkan saat
            // dibatalkan. Tanpa FK: nilainya memang boleh menjadi NULL dan tidak dipakai untuk menelusuri apa pun —
            // yang dipakai membaca dokumen adalah `IdPenjualanPembayaran` di atas.
            $tabel->unsignedBigInteger('IdPembayaranAktif')->nullable();
            $tabel->WaktuStandar();
            $tabel->unique(['IdTenant', 'IdPembayaranAktif'], 'UniqPencairanDetailIdTenantIdPembayaranAktif');
            $tabel->index(['IdPencairan', 'Urutan'], 'IdxPencairanDetailIdPencairanUrutan');
            $tabel->index(['IdTenant', 'IdPenjualanPembayaran'], 'IdxPencairanDetailIdTenantIdPembayaran');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('PencairanDetail');
        Schema::dropIfExists('Pencairan');
    }
};
