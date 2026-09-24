<?php

declare(strict_types=1);

use App\Domain\Akuntansi\Model\Akun;
use App\Domain\Bersama\Audit\Model\LogAudit;
use App\Domain\Kasir\Model\KategoriKas;
use App\Domain\Organisasi\Enum\PeranTenantBawaan;
use App\Domain\Organisasi\Model\OutletPengguna;
use App\Domain\Tenant\Kueri\PengaturanKasirTenant;
use Inertia\Testing\AssertableInertia;
use Tests\Pendukung\Akuntansi\BantuanJurnal;
use Tests\Pendukung\Kasir\BantuanKasir;
use Tests\Pendukung\Organisasi\BantuanOrganisasi;
use Tests\Pendukung\Organisasi\BantuanPerangkat;
use Tests\Pendukung\Persediaan\BantuanPersediaan;
use Tests\Pendukung\Tenant\BantuanPendaftaran;

beforeEach(function (): void {
    BantuanPendaftaran::SiapkanPrasyarat();
});

describe('F-06 halaman back-office shift', function (): void {
    it('daftar & detail shift: ringkasan kas non-penjualan dan mutasi dengan jurnal; tautan sumber jurnal membuka shift', function (): void {
        $k = BantuanKasir::Siapkan($this);
        $shift = BantuanKasir::ItemBukaShift($k['Kasir'], '500000.00');
        $keluar = BantuanKasir::ItemMutasiKas($shift['Uuid'], $k['Kasir'], 'Keluar', '45000.00', $k['KategoriKeluar']);
        BantuanKasir::KirimRingkas($this, $k['Token'], [
            $shift,
            $keluar,
            BantuanKasir::ItemMutasiKas($shift['Uuid'], $k['Kasir'], 'Masuk', '20000.00', $k['KategoriMasuk']),
            BantuanKasir::ItemMutasiKas($shift['Uuid'], $k['Kasir'], 'Setoran', '300000.00', null),
        ]);

        BantuanPersediaan::MasukSebagai($this, $k['Tenant']->Id, PeranTenantBawaan::ManajerOutlet);

        $this->get('/kelola/kasir/shift')->assertOk()->assertInertia(fn (AssertableInertia $h) => $h
            ->component('Kelola/Kasir/Shift/Daftar')
            ->where('Shift.Total', 1)
            ->where('Shift.Data.0.Uuid', $shift['Uuid'])
            ->where('Shift.Data.0.NamaKasir', $k['Kasir']->Nama)
            ->where('Shift.Data.0.TotalMasuk', '20000.00')
            ->where('Shift.Data.0.TotalKeluar', '45000.00')
            ->where('Shift.Data.0.TotalSetoran', '300000.00')
            ->where('Shift.Data.0.KasNonPenjualan', '175000.00'));

        $this->get("/kelola/kasir/shift/{$shift['Uuid']}")->assertOk()->assertInertia(fn (AssertableInertia $h) => $h
            ->component('Kelola/Kasir/Shift/Detail')
            ->where('Shift.KasAwal', '500000.00')
            ->has('MutasiKas', 3)
            ->where('MutasiKas.0.NamaKategori', 'Beli es batu & galon')
            ->where('MutasiKas.0.NomorJurnal', fn (?string $nomor): bool => is_string($nomor) && str_starts_with($nomor, 'JU/')));

        $this->get("/kelola/kasir/mutasi-kas/{$keluar['Uuid']}")->assertRedirect("/kelola/kasir/shift/{$shift['Uuid']}");
    });

    it('izin & isolasi: Kasir 403; shift tenant lain 404; pengguna per outlet hanya melihat shift outletnya', function (): void {
        $a = BantuanKasir::Siapkan($this, 'Kopi Senja Solo');
        $shiftA = BantuanKasir::ItemBukaShift($a['Kasir']);
        BantuanKasir::KirimRingkas($this, $a['Token'], [$shiftA]);

        BantuanOrganisasi::AturKonteks($a['Tenant']->Id);
        $cabang = BantuanJurnal::BuatOutlet();
        $perangkatCabang = BantuanPerangkat::BuatDanAktifkan($this, $a['Tenant']->Id, $cabang, 'Kasir Cabang');
        $shiftCabang = BantuanKasir::ItemBukaShift($a['Pemilik']);
        expect(BantuanKasir::KirimRingkas($this, $perangkatCabang['Token'], [$shiftCabang]))->toBe([['Diterima', null]]);

        BantuanPersediaan::MasukSebagai($this, $a['Tenant']->Id, PeranTenantBawaan::Kasir);
        $this->get('/kelola/kasir/shift')->assertForbidden();

        BantuanOrganisasi::AturKonteks($a['Tenant']->Id);
        $manajerCabang = BantuanOrganisasi::TambahAnggota($a['Tenant']->Id, PeranTenantBawaan::ManajerOutlet, semuaOutlet: false);
        OutletPengguna::query()->create(['IdOutlet' => $cabang->Id, 'IdPengguna' => $manajerCabang->Id, 'IdPeran' => BantuanOrganisasi::Peran($a['Tenant']->Id, PeranTenantBawaan::ManajerOutlet)->Id]);
        BantuanOrganisasi::Masuk($this, $manajerCabang, $a['Tenant']->Id);

        $this->get('/kelola/kasir/shift')->assertOk()->assertInertia(fn (AssertableInertia $h) => $h
            ->where('Shift.Total', 1)
            ->where('Shift.Data.0.Uuid', $shiftCabang['Uuid']));
        $this->get("/kelola/kasir/shift/{$shiftA['Uuid']}")->assertNotFound();

        $b = BantuanKasir::Siapkan($this, 'Warung Bakso Pak Kumis');
        BantuanPersediaan::MasukSebagai($this, $b['Tenant']->Id);
        $this->get("/kelola/kasir/shift/{$shiftA['Uuid']}")->assertNotFound();
    });
});

