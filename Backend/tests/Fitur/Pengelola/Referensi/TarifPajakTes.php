<?php

declare(strict_types=1);

use App\Domain\Bersama\Status\StatusDataMaster;
use App\Domain\Pajak\Kueri\TarifPajakBerlaku;
use App\Domain\Pajak\Model\JenisPajak;
use App\Domain\Pajak\Model\TarifPajak;
use App\Domain\Pajak\Peristiwa\TarifPajakTerbit;
use App\Domain\Pengelola\Referensi\Aksi\SiapkanPajakBawaan;
use App\Domain\Pengelola\Referensi\Model\PersetujuanDataMaster;
use App\Domain\Pengelola\TimInternal\Enum\PeranPengelolaBawaan;
use App\Domain\Pengelola\TimInternal\Model\PenggunaPengelola;
use App\Domain\Referensi\Enum\TingkatWilayah;
use App\Domain\Referensi\Enum\ZonaWaktu;
use App\Domain\Referensi\Model\Wilayah;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;
use Tests\Pendukung\Pengelola\BantuanPengelola;
use Tests\TestCase;

/**
 * @param  array<string, mixed>  $ubah
 * @return array<string, mixed>
 */
function DataTarif(array $ubah = []): array
{
    return [
        'KodeJenisPajak' => 'Ppn',
        'Tarif' => '12',
        'PengaliDppPembilang' => 11,
        'PengaliDppPenyebut' => 12,
        'KodeWilayah' => null,
        'BiayaLayananMasukDpp' => false,
        'BerlakuMulai' => '2027-01-01',
        'NomorDasarHukum' => 'PMK 999 Tahun 2026',
        'TautanDasarHukum' => null,
        ...$ubah,
    ];
}

function SebagaiPengelola(TestCase $tes, PenggunaPengelola $pengguna): TestCase
{
    return $tes->actingAs($pengguna, 'pengelola')->withSession(BantuanPengelola::SesiTerverifikasi());
}

/** Buat draf lewat HTTP sebagai Konten & Legal, ajukan, lalu kembalikan tarifnya. */
function AjukanTarifBaru(TestCase $tes, PenggunaPengelola $pengaju, array $ubah = []): TarifPajak
{
    SebagaiPengelola($tes, $pengaju)->post(BantuanPengelola::Url('/referensi/tarif-pajak'), DataTarif($ubah))->assertSessionHasNoErrors();
    $tarif = TarifPajak::query()->latest('Id')->firstOrFail();
    $tes->post(BantuanPengelola::Url("/referensi/tarif-pajak/{$tarif->Uuid}/ajukan"))->assertSessionHasNoErrors();

    return $tarif->refresh();
}

function Tinjau(TestCase $tes, PenggunaPengelola $peninjau, TarifPajak $tarif, string $keputusan = 'Setuju', ?string $catatan = null): TestResponse
{
    return SebagaiPengelola($tes, $peninjau)->post(
        BantuanPengelola::Url("/referensi/tarif-pajak/{$tarif->Uuid}/tinjau"),
        ['Keputusan' => $keputusan, 'Catatan' => $catatan],
    );
}

beforeEach(function (): void {
    app(SiapkanPajakBawaan::class)->Jalankan();
    TarifPajak::query()->delete();
    Wilayah::query()->create(['Kode' => '33', 'Nama' => 'Jawa Tengah', 'Tingkat' => TingkatWilayah::Provinsi, 'ZonaWaktu' => ZonaWaktu::Wib]);
    Wilayah::query()->create(['Kode' => '33.74', 'Nama' => 'Kota Semarang', 'Tingkat' => TingkatWilayah::KabupatenKota, 'KodeInduk' => '33', 'ZonaWaktu' => ZonaWaktu::Wib]);
});

