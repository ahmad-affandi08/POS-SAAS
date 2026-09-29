<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Grosir bagian 1 (F-12, §9.7, D-32): sales order grosir `PG/{OUTLET}/{YYMM}/{SEQ4}`. Status Draf → Dikonfirmasi →
 * SebagianDikirim → Selesai (+ Dibatalkan selama belum ada surat jalan terposting).
 *
 * Dokumen sendiri, bukan `PesananPenjualan` (pre-order kasir): di sini tidak ada perangkat, shift, maupun kasir, dan
 * pemenuhannya bertahap lewat `SuratJalan`. **Bukan peristiwa akuntansi** — tidak ada jurnal maupun mutasi stok di
 * tahap ini; pengakuan HPP, pendapatan, dan PPN terjadi saat penyerahan barang (BR-12.2, J-12.1).
 *
 * Gudang asal barang ada di `SuratJalan`, bukan di sini, karena satu SO boleh dikirim dari beberapa kali pengiriman.
 * Dokumen transaksi: tanpa soft delete.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('PesananGrosir', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->UuidPublik();
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkPesananGrosirIdTenant')->restrictOnDelete();
            $tabel->string('Nomor', 30);
            $tabel->foreignId('IdPelanggan')->constrained('Pelanggan', 'Id', 'FkPesananGrosirIdPelanggan')->restrictOnDelete();
            $tabel->foreignId('IdOutlet')->constrained('Outlet', 'Id', 'FkPesananGrosirIdOutlet')->restrictOnDelete();
            $tabel->date('Tanggal');
            $tabel->date('TanggalKirimDiminta')->nullable();
            $tabel->string('Status', 25);
            // Snapshot termin pelanggan saat dikonfirmasi; jatuh tempo piutang dihitung dari faktur (BR-12.5).
            $tabel->unsignedSmallInteger('TerminHari')->default(0);
            // Snapshot tarif & pengali DPP saat dikonfirmasi, seperti PesananPembelian, agar total tidak berubah
            // sendiri ketika TarifPajak terbit berubah.
            $tabel->decimal('TarifPpn', 9, 6)->nullable();
            $tabel->unsignedInteger('PengaliDppPembilang')->nullable();
            $tabel->unsignedInteger('PengaliDppPenyebut')->nullable();
            $tabel->decimal('Subtotal', 18, 2)->default(0);
            $tabel->decimal('Diskon', 18, 2)->default(0);
            $tabel->decimal('DasarPengenaanPajak', 18, 2)->default(0);
            $tabel->decimal('Pajak', 18, 2)->default(0);
            $tabel->decimal('Total', 18, 2)->default(0);
            $tabel->string('Catatan', 500)->nullable();
            // BR-12.6: konfirmasi yang melampaui limit kredit butuh pemegang izin `grosir.setujui-kredit` + alasan.
            $tabel->foreignId('IdPenyetujuKredit')->nullable()->constrained('Pengguna', 'Id', 'FkPesananGrosirIdPenyetujuKredit')->restrictOnDelete();
            $tabel->string('AlasanPersetujuanKredit', 255)->nullable();
            $tabel->foreignId('DibuatOleh')->nullable()->constrained('Pengguna', 'Id', 'FkPesananGrosirDibuatOleh')->restrictOnDelete();
            $tabel->foreignId('DiubahOleh')->nullable()->constrained('Pengguna', 'Id', 'FkPesananGrosirDiubahOleh')->restrictOnDelete();
            $tabel->foreignId('DikonfirmasiOleh')->nullable()->constrained('Pengguna', 'Id', 'FkPesananGrosirDikonfirmasiOleh')->restrictOnDelete();
            $tabel->timestamp('DikonfirmasiPada')->nullable();
            $tabel->timestamp('SelesaiPada')->nullable();
            $tabel->foreignId('DibatalkanOleh')->nullable()->constrained('Pengguna', 'Id', 'FkPesananGrosirDibatalkanOleh')->restrictOnDelete();
            $tabel->timestamp('DibatalkanPada')->nullable();
            $tabel->string('AlasanBatal', 255)->nullable();
            $tabel->WaktuStandar();
            $tabel->unique(['IdTenant', 'Nomor'], 'UniqPesananGrosirIdTenantNomor');
            $tabel->index(['IdTenant', 'Status', 'Tanggal'], 'IdxPesananGrosirIdTenantStatusTanggal');
            $tabel->index(['IdTenant', 'IdPelanggan', 'Status'], 'IdxPesananGrosirIdTenantIdPelangganStatus');
            $tabel->index(['IdTenant', 'IdOutlet', 'Tanggal'], 'IdxPesananGrosirIdTenantIdOutletTanggal');
        });

        Schema::create('PesananGrosirDetail', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkPesananGrosirDetailIdTenant')->restrictOnDelete();
            $tabel->foreignId('IdPesananGrosir')->constrained('PesananGrosir', 'Id', 'FkPesananGrosirDetailIdPesananGrosir')->restrictOnDelete();
            $tabel->unsignedInteger('Urutan');
            $tabel->foreignId('IdProduk')->constrained('Produk', 'Id', 'FkPesananGrosirDetailIdProduk')->restrictOnDelete();
            $tabel->string('NamaProduk', 150);
            $tabel->string('Sku', 64)->nullable();
            $tabel->foreignId('IdProdukSatuan')->nullable()->constrained('ProdukSatuan', 'Id', 'FkPesananGrosirDetailIdProdukSatuan')->nullOnDelete();
            $tabel->string('SimbolSatuan', 20);
            $tabel->decimal('Konversi', 18, 4);
            $tabel->decimal('Jumlah', 18, 4);
            // Terisi bertahap oleh surat jalan terposting; SO Selesai saat semua baris terkirim penuh.
            $tabel->decimal('JumlahTerkirim', 18, 4)->default(0);
            $tabel->decimal('Harga', 18, 2);
            $tabel->decimal('Diskon', 18, 2)->default(0);
            $tabel->decimal('Subtotal', 18, 2);
            // Pajak per baris mengikuti kelompok pajak produk, sama dengan kasir (inklusif/eksklusif).
            // Snapshot inklusif/eksklusif pajak baris (null = ikut pengaturan outlet), supaya angka dokumen
            // tidak bergeser bila pengaturan produk berubah setelah SO dibuat.
            $tabel->boolean('HargaTermasukPajak')->nullable();
            $tabel->foreignId('IdKelompokPajak')->nullable()->constrained('KelompokPajak', 'Id', 'FkPesananGrosirDetailIdKelompokPajak')->nullOnDelete();
            $tabel->WaktuStandar();
            $tabel->index(['IdTenant', 'IdPesananGrosir', 'Urutan'], 'IdxPesananGrosirDetailIdTenantIdPesananUrutan');
            $tabel->index(['IdTenant', 'IdProduk'], 'IdxPesananGrosirDetailIdTenantIdProduk');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('PesananGrosirDetail');
        Schema::dropIfExists('PesananGrosir');
    }
};
