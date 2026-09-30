<?php

declare(strict_types=1);

use App\Domain\Katalog\Enum\PelacakanProduk;
use App\Domain\Katalog\Model\Produk;
use App\Domain\Penjualan\Model\Penjualan;
use App\Domain\Penjualan\Model\PenjualanDetail;
use App\Domain\Persediaan\Enum\JenisReferensiMutasi;
use App\Domain\Persediaan\Enum\StatusNomorSeri;
use App\Domain\Persediaan\Model\MutasiStok;
use App\Domain\Persediaan\Model\NomorSeri;
use App\Domain\Persediaan\Model\SaldoStok;
use Tests\Pendukung\Kasir\BantuanKasir;
use Tests\Pendukung\Katalog\BantuanKatalog;
use Tests\Pendukung\Organisasi\BantuanOrganisasi;
use Tests\Pendukung\Penjualan\BantuanPenjualan;
use Tests\Pendukung\Persediaan\BantuanStokAwal;
use Tests\Pendukung\Persediaan\PemeriksaInvarian;
use Tests\Pendukung\Tenant\BantuanPendaftaran;

beforeEach(function (): void {
    BantuanPendaftaran::SiapkanPrasyarat();
});

/**
 * Produk bernomor seri dengan stok awal terposting, HPP Rp5.000.000 per unit.
 *
 * @param  list<string>  $nomor
 */
function BuatProdukSerialJual(array $k, array $nomor, string $nama = 'Ponsel Android 8/256 GB Hitam'): Produk
{
    $produk = BantuanKatalog::BuatProduk(['Nama' => $nama, 'Pelacakan' => PelacakanProduk::Seri], '6500000.00');
    BantuanStokAwal::BuatDanPosting($k['Gudang'], [BantuanStokAwal::Baris($produk, (string) count($nomor), '5000000', null, null, $nomor)], $k['Pemilik']->Id);

    return $produk;
}

/** @return array<string, string> Nomor → Status */
function StatusSerialJual(Produk $produk): array
{
    return NomorSeri::query()->where('IdProduk', $produk->Id)->orderBy('Nomor')->get()->mapWithKeys(fn (NomorSeri $s): array => [$s->Nomor => $s->Status->value])->all();
}

