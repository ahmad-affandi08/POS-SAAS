<?php

declare(strict_types=1);

use App\Domain\Bersama\Status\StatusDataMaster;
use App\Domain\Pengelola\Referensi\Surel\PengingatHariLibur;
use App\Domain\Pengelola\TimInternal\Enum\PeranPengelolaBawaan;
use App\Domain\Pengelola\TimInternal\Model\PenggunaPengelola;
use App\Domain\Referensi\Enum\JenisHariLibur;
use App\Domain\Referensi\Kueri\HariLiburTerbit;
use App\Domain\Referensi\Model\HariLibur;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\Pendukung\Pengelola\BantuanPengelola;
use Tests\TestCase;

function MasukSebagaiPengelola(TestCase $tes, PenggunaPengelola $pengguna): TestCase
{
    return $tes->actingAs($pengguna, 'pengelola')->withSession(BantuanPengelola::SesiTerverifikasi());
}

function BuatDrafLibur(TestCase $tes, PenggunaPengelola $pengaju, string $tanggal, string $nama, ?string $dasar = 'SKB 3 Menteri 2026'): void
{
    MasukSebagaiPengelola($tes, $pengaju)
        ->post(BantuanPengelola::Url('/referensi/hari-libur'), ['Tanggal' => $tanggal, 'Nama' => $nama, 'Jenis' => 'Nasional', 'NomorDasarHukum' => $dasar])
        ->assertSessionHasNoErrors();
}

describe('Hari libur (P-02, BR-P02.2)', function (): void {
    it('draf satu tahun diajukan lalu terbit dengan 1 penyetuju selain pengaju', function (): void {
        $konten = BantuanPengelola::BuatAnggota(PeranPengelolaBawaan::KontenLegal);
        $keuangan = BantuanPengelola::BuatAnggota(PeranPengelolaBawaan::Keuangan);
        BuatDrafLibur($this, $konten, '2027-01-01', 'Tahun Baru 2027 Masehi');
        BuatDrafLibur($this, $konten, '2027-08-17', 'Hari Kemerdekaan RI');

        MasukSebagaiPengelola($this, $konten)->post(BantuanPengelola::Url('/referensi/hari-libur/tahun/2027/ajukan'))->assertSessionHasNoErrors();
        MasukSebagaiPengelola($this, $keuangan)
            ->post(BantuanPengelola::Url('/referensi/hari-libur/tahun/2027/tinjau'), ['Keputusan' => 'Setuju'])
            ->assertSessionHasNoErrors();

        expect(HariLibur::query()->where('Status', StatusDataMaster::Terbit->value)->count())->toBe(2)
            ->and(array_map(fn (HariLibur $hari) => $hari->Nama, app(HariLiburTerbit::class)->AmbilTahun(2027)))
            ->toBe(['Tahun Baru 2027 Masehi', 'Hari Kemerdekaan RI']);
    });

    it('pengaju tidak boleh meninjau pengajuannya sendiri', function (): void {
        $superAdmin = BantuanPengelola::BuatAnggota(PeranPengelolaBawaan::SuperAdmin);
        BuatDrafLibur($this, $superAdmin, '2027-01-01', 'Tahun Baru');
        MasukSebagaiPengelola($this, $superAdmin)->post(BantuanPengelola::Url('/referensi/hari-libur/tahun/2027/ajukan'));

        $this->post(BantuanPengelola::Url('/referensi/hari-libur/tahun/2027/tinjau'), ['Keputusan' => 'Setuju'])->assertSessionHasErrors('Umum');
        expect(HariLibur::query()->sole()->Status)->toBe(StatusDataMaster::MenungguTinjauan);
    });

    it('penolakan mengembalikan semua ke draf; hari libur terbit tidak bisa diubah atau dihapus', function (): void {
        $konten = BantuanPengelola::BuatAnggota(PeranPengelolaBawaan::KontenLegal);
        $keuangan = BantuanPengelola::BuatAnggota(PeranPengelolaBawaan::Keuangan);
        BuatDrafLibur($this, $konten, '2027-01-01', 'Tahun Baru');
        MasukSebagaiPengelola($this, $konten)->post(BantuanPengelola::Url('/referensi/hari-libur/tahun/2027/ajukan'));

        MasukSebagaiPengelola($this, $keuangan)
            ->post(BantuanPengelola::Url('/referensi/hari-libur/tahun/2027/tinjau'), ['Keputusan' => 'Tolak', 'Catatan' => 'Tanggal cuti bersama belum ada'])
            ->assertSessionHasNoErrors();
        $hari = HariLibur::query()->sole();
        expect($hari->Status)->toBe(StatusDataMaster::Draf);

        $this->travel(1)->minutes();
        MasukSebagaiPengelola($this, $konten)->post(BantuanPengelola::Url('/referensi/hari-libur/tahun/2027/ajukan'));
        MasukSebagaiPengelola($this, $keuangan)->post(BantuanPengelola::Url('/referensi/hari-libur/tahun/2027/tinjau'), ['Keputusan' => 'Setuju'])
            ->assertSessionHasNoErrors();

        MasukSebagaiPengelola($this, $konten)
            ->put(BantuanPengelola::Url("/referensi/hari-libur/{$hari->Uuid}"), ['Tanggal' => '2027-01-02', 'Nama' => 'Ubah', 'Jenis' => 'Nasional'])
            ->assertSessionHasErrors('Umum');
        $this->delete(BantuanPengelola::Url("/referensi/hari-libur/{$hari->Uuid}"))->assertSessionHasErrors('Umum');

        expect(fn () => $hari->refresh()->update(['Nama' => 'X']))->toThrow(LogicException::class)
            ->and($hari->refresh()->Tanggal->toDateString())->toBe('2027-01-01');
    });

    it('pengajuan wajib dasar hukum dan menolak tanggal ganda', function (): void {
        $konten = BantuanPengelola::BuatAnggota(PeranPengelolaBawaan::KontenLegal);
        BuatDrafLibur($this, $konten, '2027-01-01', 'Tahun Baru', null);

        MasukSebagaiPengelola($this, $konten)->post(BantuanPengelola::Url('/referensi/hari-libur/tahun/2027/ajukan'))->assertSessionHasErrors('Umum');
        $this->post(BantuanPengelola::Url('/referensi/hari-libur'), ['Tanggal' => '2027-01-01', 'Nama' => 'Ganda', 'Jenis' => 'Nasional'])
            ->assertSessionHasErrors('Tanggal');
    });

    it('izin: Keuangan tidak bisa membuat draf, Konten & Legal tidak bisa meninjau', function (): void {
        $konten = BantuanPengelola::BuatAnggota(PeranPengelolaBawaan::KontenLegal);
        $kontenLain = BantuanPengelola::BuatAnggota(PeranPengelolaBawaan::KontenLegal);
        $keuangan = BantuanPengelola::BuatAnggota(PeranPengelolaBawaan::Keuangan);

        MasukSebagaiPengelola($this, $keuangan)
            ->post(BantuanPengelola::Url('/referensi/hari-libur'), ['Tanggal' => '2027-01-01', 'Nama' => 'X', 'Jenis' => 'Nasional'])
            ->assertForbidden();
        BuatDrafLibur($this, $konten, '2027-01-01', 'Tahun Baru');
        MasukSebagaiPengelola($this, $konten)->post(BantuanPengelola::Url('/referensi/hari-libur/tahun/2027/ajukan'));
        MasukSebagaiPengelola($this, $kontenLain)
            ->post(BantuanPengelola::Url('/referensi/hari-libur/tahun/2027/tinjau'), ['Keputusan' => 'Setuju'])
            ->assertForbidden();
    });
});

