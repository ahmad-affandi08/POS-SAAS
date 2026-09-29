<?php

declare(strict_types=1);

use App\Domain\Akuntansi\Enum\PeranAkun;
use App\Domain\Akuntansi\Model\JurnalDetail;
use App\Domain\Bersama\Dokumen\Enum\StatusDokumenTerposting;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Nilai\Kuantitas;
use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Pelanggan\Enum\StatusPiutang;
use App\Domain\Pelanggan\Model\Pelanggan;
use App\Domain\Pelanggan\Model\Piutang;
use App\Domain\Penjualan\Aksi\BatalkanFakturPenjualan;
use App\Domain\Penjualan\Aksi\BuatFakturPenjualan;
use App\Domain\Penjualan\Aksi\KirimPesananGrosir;
use App\Domain\Penjualan\Aksi\KonfirmasiPesananGrosir;
use App\Domain\Penjualan\Aksi\SimpanPesananGrosir;
use App\Domain\Penjualan\Data\DataBarisPesananGrosir;
use App\Domain\Penjualan\Data\DataBarisSuratJalan;
use App\Domain\Penjualan\Data\DataFakturPenjualan;
use App\Domain\Penjualan\Data\DataPesananGrosir;
use App\Domain\Penjualan\Data\DataSuratJalan;
use App\Domain\Penjualan\Model\FakturPenjualan;
use App\Domain\Penjualan\Model\SuratJalan;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Tests\Pendukung\Akuntansi\BantuanJurnal;
use Tests\Pendukung\Katalog\BantuanHarga;
use Tests\Pendukung\Katalog\BantuanKatalog;
use Tests\Pendukung\Persediaan\BantuanPersediaan;
use Tests\Pendukung\Persediaan\BantuanStokAwal;
use Tests\Pendukung\Persediaan\PemeriksaInvarian;
use Tests\Pendukung\Tenant\BantuanPendaftaran;

/*
 * Grosir bagian 1 tahap 4 (F-12, §9.7, **BR-12.4** & **BR-12.5**, J-12.2): faktur penjualan.
 *
 * Yang dibuktikan di sini: faktur hanya **memindahkan** piutang (Dr Piutang Usaha / Cr Piutang Belum Difakturkan) dan
 * tidak pernah menambah pendapatan atau PPN lagi — karena itulah menagih dua kali tidak bisa menggandakan pendapatan.
 * Lalu batas hukumnya: satu faktur = satu pelanggan, satu outlet, satu bulan kalender (UU PPN Pasal 13 ayat 2a).
 * Dan piutangnya memakai tabel `Piutang` yang sama dengan penjualan tempo, sehingga aging & pelunasan dipakai ulang.
 */

beforeEach(function (): void {
    Carbon::setTestNow('2026-09-27 03:00:00');
    BantuanPendaftaran::SiapkanPrasyarat();
    $this->k = BantuanPersediaan::SiapkanTenant('Grosir Sumber Pangan');
    $this->gula = BantuanKatalog::BuatProduk(['Nama' => 'Gula Pasir Kemasan 1 kg'], '15000.00', $this->k['Pcs']);
    $this->satuan = BantuanHarga::SatuanDasar($this->gula);
    BantuanStokAwal::BuatDanPosting(
        $this->k['Gudang'],
        [BantuanStokAwal::Baris($this->gula, '1000', '11000')],
        $this->k['Pemilik']->Id,
        '2026-08-01',
    );
    $this->toko = Pelanggan::query()->create([
        'Nama' => 'Toko Makmur Jaya',
        'NoHp' => '6281355550003',
        'LimitKredit' => '50000000',
        'TerminHari' => 30,
    ]);
});

afterEach(function (): void {
    Carbon::setTestNow();
});

/** Surat jalan terposting atas SO baru, siap difakturkan. */
function SuratJalanSiapFakturUji($tes, string $jumlah = '200', string $tanggal = '2026-09-27', ?Pelanggan $pelanggan = null): SuratJalan
{
    $pelanggan ??= $tes->toko;
    $pesanan = app(SimpanPesananGrosir::class)->Jalankan(new DataPesananGrosir(
        uuidPelanggan: $pelanggan->Uuid,
        idOutlet: $tes->k['Outlet']->Id,
        tanggal: CarbonImmutable::parse($tanggal),
        baris: [new DataBarisPesananGrosir($tes->gula->Uuid, $tes->satuan->Uuid, Kuantitas::Dari($jumlah), Uang::Nol())],
    ), $tes->k['Pemilik']->Id);
    $pesanan = app(KonfirmasiPesananGrosir::class)->Jalankan($pesanan->Uuid, $tes->k['Pemilik']->Id, true, 'Pelanggan lama, rekam jejak baik');

    return app(KirimPesananGrosir::class)->Jalankan(new DataSuratJalan(
        uuidPesanan: $pesanan->Uuid,
        idGudang: $tes->k['Gudang']->Id,
        tanggal: CarbonImmutable::parse($tanggal),
        baris: [new DataBarisSuratJalan(1, Kuantitas::Dari($jumlah))],
    ), $tes->k['Pemilik']->Id);
}

