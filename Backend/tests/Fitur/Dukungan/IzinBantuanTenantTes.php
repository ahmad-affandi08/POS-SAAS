<?php

declare(strict_types=1);

use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Dukungan\Model\TiketDukungan;
use App\Domain\Organisasi\Enum\IzinTenant;
use App\Domain\Organisasi\Enum\PeranTenantBawaan;
use App\Domain\Organisasi\Model\Pengguna;
use App\Domain\Organisasi\Model\Peran;
use App\Domain\Organisasi\Model\PeranIzin;
use App\Domain\Organisasi\Model\TenantPengguna;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Tests\Pendukung\Dukungan\BantuanDukungan;
use Tests\Pendukung\Organisasi\BantuanOrganisasi;

/** Anggota dengan peran kustom yang hanya memegang izin tertentu. */
function TambahAnggotaPeranKustomBantuanUji(int $idTenant, array $izin): Pengguna
{
    BantuanOrganisasi::AturKonteks($idTenant);
    $peran = Peran::query()->create(['Nama' => 'Staf Hubungan Pelanggan', 'Bawaan' => false]);

    foreach ($izin as $kunci) {
        PeranIzin::query()->create(['IdPeran' => $peran->Id, 'KunciIzin' => $kunci->value]);
    }

    $pengguna = Pengguna::factory()->create();
    TenantPengguna::query()->create(['IdTenant' => $idTenant, 'IdPengguna' => $pengguna->Id, 'IdPeran' => $peran->Id, 'SemuaOutlet' => true]);

    return $pengguna;
}

/** @return array<string, string> */
function IsianTiketIzinUji(): array
{
    return [
        'Kategori' => 'Perangkat',
        'Prioritas' => 'Normal',
        'Judul' => 'Laci kasir Outlet Solo Baru tidak terbuka otomatis',
        'Isi' => 'Sejak pembaruan kemarin laci kasir tidak terbuka saat pembayaran tunai Rp 1.250.000.',
    ];
}

beforeEach(function (): void {
    $this->travelTo(Carbon::parse('2026-09-24 09:00:00', 'Asia/Jakarta'));
    Mail::fake();
    Storage::fake('local');
    ['Tenant' => $this->tenant, 'Pengguna' => $this->pemilik] = BantuanDukungan::BuatTenant();
});

describe('Izin bantuan per peran (P-09, §19.1)', function (): void {
    it('peran bawaan: Pemilik, Admin, dan Manajer Outlet memegang lihat & kelola; Kasir dan Akuntan tidak', function (): void {
        foreach ([PeranTenantBawaan::Admin, PeranTenantBawaan::ManajerOutlet] as $peran) {
            expect(BantuanOrganisasi::Peran($this->tenant->Id, $peran)->AmbilKunciIzin())
                ->toContain(IzinTenant::BantuanTiketLihat->value, IzinTenant::BantuanTiketKelola->value);
        }

        foreach ([PeranTenantBawaan::Kasir, PeranTenantBawaan::Akuntan] as $peran) {
            expect(BantuanOrganisasi::Peran($this->tenant->Id, $peran)->AmbilKunciIzin())
                ->not->toContain(IzinTenant::BantuanTiketLihat->value);
        }
    });

    it('Kasir mendapat halaman Tanpa izin dan tidak bisa membuat tiket', function (): void {
        $kasir = BantuanOrganisasi::TambahAnggota($this->tenant->Id, PeranTenantBawaan::Kasir);

        BantuanDukungan::MasukSebagaiTenant($this, $kasir, $this->tenant)->get('/kelola/bantuan')
            ->assertForbidden()
            ->assertInertia(fn (AssertableInertia $halaman) => $halaman->component('Kelola/TanpaIzin'));
        $this->post('/kelola/bantuan', IsianTiketIzinUji())->assertForbidden();

        app(KonteksTenant::class)->Atur($this->tenant->Id);
        expect(TiketDukungan::query()->count())->toBe(0);
    });

    it('Manajer Outlet bisa membuat tiket', function (): void {
        $manajer = BantuanOrganisasi::TambahAnggota($this->tenant->Id, PeranTenantBawaan::ManajerOutlet);

        BantuanDukungan::MasukSebagaiTenant($this, $manajer, $this->tenant)
            ->post('/kelola/bantuan', IsianTiketIzinUji())
            ->assertSessionHasNoErrors();

        app(KonteksTenant::class)->Atur($this->tenant->Id);
        expect(TiketDukungan::query()->sole()->IdPelapor)->toBe($manajer->Id);
    });

    it('peran kustom dengan izin lihat saja: bisa membuka daftar & tiket, tidak bisa membuat, membalas, atau menyelesaikan', function (): void {
        $tiket = BantuanDukungan::BuatTiket($this->tenant, $this->pemilik);
        $staf = TambahAnggotaPeranKustomBantuanUji($this->tenant->Id, [IzinTenant::BantuanTiketLihat]);

        $tes = BantuanDukungan::MasukSebagaiTenant($this, $staf, $this->tenant);
        $tes->get('/kelola/bantuan')->assertOk();
        $tes->get("/kelola/bantuan/{$tiket->Uuid}")->assertOk();
        $tes->get('/kelola/bantuan/buat')->assertForbidden();
        $tes->post('/kelola/bantuan', IsianTiketIzinUji())->assertForbidden();
        $tes->post("/kelola/bantuan/{$tiket->Uuid}/balasan", ['Isi' => 'Sudah dicoba lagi, tetap gagal.'])->assertForbidden();
        $tes->post("/kelola/bantuan/{$tiket->Uuid}/selesaikan")->assertForbidden();
    });

    it('organisasi:siapkan-peran menambahkan izin bantuan ke peran bawaan tenant lama', function (): void {
        $manajer = BantuanOrganisasi::Peran($this->tenant->Id, PeranTenantBawaan::ManajerOutlet);
        PeranIzin::query()->where('IdPeran', $manajer->Id)
            ->whereIn('KunciIzin', [IzinTenant::BantuanTiketLihat->value, IzinTenant::BantuanTiketKelola->value])
            ->delete();
        expect($manajer->refresh()->AmbilKunciIzin())->not->toContain(IzinTenant::BantuanTiketLihat->value);

        $this->artisan('organisasi:siapkan-peran')->assertSuccessful();

        BantuanOrganisasi::AturKonteks($this->tenant->Id);
        expect(Peran::query()->whereKey($manajer->Id)->sole()->AmbilKunciIzin())
            ->toContain(IzinTenant::BantuanTiketLihat->value, IzinTenant::BantuanTiketKelola->value);
    });
});