describe('Tarif pajak bertanggal (P-02, BR-P02.1, BR-P02.2)', function (): void {
    it('BR-P02.2: tarif nasional terbit setelah 2 penyetuju berbeda, bukan pengaju', function (): void {
        Event::fake([TarifPajakTerbit::class]);
        $pengaju = BantuanPengelola::BuatAnggota(PeranPengelolaBawaan::KontenLegal);
        $keuangan = BantuanPengelola::BuatAnggota(PeranPengelolaBawaan::Keuangan);
        $superAdmin = BantuanPengelola::BuatAnggota(PeranPengelolaBawaan::SuperAdmin);
        $tarif = AjukanTarifBaru($this, $pengaju);

        expect($tarif->Status)->toBe(StatusDataMaster::MenungguTinjauan)
            ->and($tarif->PengaliDppPembilang)->toBe(11)
            ->and($tarif->PengaliDppPenyebut)->toBe(12)
            ->and($tarif->Tarif)->toBe('12.000000');

        Tinjau($this, $keuangan, $tarif)->assertSessionHasNoErrors();
        expect($tarif->refresh()->Status)->toBe(StatusDataMaster::MenungguTinjauan);

        Tinjau($this, $keuangan, $tarif)->assertSessionHasErrors('Umum');
        expect($tarif->refresh()->Status)->toBe(StatusDataMaster::MenungguTinjauan);

        Tinjau($this, $superAdmin, $tarif)->assertSessionHasNoErrors();
        expect($tarif->refresh()->Status)->toBe(StatusDataMaster::Terbit);
        Event::assertDispatched(TarifPajakTerbit::class, fn (TarifPajakTerbit $peristiwa) => $peristiwa->idTarifPajak === $tarif->Id);
    });

    it('BR-P02.2: pengaju tidak boleh menyetujui pengajuannya sendiri, walau Super Admin', function (): void {
        $superAdmin = BantuanPengelola::BuatAnggota(PeranPengelolaBawaan::SuperAdmin);
        $tarif = AjukanTarifBaru($this, $superAdmin);

        Tinjau($this, $superAdmin, $tarif)->assertSessionHasErrors('Umum');
        expect(PersetujuanDataMaster::query()->count())->toBe(0);
    });

    it('BR-P02.2: tarif daerah terbit dengan 1 penyetuju', function (): void {
        $pengaju = BantuanPengelola::BuatAnggota(PeranPengelolaBawaan::KontenLegal);
        $keuangan = BantuanPengelola::BuatAnggota(PeranPengelolaBawaan::Keuangan);
        $tarif = AjukanTarifBaru($this, $pengaju, [
            'KodeJenisPajak' => 'PbjtMakananMinuman', 'Tarif' => '10', 'PengaliDppPembilang' => 1, 'PengaliDppPenyebut' => 1,
            'KodeWilayah' => '33.74', 'BiayaLayananMasukDpp' => true, 'NomorDasarHukum' => 'Perda Kota Semarang 1/2026',
        ]);

        Tinjau($this, $keuangan, $tarif)->assertSessionHasNoErrors();

        expect($tarif->refresh()->Status)->toBe(StatusDataMaster::Terbit)
            ->and($tarif->BiayaLayananMasukDpp)->toBeTrue();
    });

    it('penolakan wajib bercatatan, mengembalikan ke draf, dan persetujuan lama tidak dihitung lagi', function (): void {
        $pengaju = BantuanPengelola::BuatAnggota(PeranPengelolaBawaan::KontenLegal);
        $keuangan = BantuanPengelola::BuatAnggota(PeranPengelolaBawaan::Keuangan);
        $superAdmin = BantuanPengelola::BuatAnggota(PeranPengelolaBawaan::SuperAdmin);
        $tarif = AjukanTarifBaru($this, $pengaju);

        Tinjau($this, $keuangan, $tarif)->assertSessionHasNoErrors();
        Tinjau($this, $superAdmin, $tarif, 'Tolak')->assertSessionHasErrors('Catatan');
        Tinjau($this, $superAdmin, $tarif, 'Tolak', 'Nomor PMK salah')->assertSessionHasNoErrors();
        expect($tarif->refresh()->Status)->toBe(StatusDataMaster::Draf);

        // Tanpa jeda waktu: persetujuan putaran lama tidak boleh terbawa walau terjadi di detik yang sama.
        SebagaiPengelola($this, $pengaju)->post(BantuanPengelola::Url("/referensi/tarif-pajak/{$tarif->Uuid}/ajukan"))->assertSessionHasNoErrors();
        Tinjau($this, $keuangan, $tarif)->assertSessionHasNoErrors();

        expect($tarif->refresh()->Status)->toBe(StatusDataMaster::MenungguTinjauan);
    });

    it('BR-P02.1: tarif terbit tidak bisa diubah atau dihapus; koreksi lewat tarif baru', function (): void {
        $pengaju = BantuanPengelola::BuatAnggota(PeranPengelolaBawaan::KontenLegal);
        $keuangan = BantuanPengelola::BuatAnggota(PeranPengelolaBawaan::Keuangan);
        $tarif = AjukanTarifBaru($this, $pengaju, ['KodeJenisPajak' => 'PbjtMakananMinuman', 'Tarif' => '10', 'PengaliDppPembilang' => 1, 'PengaliDppPenyebut' => 1, 'KodeWilayah' => '33.74']);
        Tinjau($this, $keuangan, $tarif);

        SebagaiPengelola($this, $pengaju)
            ->put(BantuanPengelola::Url("/referensi/tarif-pajak/{$tarif->Uuid}"), DataTarif(['KodeJenisPajak' => 'PbjtMakananMinuman', 'Tarif' => '5', 'PengaliDppPembilang' => 1, 'PengaliDppPenyebut' => 1, 'KodeWilayah' => '33.74']))
            ->assertSessionHasErrors('Umum');

        expect(fn () => $tarif->refresh()->update(['Tarif' => '5']))->toThrow(LogicException::class)
            ->and(fn () => $tarif->refresh()->delete())->toThrow(LogicException::class)
            ->and($tarif->refresh()->Tarif)->toBe('10.000000');
    });

    it('tarif pengganti mengakhiri tarif lama sehari sebelum tanggal berlaku baru', function (): void {
        $pengaju = BantuanPengelola::BuatAnggota(PeranPengelolaBawaan::KontenLegal);
        $keuangan = BantuanPengelola::BuatAnggota(PeranPengelolaBawaan::Keuangan);
        $daerah = ['KodeJenisPajak' => 'PbjtMakananMinuman', 'PengaliDppPembilang' => 1, 'PengaliDppPenyebut' => 1, 'KodeWilayah' => '33.74'];

        $lama = AjukanTarifBaru($this, $pengaju, [...$daerah, 'Tarif' => '10', 'BerlakuMulai' => '2026-01-01']);
        Tinjau($this, $keuangan, $lama);
        $baru = AjukanTarifBaru($this, $pengaju, [...$daerah, 'Tarif' => '8', 'BerlakuMulai' => '2027-01-01']);
        Tinjau($this, $keuangan, $baru);

        expect($lama->refresh()->BerlakuSampai?->toDateString())->toBe('2026-12-31')
            ->and($lama->Tarif)->toBe('10.000000');

        $kueri = app(TarifPajakBerlaku::class);
        expect($kueri->Cari('PbjtMakananMinuman', '33.74', Carbon::parse('2026-12-31'))?->Id)->toBe($lama->Id)
            ->and($kueri->Cari('PbjtMakananMinuman', '33.74', Carbon::parse('2027-01-01'))?->Id)->toBe($baru->Id)
            ->and($kueri->Cari('PbjtMakananMinuman', '33.74', Carbon::parse('2025-06-01')))->toBeNull()
            ->and($kueri->Cari('PbjtMakananMinuman', '33.75', Carbon::parse('2027-01-01')))->toBeNull();

        $this->assertDatabaseHas('LogAuditPengelola', ['Aksi' => 'referensi.tarif-pajak.akhiri', 'IdObjek' => $lama->Id]);
    });

    it('menolak menerbitkan tarif dengan tanggal berlaku sebelum tarif terbit yang ada', function (): void {
        $pengaju = BantuanPengelola::BuatAnggota(PeranPengelolaBawaan::KontenLegal);
        $keuangan = BantuanPengelola::BuatAnggota(PeranPengelolaBawaan::Keuangan);
        $daerah = ['KodeJenisPajak' => 'PbjtMakananMinuman', 'PengaliDppPembilang' => 1, 'PengaliDppPenyebut' => 1, 'KodeWilayah' => '33.74'];

        Tinjau($this, $keuangan, AjukanTarifBaru($this, $pengaju, [...$daerah, 'Tarif' => '10', 'BerlakuMulai' => '2027-01-01']));
        $mundur = AjukanTarifBaru($this, $pengaju, [...$daerah, 'Tarif' => '9', 'BerlakuMulai' => '2026-06-01']);

        Tinjau($this, $keuangan, $mundur)->assertSessionHasErrors('Umum');
        expect($mundur->refresh()->Status)->toBe(StatusDataMaster::MenungguTinjauan);
    });

    it('draf tidak pernah dipakai kalkulasi', function (): void {
        $pengaju = BantuanPengelola::BuatAnggota(PeranPengelolaBawaan::KontenLegal);
        AjukanTarifBaru($this, $pengaju, ['BerlakuMulai' => '2020-01-01']);

        expect(app(TarifPajakBerlaku::class)->Cari('Ppn', null, Carbon::parse('2026-01-01')))->toBeNull();
    });

    it('memvalidasi tarif, pengali DPP, wilayah, dan dasar hukum', function (array $ubah, string $bidang): void {
        $pengaju = BantuanPengelola::BuatAnggota(PeranPengelolaBawaan::KontenLegal);

        SebagaiPengelola($this, $pengaju)
            ->post(BantuanPengelola::Url('/referensi/tarif-pajak'), DataTarif($ubah))
            ->assertSessionHasErrors($bidang);
    })->with([
        'tarif nol' => [['Tarif' => '0'], 'Tarif'],
        'tarif lebih dari 100' => [['Tarif' => '101'], 'Tarif'],
        'tarif koma' => [['Tarif' => '12,5'], 'Tarif'],
        'pengali lebih dari 1' => [['PengaliDppPembilang' => 13], 'PengaliDppPembilang'],
        'nasional memakai wilayah' => [['KodeWilayah' => '33.74'], 'KodeWilayah'],
        'daerah tanpa wilayah' => [['KodeJenisPajak' => 'PbjtMakananMinuman'], 'KodeWilayah'],
        'daerah wilayah provinsi' => [['KodeJenisPajak' => 'PbjtMakananMinuman', 'KodeWilayah' => '33'], 'KodeWilayah'],
    ]);

    it('pengajuan wajib melampirkan dasar hukum', function (): void {
        $pengaju = BantuanPengelola::BuatAnggota(PeranPengelolaBawaan::KontenLegal);
        SebagaiPengelola($this, $pengaju)->post(BantuanPengelola::Url('/referensi/tarif-pajak'), DataTarif(['NomorDasarHukum' => null]));
        $tarif = TarifPajak::query()->sole();

        $this->post(BantuanPengelola::Url("/referensi/tarif-pajak/{$tarif->Uuid}/ajukan"))->assertSessionHasErrors('NomorDasarHukum');
        expect($tarif->refresh()->Status)->toBe(StatusDataMaster::Draf);
    });

    it('izin: Keuangan tidak bisa membuat draf, Konten & Legal tidak bisa menyetujui', function (): void {
        $konten = BantuanPengelola::BuatAnggota(PeranPengelolaBawaan::KontenLegal);
        $kontenLain = BantuanPengelola::BuatAnggota(PeranPengelolaBawaan::KontenLegal);
        $keuangan = BantuanPengelola::BuatAnggota(PeranPengelolaBawaan::Keuangan);

        SebagaiPengelola($this, $keuangan)->post(BantuanPengelola::Url('/referensi/tarif-pajak'), DataTarif())->assertForbidden();
        $tarif = AjukanTarifBaru($this, $konten);
        Tinjau($this, $kontenLain, $tarif)->assertForbidden();
        expect(PersetujuanDataMaster::query()->count())->toBe(0);
    });

    it('seeder membuat draf PPN 12% dengan DPP 11/12 sekali saja, tidak langsung terbit', function (): void {
        app(SiapkanPajakBawaan::class)->Jalankan();
        app(SiapkanPajakBawaan::class)->Jalankan();

        $ppn = TarifPajak::query()->where('IdJenisPajak', JenisPajak::query()->where('Kode', 'Ppn')->sole()->Id)->sole();
        expect($ppn->Status)->toBe(StatusDataMaster::Draf)
            ->and($ppn->Tarif)->toBe('12.000000')
            ->and([$ppn->PengaliDppPembilang, $ppn->PengaliDppPenyebut])->toBe([11, 12]);
    });

    it('keputusan peninjau bersifat append-only', function (): void {
        $pengaju = BantuanPengelola::BuatAnggota(PeranPengelolaBawaan::KontenLegal);
        $keuangan = BantuanPengelola::BuatAnggota(PeranPengelolaBawaan::Keuangan);
        Tinjau($this, $keuangan, AjukanTarifBaru($this, $pengaju));

        expect(fn () => PersetujuanDataMaster::query()->sole()->delete())->toThrow(LogicException::class);
    });
    it('dua draf untuk pajak & wilayah sama: yang terbit belakangan mengakhiri yang pertama, tanggal sama ditolak', function (): void {
        $pengaju = BantuanPengelola::BuatAnggota(PeranPengelolaBawaan::KontenLegal);
        $keuangan = BantuanPengelola::BuatAnggota(PeranPengelolaBawaan::Keuangan);
        $daerah = ['KodeJenisPajak' => 'PbjtMakananMinuman', 'PengaliDppPembilang' => 1, 'PengaliDppPenyebut' => 1, 'KodeWilayah' => '33.74'];
        $pertama = AjukanTarifBaru($this, $pengaju, [...$daerah, 'Tarif' => '10', 'BerlakuMulai' => '2027-01-01']);
        $kedua = AjukanTarifBaru($this, $pengaju, [...$daerah, 'Tarif' => '9', 'BerlakuMulai' => '2027-07-01']);
        $kembar = AjukanTarifBaru($this, $pengaju, [...$daerah, 'Tarif' => '8', 'BerlakuMulai' => '2027-07-01']);

        Tinjau($this, $keuangan, $pertama)->assertSessionHasNoErrors();
        Tinjau($this, $keuangan, $kedua)->assertSessionHasNoErrors();
        Tinjau($this, $keuangan, $kembar)->assertSessionHasErrors('Umum');

        expect($pertama->refresh()->BerlakuSampai?->toDateString())->toBe('2027-06-30')
            ->and($kembar->refresh()->Status)->toBe(StatusDataMaster::MenungguTinjauan)
            ->and(TarifPajak::query()->where('Status', 'Terbit')->count())->toBe(2);
    });

    it('menerima tarif dengan 6 desimal dan menyaring daftar dengan saring[Status]', function (): void {
        $pengaju = BantuanPengelola::BuatAnggota(PeranPengelolaBawaan::KontenLegal);
        AjukanTarifBaru($this, $pengaju, ['Tarif' => '11.123456']);
        SebagaiPengelola($this, $pengaju)->post(BantuanPengelola::Url('/referensi/tarif-pajak'), DataTarif(['BerlakuMulai' => '2028-01-01']));

        expect(TarifPajak::query()->where('Status', 'MenungguTinjauan')->sole()->Tarif)->toBe('11.123456');
        $this->get(BantuanPengelola::Url('/referensi/tarif-pajak?saring[Status]=Draf'))
            ->assertInertia(fn (AssertableInertia $halaman) => $halaman->has('Tarif.Data', 1)->where('Tarif.Data.0.Status', 'Draf'));
    });
});
