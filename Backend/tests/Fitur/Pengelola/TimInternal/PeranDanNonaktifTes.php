<?php

declare(strict_types=1);

use App\Domain\Pengelola\TimInternal\Enum\PeranPengelolaBawaan;
use App\Domain\Pengelola\TimInternal\Model\LogAuditPengelola;
use Inertia\Testing\AssertableInertia;
use Tests\Pendukung\Pengelola\BantuanPengelola;

describe('Tetapkan peran & nonaktifkan anggota (P-01 langkah 5–6, BR-P01.1)', function (): void {
    it('menetapkan lebih dari satu peran dan mencatat nilai lama/baru', function (): void {
        $superAdmin = BantuanPengelola::BuatAnggota(PeranPengelolaBawaan::SuperAdmin);
        $anggota = BantuanPengelola::BuatAnggota(PeranPengelolaBawaan::Analis);

        $this->actingAs($superAdmin, 'pengelola')
            ->withSession(BantuanPengelola::SesiTerverifikasi())
            ->withServerVariables(['REMOTE_ADDR' => '10.1.2.3'])
            ->put(BantuanPengelola::Url("/tim-internal/{$anggota->Uuid}/peran"), [
                'KodePeran' => ['Keuangan', 'Dukungan'],
                'Alasan' => 'Tim kecil merangkap',
            ])
            ->assertSessionHasNoErrors();

        $anggota->LupakanIzin();
        expect($anggota->DaftarKodePeran())->toEqualCanonicalizing(['Keuangan', 'Dukungan']);

        $log = LogAuditPengelola::query()->where('Aksi', 'tim.peran.tetapkan')->sole();
        expect($log->IdPenggunaPengelola)->toBe($superAdmin->Id)
            ->and($log->IdObjek)->toBe($anggota->Id)
            ->and($log->NilaiLama)->toBe(['KodePeran' => ['Analis']])
            ->and($log->NilaiBaru)->toBe(['KodePeran' => ['Dukungan', 'Keuangan']])
            ->and($log->Alasan)->toBe('Tim kecil merangkap')
            ->and($log->Ip)->toBe('10.1.2.3');
    });

    it('BR-P01.1: menolak menonaktifkan Super Admin bila Super Admin aktif hanya 2', function (): void {
        $pelaku = BantuanPengelola::BuatAnggota(PeranPengelolaBawaan::SuperAdmin);
        $target = BantuanPengelola::BuatAnggota(PeranPengelolaBawaan::SuperAdmin);

        $this->actingAs($pelaku, 'pengelola')
            ->withSession(BantuanPengelola::SesiTerverifikasi())
            ->post(BantuanPengelola::Url("/tim-internal/{$target->Uuid}/nonaktifkan"), ['Alasan' => 'Keluar'])
            ->assertSessionHasErrors('Umum');
        expect(session('errors')->first('Umum'))->toContain('minimal 2 Super Admin');

        expect($target->refresh()->Aktif)->toBeTrue();
        $this->assertDatabaseMissing('LogAuditPengelola', ['Aksi' => 'tim.anggota.nonaktifkan']);
    });

    it('BR-P01.1: mengizinkan menonaktifkan Super Admin bila masih tersisa minimal 2', function (): void {
        $pelaku = BantuanPengelola::BuatAnggota(PeranPengelolaBawaan::SuperAdmin);
        BantuanPengelola::BuatAnggota(PeranPengelolaBawaan::SuperAdmin);
        $target = BantuanPengelola::BuatAnggota(PeranPengelolaBawaan::SuperAdmin);

        $this->actingAs($pelaku, 'pengelola')
            ->withSession(BantuanPengelola::SesiTerverifikasi())
            ->post(BantuanPengelola::Url("/tim-internal/{$target->Uuid}/nonaktifkan"), ['Alasan' => 'Keluar dari perusahaan'])
            ->assertSessionHasNoErrors();

        $target->refresh();
        expect($target->Aktif)->toBeFalse()
            ->and($target->DinonaktifkanPada)->not->toBeNull();
        $this->assertDatabaseHas('PenggunaPengelola', ['Id' => $target->Id]);
        $this->assertDatabaseHas('LogAuditPengelola', [
            'Aksi' => 'tim.anggota.nonaktifkan',
            'IdObjek' => $target->Id,
            'Alasan' => 'Keluar dari perusahaan',
        ]);
    });

    it('BR-P01.1: menolak mencabut peran Super Admin bila Super Admin aktif hanya 2', function (): void {
        $pelaku = BantuanPengelola::BuatAnggota(PeranPengelolaBawaan::SuperAdmin);
        $target = BantuanPengelola::BuatAnggota(PeranPengelolaBawaan::SuperAdmin);

        $this->actingAs($pelaku, 'pengelola')
            ->withSession(BantuanPengelola::SesiTerverifikasi())
            ->put(BantuanPengelola::Url("/tim-internal/{$target->Uuid}/peran"), ['KodePeran' => ['Analis']])
            ->assertSessionHasErrors('KodePeran');

        $target->LupakanIzin();
        expect($target->DaftarKodePeran())->toBe(['SuperAdmin']);
    });

    it('BR-P01.1: Super Admin yang sudah nonaktif tidak dihitung', function (): void {
        $pelaku = BantuanPengelola::BuatAnggota(PeranPengelolaBawaan::SuperAdmin);
        $target = BantuanPengelola::BuatAnggota(PeranPengelolaBawaan::SuperAdmin);
        $nonaktif = BantuanPengelola::BuatAnggota(PeranPengelolaBawaan::SuperAdmin);
        $nonaktif->update(['Aktif' => false]);

        $this->actingAs($pelaku, 'pengelola')
            ->withSession(BantuanPengelola::SesiTerverifikasi())
            ->post(BantuanPengelola::Url("/tim-internal/{$target->Uuid}/nonaktifkan"), ['Alasan' => 'Keluar'])
            ->assertSessionHasErrors('Umum');
    });

    it('anggota yang dinonaktifkan langsung terputus di request berikutnya', function (): void {
        $anggota = BantuanPengelola::BuatAnggota(PeranPengelolaBawaan::SuperAdmin);
        $this->actingAs($anggota, 'pengelola')->withSession(BantuanPengelola::SesiTerverifikasi());
        $this->get(BantuanPengelola::Url('/'))->assertOk();

        $anggota->update(['Aktif' => false, 'DinonaktifkanPada' => now()]);

        $this->get(BantuanPengelola::Url('/tim-internal'))
            ->assertRedirect(route('pengelola.masuk'))
            ->assertSessionHas('Kilat', fn (string $pesan) => str_contains($pesan, 'dinonaktifkan'));
        $this->assertGuest('pengelola');
    });

    it('nonaktifkan wajib menyertakan alasan', function (): void {
        $pelaku = BantuanPengelola::BuatAnggota(PeranPengelolaBawaan::SuperAdmin);
        $target = BantuanPengelola::BuatAnggota(PeranPengelolaBawaan::Analis);

        $this->actingAs($pelaku, 'pengelola')
            ->withSession(BantuanPengelola::SesiTerverifikasi())
            ->post(BantuanPengelola::Url("/tim-internal/{$target->Uuid}/nonaktifkan"), ['Alasan' => ''])
            ->assertSessionHasErrors(['Alasan' => 'Alasan wajib diisi.']);
    });

    it('menampilkan daftar tim, undangan menunggu, dan peringatan Super Admin < 2', function (): void {
        $superAdmin = BantuanPengelola::BuatAnggota(PeranPengelolaBawaan::SuperAdmin);
        BantuanPengelola::BuatAnggota(PeranPengelolaBawaan::Analis);

        $this->actingAs($superAdmin, 'pengelola')
            ->withSession(BantuanPengelola::SesiTerverifikasi())
            ->get(BantuanPengelola::Url('/tim-internal'))
            ->assertInertia(fn (AssertableInertia $halaman) => $halaman
                ->component('Pengelola/TimInternal/Daftar')
                ->has('Anggota', 2)
                ->has('Peran', 7)
                ->where('PeringatanSuperAdmin', true)
                ->missing('Anggota.0.KataSandi')
                ->missing('Anggota.0.Rahasia2fa'));
    });
});
