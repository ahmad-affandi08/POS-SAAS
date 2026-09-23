<?php

declare(strict_types=1);

use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Bersama\Tenant\TenantBelumDitetapkan;
use Tests\Pendukung\Model\UjiCatatan;

function AturTenant(int $idTenant): void
{
    app(KonteksTenant::class)->Atur($idTenant);
}

describe('Isolasi tenant lewat MilikTenant (PRD §13.4, CLAUDE.md #11)', function (): void {
    it('mengisi IdTenant otomatis dari tenant aktif', function (): void {
        AturTenant(7);

        $catatan = UjiCatatan::query()->create(['Judul' => 'Catatan tenant 7']);

        expect($catatan->IdTenant)->toBe(7);
    });

    it('hanya mengembalikan data milik tenant aktif', function (): void {
        AturTenant(1);
        UjiCatatan::query()->create(['Judul' => 'Milik tenant 1']);
        AturTenant(2);
        UjiCatatan::query()->create(['Judul' => 'Milik tenant 2']);

        AturTenant(1);
        expect(UjiCatatan::query()->pluck('Judul')->all())->toBe(['Milik tenant 1']);

        AturTenant(2);
        expect(UjiCatatan::query()->pluck('Judul')->all())->toBe(['Milik tenant 2']);
    });

    it('tidak bisa membuka data tenant lain walau Uuid-nya diketahui', function (): void {
        AturTenant(1);
        $milikTenantSatu = UjiCatatan::query()->create(['Judul' => 'Rahasia tenant 1']);

        AturTenant(2);
        expect(UjiCatatan::query()->where('Uuid', $milikTenantSatu->Uuid)->first())->toBeNull();
    });

    it('gagal tertutup ketika tenant belum ditetapkan', function (): void {
        app(KonteksTenant::class)->Kosongkan();

        UjiCatatan::query()->get();
    })->throws(TenantBelumDitetapkan::class);
});
