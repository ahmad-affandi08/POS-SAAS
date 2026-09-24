<?php

declare(strict_types=1);

use App\Domain\Bersama\Nilai\Kuantitas;
use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Pajak\Enum\DasarPengenaanPajak;
use App\Domain\Penjualan\Enum\ArahPembulatan;
use App\Domain\Penjualan\Kalkulasi\DataBarisKalkulasi;
use App\Domain\Penjualan\Kalkulasi\DataKalkulasi;
use App\Domain\Penjualan\Kalkulasi\DataPajakKalkulasi;
use App\Domain\Penjualan\Kalkulasi\DataPembayaranKalkulasi;
use App\Domain\Penjualan\Kalkulasi\DataPembulatanTunai;
use App\Domain\Penjualan\Kalkulasi\DataPotongan;
use App\Domain\Penjualan\Kalkulasi\HasilBarisKalkulasi;
use App\Domain\Penjualan\Kalkulasi\MesinKalkulasi;
use App\Domain\Penjualan\Kalkulasi\PengalokasiSisaTerbesar;
use Brick\Math\BigRational;

/*
 * Perilaku mesin kalkulasi penjualan F-07a (Rincian F-07a, BR-07.2, BR-08.6) di luar test vector bersama:
 * alokasi sisa terbesar, penolakan masukan tidak valid, dan invariant Σ baris = angka dokumen.
 */

/**
 * @param  list<string>|null  $kodePajak
 * @param  list<DataPotongan>  $potongan
 */
function BuatBarisUji(string $jumlah, string $harga, ?array $kodePajak = null, array $potongan = []): DataBarisKalkulasi
{
    return new DataBarisKalkulasi(Kuantitas::Dari($jumlah), Uang::Dari($harga), null, null, $kodePajak, $potongan);
}

/**
 * @param  list<Uang>  $daftar
 * @return list<string>
 */
function UbahDaftarUangKeTeks(array $daftar): array
{
    return array_map(fn (Uang $uang): string => $uang->KeString(), $daftar);
}

describe('PengalokasiSisaTerbesar (Rincian F-07a)', function (): void {
    it('memberi sisa sen ke pecahan terbesar dan seri ke baris lebih awal', function (): void {
        $pengalokasi = new PengalokasiSisaTerbesar;

        expect(UbahDaftarUangKeTeks($pengalokasi->AlokasikanSebanding(Uang::Dari('100'), [Uang::Dari('1000'), Uang::Dari('1000'), Uang::Dari('1000')])))
            ->toBe(['33.34', '33.33', '33.33'])
            ->and(UbahDaftarUangKeTeks($pengalokasi->AlokasikanSebanding(Uang::Dari('0.05'), [Uang::Dari('1'), Uang::Dari('2'), Uang::Dari('3')])))
            ->toBe(['0.01', '0.02', '0.02']);
    });

    it('menjaga Σ bagian sama persis dengan total untuk bobot tidak rata', function (): void {
        $hasil = (new PengalokasiSisaTerbesar)->AlokasikanSebanding(
            Uang::Dari('17720.45'),
            [Uang::Dari('224910'), Uang::Dari('99999'), Uang::Dari('29500'), Uang::Dari('0.01')],
        );
        $total = array_reduce($hasil, fn (Uang $jumlah, Uang $bagian): Uang => $jumlah->Tambah($bagian), Uang::Nol());

        expect($total->KeString())->toBe('17720.45');
    });

    it('menghasilkan nol semua bila bobot nol', function (): void {
        expect(UbahDaftarUangKeTeks((new PengalokasiSisaTerbesar)->AlokasikanSebanding(Uang::Dari('10'), [Uang::Nol(), Uang::Nol()])))
            ->toBe(['0.00', '0.00']);
    });

    it('menolak total yang tidak bisa dicapai dari bagian tepat', function (): void {
        (new PengalokasiSisaTerbesar)->AlokasikanTepat(Uang::Dari('5'), [BigRational::of('1'), BigRational::of('1')]);
    })->throws(InvalidArgumentException::class);
});