describe('F-06 kategori kas', function (): void {
    it('tambah, ubah, nonaktifkan: akun wajib sesuai jenis, nama unik per jenis, jenis tidak bisa diubah; diaudit', function (): void {
        $k = BantuanKasir::Siapkan($this);
        BantuanPersediaan::MasukSebagai($this, $k['Tenant']->Id, PeranTenantBawaan::Akuntan);
        BantuanOrganisasi::AturKonteks($k['Tenant']->Id);
        $beban = Akun::query()->where('Kode', '6-2000')->sole();
        $pendapatan = Akun::query()->where('Kode', '4-9000')->sole();

        $this->get('/kelola/kasir/kategori-kas')->assertOk()->assertInertia(fn (AssertableInertia $h) => $h
            ->component('Kelola/Kasir/KategoriKas')
            ->has('Kategori', 2)
            ->where('OpsiAkun.Keluar', fn ($opsi): bool => collect($opsi)->contains('Uuid', $beban->Uuid) && ! collect($opsi)->contains('Uuid', $pendapatan->Uuid)));

        $this->post('/kelola/kasir/kategori-kas', ['Nama' => 'Bayar parkir motor', 'Jenis' => 'Keluar', 'UuidAkun' => $beban->Uuid])->assertRedirect('/kelola/kasir/kategori-kas');
        $this->post('/kelola/kasir/kategori-kas', ['Nama' => 'Bayar parkir motor', 'Jenis' => 'Keluar', 'UuidAkun' => $beban->Uuid])->assertSessionHasErrors('Nama');
        $this->post('/kelola/kasir/kategori-kas', ['Nama' => 'Salah akun', 'Jenis' => 'Keluar', 'UuidAkun' => $pendapatan->Uuid])->assertSessionHasErrors('UuidAkun');

        BantuanOrganisasi::AturKonteks($k['Tenant']->Id);
        $parkir = KategoriKas::query()->where('Nama', 'Bayar parkir motor')->sole();
        $this->put("/kelola/kasir/kategori-kas/{$parkir->Uuid}", ['Nama' => 'Parkir & retribusi', 'Jenis' => 'Masuk', 'UuidAkun' => $pendapatan->Uuid])->assertSessionHasErrors('Jenis');
        $this->put("/kelola/kasir/kategori-kas/{$parkir->Uuid}", ['Nama' => 'Parkir & retribusi', 'Jenis' => 'Keluar', 'UuidAkun' => $beban->Uuid])->assertRedirect();
        $this->put("/kelola/kasir/kategori-kas/{$parkir->Uuid}/status", ['Aktif' => false])->assertRedirect();

        BantuanOrganisasi::AturKonteks($k['Tenant']->Id);
        $parkir->refresh();
        expect($parkir->Nama)->toBe('Parkir & retribusi')
            ->and($parkir->Aktif)->toBeFalse()
            ->and(LogAudit::query()->where('Peristiwa', 'kas.kategori.simpan')->count())->toBe(2)
            ->and(LogAudit::query()->where('Peristiwa', 'kas.kategori.status')->count())->toBe(1);
    });

    it('izin akuntansi.kelola (Manajer Outlet 403); kategori tenant lain 404', function (): void {
        $a = BantuanKasir::Siapkan($this, 'Kopi Senja Solo');
        BantuanPersediaan::MasukSebagai($this, $a['Tenant']->Id, PeranTenantBawaan::ManajerOutlet);
        $this->get('/kelola/kasir/kategori-kas')->assertForbidden();

        $b = BantuanKasir::Siapkan($this, 'Warung Bakso Pak Kumis');
        BantuanPersediaan::MasukSebagai($this, $b['Tenant']->Id);
        $this->put("/kelola/kasir/kategori-kas/{$a['KategoriKeluar']->Uuid}/status", ['Aktif' => false])->assertNotFound();

        BantuanOrganisasi::AturKonteks($a['Tenant']->Id);
        expect($a['KategoriKeluar']->fresh()?->Aktif)->toBeTrue();
    });
});

