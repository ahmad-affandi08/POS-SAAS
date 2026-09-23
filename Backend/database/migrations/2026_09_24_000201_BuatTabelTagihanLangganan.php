<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tagihan langganan platform Fase 0 (P-08, F-19, PRD §15.3): tagihan, pembayaran transfer manual + bukti, pemakaian
 * kupon (BR-P04.7), dan penghitung nomor tagihan per tahun (BR-P08.1). Uang DECIMAL(18,2), tarif DECIMAL(9,6).
 */
return new class extends Migration
{
    public function up(): void
    {
        // BR-P08.1: satu baris per tahun, dikunci FOR UPDATE saat penomoran sehingga nomor urut tanpa celah.
        Schema::create('NomorUrutTagihanLangganan', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->unsignedSmallInteger('Tahun')->unique('UniqNomorUrutTagihanLanggananTahun');
            $tabel->unsignedInteger('NomorTerakhir')->default(0);
            $tabel->WaktuStandar();
        });

        Schema::create('TagihanLangganan', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->UuidPublik();
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkTagihanLanggananIdTenant')->restrictOnDelete();
            $tabel->string('Nomor', 30)->unique('UniqTagihanLanggananNomor');
            $tabel->string('Jenis', 20);
            $tabel->string('Status', 20);
            $tabel->foreignId('IdPaket')->constrained('Paket', 'Id', 'FkTagihanLanggananIdPaket')->restrictOnDelete();
            $tabel->foreignId('IdHargaPaket')->constrained('HargaPaket', 'Id', 'FkTagihanLanggananIdHargaPaket')->restrictOnDelete();
            $tabel->string('Siklus', 20);
            $tabel->unsignedTinyInteger('JumlahBulan');
            $tabel->decimal('Subtotal', 18, 2);
            $tabel->foreignId('IdKuponLangganan')->nullable()->constrained('KuponLangganan', 'Id', 'FkTagihanLanggananIdKuponLangganan')->restrictOnDelete();
            $tabel->string('KodeKupon', 30)->nullable();
            $tabel->decimal('Diskon', 18, 2)->default(0);
            // Snapshot PPN (§12.2): tarif persen, pengali DPP pecahan, DPP, dan jumlah PPN. Null tarif = platform non-PKP.
            $tabel->foreignId('IdTarifPajak')->nullable()->constrained('TarifPajak', 'Id', 'FkTagihanLanggananIdTarifPajak')->restrictOnDelete();
            $tabel->decimal('TarifPpn', 9, 6)->default(0);
            $tabel->unsignedSmallInteger('PengaliDppPembilang')->default(1);
            $tabel->unsignedSmallInteger('PengaliDppPenyebut')->default(1);
            $tabel->decimal('DasarPengenaanPajak', 18, 2)->default(0);
            $tabel->decimal('JumlahPpn', 18, 2)->default(0);
            $tabel->decimal('Total', 18, 2);
            $tabel->timestamp('TerbitPada');
            $tabel->timestamp('JatuhTempoPada');
            $tabel->timestamp('DibayarPada')->nullable();
            $tabel->timestamp('DibatalkanPada')->nullable();
            $tabel->string('AlasanBatal', 500)->nullable();
            // Periode layanan & jangkar grandfathering (BR-P04.1) diisi saat Lunas.
            $tabel->timestamp('PeriodeMulai')->nullable();
            $tabel->timestamp('PeriodeSelesai')->nullable();
            $tabel->date('MulaiLanggananPaket')->nullable();
            $tabel->string('RefGateway', 100)->nullable();
            $tabel->foreignId('IdPenggunaPembuat')->nullable()->constrained('Pengguna', 'Id', 'FkTagihanLanggananIdPenggunaPembuat')->restrictOnDelete();
            $tabel->WaktuStandar();
            $tabel->index(['IdTenant', 'Status'], 'IdxTagihanLanggananTenantStatus');
            $tabel->index(['Status', 'JatuhTempoPada'], 'IdxTagihanLanggananStatusJatuhTempo');
        });

        Schema::create('PembayaranLangganan', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->UuidPublik();
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkPembayaranLanggananIdTenant')->restrictOnDelete();
            $tabel->foreignId('IdTagihanLangganan')->constrained('TagihanLangganan', 'Id', 'FkPembayaranLanggananIdTagihanLangganan')->restrictOnDelete();
            $tabel->string('Metode', 20);
            $tabel->string('Status', 20);
            $tabel->decimal('Jumlah', 18, 2);
            $tabel->date('TanggalTransfer')->nullable();
            $tabel->string('BankPengirim', 100)->nullable();
            $tabel->string('NamaPengirim', 150)->nullable();
            // Snapshot rekening tujuan platform saat unggah (konfigurasi bisa berubah).
            $tabel->string('KodeRekeningTujuan', 30)->nullable();
            $tabel->string('BankTujuan', 100)->nullable();
            $tabel->string('NomorRekeningTujuan', 50)->nullable();
            $tabel->string('PathBukti', 255)->nullable();
            $tabel->string('NamaFileBukti', 255)->nullable();
            $tabel->string('MimeBukti', 100)->nullable();
            $tabel->unsignedInteger('UkuranBukti')->nullable();
            $tabel->string('RefGateway', 100)->nullable();
            $tabel->foreignId('IdPenggunaPengunggah')->nullable()->constrained('Pengguna', 'Id', 'FkPembayaranLanggananIdPenggunaPengunggah')->restrictOnDelete();
            // Tujuan pemberitahuan hasil verifikasi (P-08); disimpan agar sisi pengelola tidak membaca tabel Organisasi.
            $tabel->string('EmailPemberitahuan', 191)->nullable();
            $tabel->string('NamaPemberitahuan', 150)->nullable();
            $tabel->foreignId('IdPenggunaPengelolaVerifikator')->nullable()->constrained('PenggunaPengelola', 'Id', 'FkPembayaranLanggananIdVerifikator')->restrictOnDelete();
            $tabel->timestamp('DiverifikasiPada')->nullable();
            $tabel->decimal('JumlahDiterima', 18, 2)->nullable();
            $tabel->string('AlasanTolak', 500)->nullable();
            $tabel->WaktuStandar();
            $tabel->index(['IdTenant', 'IdTagihanLangganan'], 'IdxPembayaranLanggananTenantTagihan');
            $tabel->index(['Status', 'DibuatPada'], 'IdxPembayaranLanggananStatus');
        });

        // BR-P04.7: pemakaian kupon per tagihan. Kuota kupon dihitung lintas tenant sehingga tabel ini milik platform.
        Schema::create('KuponLanggananPemakaian', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->foreignId('IdKuponLangganan')->constrained('KuponLangganan', 'Id', 'FkKuponLanggananPemakaianIdKupon')->restrictOnDelete();
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkKuponLanggananPemakaianIdTenant')->restrictOnDelete();
            $tabel->foreignId('IdTagihanLangganan')->unique('UniqKuponLanggananPemakaianTagihan')->constrained('TagihanLangganan', 'Id', 'FkKuponLanggananPemakaianIdTagihan')->restrictOnDelete();
            $tabel->unsignedSmallInteger('BulanDiskon');
            $tabel->decimal('Diskon', 18, 2);
            $tabel->timestamp('DibatalkanPada')->nullable();
            $tabel->WaktuStandar();
            $tabel->index(['IdTenant', 'IdKuponLangganan'], 'IdxKuponLanggananPemakaianTenantKupon');
            $tabel->index(['IdKuponLangganan', 'DibatalkanPada'], 'IdxKuponLanggananPemakaianKupon');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('KuponLanggananPemakaian');
        Schema::dropIfExists('PembayaranLangganan');
        Schema::dropIfExists('TagihanLangganan');
        Schema::dropIfExists('NomorUrutTagihanLangganan');
    }
};
