<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F-16d bagian 1 (CRM-04 deposit pelanggan, J-16.1, §15 `MutasiDeposit`):
 * - `MutasiDeposit` buku deposit append-only per pelanggan; saldo = Σ `Jumlah`. `SaldoSetelah` diisi sambil mengunci
 *   baris pelanggan. Satu mutasi per (Jenis, JenisSumber, IdSumber) = idempoten per dokumen sumber. Penarikan &
 *   penyesuaian back-office (tanpa dokumen lain) memakai baris mutasinya sendiri sebagai sumber jurnal (`IdJurnal`).
 * - `Pelanggan.SaldoDeposit` = cache Σ `MutasiDeposit.Jumlah` (dibangun ulang dari buku; dipakai urut daftar).
 * - `IsiDeposit` dokumen isi saldo dari POS (outbox `Deposit.Isi`, bisa offline). Tidak diedit; batal lewat
 *   pembalik (`IdJurnalBatal`). `IdPelanggan` kosong = pelanggan tidak dikenal saat sinkron (uang tetap diterima,
 *   ditandai tinjauan).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('Pelanggan', function (Blueprint $tabel): void {
            $tabel->decimal('SaldoDeposit', 18, 2)->default(0)->after('TerminHari');
            $tabel->index(['IdTenant', 'SaldoDeposit'], 'IdxPelangganIdTenantSaldoDeposit');
        });

        Schema::create('IsiDeposit', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->UuidPublik();
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkIsiDepositIdTenant')->restrictOnDelete();
            $tabel->foreignId('IdOutlet')->constrained('Outlet', 'Id', 'FkIsiDepositIdOutlet')->restrictOnDelete();
            $tabel->foreignId('IdPerangkat')->constrained('Perangkat', 'Id', 'FkIsiDepositIdPerangkat')->restrictOnDelete();
            $tabel->foreignId('IdShift')->constrained('Shift', 'Id', 'FkIsiDepositIdShift')->restrictOnDelete();
            $tabel->foreignId('IdPelanggan')->nullable()->constrained('Pelanggan', 'Id', 'FkIsiDepositIdPelanggan')->restrictOnDelete();
            $tabel->char('UuidPelanggan', 26);
            $tabel->foreignId('IdPengguna')->constrained('Pengguna', 'Id', 'FkIsiDepositIdPengguna')->restrictOnDelete();
            $tabel->string('Nomor', 80);
            $tabel->timestamp('DibuatOfflinePada');
            $tabel->date('TanggalBisnis');
            $tabel->foreignId('IdMetodePembayaran')->constrained('MetodePembayaran', 'Id', 'FkIsiDepositIdMetodePembayaran')->restrictOnDelete();
            $tabel->string('JenisMetode', 20);
            $tabel->string('NamaMetode', 100);
            $tabel->decimal('Jumlah', 18, 2);
            $tabel->string('Referensi', 100)->nullable();
            $tabel->string('Status', 20);
            $tabel->foreignId('IdJurnal')->nullable()->constrained('Jurnal', 'Id', 'FkIsiDepositIdJurnal')->restrictOnDelete();
            $tabel->boolean('PerluTinjauan')->default(false);
            $tabel->string('AlasanTinjauan', 500)->nullable();
            $tabel->timestamp('DibatalkanPada')->nullable();
            $tabel->string('AlasanBatal', 255)->nullable();
            $tabel->foreignId('IdPembatal')->nullable()->constrained('Pengguna', 'Id', 'FkIsiDepositIdPembatal')->restrictOnDelete();
            $tabel->foreignId('IdJurnalBatal')->nullable()->constrained('Jurnal', 'Id', 'FkIsiDepositIdJurnalBatal')->restrictOnDelete();
            $tabel->timestamp('DiterimaPada');
            $tabel->WaktuStandar();
            $tabel->unique(['IdTenant', 'Nomor'], 'UniqIsiDepositIdTenantNomor');
            $tabel->index(['IdTenant', 'IdShift'], 'IdxIsiDepositIdTenantIdShift');
            $tabel->index(['IdTenant', 'IdPelanggan'], 'IdxIsiDepositIdTenantIdPelanggan');
            $tabel->index(['IdTenant', 'TanggalBisnis'], 'IdxIsiDepositIdTenantTanggalBisnis');
        });

        Schema::create('MutasiDeposit', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->UuidPublik();
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkMutasiDepositIdTenant')->restrictOnDelete();
            $tabel->foreignId('IdPelanggan')->constrained('Pelanggan', 'Id', 'FkMutasiDepositIdPelanggan')->restrictOnDelete();
            $tabel->string('Jenis', 20);
            $tabel->decimal('Jumlah', 18, 2);
            $tabel->decimal('SaldoSetelah', 18, 2);
            $tabel->string('JenisSumber', 20);
            $tabel->unsignedBigInteger('IdSumber')->nullable();
            $tabel->string('NomorSumber', 80)->nullable();
            $tabel->date('Tanggal');
            $tabel->foreignId('IdAkunKasBank')->nullable()->constrained('Akun', 'Id', 'FkMutasiDepositIdAkunKasBank')->restrictOnDelete();
            $tabel->foreignId('IdJurnal')->nullable()->constrained('Jurnal', 'Id', 'FkMutasiDepositIdJurnal')->restrictOnDelete();
            $tabel->string('Keterangan', 255)->nullable();
            $tabel->foreignId('IdPengguna')->nullable()->constrained('Pengguna', 'Id', 'FkMutasiDepositIdPengguna')->restrictOnDelete();
            $tabel->WaktuStandar();
            $tabel->unique(['IdTenant', 'Jenis', 'JenisSumber', 'IdSumber'], 'UniqMutasiDepositIdTenantJenisSumber');
            $tabel->index(['IdTenant', 'IdPelanggan', 'Id'], 'IdxMutasiDepositIdTenantIdPelanggan');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('MutasiDeposit');
        Schema::dropIfExists('IsiDeposit');

        Schema::table('Pelanggan', function (Blueprint $tabel): void {
            $tabel->dropIndex('IdxPelangganIdTenantSaldoDeposit');
            $tabel->dropColumn('SaldoDeposit');
        });
    }
};
