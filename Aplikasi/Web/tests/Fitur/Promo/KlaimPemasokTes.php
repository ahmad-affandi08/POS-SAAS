<?php

declare(strict_types=1);

use App\Domain\Akuntansi\Enum\JenisSumberJurnal;
use App\Domain\Akuntansi\Enum\PeranAkun;
use App\Domain\Akuntansi\Model\Akun;
use App\Domain\Akuntansi\Model\Jurnal;
use App\Domain\Akuntansi\Model\JurnalDetail;
use App\Domain\Organisasi\Enum\PeranTenantBawaan;
use App\Domain\Penjualan\Model\Penjualan;
use App\Domain\Promo\Enum\StatusKlaimPromo;
use App\Domain\Promo\Model\KlaimPromoPemasok;
use App\Domain\Promo\Model\PenerimaanKlaimPemasok;
use App\Domain\Promo\Model\Promo;
use Inertia\Testing\AssertableInertia;
use Tests\Pendukung\Akuntansi\BantuanJurnal;
use Tests\Pendukung\Kasir\BantuanKasir;
use Tests\Pendukung\Organisasi\BantuanOrganisasi;
use Tests\Pendukung\Pembelian\BantuanPembelian;
use Tests\Pendukung\Penjualan\BantuanPenjualan;
use Tests\Pendukung\Persediaan\BantuanPersediaan;
use Tests\Pendukung\Persediaan\PemeriksaInvarian;
use Tests\Pendukung\Tenant\BantuanPendaftaran;
use Tests\TestCase;

/*
 * F-16c bagian 4b pendanaan promo (PSAK 72): potongan ke pelanggan tetap Diskon Penjualan; bagian yang ditanggung
 * pemasok menjadi klaim per transaksi; penerimaan pembayaran klaim dijurnal Dr kas/bank, Cr HPP (J-16.5).
 */

beforeEach(function (): void {
    BantuanPendaftaran::SiapkanPrasyarat();
});

/**
 * Promo diskon 10% produk Rp 38.500, 60% ditanggung pemasok.
 *
 * @return array<string, mixed>
 */
function SiapkanPromoPemasok(TestCase $tes): array
{
    $k = BantuanPenjualan::Siapkan($tes, 'Minimarket Berkah Promo');
    $produk = BantuanPenjualan::BuatProdukBerstok($k['Gudang'], $k['Pemilik']->Id);
    $pemasok = BantuanPembelian::BuatPemasok('PT Kopi Nusantara Distribusi');
    $promo = Promo::query()->create([
        'Kode' => 'KOPI10',
        'Nama' => 'Diskon 10% kopi, didukung distributor',
        'Definisi' => [
            'Hari' => [], 'JamMulai' => null, 'JamSelesai' => null, 'Outlet' => [], 'Kanal' => [], 'Tier' => [],
            'MinimalSubtotal' => '0.00',
            'Kondisi' => ['Jenis' => 'Produk', 'Uuid' => [$produk->Uuid], 'JumlahMinimal' => '0.0000'],
            'Aksi' => ['Jenis' => 'DiskonPersenItem', 'Persen' => '10'],
            'BatasPerTransaksi' => null,
        ],
        'IdPemasok' => $pemasok->Id,
        'PersenDanaPemasok' => '60.00',
    ]);

    return $k + ['Produk' => $produk, 'Promo' => $promo, 'Pemasok' => $pemasok];
}

/**
 * @param  array<string, mixed>  $k
 * @return array{Jenis: string, Uuid: string, Data: array<string, mixed>}
 */
function ItemPromoPemasok(array $k): array
{
    return BantuanPenjualan::Item($k, [
        'Baris' => [['Produk' => $k['Produk'], 'Jumlah' => '2', 'Harga' => '38500.00']],
        'Promo' => [['Promo' => $k['Promo'], 'Baris' => [0 => '7700.00']]],
    ]);
}