describe('Pengingat hari libur (BR-P02.4)', function (): void {
    it('tidak mengirim pengingat sebelum 1 November', function (): void {
        Mail::fake();
        BantuanPengelola::BuatAnggota(PeranPengelolaBawaan::KontenLegal);
        $this->travelTo(Carbon::parse('2026-10-31 09:00', 'Asia/Jakarta'));

        $this->artisan('pengelola:ingatkan-hari-libur')->assertSuccessful();

        Mail::assertNothingSent();
    });

    it('mengingatkan Konten & Legal mulai 1 November dan menandai terlambat setelah 1 Desember', function (): void {
        Mail::fake();
        $konten = BantuanPengelola::BuatAnggota(PeranPengelolaBawaan::KontenLegal);
        BantuanPengelola::BuatAnggota(PeranPengelolaBawaan::Keuangan);

        $this->travelTo(Carbon::parse('2026-11-01 08:00', 'Asia/Jakarta'));
        $this->artisan('pengelola:ingatkan-hari-libur')->assertSuccessful();
        Mail::assertSent(PengingatHariLibur::class, fn (PengingatHariLibur $surel) => $surel->hasTo($konten->Email) && $surel->tahun === 2027 && ! $surel->terlambat);
        Mail::assertSentCount(1);

        $this->travelTo(Carbon::parse('2026-12-02 08:00', 'Asia/Jakarta'));
        $this->artisan('pengelola:ingatkan-hari-libur')->assertSuccessful();
        Mail::assertSent(PengingatHariLibur::class, fn (PengingatHariLibur $surel) => $surel->terlambat);
    });

    it('berhenti mengingatkan setelah hari libur tahun berikutnya terbit', function (): void {
        Mail::fake();
        BantuanPengelola::BuatAnggota(PeranPengelolaBawaan::KontenLegal);
        HariLibur::query()->create(['Tanggal' => '2027-01-01', 'Nama' => 'Tahun Baru', 'Jenis' => JenisHariLibur::Nasional, 'Status' => StatusDataMaster::Terbit]);
        $this->travelTo(Carbon::parse('2026-11-15 08:00', 'Asia/Jakarta'));

        $this->artisan('pengelola:ingatkan-hari-libur')->assertSuccessful();

        Mail::assertNothingSent();
    });

    it('mengirim ke Super Admin bila belum ada anggota Konten & Legal, dan terjadwal setiap hari', function (): void {
        Mail::fake();
        $superAdmin = BantuanPengelola::BuatAnggota(PeranPengelolaBawaan::SuperAdmin);
        $this->travelTo(Carbon::parse('2026-11-03 08:00', 'Asia/Jakarta'));

        $this->artisan('pengelola:ingatkan-hari-libur')->assertSuccessful();

        Mail::assertSent(PengingatHariLibur::class, fn (PengingatHariLibur $surel) => $surel->hasTo($superAdmin->Email));
        $this->artisan('schedule:list')->expectsOutputToContain('pengelola:ingatkan-hari-libur')->assertSuccessful();
    });
});
