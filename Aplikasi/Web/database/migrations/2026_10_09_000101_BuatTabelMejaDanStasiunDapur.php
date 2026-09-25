<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F-10a data master mode meja & dapur (PRD Rincian F-10a, §15 "Meja, Dapur, Layanan"): area & meja per outlet,
 * stasiun dapur tingkat tenant (kategori tingkat tenant merujuknya lewat `Kategori.IdStasiunDapur`), status
 * Aktif/Diarsipkan (tidak dihapus karena dirujuk pesanan). Status pakai meja (kosong/terisi/minta bill) bukan kolom:
 * diturunkan dari pesanan terbuka (F-07 mode meja). `TokenQr` meja dibuat F-17.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('AreaMeja', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->UuidPublik();
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkAreaMejaIdTenant')->restrictOnDelete();
            $tabel->foreignId('IdOutlet')->constrained('Outlet', 'Id', 'FkAreaMejaIdOutlet')->restrictOnDelete();
            $tabel->string('Nama', 60);
            $tabel->unsignedSmallInteger('Urutan')->default(0);
            $tabel->string('Status', 20)->default('Aktif');
            $tabel->timestamp('DiarsipkanPada')->nullable();
            $tabel->WaktuStandar();
            $tabel->unique(['IdOutlet', 'Nama'], 'UniqAreaMejaIdOutletNama');
            $tabel->index(['IdTenant', 'IdOutlet', 'Urutan'], 'IdxAreaMejaIdTenantIdOutletUrutan');
        });

        Schema::create('Meja', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->UuidPublik();
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkMejaIdTenant')->restrictOnDelete();
            $tabel->foreignId('IdOutlet')->constrained('Outlet', 'Id', 'FkMejaIdOutlet')->restrictOnDelete();
            $tabel->foreignId('IdAreaMeja')->nullable()->constrained('AreaMeja', 'Id', 'FkMejaIdAreaMeja')->restrictOnDelete();
            $tabel->string('Nama', 30);
            $tabel->unsignedSmallInteger('Kapasitas')->default(4);
            $tabel->string('Bentuk', 20)->default('Persegi');
            $tabel->unsignedSmallInteger('PosisiX')->nullable();
            $tabel->unsignedSmallInteger('PosisiY')->nullable();
            $tabel->unsignedSmallInteger('Urutan')->default(0);
            $tabel->string('Status', 20)->default('Aktif');
            $tabel->timestamp('DiarsipkanPada')->nullable();
            $tabel->WaktuStandar();
            $tabel->unique(['IdOutlet', 'Nama'], 'UniqMejaIdOutletNama');
            $tabel->index(['IdTenant', 'IdOutlet', 'IdAreaMeja'], 'IdxMejaIdTenantIdOutletIdAreaMeja');
        });

        Schema::create('StasiunDapur', function (Blueprint $tabel): void {
            $tabel->id('Id');
            $tabel->UuidPublik();
            $tabel->foreignId('IdTenant')->constrained('Tenant', 'Id', 'FkStasiunDapurIdTenant')->restrictOnDelete();
            $tabel->string('Nama', 60);
            $tabel->unsignedSmallInteger('Urutan')->default(0);
            $tabel->string('Status', 20)->default('Aktif');
            $tabel->timestamp('DiarsipkanPada')->nullable();
            $tabel->WaktuStandar();
            $tabel->unique(['IdTenant', 'Nama'], 'UniqStasiunDapurIdTenantNama');
        });

        Schema::table('Kategori', function (Blueprint $tabel): void {
            $tabel->foreignId('IdStasiunDapur')->nullable()->after('Urutan')
                ->constrained('StasiunDapur', 'Id', 'FkKategoriIdStasiunDapur')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('Kategori', function (Blueprint $tabel): void {
            $tabel->dropForeign('FkKategoriIdStasiunDapur');
            $tabel->dropColumn('IdStasiunDapur');
        });
        Schema::dropIfExists('StasiunDapur');
        Schema::dropIfExists('Meja');
        Schema::dropIfExists('AreaMeja');
    }
};
