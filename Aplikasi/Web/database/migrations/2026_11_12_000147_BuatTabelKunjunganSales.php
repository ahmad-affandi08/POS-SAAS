<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modul Salesman bagian 1 (§9.7, SLS-11): kunjungan salesman ke pelanggan, dikirim dari HP lewat outbox
 * `Kunjungan.Catat` saat check-out (bisa offline). `Uuid` dibuat perangkat (idempoten). Lokasi check-in disimpan
 * sebagai DECIMAL(10,7) dari string desimal — tidak pernah lewat float. `Tanggal` = tanggal kalender kunjungan di zona
 * waktu outlet (untuk saring per hari tanpa menghitung zona di setiap kueri).
 *
 * Bukan peristiwa akuntansi maupun stok; catatan kejadian lapangan. Tanpa soft delete.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('KunjunganSales', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->UuidPublik();
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkKunjunganSalesIdTenant')->restrictOnDelete();
            $tabel->foreignId('IdOutlet')->constrained('Outlet', 'Id', 'FkKunjunganSalesIdOutlet')->restrictOnDelete();
            $tabel->foreignId('IdPelanggan')->constrained('Pelanggan', 'Id', 'FkKunjunganSalesIdPelanggan')->restrictOnDelete();
            $tabel->foreignId('IdPengguna')->constrained('Pengguna', 'Id', 'FkKunjunganSalesIdPengguna')->restrictOnDelete();
            $tabel->foreignId('IdPerangkat')->nullable()->constrained('Perangkat', 'Id', 'FkKunjunganSalesIdPerangkat')->restrictOnDelete();
            $tabel->date('Tanggal');
            $tabel->timestamp('MasukPada');
            $tabel->timestamp('KeluarPada')->nullable();
            $tabel->decimal('Latitude', 10, 7)->nullable();
            $tabel->decimal('Longitude', 10, 7)->nullable();
            $tabel->unsignedInteger('AkurasiMeter')->nullable();
            $tabel->string('Hasil', 20);
            $tabel->string('Catatan', 255)->nullable();
            $tabel->foreignId('IdPesananGrosir')->nullable()->constrained('PesananGrosir', 'Id', 'FkKunjunganSalesIdPesananGrosir')->restrictOnDelete();
            $tabel->timestamp('DiterimaPada');
            $tabel->WaktuStandar();
            $tabel->index(['IdTenant', 'Tanggal'], 'IdxKunjunganSalesIdTenantTanggal');
            $tabel->index(['IdTenant', 'IdPengguna', 'Tanggal'], 'IdxKunjunganSalesIdTenantIdPenggunaTanggal');
            $tabel->index(['IdTenant', 'IdPelanggan', 'MasukPada'], 'IdxKunjunganSalesIdTenantIdPelangganMasukPada');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('KunjunganSales');
    }
};
