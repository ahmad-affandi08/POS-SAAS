<?php

declare(strict_types=1);

use App\Domain\Kasir\Model\MutasiKas;
use App\Domain\Organisasi\Enum\PeranTenantBawaan;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Tests\Pendukung\Kasir\BantuanKasir;
use Tests\Pendukung\Organisasi\BantuanOrganisasi;
use Tests\Pendukung\Persediaan\BantuanPersediaan;
use Tests\Pendukung\Tenant\BantuanPendaftaran;

/*
 * K-18 (F-06, §19.1): foto bukti kas masuk/keluar dari kasir lewat `MutasiKas.Catat.Bukti` (JPEG base64). Disimpan di
 * disk privat, ditampilkan back-office hanya lewat rute berizin, kiriman ulang tidak menyisakan berkas yatim.
 */

beforeEach(function (): void {
    BantuanPendaftaran::SiapkanPrasyarat();
    Storage::fake('local');
});

/** JPEG minimal (tanda tangan FFD8FF) untuk uji foto bukti. */
function FotoBuktiUji(): string
{
    return base64_encode("\xFF\xD8\xFF\xE0\x00\x10JFIF\x00\x01\x01\x00\x00\x01\x00\x01\x00\x00\xFF\xD9");
}

it('kas keluar berfoto: tersimpan privat, Duplikat tidak menambah berkas, tampil & diunduh back-office berizin', function (): void {
    $k = BantuanKasir::Siapkan($this);
    $shift = BantuanKasir::ItemBukaShift($k['Kasir']);
    $keluar = BantuanKasir::ItemMutasiKas($shift['Uuid'], $k['Kasir'], 'Keluar', '45000.00', $k['KategoriKeluar'], ['Bukti' => FotoBuktiUji()]);
    $masuk = BantuanKasir::ItemMutasiKas($shift['Uuid'], $k['Kasir'], 'Masuk', '20000.00', $k['KategoriMasuk'], ['Bukti' => null]);

    expect(BantuanKasir::KirimRingkas($this, $k['Token'], [$shift, $keluar, $masuk]))->toBe([['Diterima', null], ['Diterima', null], ['Diterima', null]])
        ->and(BantuanKasir::KirimRingkas($this, $k['Token'], [$keluar]))->toBe([['Duplikat', null]]);

    BantuanOrganisasi::AturKonteks($k['Tenant']->Id);
    $mutasi = MutasiKas::query()->where('Uuid', $keluar['Uuid'])->sole();
    expect($mutasi->PathLampiran)->toStartWith("kasir/bukti-kas/{$k['Tenant']->Id}/")
        ->and(Storage::disk('local')->allFiles('kasir/bukti-kas'))->toHaveCount(1)
        ->and($mutasi->toArray())->not->toHaveKey('PathLampiran');

    BantuanPersediaan::MasukSebagai($this, $k['Tenant']->Id, PeranTenantBawaan::ManajerOutlet);
    $this->get("/kelola/kasir/shift/{$shift['Uuid']}")->assertOk()->assertInertia(fn (AssertableInertia $h) => $h
        ->where('MutasiKas.0.AdaBukti', true)
        ->where('MutasiKas.1.AdaBukti', false)
        ->missing('MutasiKas.0.PathLampiran'));
    $this->get("/kelola/kasir/mutasi-kas/{$keluar['Uuid']}/bukti")->assertOk()->assertHeader('Content-Type', 'image/jpeg');
    $this->get("/kelola/kasir/mutasi-kas/{$masuk['Uuid']}/bukti")->assertNotFound();

    // Kasir tanpa izin laporan = 403; tenant lain = 404.
    BantuanPersediaan::MasukSebagai($this, $k['Tenant']->Id, PeranTenantBawaan::Kasir);
    $this->get("/kelola/kasir/mutasi-kas/{$keluar['Uuid']}/bukti")->assertForbidden();
    $lain = BantuanKasir::Siapkan($this, 'Toko Lain Sejahtera');
    BantuanPersediaan::MasukSebagai($this, $lain['Tenant']->Id);
    $this->get("/kelola/kasir/mutasi-kas/{$keluar['Uuid']}/bukti")->assertNotFound();
});

it('foto bukti bukan JPEG, terlalu besar, atau pada setoran ditolak tanpa berkas tertinggal', function (): void {
    $k = BantuanKasir::Siapkan($this);
    $shift = BantuanKasir::ItemBukaShift($k['Kasir']);
    BantuanKasir::KirimRingkas($this, $k['Token'], [$shift]);
    config(['kasir.UkuranMaksimalBuktiKasKb' => 2]);

    expect(BantuanKasir::KirimRingkas($this, $k['Token'], [
        BantuanKasir::ItemMutasiKas($shift['Uuid'], $k['Kasir'], 'Keluar', '45000.00', $k['KategoriKeluar'], ['Bukti' => base64_encode('%PDF-1.4 bukan foto')]),
        BantuanKasir::ItemMutasiKas($shift['Uuid'], $k['Kasir'], 'Keluar', '45000.00', $k['KategoriKeluar'], ['Bukti' => base64_encode("\xFF\xD8\xFF".str_repeat('a', 2097))]),
        BantuanKasir::ItemMutasiKas($shift['Uuid'], $k['Kasir'], 'Setoran', '100000.00', null, ['Bukti' => FotoBuktiUji()]),
    ]))->toBe([['Ditolak', 'BuktiTidakValid'], ['Ditolak', 'BuktiTerlaluBesar'], ['Ditolak', 'BuktiTidakValid']])
        ->and(Storage::disk('local')->allFiles('kasir/bukti-kas'))->toBe([]);
});