describe('F-05h penjualan produk bernomor seri', function (): void {
    it('nomor seri yang dicatat kasir dikeluarkan satu per unit (Terjual, tertaut ke baris), HPP baris = Σ unit, saldo turun; invariant terjaga', function (): void {
        $k = BantuanPenjualan::Siapkan($this);
        BantuanOrganisasi::AturKonteks($k['Tenant']->Id);
        $hp = BuatProdukSerialJual($k, ['IMEI-0001', 'IMEI-0002', 'IMEI-0003']);

        $p = BantuanPenjualan::Jual($this, $k, ['Baris' => [['Produk' => $hp, 'Jumlah' => '2', 'Harga' => '6500000.00', 'NomorSeri' => ['IMEI-0001', 'imei-0003']]]]);
        $d = PenjualanDetail::query()->where('IdPenjualan', $p->Id)->sole();

        expect(StatusSerialJual($hp))->toBe(['IMEI-0001' => 'Terjual', 'IMEI-0002' => 'Tersedia', 'IMEI-0003' => 'Terjual'])
            ->and(NomorSeri::query()->where('IdProduk', $hp->Id)->where('Status', StatusNomorSeri::Terjual->value)->pluck('IdPenjualanDetail')->unique()->values()->all())->toBe([$d->Id])
            ->and(MutasiStok::query()->where('JenisReferensi', JenisReferensiMutasi::Penjualan->value)->where('IdReferensi', $p->Id)->count())->toBe(2)
            ->and($d->TotalHpp)->toBe('10000000.00')
            ->and(SaldoStok::query()->where('IdProduk', $hp->Id)->value('JumlahTersedia'))->toBe('1.0000')
            ->and($p->PerluTinjauan)->toBeFalse()
            ->and(PemeriksaInvarian::PeriksaSemua($k['Tenant']->Id))->toBe([]);
    });

    it('jumlah nomor seri tidak sama dengan jumlah unit, atau nomor ganda, ditolak tanpa data tersimpan', function (): void {
        $k = BantuanPenjualan::Siapkan($this);
        BantuanOrganisasi::AturKonteks($k['Tenant']->Id);
        $hp = BuatProdukSerialJual($k, ['IMEI-0001', 'IMEI-0002']);
        $item = fn (array $nomor, string $jumlah = '2') => BantuanPenjualan::Item($k, ['Baris' => [['Produk' => $hp, 'Jumlah' => $jumlah, 'Harga' => '6500000.00', 'NomorSeri' => $nomor]]]);
        $kurang = $item(['IMEI-0001']);
        $tanpa = BantuanPenjualan::Item($k, ['Baris' => [['Produk' => $hp, 'Jumlah' => '1', 'Harga' => '6500000.00']]]);
        $ganda = BantuanPenjualan::Item($k, ['Baris' => [
            ['Produk' => $hp, 'Jumlah' => '1', 'Harga' => '6500000.00', 'NomorSeri' => ['IMEI-0001']],
            ['Produk' => $hp, 'Jumlah' => '1', 'Harga' => '6500000.00', 'NomorSeri' => ['imei-0001']],
        ]]);

        expect(BantuanKasir::KirimRingkas($this, $k['Token'], [$kurang, $tanpa, $ganda]))->toBe([
            ['Ditolak', 'NomorSeriTidakSesuai'],
            ['Ditolak', 'NomorSeriTidakSesuai'],
            ['Ditolak', 'NomorSeriGanda'],
        ]);

        BantuanOrganisasi::AturKonteks($k['Tenant']->Id);
        expect(Penjualan::query()->whereIn('Uuid', [$kurang['Uuid'], $tanpa['Uuid'], $ganda['Uuid']])->count())->toBe(0)
            ->and(StatusSerialJual($hp))->toBe(['IMEI-0001' => 'Tersedia', 'IMEI-0002' => 'Tersedia']);
    });

    it('nomor yang tidak tersedia (belum diterima atau sudah terjual): diterima, unit itu tidak mengurangi stok, ditinjau SerialBermasalah', function (): void {
        $k = BantuanPenjualan::Siapkan($this);
        BantuanOrganisasi::AturKonteks($k['Tenant']->Id);
        $hp = BuatProdukSerialJual($k, ['IMEI-0001', 'IMEI-0002']);
        BantuanPenjualan::Jual($this, $k, ['Baris' => [['Produk' => $hp, 'Jumlah' => '1', 'Harga' => '6500000.00', 'NomorSeri' => ['IMEI-0001']]]]);

        $p = BantuanPenjualan::Jual($this, $k, ['Baris' => [['Produk' => $hp, 'Jumlah' => '2', 'Harga' => '6500000.00', 'NomorSeri' => ['IMEI-0001', 'IMEI-0002']]]]);
        $d = PenjualanDetail::query()->where('IdPenjualan', $p->Id)->sole();

        expect($p->PerluTinjauan)->toBeTrue()
            ->and($p->AlasanTinjauan)->toContain('SerialBermasalah')->toContain('IMEI-0001')
            ->and(StatusSerialJual($hp))->toBe(['IMEI-0001' => 'Terjual', 'IMEI-0002' => 'Terjual'])
            ->and($d->TotalHpp)->toBe('5000000.00')
            ->and(PemeriksaInvarian::PeriksaSemua($k['Tenant']->Id))->toBe([]);

        $tak = BantuanPenjualan::Jual($this, $k, ['Baris' => [['Produk' => $hp, 'Jumlah' => '1', 'Harga' => '6500000.00', 'NomorSeri' => ['IMEI-9999']]]]);
        expect($tak->AlasanTinjauan)->toContain('IMEI-9999')
            ->and(NomorSeri::query()->where('Nomor', 'IMEI-9999')->exists())->toBeFalse();
    });
});

