<?php

declare(strict_types=1);

use App\Domain\Katalog\Impor\Enum\StatusBarisImpor;
use App\Domain\Katalog\Impor\Layanan\PemvalidasiImpor;
use App\Domain\Katalog\Impor\Model\ImporProdukBaris;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Pendukung\Katalog\BantuanImpor;
use Tests\Pendukung\Katalog\BantuanKatalog;
use Tests\Pendukung\Organisasi\BantuanOrganisasi;
use Tests\Pendukung\Tenant\BantuanPendaftaran;

/*
 * QA F-03 impor (CLAUDE.md #11): pembaruan massal baris impor saat validasi (penanda baris ganda) berjalan lewat
 * scope `MilikTenant` dengan tenant dari `KonteksTenant`, bukan dari data baris. Id baris tenant lain yang ikut
 * terkirim tidak pernah berubah; nilai dengan kutip/backslash tersimpan apa adanya.
 */

beforeEach(function (): void {
    BantuanPendaftaran::SiapkanPrasyarat();
    Storage::fake('local');
});

it('PerbaruiMassal hanya mengubah baris tenant konteks; Id baris tenant lain diabaikan', function (): void {
    $a = BantuanKatalog::SiapkanTenantProduk('Toko Sumber Rejeki');
    $masukA = BantuanKatalog::MasukSebagai($this, $a['Tenant']->Id);
    $imporA = BantuanImpor::Unggah($masukA, BantuanImpor::BuatCsv([['Nama Produk'], ['Kopi Tubruk'], ['Teh Tarik']]));
    BantuanImpor::Petakan($masukA, $imporA, ['UuidKelompokPajakBawaan' => $a['KelompokPajak']->Uuid])->assertSessionHasNoErrors();

    $b = BantuanKatalog::SiapkanTenantProduk('Warung Bu Tini');
    $masukB = BantuanKatalog::MasukSebagai($this, $b['Tenant']->Id);
    $imporB = BantuanImpor::Unggah($masukB, BantuanImpor::BuatCsv([['Nama Produk'], ['Es Cendol']]));
    BantuanImpor::Petakan($masukB, $imporB, ['UuidKelompokPajakBawaan' => $b['KelompokPajak']->Uuid])->assertSessionHasNoErrors();

    $idA = DB::table('ImporProdukBaris')->where('IdImporProduk', $imporA->Id)->orderBy('Id')->pluck('Id')->all();
    $idB = DB::table('ImporProdukBaris')->where('IdImporProduk', $imporB->Id)->pluck('Id')->all();
    expect($idA)->toHaveCount(2)->and($idB)->toHaveCount(1);

    $galat = json_encode([['Bidang' => 'Nama Produk', 'Pesan' => "Nama O'Brien \"ganda\" \\ baris"]], JSON_UNESCAPED_UNICODE);
    $perubahan = [];

    foreach ([...$idA, ...$idB] as $id) {
        $perubahan[(int) $id] = ['Status' => StatusBarisImpor::Galat->value, 'Galat' => $galat];
    }

    BantuanOrganisasi::AturKonteks($a['Tenant']->Id);
    (new ReflectionMethod(PemvalidasiImpor::class, 'PerbaruiMassal'))->invoke(null, $perubahan);

    $statusA = ImporProdukBaris::query()->whereKey($idA)->get();
    expect($statusA->pluck('Status')->all())->toBe([StatusBarisImpor::Galat, StatusBarisImpor::Galat])
        ->and($statusA->first()?->Galat)->toEqual([['Bidang' => 'Nama Produk', 'Pesan' => "Nama O'Brien \"ganda\" \\ baris"]])
        ->and(DB::table('ImporProdukBaris')->whereIn('Id', $idB)->value('Status'))->toBe(StatusBarisImpor::Valid->value)
        ->and(DB::table('ImporProdukBaris')->whereIn('Id', $idB)->value('Galat'))->toBeNull();
});