/** @param  list<SuratJalan>  $suratJalan */
function FakturUji($tes, array $suratJalan, string $tanggal = '2026-09-27', ?string $nomorFakturPajak = null): FakturPenjualan
{
    return app(BuatFakturPenjualan::class)->Jalankan(new DataFakturPenjualan(
        uuidSuratJalan: array_map(fn (SuratJalan $sj): string => $sj->Uuid, $suratJalan),
        tanggal: CarbonImmutable::parse($tanggal),
        nomorFakturPajak: $nomorFakturPajak,
    ), $tes->k['Pemilik']->Id);
}

/** @return array{Debit: string, Kredit: string} */
function BarisJurnalFakturUji(int $idJurnal, PeranAkun $peran): array
{
    $debit = Uang::Nol();
    $kredit = Uang::Nol();

    foreach (JurnalDetail::query()->where('IdJurnal', $idJurnal)->where('IdAkun', BantuanJurnal::IdAkunPeran($peran))->get() as $baris) {
        $debit = $debit->Tambah(Uang::Dari($baris->Debit));
        $kredit = $kredit->Tambah(Uang::Dari($baris->Kredit));
    }

    return ['Debit' => $debit->KeString(), 'Kredit' => $kredit->KeString()];
}

describe('BuatFakturPenjualan (BR-12.4, J-12.2)', function (): void {
    it('memindahkan piutang tanpa menambah pendapatan, dan membuat Piutang yang sama dengan penjualan tempo', function (): void {
        $suratJalan = SuratJalanSiapFakturUji($this, '200');

        $faktur = FakturUji($this, [$suratJalan], '2026-09-27', '0100002512345678');

        $kode = mb_strtoupper($this->k['Outlet']->Kode);
        expect($faktur->Nomor)->toBe("FJ/{$kode}/2609/0001")
            ->and($faktur->Status)->toBe(StatusDokumenTerposting::Diposting)
            ->and($faktur->Total)->toBe('3000000.00')
            ->and($faktur->PeriodePenyerahan)->toBe('2026-09')
            ->and($faktur->NomorFakturPajak)->toBe('0100002512345678')
            // Jatuh tempo = tanggal faktur + termin pelanggan (BR-12.5), bukan tanggal penyerahan.
            ->and($faktur->JatuhTempo->toDateString())->toBe('2026-10-27')
            ->and($faktur->TerminHari)->toBe(30)
            ->and($faktur->SuratJalan)->toHaveCount(1);

        $idJurnal = (int) $faktur->IdJurnal;
        expect(BarisJurnalFakturUji($idJurnal, PeranAkun::PiutangUsaha))->toBe(['Debit' => '3000000.00', 'Kredit' => '0.00'])
            ->and(BarisJurnalFakturUji($idJurnal, PeranAkun::PiutangBelumDifakturkan))->toBe(['Debit' => '0.00', 'Kredit' => '3000000.00'])
            // Inti BR-12.2: faktur TIDAK mengakui pendapatan lagi. Kalau baris ini pernah tidak nol, pendapatan ganda.
            ->and(BarisJurnalFakturUji($idJurnal, PeranAkun::Penjualan))->toBe(['Debit' => '0.00', 'Kredit' => '0.00'])
            ->and(BarisJurnalFakturUji($idJurnal, PeranAkun::PpnKeluaran))->toBe(['Debit' => '0.00', 'Kredit' => '0.00'])
            ->and(BarisJurnalFakturUji($idJurnal, PeranAkun::Hpp))->toBe(['Debit' => '0.00', 'Kredit' => '0.00']);

        $piutang = Piutang::query()->where('IdFakturPenjualan', $faktur->Id)->sole();
        expect($piutang->Nomor)->toBe($faktur->Nomor)
            ->and($piutang->Jumlah)->toBe('3000000.00')
            ->and($piutang->Status)->toBe(StatusPiutang::BelumLunas)
            ->and($piutang->JatuhTempo->toDateString())->toBe('2026-10-27')
            // Sumbernya faktur, bukan penjualan: tepat satu yang terisi (BR-12.5).
            ->and($piutang->IdPenjualan)->toBeNull();

        expect($suratJalan->refresh()->IdFakturPenjualan)->toBe($faktur->Id)
            ->and(PemeriksaInvarian::PeriksaSemua($this->k['Tenant']->Id))->toBe([]);
    });

    it('menggabungkan beberapa surat jalan satu bulan, dan menolak yang beda bulan', function (): void {
        $awal = SuratJalanSiapFakturUji($this, '100', '2026-09-10');
        $akhir = SuratJalanSiapFakturUji($this, '50', '2026-09-25');

        $faktur = FakturUji($this, [$awal, $akhir], '2026-09-27');

        // 100 x 15.000 + 50 x 15.000 = 2.250.000 dalam satu Faktur Pajak gabungan (UU PPN Pasal 13 ayat 2a).
        expect($faktur->Total)->toBe('2250000.00')
            ->and($faktur->SuratJalan)->toHaveCount(2)
            ->and(BarisJurnalFakturUji((int) $faktur->IdJurnal, PeranAkun::PiutangUsaha))->toBe(['Debit' => '2250000.00', 'Kredit' => '0.00']);

        Carbon::setTestNow('2026-10-05 03:00:00');
        $bulanBerikut = SuratJalanSiapFakturUji($this, '20', '2026-10-02');
        $bulanIni = SuratJalanSiapFakturUji($this, '20', '2026-09-26');

        try {
            FakturUji($this, [$bulanIni, $bulanBerikut], '2026-10-05');
            $this->fail('Surat jalan dari dua bulan kalender seharusnya ditolak.');
        } catch (PelanggaranAturanBisnis $galat) {
            expect($galat->kode)->toBe('SuratJalanBedaBulan');
        }
    });

    it('menolak beda pelanggan, surat jalan yang sudah difakturkan, surat jalan dibatalkan, dan tanggal sebelum penyerahan', function (): void {
        $lain = Pelanggan::query()->create([
            'Nama' => 'Warung Bu Tini Sejahtera',
            'NoHp' => '6281355550004',
            'LimitKredit' => '10000000',
            'TerminHari' => 14,
        ]);
        $milikToko = SuratJalanSiapFakturUji($this, '30', '2026-09-20');
        $milikLain = SuratJalanSiapFakturUji($this, '30', '2026-09-20', $lain);

        try {
            FakturUji($this, [$milikToko, $milikLain]);
            $this->fail('Satu faktur untuk dua pelanggan seharusnya ditolak.');
        } catch (PelanggaranAturanBisnis $galat) {
            expect($galat->kode)->toBe('SuratJalanBedaPelanggan');
        }

        FakturUji($this, [$milikToko]);

        try {
            FakturUji($this, [$milikToko->refresh()]);
            $this->fail('Surat jalan yang sudah difakturkan seharusnya ditolak.');
        } catch (PelanggaranAturanBisnis $galat) {
            expect($galat->kode)->toBe('SuratJalanSudahDifakturkan');
        }

        $baru = SuratJalanSiapFakturUji($this, '10', '2026-09-26');

        try {
            FakturUji($this, [$baru], '2026-09-20');
            $this->fail('Tanggal faktur sebelum penyerahan terakhir seharusnya ditolak.');
        } catch (PelanggaranAturanBisnis $galat) {
            expect($galat->kode)->toBe('TanggalFakturSebelumPenyerahan');
        }

        try {
            FakturUji($this, []);
            $this->fail('Faktur tanpa surat jalan seharusnya ditolak.');
        } catch (PelanggaranAturanBisnis $galat) {
            expect($galat->kode)->toBe('FakturTanpaSuratJalan');
        }
    });
});