describe('F-06 pengaturan kasir', function (): void {
    it('BR-06.4/BR-06.2 bawaan Rp 200.000 & shift bersama mati; simpan tercatat audit; izin outlet.kelola; nilai tidak valid ditolak', function (): void {
        $k = BantuanKasir::Siapkan($this);
        BantuanPersediaan::MasukSebagai($this, $k['Tenant']->Id, PeranTenantBawaan::Kasir);
        $this->get('/kelola/kasir/pengaturan')->assertForbidden();

        BantuanPersediaan::MasukSebagai($this, $k['Tenant']->Id, PeranTenantBawaan::Admin);
        $this->get('/kelola/kasir/pengaturan')->assertOk()->assertInertia(fn (AssertableInertia $h) => $h
            ->component('Kelola/Kasir/Pengaturan')
            ->where('BatasKasKeluar', '200000.00')
            ->where('ShiftBersama', false));

        $this->put('/kelola/kasir/pengaturan', ['BatasKasKeluar' => '-1', 'ShiftBersama' => true])->assertSessionHasErrors('BatasKasKeluar');
        $this->put('/kelola/kasir/pengaturan', ['BatasKasKeluar' => '1.5e6', 'ShiftBersama' => true])->assertSessionHasErrors('BatasKasKeluar');
        $this->put('/kelola/kasir/pengaturan', ['BatasKasKeluar' => '500000', 'ShiftBersama' => true])->assertRedirect('/kelola/kasir/pengaturan');

        BantuanOrganisasi::AturKonteks($k['Tenant']->Id);
        $pengaturan = app(PengaturanKasirTenant::class)->Ambil();
        expect($pengaturan->batasKasKeluar->KeString())->toBe('500000.00')
            ->and($pengaturan->shiftBersama)->toBeTrue()
            ->and(LogAudit::query()->where('Peristiwa', 'kasir.pengaturan.ubah')->count())->toBe(1);

        $this->put('/kelola/kasir/pengaturan', ['BatasKasKeluar' => '500000.00', 'ShiftBersama' => true])->assertRedirect();
        BantuanOrganisasi::AturKonteks($k['Tenant']->Id);
        expect(LogAudit::query()->where('Peristiwa', 'kasir.pengaturan.ubah')->count())->toBe(1);
    });
});