describe('validasi masukan mesin kalkulasi (Rincian F-07a)', function (): void {
    it('menolak jumlah baris nol atau negatif', function (string $jumlah): void {
        BuatBarisUji($jumlah, '1000');
    })->with(['0', '-1'])->throws(InvalidArgumentException::class);

    it('menolak harga satuan negatif', function (): void {
        BuatBarisUji('1', '-1');
    })->throws(InvalidArgumentException::class);

    it('menolak persen potongan di luar 0 sampai 100', function (string $persen): void {
        DataPotongan::BuatPersen($persen);
    })->with(['-1', '100.01'])->throws(InvalidArgumentException::class);

    it('menolak potongan nominal negatif', function (): void {
        DataPotongan::BuatNominal(Uang::Dari('-5'));
    })->throws(InvalidArgumentException::class);

    it('menolak persen biaya layanan di atas 100', function (): void {
        new DataKalkulasi(false, [BuatBarisUji('1', '1000')], [], '101');
    })->throws(InvalidArgumentException::class);

    it('menolak kelipatan pembulatan tunai nol', function (): void {
        new DataPembulatanTunai(0, ArahPembulatan::Bawah);
    })->throws(InvalidArgumentException::class);

    it('menolak tarif pajak di luar 0 sampai 100 dan pengali DPP tidak positif', function (): void {
        expect(fn () => new DataPajakKalkulasi('PPN', '101'))->toThrow(InvalidArgumentException::class)
            ->and(fn () => new DataPajakKalkulasi('PPN', '12', DasarPengenaanPajak::Subtotal, 11, 0))->toThrow(InvalidArgumentException::class);
    });

    it('menolak pajak baris yang merujuk kode tak dikenal', function (): void {
        new DataKalkulasi(false, [BuatBarisUji('1', '1000', ['PB1'])], [new DataPajakKalkulasi('PPN', '12')]);
    })->throws(InvalidArgumentException::class, 'PB1');

    it('menolak pembayaran non-tunai tanpa jumlah', function (): void {
        new DataPembayaranKalkulasi(false);
    })->throws(InvalidArgumentException::class);
});

describe('MesinKalkulasi (Rincian F-07a)', function (): void {
    it('Σ TotalBaris sama dengan total sebelum pembulatan dan Σ pajak baris sama dengan TotalPajak', function (): void {
        $hasil = (new MesinKalkulasi)->Hitung(new DataKalkulasi(
            true,
            [
                BuatBarisUji('3', '33333', null, [DataPotongan::BuatPersen('7')]),
                new DataBarisKalkulasi(Kuantitas::Dari('1.5'), Uang::Dari('12345.67'), Uang::Dari('1000'), false),
                BuatBarisUji('1', '9999', []),
            ],
            [new DataPajakKalkulasi('PPN', '12', DasarPengenaanPajak::SubtotalPlusLayanan, 11, 12)],
            '5',
            new DataPembulatanTunai(100, ArahPembulatan::Terdekat),
            [DataPotongan::BuatNominal(Uang::Dari('1234.56'))],
            [new DataPembayaranKalkulasi(true, Uang::Dari('200000'))],
        ));

        $jumlahkan = fn (Closure $ambil): string => array_reduce(
            $hasil->baris,
            fn (Uang $jumlah, HasilBarisKalkulasi $baris): Uang => $jumlah->Tambah($ambil($baris)),
            Uang::Nol(),
        )->KeString();

        expect($jumlahkan(fn (HasilBarisKalkulasi $baris): Uang => $baris->totalBaris))
            ->toBe($hasil->totalAkhir->Kurangi($hasil->pembulatan)->KeString())
            ->and($jumlahkan(fn (HasilBarisKalkulasi $baris): Uang => $baris->pajak))->toBe($hasil->totalPajak->KeString())
            ->and($jumlahkan(fn (HasilBarisKalkulasi $baris): Uang => $baris->diskonPesanan))->toBe('1234.56')
            ->and($jumlahkan(fn (HasilBarisKalkulasi $baris): Uang => $baris->biayaLayanan))->toBe($hasil->biayaLayanan->KeString())
            ->and($hasil->baris[2]->pajak->KeString())->toBe('0.00')
            ->and($hasil->kembalian?->KeString())->toBe(Uang::Dari('200000')->Kurangi($hasil->totalAkhir)->KeString());
    });

    it('tanpa pembayaran tunai tidak ada pembulatan dan kembalian (BR-08.6)', function (): void {
        $hasil = (new MesinKalkulasi)->Hitung(new DataKalkulasi(
            false,
            [BuatBarisUji('1', '10050')],
            pembulatanTunai: new DataPembulatanTunai(100, ArahPembulatan::Bawah),
            pembayaran: [new DataPembayaranKalkulasi(false, Uang::Dari('10050'))],
        ));

        expect($hasil->pembulatan->KeString())->toBe('0.00')
            ->and($hasil->totalAkhir->KeString())->toBe('10050.00')
            ->and($hasil->kembalian)->toBeNull();
    });
});