describe('BatalkanFakturPenjualan', function (): void {
    it('membalik reklasifikasi, membatalkan piutang, dan melepas surat jalannya untuk difakturkan ulang', function (): void {
        $suratJalan = SuratJalanSiapFakturUji($this, '200');
        $faktur = FakturUji($this, [$suratJalan]);

        $dibatalkan = app(BatalkanFakturPenjualan::class)->Jalankan($faktur->Uuid, 'Salah pelanggan di fakturnya', $this->k['Pemilik']->Id);

        expect($dibatalkan->Status)->toBe(StatusDokumenTerposting::Dibatalkan)
            ->and($dibatalkan->IdJurnalPembatalan)->not->toBeNull();

        $idPembalik = (int) $dibatalkan->IdJurnalPembatalan;
        expect(BarisJurnalFakturUji($idPembalik, PeranAkun::PiutangUsaha))->toBe(['Debit' => '0.00', 'Kredit' => '3000000.00'])
            // Nilainya kembali ke akun perantara: barangnya masih di pembeli, tagihannya yang batal.
            ->and(BarisJurnalFakturUji($idPembalik, PeranAkun::PiutangBelumDifakturkan))->toBe(['Debit' => '3000000.00', 'Kredit' => '0.00'])
            ->and(BarisJurnalFakturUji($idPembalik, PeranAkun::Penjualan))->toBe(['Debit' => '0.00', 'Kredit' => '0.00']);

        $piutang = Piutang::query()->where('IdFakturPenjualan', $faktur->Id)->sole();
        expect($piutang->Status)->toBe(StatusPiutang::Dibatalkan)
            ->and($piutang->AmbilSisa()->KeString())->toBe('0.00');

        // Surat jalannya bebas lagi, jadi bisa difakturkan ulang dengan benar.
        expect($suratJalan->refresh()->IdFakturPenjualan)->toBeNull();
        $fakturBaru = FakturUji($this, [$suratJalan], '2026-09-27');
        expect($fakturBaru->Total)->toBe('3000000.00')
            ->and(PemeriksaInvarian::PeriksaSemua($this->k['Tenant']->Id))->toBe([]);
    });

    it('idempoten, menuntut alasan, dan menolak faktur yang piutangnya sudah dibayar sebagian', function (): void {
        $suratJalan = SuratJalanSiapFakturUji($this, '200');
        $faktur = FakturUji($this, [$suratJalan]);

        try {
            app(BatalkanFakturPenjualan::class)->Jalankan($faktur->Uuid, 'oke', $this->k['Pemilik']->Id);
            $this->fail('Alasan 4 karakter seharusnya ditolak.');
        } catch (PelanggaranAturanBisnis $galat) {
            expect($galat->kode)->toBe('AlasanBatalWajib');
        }

        // Pembayaran sebagian ditiru langsung di piutangnya; yang diuji di sini penjaga pembatalan fakturnya.
        $piutang = Piutang::query()->where('IdFakturPenjualan', $faktur->Id)->sole();
        $piutang->JumlahDibayar = '500000.00';
        $piutang->SelaraskanStatus();
        $piutang->save();

        try {
            app(BatalkanFakturPenjualan::class)->Jalankan($faktur->Uuid, 'Pembeli minta faktur diganti', $this->k['Pemilik']->Id);
            $this->fail('Faktur berpiutang yang sudah dibayar sebagian seharusnya ditolak.');
        } catch (PelanggaranAturanBisnis $galat) {
            expect($galat->kode)->toBe('PiutangSudahDibayar');
        }

        $piutang->JumlahDibayar = '0.00';
        $piutang->SelaraskanStatus();
        $piutang->save();

        app(BatalkanFakturPenjualan::class)->Jalankan($faktur->Uuid, 'Pembeli minta faktur diganti', $this->k['Pemilik']->Id);
        $ulang = app(BatalkanFakturPenjualan::class)->Jalankan($faktur->Uuid, 'Pembeli minta faktur diganti', $this->k['Pemilik']->Id);

        expect($ulang->Status)->toBe(StatusDokumenTerposting::Dibatalkan)
            ->and(BarisJurnalFakturUji((int) $ulang->IdJurnalPembatalan, PeranAkun::PiutangUsaha))->toBe(['Debit' => '0.00', 'Kredit' => '3000000.00']);
    });
});

describe('BR-12.5 piutang bersumber tepat satu', function (): void {
    it('menolak piutang tanpa sumber maupun dengan dua sumber', function (): void {
        $suratJalan = SuratJalanSiapFakturUji($this, '10');
        $faktur = FakturUji($this, [$suratJalan]);
        $piutang = Piutang::query()->where('IdFakturPenjualan', $faktur->Id)->sole();

        expect(fn (): bool => Piutang::query()->create([
            'IdPelanggan' => $this->toko->Id,
            'IdOutlet' => $this->k['Outlet']->Id,
            'Nomor' => 'UJI/TANPA-SUMBER',
            'TanggalBisnis' => '2026-09-27',
            'JatuhTempo' => '2026-10-27',
            'Jumlah' => '1000.00',
        ]) instanceof Piutang)->toThrow(LogicException::class);

        $piutang->IdPenjualan = 424242;
        expect(fn (): bool => $piutang->save())->toThrow(LogicException::class);
    });
});
