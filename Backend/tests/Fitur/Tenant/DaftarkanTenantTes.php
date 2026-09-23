<?php

declare(strict_types=1);

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Organisasi\Model\Gudang;
use App\Domain\Organisasi\Model\Outlet;
use App\Domain\Organisasi\Model\TenantPengguna;
use App\Domain\Tenant\Aksi\DaftarkanTenant;
use App\Domain\Tenant\Enum\StatusLangganan;
use App\Domain\Tenant\Model\DokumenLegal;
use App\Domain\Tenant\Model\Langganan;
use App\Domain\Tenant\Model\PersetujuanDokumenLegal;
use App\Domain\Tenant\Model\Tenant;
use Illuminate\Support\Carbon;
use Tests\Pendukung\Tenant\BantuanPendaftaran;

beforeEach(function (): void {
    $this->travelTo(Carbon::parse('2026-09-23 10:00:00', 'Asia/Jakarta'));
    BantuanPendaftaran::SiapkanPrasyarat();
});

describe('Pendaftaran tenant (F-00)', function (): void {
    it('membentuk tenant, Owner, langganan Trial 14 hari, persetujuan legal, Outlet Utama, dan gudang bawaan', function (): void {
        $hasil = app(DaftarkanTenant::class)->Jalankan(BantuanPendaftaran::Data());
        $tenant = $hasil['Tenant'];

        $langganan = Langganan::query()->where('IdTenant', $tenant->Id)->sole();
        expect($tenant->Slug)->toBe('kopi-nusantara')
            ->and($hasil['Pengguna']->EmailDiverifikasiPada)->toBeNull()
            ->and(TenantPengguna::query()->where('IdTenant', $tenant->Id)->sole()->Pemilik)->toBeTrue()
            ->and($langganan->Status)->toBe(StatusLangganan::Trial)
            ->and($langganan->Paket->Kode)->toBe('PRO')
            ->and($langganan->TrialBerakhirPada?->equalTo(now()->addDays(14)))->toBeTrue()
            ->and(PersetujuanDokumenLegal::query()->where('IdTenant', $tenant->Id)->pluck('IdDokumenLegal')->sort()->values()->all())
            ->toBe(DokumenLegal::query()->orderBy('Id')->pluck('Id')->all())
            ->and(PersetujuanDokumenLegal::query()->first()?->Ip)->toBe('203.0.113.9');

        app(KonteksTenant::class)->Atur($tenant->Id);
        expect(Outlet::query()->sole()->Nama)->toBe('Outlet Utama')
            ->and(Gudang::query()->sole()->IdOutlet)->toBe(Outlet::query()->sole()->Id);
    });

    it('BR-00.2: slug unik dari nama usaha dan tidak memakai kata rute sistem', function (): void {
        app(DaftarkanTenant::class)->Jalankan(BantuanPendaftaran::Data());
        app(DaftarkanTenant::class)->Jalankan(BantuanPendaftaran::Data('dua@contoh.id', '081200000002'));
        app(DaftarkanTenant::class)->Jalankan(BantuanPendaftaran::Data('tiga@contoh.id', '081200000003', namaUsaha: 'Kelola'));

        expect(Tenant::query()->orderBy('Id')->pluck('Slug')->all())->toBe(['kopi-nusantara', 'kopi-nusantara-2', 'kelola-usaha']);
    });

    it('BR-00.1: email dan nomor WhatsApp unik; pendaftaran gagal tidak meninggalkan data', function (): void {
        app(DaftarkanTenant::class)->Jalankan(BantuanPendaftaran::Data());

        expect(fn () => app(DaftarkanTenant::class)->Jalankan(BantuanPendaftaran::Data(noHp: '081299999999')))
            ->toThrow(PelanggaranAturanBisnis::class, 'Email ini sudah terdaftar')
            ->and(fn () => app(DaftarkanTenant::class)->Jalankan(BantuanPendaftaran::Data('lain@contoh.id')))
            ->toThrow(PelanggaranAturanBisnis::class, 'Nomor WhatsApp')
            ->and(Tenant::query()->count())->toBe(1);
    });

    it('BR-00.6: paket pilihan dipakai bila aktif; GRATIS langsung berstatus Gratis; negosiasi memakai paket bawaan', function (): void {
        $starter = app(DaftarkanTenant::class)->Jalankan(BantuanPendaftaran::Data(kodePaket: 'STARTER'))['Tenant'];
        $gratis = app(DaftarkanTenant::class)->Jalankan(BantuanPendaftaran::Data('b@contoh.id', '081200000010', 'GRATIS'))['Tenant'];
        $enterprise = app(DaftarkanTenant::class)->Jalankan(BantuanPendaftaran::Data('c@contoh.id', '081200000011', 'ENTERPRISE'))['Tenant'];

        expect($starter->Langganan?->Paket->Kode)->toBe('STARTER')
            ->and($gratis->Langganan?->Status)->toBe(StatusLangganan::Gratis)
            ->and($gratis->Langganan?->TrialBerakhirPada)->toBeNull()
            ->and($enterprise->Langganan?->Paket->Kode)->toBe('PRO');
    });

    it('BR-P06.2: pendaftaran ditolak bila S&K atau Kebijakan Privasi belum berlaku', function (): void {
        DokumenLegal::query()->where('Jenis', 'KebijakanPrivasi')->delete();

        expect(fn () => app(DaftarkanTenant::class)->Jalankan(BantuanPendaftaran::Data()))->toThrow(PelanggaranAturanBisnis::class)
            ->and(Tenant::query()->count())->toBe(0);
    });

    it('BR-00.7: transisi status langganan di luar state machine ditolak', function (): void {
        $tenant = app(DaftarkanTenant::class)->Jalankan(BantuanPendaftaran::Data())['Tenant'];
        $langganan = Langganan::query()->where('IdTenant', $tenant->Id)->sole();

        expect(fn () => $langganan->update(['Status' => StatusLangganan::Ditangguhkan]))->toThrow(LogicException::class);
        $langganan->update(['Status' => StatusLangganan::Gratis]);
        expect($langganan->refresh()->Status)->toBe(StatusLangganan::Gratis);
    });
});
