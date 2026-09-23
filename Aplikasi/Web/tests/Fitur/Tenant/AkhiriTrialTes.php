<?php

declare(strict_types=1);

use App\Domain\Tenant\Aksi\DaftarkanTenant;
use App\Domain\Tenant\Enum\StatusLangganan;
use App\Domain\Tenant\Model\Langganan;
use App\Domain\Tenant\Model\Tenant;
use Illuminate\Support\Carbon;
use Tests\Pendukung\Tenant\BantuanPendaftaran;

beforeEach(function (): void {
    $this->travelTo(Carbon::parse('2026-09-23 10:00:00', 'Asia/Jakarta'));
    BantuanPendaftaran::SiapkanPrasyarat();
});

describe('Akhir trial (BR-00.3)', function (): void {
    it('trial yang lewat turun ke paket Gratis tanpa menghapus tenant; yang belum lewat tidak berubah; idempoten', function (): void {
        $lama = app(DaftarkanTenant::class)->Jalankan(BantuanPendaftaran::Data())['Tenant'];
        $this->travel(10)->days();
        $baru = app(DaftarkanTenant::class)->Jalankan(BantuanPendaftaran::Data('baru@contoh.id', '081200000099', namaUsaha: 'Toko Baru'))['Tenant'];
        $this->travel(5)->days();

        $this->artisan('tenant:akhiri-trial')->expectsOutputToContain('1 langganan')->assertSuccessful();
        $this->artisan('tenant:akhiri-trial')->expectsOutputToContain('0 langganan')->assertSuccessful();

        $langgananLama = Langganan::query()->where('IdTenant', $lama->Id)->sole();
        expect($langgananLama->Status)->toBe(StatusLangganan::Gratis)
            ->and($langgananLama->Paket->Kode)->toBe('GRATIS')
            ->and(Langganan::query()->where('IdTenant', $baru->Id)->sole()->Status)->toBe(StatusLangganan::Trial)
            ->and(Tenant::query()->count())->toBe(2);
    });

    it('langganan yang sudah dibayar (Aktif) tidak diturunkan walau tanggal trial sudah lewat', function (): void {
        $tenant = app(DaftarkanTenant::class)->Jalankan(BantuanPendaftaran::Data())['Tenant'];
        Langganan::query()->where('IdTenant', $tenant->Id)->sole()->update(['Status' => StatusLangganan::Aktif]);
        $this->travel(20)->days();

        $this->artisan('tenant:akhiri-trial')->assertSuccessful();

        expect(Langganan::query()->where('IdTenant', $tenant->Id)->sole()->Status)->toBe(StatusLangganan::Aktif);
    });
});
