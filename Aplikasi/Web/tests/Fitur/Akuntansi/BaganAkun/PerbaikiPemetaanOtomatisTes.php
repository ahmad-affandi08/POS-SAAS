<?php

declare(strict_types=1);

use App\Domain\Akuntansi\Enum\PeranAkun;
use App\Domain\Akuntansi\Enum\SaldoNormal;
use App\Domain\Akuntansi\Enum\TipeAkun;
use App\Domain\Akuntansi\Kueri\KesiapanPeranAkun;
use App\Domain\Akuntansi\Model\Akun;
use App\Domain\Akuntansi\Model\PemetaanAkun;
use App\Domain\Bersama\Audit\Model\LogAudit;
use App\Domain\Organisasi\Enum\PeranTenantBawaan;
use Tests\Pendukung\Katalog\BantuanKatalog;
use Tests\Pendukung\Organisasi\BantuanOrganisasi;
use Tests\Pendukung\PanduanAwal\BantuanPanduanAwal;
use Tests\Pendukung\Tenant\BantuanPendaftaran;

/*
 * Audit kemudahan pakai #12: "Perbaiki otomatis" melengkapi bagan akun & pemetaan peran akun yang belum ada dari
 * template sektor (aditif), sehingga posting persediaan tidak lagi buntu di `PemetaanAkunBelumAda`. Pemetaan yang
 * sudah diatur tidak ditimpa; kiriman kedua tidak mengubah apa pun; pelaku tanpa `akuntansi.kelola` ditolak.
 */

beforeEach(function (): void {
    BantuanPendaftaran::SiapkanPrasyarat();
});

it('tenant tanpa pemetaan: akun & pemetaan dilengkapi dari template, pilihan yang sudah ada tetap, kiriman ulang tidak mengubah apa pun', function (): void {
    BantuanPanduanAwal::TerbitkanTemplate('RTL-GEN');
    $t = BantuanKatalog::SiapkanTenantProduk('Toko Kelontong Akun Kosong Wonogiri');
    $kasPilihan = Akun::query()->create(['Kode' => '1-1199', 'Nama' => 'Kas Laci Toko', 'Jenis' => TipeAkun::Aset, 'SaldoNormal' => SaldoNormal::Debit, 'KasBank' => true]);
    PemetaanAkun::query()->create(['Kunci' => PeranAkun::KasOutlet->value, 'IdAkun' => $kasPilihan->Id, 'IdOutlet' => null]);
    expect(app(KesiapanPeranAkun::class)->Periksa([PeranAkun::PersediaanBarangDagang, PeranAkun::EkuitasSaldoAwal], null)['Siap'])->toBeFalse();

    BantuanKatalog::MasukSebagai($this, $t['Tenant']->Id)
        ->from('/kelola/persediaan/stok-awal')
        ->post('/kelola/akuntansi/pemetaan/perbaiki-otomatis')
        ->assertRedirect('/kelola/persediaan/stok-awal')
        ->assertSessionHas('Kilat', fn (string $kilat) => str_contains($kilat, 'pemetaan baru'));

    BantuanOrganisasi::AturKonteks($t['Tenant']->Id);
    expect(app(KesiapanPeranAkun::class)->Periksa([PeranAkun::PersediaanBarangDagang, PeranAkun::EkuitasSaldoAwal], null)['Siap'])->toBeTrue()
        ->and(PemetaanAkun::query()->where('Kunci', PeranAkun::KasOutlet->value)->whereNull('IdOutlet')->value('IdAkun'))->toBe($kasPilihan->Id)
        ->and(LogAudit::query()->where('Peristiwa', 'akun.pemetaan.lengkapi-otomatis')->count())->toBe(1);
    $jumlahPemetaan = PemetaanAkun::query()->count();

    BantuanKatalog::MasukSebagai($this, $t['Tenant']->Id)->post('/kelola/akuntansi/pemetaan/perbaiki-otomatis')
        ->assertSessionHas('Kilat', 'Pemetaan akun sudah lengkap. Tidak ada yang diubah.');
    BantuanOrganisasi::AturKonteks($t['Tenant']->Id);
    expect(PemetaanAkun::query()->count())->toBe($jumlahPemetaan)
        ->and(LogAudit::query()->where('Peristiwa', 'akun.pemetaan.lengkapi-otomatis')->count())->toBe(1);
});

it('tanpa izin akuntansi.kelola ditolak 403 dan tidak menulis apa pun', function (): void {
    BantuanPanduanAwal::TerbitkanTemplate('RTL-GEN');
    $t = BantuanKatalog::SiapkanTenantProduk('Toko Kelontong Tanpa Izin Wonogiri');

    BantuanKatalog::MasukSebagai($this, $t['Tenant']->Id, PeranTenantBawaan::Kasir)
        ->post('/kelola/akuntansi/pemetaan/perbaiki-otomatis')
        ->assertForbidden();

    BantuanOrganisasi::AturKonteks($t['Tenant']->Id);
    expect(PemetaanAkun::query()->count())->toBe(0);
});