describe('F-05h void & retur mengembalikan nomor seri', function (): void {
    it('void: semua nomor seri penjualan kembali Tersedia di lokasi asal dan tidak tertaut lagi', function (): void {
        $k = BantuanPenjualan::Siapkan($this);
        BantuanOrganisasi::AturKonteks($k['Tenant']->Id);
        $hp = BuatProdukSerialJual($k, ['IMEI-0001', 'IMEI-0002', 'IMEI-0003']);
        $p = BantuanPenjualan::Jual($this, $k, ['Baris' => [['Produk' => $hp, 'Jumlah' => '2', 'Harga' => '6500000.00', 'NomorSeri' => ['IMEI-0001', 'IMEI-0002']]]]);

        expect(BantuanKasir::KirimRingkas($this, $k['Token'], [BantuanPenjualan::ItemVoid($k, $p)]))->toBe([['Diterima', null]]);

        BantuanOrganisasi::AturKonteks($k['Tenant']->Id);
        expect(StatusSerialJual($hp))->toBe(['IMEI-0001' => 'Tersedia', 'IMEI-0002' => 'Tersedia', 'IMEI-0003' => 'Tersedia'])
            ->and(NomorSeri::query()->where('IdProduk', $hp->Id)->whereNotNull('IdPenjualanDetail')->count())->toBe(0)
            ->and(SaldoStok::query()->where('IdProduk', $hp->Id)->value('JumlahTersedia'))->toBe('3.0000')
            ->and(PemeriksaInvarian::PeriksaSemua($k['Tenant']->Id))->toBe([]);
    });

    it('retur: nomor yang disebut kasir kembali; tanpa nomor, unit belum-diretur berurutan; nomor asing atau yang sudah diretur ditolak', function (): void {
        $k = BantuanPenjualan::Siapkan($this);
        BantuanOrganisasi::AturKonteks($k['Tenant']->Id);
        $hp = BuatProdukSerialJual($k, ['IMEI-0001', 'IMEI-0002', 'IMEI-0003']);
        $p = BantuanPenjualan::Jual($this, $k, ['Baris' => [['Produk' => $hp, 'Jumlah' => '3', 'Harga' => '6500000.00', 'NomorSeri' => ['IMEI-0001', 'IMEI-0002', 'IMEI-0003']]]]);
        $d = PenjualanDetail::query()->where('IdPenjualan', $p->Id)->sole();
        $kirim = function (array $baris) use ($k, $p): array {
            $hasil = BantuanKasir::KirimRingkas($this, $k['Token'], [BantuanPenjualan::ItemRetur($k, $p, [$baris])]);
            BantuanOrganisasi::AturKonteks($k['Tenant']->Id);

            return $hasil;
        };

        // Nomor asing ditolak; nomor yang dipilih (bukan yang pertama) kembali.
        expect($kirim(['Detail' => $d, 'Jumlah' => '1', 'NomorSeri' => ['IMEI-7777']]))->toBe([['Ditolak', 'NomorSeriTidakSesuai']]);
        expect($kirim(['Detail' => $d, 'Jumlah' => '1', 'NomorSeri' => ['imei-0002']]))->toBe([['Diterima', null]]);
        expect(StatusSerialJual($hp))->toBe(['IMEI-0001' => 'Terjual', 'IMEI-0002' => 'Tersedia', 'IMEI-0003' => 'Terjual']);

        // Nomor yang sudah diretur tidak bisa diretur lagi.
        expect($kirim(['Detail' => $d, 'Jumlah' => '1', 'NomorSeri' => ['IMEI-0002']]))->toBe([['Ditolak', 'NomorSeriTidakSesuai']]);

        // Tanpa nomor: berurutan dari yang belum diretur (IMEI-0001, lalu IMEI-0003).
        expect($kirim(['Detail' => $d, 'Jumlah' => '1']))->toBe([['Diterima', null]]);
        expect(StatusSerialJual($hp))->toBe(['IMEI-0001' => 'Tersedia', 'IMEI-0002' => 'Tersedia', 'IMEI-0003' => 'Terjual']);
        expect($kirim(['Detail' => $d, 'Jumlah' => '1']))->toBe([['Diterima', null]]);

        expect(StatusSerialJual($hp))->toBe(['IMEI-0001' => 'Tersedia', 'IMEI-0002' => 'Tersedia', 'IMEI-0003' => 'Tersedia'])
            ->and(SaldoStok::query()->where('IdProduk', $hp->Id)->value('JumlahTersedia'))->toBe('3.0000')
            ->and(PemeriksaInvarian::PeriksaSemua($k['Tenant']->Id))->toBe([]);
    });
});
