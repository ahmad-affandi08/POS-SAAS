<?php

declare(strict_types=1);

use App\Domain\Organisasi\Kueri\PetaUuidOutlet;
use Tests\Pendukung\Katalog\BantuanKatalog;
use Tests\Pendukung\Organisasi\BantuanOrganisasi;
use Tests\Pendukung\Tenant\BantuanPendaftaran;

beforeEach(function (): void {
    BantuanPendaftaran::SiapkanPrasyarat();
});

it('memetakan Id outlet tenant aktif ke Uuid dan mengabaikan outlet tenant lain (isolasi tenant)', function (): void {
    ['Tenant' => $tenantA, 'Outlet' => $outletA] = BantuanKatalog::BuatTenant('Kopi Senja Solo');
    ['Outlet' => $outletB] = BantuanKatalog::BuatTenant('Kopi Pagi Semarang');

    BantuanOrganisasi::AturKonteks($tenantA->Id);
    $kueri = app(PetaUuidOutlet::class);

    expect($kueri->Ambil([$outletA->Id, $outletB->Id, $outletA->Id, 999999]))->toBe([$outletA->Id => $outletA->Uuid])
        ->and($kueri->Ambil([]))->toBe([]);
});