describe('F-16c bagian 4b klaim promo pemasok', function (): void {
    it('klaim = potongan × bagian pemasok per transaksi; void membatalkan klaim; diskon tetap di jurnal penjualan', function (): void {
        $k = SiapkanPromoPemasok($this);
        $satu = ItemPromoPemasok($k);
        $dua = ItemPromoPemasok($k);
        expect(BantuanKasir::KirimRingkas($this, $k['Token'], [$satu, $dua]))->toBe([['Diterima', null], ['Diterima', null]]);
        // Kirim ulang tidak menggandakan klaim.
        expect(BantuanKasir::KirimRingkas($this, $k['Token'], [$satu]))->toBe([['Duplikat', null]]);

        BantuanOrganisasi::AturKonteks($k['Tenant']->Id);
        // 7.700 × 60% = 4.620 per transaksi.
        expect(KlaimPromoPemasok::query()->count())->toBe(2)
            ->and(KlaimPromoPemasok::query()->pluck('Jumlah')->map(fn ($j): string => (string) $j)->all())->toBe(['4620.00', '4620.00']);

        $jualDua = Penjualan::query()->where('Uuid', $dua['Uuid'])->sole();
        expect(BantuanKasir::KirimRingkas($this, $k['Token'], [BantuanPenjualan::ItemVoid($k, $jualDua)]))->toBe([['Diterima', null]]);
        BantuanOrganisasi::AturKonteks($k['Tenant']->Id);
        expect(KlaimPromoPemasok::query()->where('IdPenjualan', $jualDua->Id)->sole()->Status)->toBe(StatusKlaimPromo::Dibatalkan);

        // Diskon penjualan tetap penuh 7.700 di jurnal penjualan (kontra pendapatan).
        $jualSatu = Penjualan::query()->where('Uuid', $satu['Uuid'])->sole();
        $jurnal = Jurnal::query()->where('JenisSumber', JenisSumberJurnal::Penjualan->value)->where('IdSumber', $jualSatu->Id)->orderBy('Id')->firstOrFail();
        expect((string) JurnalDetail::query()->where('IdJurnal', $jurnal->Id)->where('IdAkun', BantuanJurnal::IdAkunPeran(PeranAkun::DiskonPenjualan))->sum('Debit'))->toBe('7700.00');
    });

    it('penerimaan klaim: semua klaim terbuka ≤ tanggal diterima, jurnal Dr kas/bank Cr HPP seimbang; halaman & izin', function (): void {
        $k = SiapkanPromoPemasok($this);
        expect(BantuanKasir::KirimRingkas($this, $k['Token'], [ItemPromoPemasok($k), ItemPromoPemasok($k)]))->toBe([['Diterima', null], ['Diterima', null]]);

        BantuanPersediaan::MasukSebagai($this, $k['Tenant']->Id, PeranTenantBawaan::Pemilik);
        $this->get('/kelola/promo/klaim-pemasok')->assertOk()->assertInertia(fn (AssertableInertia $h) => $h
            ->component('Kelola/Promo/KlaimPemasok')
            ->where('Terbuka.0.NamaPemasok', 'PT Kopi Nusantara Distribusi')
            ->where('Terbuka.0.JumlahTransaksi', 2)
            ->where('Terbuka.0.Total', '9240.00')
            ->where('Izin.Terima', true));

        BantuanOrganisasi::AturKonteks($k['Tenant']->Id);
        $kas = Akun::query()->where('KasBank', true)->orderBy('Kode')->firstOrFail();
        $hariIni = now($k['Outlet']->ZonaWaktu)->toDateString();
        $isian = ['UuidPemasok' => $k['Pemasok']->Uuid, 'Tanggal' => $hariIni, 'UuidAkunKasBank' => $kas->Uuid, 'Keterangan' => 'Transfer klaim Oktober'];

        $this->post('/kelola/promo/klaim-pemasok/penerimaan', [...$isian, 'UuidAkunKasBank' => ''])->assertSessionHasErrors('UuidAkunKasBank');
        $this->post('/kelola/promo/klaim-pemasok/penerimaan', $isian)->assertRedirect('/kelola/promo/klaim-pemasok');

        BantuanOrganisasi::AturKonteks($k['Tenant']->Id);
        $penerimaan = PenerimaanKlaimPemasok::query()->sole();
        $jurnal = Jurnal::query()->whereKey($penerimaan->IdJurnal)->firstOrFail();
        $baris = JurnalDetail::query()->where('IdJurnal', $jurnal->Id)->orderBy('Urutan')->get()
            ->map(fn (JurnalDetail $d): array => [$d->IdAkun, (string) $d->Debit, (string) $d->Kredit])->all();
        expect((string) $penerimaan->Jumlah)->toBe('9240.00')
            ->and($jurnal->JenisSumber)->toBe(JenisSumberJurnal::PenerimaanKlaimPemasok)
            ->and($baris)->toBe([
                [$kas->Id, '9240.00', '0.00'],
                [BantuanJurnal::IdAkunPeran(PeranAkun::Hpp), '0.00', '9240.00'],
            ])
            ->and(KlaimPromoPemasok::query()->where('Status', StatusKlaimPromo::Diterima->value)->count())->toBe(2)
            ->and(PemeriksaInvarian::PeriksaJurnalSeimbang($k['Tenant']->Id))->toBe([]);

        // Tidak ada lagi klaim terbuka.
        $this->post('/kelola/promo/klaim-pemasok/penerimaan', $isian)->assertSessionHasErrors('Tanggal');

        // Kasir tanpa izin pelanggan.lihat tidak bisa membuka halaman klaim.
        BantuanPersediaan::MasukSebagai($this, $k['Tenant']->Id, PeranTenantBawaan::Kasir);
        $this->get('/kelola/promo/klaim-pemasok')->assertForbidden();
    });

    it('formulir promo: bagian pemasok 0–100%, bagian > 0 wajib pemasok; tersimpan di promo', function (): void {
        $k = SiapkanPromoPemasok($this);
        BantuanPersediaan::MasukSebagai($this, $k['Tenant']->Id, PeranTenantBawaan::Pemilik);
        $isian = [
            'Kode' => 'SUSU20', 'Nama' => 'Susu UHT 20% didanai pabrik', 'Prioritas' => 0, 'Eksklusif' => false,
            'TanggalMulai' => null, 'TanggalSelesai' => null, 'Kuota' => null, 'Hari' => [], 'JamMulai' => null, 'JamSelesai' => null,
            'Outlet' => [], 'Kanal' => [], 'Tier' => [], 'MinimalSubtotal' => '0', 'JenisKondisi' => 'Semua', 'UuidKondisi' => [],
            'JumlahMinimal' => '0', 'JenisAksi' => 'DiskonPersenPesanan', 'Persen' => '20',
            'UuidPemasok' => $k['Pemasok']->Uuid, 'PersenDanaPemasok' => '100',
        ];

        $this->post('/kelola/promo', [...$isian, 'UuidPemasok' => null, 'PersenDanaPemasok' => '50'])->assertSessionHasErrors('UuidPemasok');
        $this->post('/kelola/promo', [...$isian, 'PersenDanaPemasok' => '150'])->assertSessionHasErrors('PersenDanaPemasok');
        $this->post('/kelola/promo', [...$isian, 'UuidPemasok' => '01K5ZZZZZZZZZZZZZZZZZZZZZZ'])->assertSessionHasErrors('UuidPemasok');
        $this->post('/kelola/promo', $isian)->assertRedirect('/kelola/promo');

        BantuanOrganisasi::AturKonteks($k['Tenant']->Id);
        $promo = Promo::query()->where('Kode', 'SUSU20')->sole();
        expect($promo->IdPemasok)->toBe($k['Pemasok']->Id)
            ->and((string) $promo->PersenDanaPemasok)->toBe('100.00');
        $this->get("/kelola/promo/{$promo->Uuid}/ubah")->assertOk()->assertInertia(fn (AssertableInertia $h) => $h
            ->where('Promo.UuidPemasok', $k['Pemasok']->Uuid)
            ->where('Promo.PersenDanaPemasok', '100.00')
            ->where('OpsiPemasok.0.Label', 'PT Kopi Nusantara Distribusi'));
    });
});
