<?php

declare(strict_types=1);

use App\Domain\Akuntansi\Enum\JenisSumberJurnal;
use App\Domain\Akuntansi\Enum\PeranAkun;
use App\Domain\Akuntansi\Layanan\PenentuAkun;
use App\Domain\Akuntansi\Model\Jurnal;
use App\Domain\Akuntansi\Model\JurnalDetail;
use App\Domain\Bersama\Audit\Model\LogAudit;
use App\Domain\Bersama\Dokumen\Model\RiwayatStatusDokumen;
use App\Domain\Bersama\Nilai\Kuantitas;
use App\Domain\Katalog\Enum\JenisProduk;
use App\Domain\Katalog\Model\Produk;
use App\Domain\Katalog\PaketProduk\Aksi\SimpanKomponenPaket;
use App\Domain\Katalog\PaketProduk\Data\DataKomponenPaket;
use App\Domain\Katalog\Pilihan\Model\Pilihan;
use App\Domain\Organisasi\Enum\PeranTenantBawaan;
use App\Domain\Organisasi\Kueri\TanggalBisnisOutlet;
use App\Domain\Organisasi\Model\Outlet;
use App\Domain\Penjualan\Enum\JenisMetodePembayaran;
use App\Domain\Penjualan\Enum\StatusPenjualan;
use App\Domain\Penjualan\Model\Penjualan;
use App\Domain\Penjualan\Model\PenjualanDetail;
use App\Domain\Penjualan\Model\PenjualanPajak;
use App\Domain\Penjualan\Model\PenjualanPembayaran;
use App\Domain\Persediaan\Enum\JenisReferensiMutasi;
use App\Domain\Persediaan\Model\MutasiStok;
use App\Domain\Persediaan\Model\SaldoStok;
use Illuminate\Support\Carbon;
use Tests\Pendukung\Kasir\BantuanKasir;
use Tests\Pendukung\Katalog\BantuanKatalog;
use Tests\Pendukung\Katalog\BantuanKomposisi;
use Tests\Pendukung\Organisasi\BantuanOrganisasi;
use Tests\Pendukung\PanduanAwal\BantuanPanduanAwal;
use Tests\Pendukung\Penjualan\BantuanPenjualan;
use Tests\Pendukung\Persediaan\BantuanPersediaan;
use Tests\Pendukung\Persediaan\PemeriksaInvarian;
use Tests\Pendukung\Tenant\BantuanPendaftaran;

beforeEach(function (): void {
    BantuanPendaftaran::SiapkanPrasyarat();
});

/**
 * Saldo (debit − kredit) jurnal pada akun peran tertentu (dimensi outlet).
 */
function SaldoPeranJurnalPenjualan(int $idJurnal, PeranAkun $peran, int $idOutlet): string
{
    $idAkun = app(PenentuAkun::class)->AmbilIdAkun($peran, $idOutlet);
    $baris = JurnalDetail::query()->where('IdJurnal', $idJurnal)->where('IdAkun', $idAkun)->get();
    $saldo = Kuantitas::Nol();

    foreach ($baris as $b) {
        $saldo = $saldo->Tambah(Kuantitas::Dari($b->Debit))->Kurangi(Kuantitas::Dari($b->Kredit));
    }

    return (string) $saldo->KeDesimal()->toScale(2);
}

function SaldoStokPenjualan(int $idProduk, int $idGudang): ?string
{
    return SaldoStok::query()->where('IdProduk', $idProduk)->where('IdGudang', $idGudang)->value('JumlahTersedia');
}

describe('F-07b penjualan lewat sinkron (Penjualan.Buat): diterima & tersimpan', function (): void {
    it('penjualan PPN 12% (DPP 11/12) split QRIS + tunai: dokumen, baris, pajak, pembayaran, stok, jurnal J-07.1/J-07.2, riwayat & audit tercatat; invariant terjaga', function (): void {
        $k = BantuanPenjualan::Siapkan($this);
        BantuanPanduanAwal::TerbitkanTarif('Ppn', null, '12.000000');
        BantuanOrganisasi::AturKonteks($k['Tenant']->Id);
        $minyak = BantuanPenjualan::BuatProdukBerstok($k['Gudang'], $k['Pemilik']->Id);
        $jasa = BantuanKatalog::BuatProduk(['Nama' => 'Jasa Antar Belanja Dalam Kota', 'Jenis' => JenisProduk::Jasa], '10000.00');
        $dibuat = now()->subMinutes(7)->toImmutable();

        $item = BantuanPenjualan::Item($k, [
            'DibuatPada' => $dibuat,
            'Pajak' => [['Ppn', '12.000000', 11, 12]],
            'Baris' => [
                ['Produk' => $minyak, 'Jumlah' => '2', 'Harga' => '38500.00'],
                ['Produk' => $jasa, 'Jumlah' => '1', 'Harga' => '10000.00'],
            ],
            'Pembayaran' => [
                ['Metode' => $k['Qris'], 'Jumlah' => '50000.00', 'Referensi' => 'QR-88123'],
                ['Metode' => $k['Tunai'], 'Jumlah' => '50000.00'],
            ],
        ]);

        expect($item['Data']['Ringkasan'])->toBe(['Subtotal' => '87000.00', 'TotalPajak' => '9570.00', 'Pembulatan' => '0.00', 'TotalAkhir' => '96570.00', 'Kembalian' => '3430.00']);

        $respons = BantuanKasir::Kirim($this, $k['Token'], [$item])->assertOk();
        expect($respons->json('Hasil'))->toBe([['Uuid' => $item['Uuid'], 'Jenis' => 'Penjualan.Buat', 'Status' => 'Diterima', 'Galat' => null]]);

        BantuanOrganisasi::AturKonteks($k['Tenant']->Id);
        $p = Penjualan::query()->where('Uuid', $item['Uuid'])->sole();
        expect($p->Status)->toBe(StatusPenjualan::Lunas)
            ->and($p->Nomor)->toBe($item['Data']['Nomor'])
            ->and($p->IdPengguna)->toBe($k['Kasir']->Id)
            ->and($p->IdOutlet)->toBe($k['Outlet']->Id)
            ->and($p->IdPerangkat)->toBe($k['Perangkat']->Id)
            ->and($p->Subtotal)->toBe('87000.00')
            ->and($p->TotalPajak)->toBe('9570.00')
            ->and($p->TotalAkhir)->toBe('96570.00')
            ->and($p->TotalDibayar)->toBe('100000.00')
            ->and($p->Kembalian)->toBe('3430.00')
            ->and($p->TotalHpp)->toBe('60000.00')
            ->and($p->PerluTinjauan)->toBeFalse()
            ->and($p->DibuatOfflinePada->toIso8601String())->toBe(Carbon::parse($dibuat)->utc()->startOfSecond()->toIso8601String())
            ->and($p->DiterimaPada)->not->toBeNull()
            ->and($p->IdJurnal)->not->toBeNull();

        $detail = PenjualanDetail::query()->where('IdPenjualan', $p->Id)->orderBy('Urutan')->get();
        expect($detail)->toHaveCount(2)
            ->and($detail[0]->Bruto)->toBe('77000.00')
            ->and($detail[0]->JumlahPajak)->toBe('8470.00')
            ->and($detail[0]->PajakEksklusif)->toBe('8470.00')
            ->and($detail[0]->TotalBaris)->toBe('85470.00')
            ->and($detail[0]->HppSatuan)->toBe('30000.000000')
            ->and($detail[0]->TotalHpp)->toBe('60000.00')
            ->and($detail[0]->SnapshotPajak)->toEqual([['Kode' => 'Ppn', 'Tarif' => '12.000000', 'PengaliDppPembilang' => 11, 'PengaliDppPenyebut' => 12, 'DasarPengenaan' => 'Subtotal']])
            ->and($detail[1]->JumlahPajak)->toBe('1100.00')
            ->and($detail[1]->TotalHpp)->toBe('0.00');

        $pajak = PenjualanPajak::query()->where('IdPenjualan', $p->Id)->sole();
        expect($pajak->KodeJenisPajak)->toBe('Ppn')
            ->and($pajak->Dpp)->toBe('79750.00')
            ->and($pajak->Jumlah)->toBe('9570.00');

        $bayar = PenjualanPembayaran::query()->where('IdPenjualan', $p->Id)->orderBy('Urutan')->get();
        expect($bayar->pluck('Jumlah')->all())->toBe(['50000.00', '50000.00'])
            ->and($bayar[0]->Referensi)->toBe('QR-88123');

        $mutasi = MutasiStok::query()->where('JenisReferensi', JenisReferensiMutasi::Penjualan->value)->where('IdReferensi', $p->Id)->sole();
        expect($mutasi->Jumlah)->toBe('-2.0000')
            ->and($mutasi->IdGudang)->toBe($k['Gudang']->Id)
            ->and($mutasi->IdReferensiDetail)->toBe($detail[0]->Id)
            ->and(SaldoStokPenjualan($minyak->Id, $k['Gudang']->Id))->toBe('8.0000');

        $j = (int) $p->IdJurnal;
        $o = $k['Outlet']->Id;
        expect(Jurnal::query()->findOrFail($j)->JenisSumber)->toBe(JenisSumberJurnal::Penjualan)
            ->and(SaldoPeranJurnalPenjualan($j, PeranAkun::PiutangPencairan, $o))->toBe('50000.00')
            ->and(SaldoPeranJurnalPenjualan($j, PeranAkun::KasOutlet, $o))->toBe('46570.00')
            ->and(SaldoPeranJurnalPenjualan($j, PeranAkun::Penjualan, $o))->toBe('-77000.00')
            ->and(SaldoPeranJurnalPenjualan($j, PeranAkun::PendapatanJasa, $o))->toBe('-10000.00')
            ->and(SaldoPeranJurnalPenjualan($j, PeranAkun::PpnKeluaran, $o))->toBe('-9570.00')
            ->and(SaldoPeranJurnalPenjualan($j, PeranAkun::Hpp, $o))->toBe('60000.00')
            ->and(SaldoPeranJurnalPenjualan($j, PeranAkun::PersediaanBarangDagang, $o))->toBe('-60000.00')
            ->and(RiwayatStatusDokumen::query()->where('JenisDokumen', 'Penjualan')->where('IdDokumen', $p->Id)->value('StatusKe'))->toBe('Lunas')
            ->and(LogAudit::query()->where('Peristiwa', 'penjualan.terima')->where('IdPengguna', $k['Kasir']->Id)->count())->toBe(1)
            ->and(PemeriksaInvarian::PeriksaSemua($k['Tenant']->Id))->toBe([]);
    });

    it('F&B harga termasuk PBJT 10% + biaya layanan 5% (masuk DPP) dan pembulatan tunai ke bawah Rp 100: jurnal seimbang, pembulatan negatif di debit Pendapatan Lain', function (): void {
        $k = BantuanPenjualan::Siapkan($this);
        $k['Outlet']->forceFill(['KodeKota' => '33.72'])->save();
        BantuanPanduanAwal::TerbitkanTarif('PbjtMakananMinuman', '33.72', '10.000000', true);
        BantuanOrganisasi::AturKonteks($k['Tenant']->Id);
        $kopi = BantuanKatalog::BuatProduk(['Nama' => 'Es Kopi Susu Gula Aren Ukuran Besar', 'Jenis' => JenisProduk::NonStok], '27500.00');

        $item = BantuanPenjualan::Item($k, [
            'HargaTermasukPajak' => true,
            'PersenBiayaLayanan' => '5',
            'PembulatanTunai' => [100, 'Bawah'],
            'Pajak' => [['PbjtMakananMinuman', '10.000000', 1, 1, 'SubtotalPlusLayanan']],
            'Baris' => [['Produk' => $kopi, 'Jumlah' => '3', 'Harga' => '27500.00']],
            'Pembayaran' => [['Metode' => $k['Tunai'], 'Jumlah' => '100000.00']],
        ]);

        expect(BantuanKasir::KirimRingkas($this, $k['Token'], [$item]))->toBe([['Diterima', null]]);

        BantuanOrganisasi::AturKonteks($k['Tenant']->Id);
        $p = Penjualan::query()->where('Uuid', $item['Uuid'])->sole();
        $j = (int) $p->IdJurnal;
        $o = $k['Outlet']->Id;
        expect($p->Pembulatan)->toBe($item['Data']['Ringkasan']['Pembulatan'])
            ->and(str_starts_with($p->Pembulatan, '-'))->toBeTrue()
            ->and(SaldoPeranJurnalPenjualan($j, PeranAkun::KasOutlet, $o))->toBe($p->TotalAkhir)
            ->and(SaldoPeranJurnalPenjualan($j, PeranAkun::PendapatanLain, $o))->toBe(ltrim($p->Pembulatan, '-'))
            ->and(SaldoPeranJurnalPenjualan($j, PeranAkun::PendapatanBiayaLayanan, $o))->toBe('-'.$p->BiayaLayanan)
            ->and(SaldoPeranJurnalPenjualan($j, PeranAkun::HutangPbjt, $o))->toBe('-'.$p->TotalPajak)
            ->and(PemeriksaInvarian::PeriksaSemua($k['Tenant']->Id))->toBe([]);
    });
});

describe('F-07b idempotensi & isolasi', function (): void {
    it('BR-07.6 Uuid sama dikirim ulang = Duplikat tanpa dokumen/stok/jurnal ganda; batch dikirim ulang seluruhnya Duplikat; Uuid sama dengan nomor lain = UuidSudahDipakai', function (): void {
        $k = BantuanPenjualan::Siapkan($this);
        $minyak = BantuanPenjualan::BuatProdukBerstok($k['Gudang'], $k['Pemilik']->Id);
        $satu = BantuanPenjualan::Item($k, ['Baris' => [['Produk' => $minyak, 'Jumlah' => '1', 'Harga' => '38500.00']]]);
        $dua = BantuanPenjualan::Item($k, ['Baris' => [['Produk' => $minyak, 'Jumlah' => '3', 'Harga' => '38500.00']]]);

        expect(BantuanKasir::KirimRingkas($this, $k['Token'], [$satu, $dua]))->toBe([['Diterima', null], ['Diterima', null]])
            ->and(BantuanKasir::KirimRingkas($this, $k['Token'], [$satu, $dua]))->toBe([['Duplikat', null], ['Duplikat', null]]);

        $lain = $satu;
        $lain['Data']['Nomor'] = BantuanPenjualan::Nomor($k, 9001);
        expect(BantuanKasir::KirimRingkas($this, $k['Token'], [$lain]))->toBe([['Ditolak', 'UuidSudahDipakai']]);

        BantuanOrganisasi::AturKonteks($k['Tenant']->Id);
        expect(Penjualan::query()->count())->toBe(2)
            ->and(MutasiStok::query()->where('JenisReferensi', JenisReferensiMutasi::Penjualan->value)->count())->toBe(2)
            ->and(Jurnal::query()->where('JenisSumber', JenisSumberJurnal::Penjualan->value)->count())->toBe(2)
            ->and(SaldoStokPenjualan($minyak->Id, $k['Gudang']->Id))->toBe('6.0000')
            ->and(PemeriksaInvarian::PeriksaSemua($k['Tenant']->Id))->toBe([]);
    });

    it('isolasi tenant: produk, shift, dan metode bayar tenant lain tidak bisa dirujuk; Uuid penjualan tenant lain = UuidSudahDipakai', function (): void {
        $a = BantuanPenjualan::Siapkan($this, 'Toko Kelontong Berkah Solo');
        $minyakA = BantuanPenjualan::BuatProdukBerstok($a['Gudang'], $a['Pemilik']->Id);
        $itemA = BantuanPenjualan::Item($a, ['Baris' => [['Produk' => $minyakA, 'Jumlah' => '1', 'Harga' => '38500.00']]]);
        expect(BantuanKasir::KirimRingkas($this, $a['Token'], [$itemA]))->toBe([['Diterima', null]]);

        $b = BantuanPenjualan::Siapkan($this, 'Warung Bakso Pak Kumis');
        $minyakB = BantuanPenjualan::BuatProdukBerstok($b['Gudang'], $b['Pemilik']->Id);

        expect(BantuanKasir::KirimRingkas($this, $b['Token'], [
            BantuanPenjualan::Item($b, ['Baris' => [['Produk' => $minyakA, 'Jumlah' => '1', 'Harga' => '38500.00']]]),
            BantuanPenjualan::Item($b, ['Baris' => [['Produk' => $minyakB, 'Jumlah' => '1', 'Harga' => '38500.00']]], ['UuidShift' => $a['UuidShift']]),
            BantuanPenjualan::Item($b, ['Baris' => [['Produk' => $minyakB, 'Jumlah' => '1', 'Harga' => '38500.00']], 'Pembayaran' => [['Metode' => $a['Tunai'], 'Jumlah' => '38500.00']]]),
            BantuanPenjualan::Item($b, ['Baris' => [['Produk' => $minyakB, 'Jumlah' => '1', 'Harga' => '38500.00']]], uuid: $itemA['Uuid']),
        ]))->toBe([
            ['Ditolak', 'ProdukTidakDikenal'],
            ['Ditolak', 'ShiftTidakDitemukan'],
            ['Ditolak', 'MetodeBayarTidakDikenal'],
            ['Ditolak', 'UuidSudahDipakai'],
        ]);

        BantuanOrganisasi::AturKonteks($b['Tenant']->Id);
        expect(Penjualan::query()->count())->toBe(0);
        BantuanOrganisasi::AturKonteks($a['Tenant']->Id);
        expect(Penjualan::query()->count())->toBe(1)
            ->and(SaldoStokPenjualan($minyakA->Id, $a['Gudang']->Id))->toBe('9.0000');
    });
});

describe('F-07b aturan penolakan', function (): void {
    it('shift, kasir, izin, nomor (BR-07.1), waktu: ShiftTidakDitemukan, KasirTidakDitemukan, TanpaIzin, NomorTidakValid, NomorSudahDipakai, WaktuTidakValid', function (): void {
        $k = BantuanPenjualan::Siapkan($this);
        $minyak = BantuanPenjualan::BuatProdukBerstok($k['Gudang'], $k['Pemilik']->Id);
        $gudang = BantuanOrganisasi::TambahAnggota($k['Tenant']->Id, PeranTenantBawaan::StafGudang);
        $luarOutlet = BantuanOrganisasi::TambahAnggota($k['Tenant']->Id, PeranTenantBawaan::Kasir, semuaOutlet: false);
        $baris = ['Baris' => [['Produk' => $minyak, 'Jumlah' => '1', 'Harga' => '38500.00']]];
        $pertama = BantuanPenjualan::Item($k, $baris);

        expect(BantuanKasir::KirimRingkas($this, $k['Token'], [
            $pertama,
            BantuanPenjualan::Item($k, $baris, ['UuidShift' => BantuanKasir::Uuid()]),
            BantuanPenjualan::Item($k, $baris + ['Kasir' => $luarOutlet]),
            BantuanPenjualan::Item($k, $baris + ['Kasir' => $gudang]),
            BantuanPenjualan::Item($k, $baris, ['Nomor' => 'INV/UTAMA/260924/K01-0001']),
            BantuanPenjualan::Item($k, $baris, ['Nomor' => $pertama['Data']['Nomor']]),
            BantuanPenjualan::Item($k, $baris + ['DibuatPada' => now()->addHour()->toImmutable()]),
            BantuanPenjualan::Item($k, $baris + ['DibuatPada' => now()->subDays(2)->toImmutable()]),
        ]))->toBe([
            ['Diterima', null],
            ['Ditolak', 'ShiftTidakDitemukan'],
            ['Ditolak', 'KasirTidakDitemukan'],
            ['Ditolak', 'TanpaIzin'],
            ['Ditolak', 'NomorTidakValid'],
            ['Ditolak', 'NomorSudahDipakai'],
            ['Ditolak', 'WaktuTidakValid'],
            ['Ditolak', 'WaktuTidakValid'],
        ]);
    });

    it('produk: bahan baku, induk varian, konsinyasi = ProdukTidakBisaDijual; batch/seri (juga sebagai bahan resep) = PelacakanBelumDidukung; produk diarsipkan/dihapus setelah dijual offline tetap diterima (dihapus: stok tidak dikurangi, ditinjau)', function (): void {
        $k = BantuanPenjualan::Siapkan($this);
        $semua = BantuanPersediaan::BuatProdukSemuaJenis(BantuanKomposisi::Satuan('Pieces', 'pcs', false), BantuanKomposisi::Satuan('Kilogram', 'kg'));
        $induk = BantuanKatalog::BuatProduk(['Nama' => 'Kaos Polos Katun Combed 30s', 'Jenis' => JenisProduk::IndukVarian]);
        $resepBatch = BantuanKomposisi::BuatProdukResep('Susu Segar Karamel Dingin');
        BantuanKomposisi::SimpanResep($resepBatch, [[$semua['Batch'], '1']]);
        $diarsipkan = BantuanPenjualan::BuatProdukBerstok($k['Gudang'], $k['Pemilik']->Id, 'Sabun Cuci Piring Jeruk Nipis 800 ml', '5', '12000', '15500.00');
        $diarsipkan->forceFill(['DiarsipkanPada' => now(), 'Aktif' => false])->save();
        // Produk hanya bisa dihapus bila belum pernah dipakai (BR-03.2), jadi belum punya stok.
        $dihapus = BantuanKatalog::BuatProduk(['Nama' => 'Pewangi Pakaian Sachet Lavender'], '1500.00');
        $dihapus->delete();

        $item = fn (Produk $p) => BantuanPenjualan::Item($k, ['Baris' => [['Produk' => $p, 'Jumlah' => '1', 'Harga' => '15500.00']]]);

        expect(BantuanKasir::KirimRingkas($this, $k['Token'], [
            $item($semua['BahanBaku']),
            $item($induk),
            $item($semua['Konsinyasi']),
            $item($semua['Batch']),
            $item($semua['Seri']),
            $item($resepBatch),
            $item($diarsipkan),
            $itemDihapus = $item($dihapus),
        ]))->toBe([
            ['Ditolak', 'ProdukTidakBisaDijual'],
            ['Ditolak', 'ProdukTidakBisaDijual'],
            ['Ditolak', 'ProdukTidakBisaDijual'],
            ['Ditolak', 'PelacakanBelumDidukung'],
            ['Ditolak', 'PelacakanBelumDidukung'],
            ['Ditolak', 'PelacakanBelumDidukung'],
            ['Diterima', null],
            ['Diterima', null],
        ]);

        BantuanOrganisasi::AturKonteks($k['Tenant']->Id);
        expect(SaldoStokPenjualan($diarsipkan->Id, $k['Gudang']->Id))->toBe('4.0000')
            ->and(SaldoStokPenjualan($dihapus->Id, $k['Gudang']->Id))->toBeNull()
            ->and(Penjualan::query()->where('Uuid', $itemDihapus['Uuid'])->sole()->AlasanTinjauan)->toContain('ProdukDihapus')
            ->and(PemeriksaInvarian::PeriksaSemua($k['Tenant']->Id))->toBe([]);
    });

    it('CLAUDE.md #12 tarif pajak snapshot wajib sama dengan TarifPajak terbit yang berlaku: tarif berbeda, pengali berbeda, PBJT tanpa kota outlet = TarifPajakTidakSah', function (): void {
        $k = BantuanPenjualan::Siapkan($this);
        BantuanPanduanAwal::TerbitkanTarif('Ppn', null, '12.000000');
        BantuanPanduanAwal::TerbitkanTarif('PbjtMakananMinuman', '33.72', '10.000000');
        BantuanOrganisasi::AturKonteks($k['Tenant']->Id);
        $kopi = BantuanKatalog::BuatProduk(['Nama' => 'Kopi Tubruk Robusta Temanggung', 'Jenis' => JenisProduk::NonStok], '15000.00');
        $item = fn (array $pajak) => BantuanPenjualan::Item($k, ['Pajak' => [$pajak], 'Baris' => [['Produk' => $kopi, 'Jumlah' => '1', 'Harga' => '15000.00']]]);

        expect(BantuanKasir::KirimRingkas($this, $k['Token'], [
            $item(['Ppn', '11.000000', 1, 1]),
            $item(['Ppn', '12.000000', 1, 1]),
            $item(['PbjtMakananMinuman', '10.000000', 1, 1]),
            $item(['PajakKarangan', '5.000000', 1, 1]),
            $item(['Ppn', '12.000000', 11, 12]),
        ]))->toBe([
            ['Ditolak', 'TarifPajakTidakSah'],
            ['Ditolak', 'TarifPajakTidakSah'],
            ['Ditolak', 'TarifPajakTidakSah'],
            ['Ditolak', 'TarifPajakTidakSah'],
            ['Diterima', null],
        ]);
    });

    it('BR-07.3 diskon manual: kasir tanpa izin = TanpaIzin; di atas batas tanpa penyetuju = DiskonMelebihiBatas; supervisor menyetujui sampai 30%; di atas 30% hanya Pemilik; penyetuju tanpa izin = PenyetujuTidakBerwenang', function (): void {
        $k = BantuanPenjualan::Siapkan($this);
        $pemilik = BantuanOrganisasi::TambahAnggota($k['Tenant']->Id, PeranTenantBawaan::Pemilik);
        $kasirLain = BantuanOrganisasi::TambahAnggota($k['Tenant']->Id, PeranTenantBawaan::Kasir);
        BantuanOrganisasi::AturKonteks($k['Tenant']->Id);
        $kopi = BantuanKatalog::BuatProduk(['Nama' => 'Kopi Bubuk Robusta Temanggung 250 gram', 'Jenis' => JenisProduk::NonStok], '100000.00');
        $diskon = fn (array $d, array $opsi = []) => BantuanPenjualan::Item($k, ['Baris' => [['Produk' => $kopi, 'Jumlah' => '1', 'Harga' => '100000.00', 'DiskonManual' => $d]]] + $opsi);

        $hasil = BantuanKasir::KirimRingkas($this, $k['Token'], [
            $d1 = $diskon(['Persen' => '5']),
            $d2 = $diskon(['Jumlah' => '15000.00']),
            $d3 = $diskon(['Persen' => '5'], ['Penyetuju' => $k['Supervisor']]),
            $d4 = $diskon(['Persen' => '30'], ['Penyetuju' => $k['Supervisor']]),
            $d5 = $diskon(['Persen' => '30.01'], ['Penyetuju' => $k['Supervisor']]),
            $d6 = $diskon(['Persen' => '80'], ['Penyetuju' => $pemilik]),
            $d7 = $diskon(['Persen' => '20'], ['Kasir' => $k['Supervisor']]),
            $d8 = $diskon(['Persen' => '100'], ['Kasir' => $pemilik]),
            $d9 = $diskon(['Persen' => '5'], ['Penyetuju' => $kasirLain]),
            $d10 = BantuanPenjualan::Item($k, ['Baris' => [['Produk' => $kopi, 'Jumlah' => '1', 'Harga' => '100000.00']], 'DiskonManualPesanan' => ['Persen' => '25'], 'Penyetuju' => $k['Supervisor']]),
        ]);

        expect($hasil)->toBe([
            ['Ditolak', 'TanpaIzin'],
            ['Ditolak', 'DiskonMelebihiBatas'],
            ['Diterima', null],
            ['Diterima', null],
            ['Ditolak', 'DiskonMelebihiBatas'],
            ['Diterima', null],
            ['Diterima', null],
            ['Diterima', null],
            ['Ditolak', 'PenyetujuTidakBerwenang'],
            ['Diterima', null],
        ]);

        BantuanOrganisasi::AturKonteks($k['Tenant']->Id);
        $p3 = Penjualan::query()->where('Uuid', $d3['Uuid'])->sole();
        $p10 = Penjualan::query()->where('Uuid', $d10['Uuid'])->sole();
        expect($p3->IdPenyetujuDiskon)->toBe($k['Supervisor']->Id)
            ->and($p3->TotalDiskon)->toBe('5000.00')
            ->and($p10->DiskonPesanan)->toBe('25000.00')
            ->and(SaldoPeranJurnalPenjualan((int) $p3->IdJurnal, PeranAkun::DiskonPenjualan, $k['Outlet']->Id))->toBe('5000.00')
            ->and(Penjualan::query()->where('Uuid', $d8['Uuid'])->sole()->IdJurnal)->not->toBeNull()
            ->and(PemeriksaInvarian::PeriksaSemua($k['Tenant']->Id))->toBe([]);
        unset($d1, $d2, $d4, $d5, $d6, $d7, $d9);
    });

    it('BR-07.3 penyetuju = kasir sendiri (perangkat tanpa dialog PIN): diterima bila kasir ber-izin penjualan.diskon.setujui sampai BatasDiskonPenyetuju; Pemilik tanpa batas; kasir tanpa izin setujui = PenyetujuTidakBerwenang', function (): void {
        $k = BantuanPenjualan::Siapkan($this);
        $pemilik = BantuanOrganisasi::TambahAnggota($k['Tenant']->Id, PeranTenantBawaan::Pemilik);
        BantuanOrganisasi::AturKonteks($k['Tenant']->Id);
        $kopi = BantuanKatalog::BuatProduk(['Nama' => 'Kopi Susu Gula Aren Literan', 'Jenis' => JenisProduk::NonStok], '80000.00');
        $diskon = fn (string $persen, $kasir) => BantuanPenjualan::Item($k, ['Baris' => [['Produk' => $kopi, 'Jumlah' => '1', 'Harga' => '80000.00', 'DiskonManual' => ['Persen' => $persen]]], 'Kasir' => $kasir, 'Penyetuju' => $kasir]);

        $hasil = BantuanKasir::KirimRingkas($this, $k['Token'], [
            $sendiri = $diskon('30', $k['Supervisor']),
            $diskon('31', $k['Supervisor']),
            $diskon('90', $pemilik),
            $diskon('5', $k['Kasir']),
        ]);

        expect($hasil)->toBe([
            ['Diterima', null],
            ['Ditolak', 'DiskonMelebihiBatas'],
            ['Diterima', null],
            ['Ditolak', 'PenyetujuTidakBerwenang'],
        ]);

        BantuanOrganisasi::AturKonteks($k['Tenant']->Id);
        $p = Penjualan::query()->where('Uuid', $sendiri['Uuid'])->sole();
        expect($p->IdPenyetujuDiskon)->toBe($k['Supervisor']->Id)
            ->and($p->IdPengguna)->toBe($k['Supervisor']->Id);
    });

    it('batas diskon mengikuti pengaturan kasir tenant (BatasDiskonManual/BatasDiskonPenyetuju)', function (): void {
        $k = BantuanPenjualan::Siapkan($this);
        $tenant = $k['Tenant']->refresh();
        $tenant->Pengaturan = [...($tenant->Pengaturan ?? []), 'BatasDiskonManual' => '0', 'BatasDiskonPenyetuju' => '15'];
        $tenant->save();
        BantuanOrganisasi::AturKonteks($k['Tenant']->Id);
        $kopi = BantuanKatalog::BuatProduk(['Nama' => 'Teh Melati Tubruk Premium', 'Jenis' => JenisProduk::NonStok], '20000.00');
        $diskon = fn (string $persen) => BantuanPenjualan::Item($k, ['Baris' => [['Produk' => $kopi, 'Jumlah' => '1', 'Harga' => '20000.00', 'DiskonManual' => ['Persen' => $persen]]], 'Penyetuju' => $k['Supervisor']]);

        expect(BantuanKasir::KirimRingkas($this, $k['Token'], [$diskon('15'), $diskon('16')]))->toBe([['Diterima', null], ['Ditolak', 'DiskonMelebihiBatas']]);
    });

    it('hitung ulang MesinKalkulasi: Ringkasan berbeda = HitunganTidakCocok; harga pilihan tidak sama dengan Σ pilihan = HitunganTidakCocok; harga jual snapshot berbeda dari harga katalog tetap diterima (§18.3)', function (): void {
        $k = BantuanPenjualan::Siapkan($this);
        $minyak = BantuanPenjualan::BuatProdukBerstok($k['Gudang'], $k['Pemilik']->Id);
        $baris = ['Baris' => [['Produk' => $minyak, 'Jumlah' => '2', 'Harga' => '35000.00']]];
        $salah = BantuanPenjualan::Item($k, $baris);
        $salah['Data']['Ringkasan']['TotalAkhir'] = '70100.00';
        $pilihan = BantuanPenjualan::Item($k, $baris);
        $pilihan['Data']['Baris'][0]['Pilihan'] = [['UuidPilihan' => BantuanKasir::Uuid(), 'Nama' => 'Bungkus kado', 'Harga' => '2000.00']];

        $respons = BantuanKasir::Kirim($this, $k['Token'], [$salah, $pilihan, BantuanPenjualan::Item($k, $baris)])->assertOk();

        expect($respons->json('Hasil.0.Galat.Kode'))->toBe('HitunganTidakCocok')
            ->and($respons->json('Hasil.0.Galat.Detail'))->toBe(['Perangkat' => '70100.00', 'Server' => '70000.00'])
            ->and($respons->json('Hasil.1.Galat.Kode'))->toBe('HitunganTidakCocok')
            ->and($respons->json('Hasil.2.Status'))->toBe('Diterima');
    });

    it('pembayaran fase 1: metode lain = MetodeBayarBelumDidukung; dua tunai atau non-tunai melebihi total = PembayaranTidakValid; kurang = PembayaranKurang; metode nonaktif setelah transaksi offline tetap diterima', function (): void {
        $k = BantuanPenjualan::Siapkan($this);
        $minyak = BantuanPenjualan::BuatProdukBerstok($k['Gudang'], $k['Pemilik']->Id);
        $nonaktif = BantuanPenjualan::BuatMetode(JenisMetodePembayaran::Ewallet, 'GoPay Toko', false);
        $baris = ['Baris' => [['Produk' => $minyak, 'Jumlah' => '1', 'Harga' => '38500.00']]];

        expect(BantuanKasir::KirimRingkas($this, $k['Token'], [
            BantuanPenjualan::Item($k, $baris + ['Pembayaran' => [['Metode' => $k['Tempo'], 'Jumlah' => '38500.00']]]),
            BantuanPenjualan::Item($k, $baris + ['Pembayaran' => [['Metode' => $k['Tunai'], 'Jumlah' => '20000.00'], ['Metode' => $k['Tunai'], 'Jumlah' => '20000.00']]]),
            BantuanPenjualan::Item($k, $baris + ['Pembayaran' => [['Metode' => $k['Edc'], 'Jumlah' => '40000.00']]]),
            BantuanPenjualan::Item($k, $baris + ['Pembayaran' => [['Metode' => $k['Transfer'], 'Jumlah' => '30000.00']]]),
            BantuanPenjualan::Item($k, $baris + ['Pembayaran' => [['Metode' => $k['Tunai'], 'Jumlah' => '30000.00']]], ['Ringkasan' => ['Subtotal' => '38500.00', 'TotalPajak' => '0.00', 'Pembulatan' => '0.00', 'TotalAkhir' => '38500.00', 'Kembalian' => '0.00']]),
            BantuanPenjualan::Item($k, $baris + ['Pembayaran' => [['Metode' => $nonaktif, 'Jumlah' => '38500.00']]]),
        ]))->toBe([
            ['Ditolak', 'MetodeBayarBelumDidukung'],
            ['Ditolak', 'PembayaranTidakValid'],
            ['Ditolak', 'PembayaranTidakValid'],
            ['Ditolak', 'PembayaranKurang'],
            ['Ditolak', 'PembayaranKurang'],
            ['Diterima', null],
        ]);

        BantuanOrganisasi::AturKonteks($k['Tenant']->Id);
        $p = Penjualan::query()->sole();
        expect(SaldoPeranJurnalPenjualan((int) $p->IdJurnal, PeranAkun::PiutangPencairan, $k['Outlet']->Id))->toBe('38500.00');
    });

    it('periode terkunci = PeriodeTerkunci tanpa dokumen, stok, atau jurnal tersimpan (sementara; §18.3 posting ke periode berikutnya = utang)', function (): void {
        $k = BantuanPenjualan::Siapkan($this);
        $minyak = BantuanPenjualan::BuatProdukBerstok($k['Gudang'], $k['Pemilik']->Id);
        $item = BantuanPenjualan::Item($k, ['Baris' => [['Produk' => $minyak, 'Jumlah' => '1', 'Harga' => '38500.00']]]);
        BantuanOrganisasi::AturKonteks($k['Tenant']->Id);
        $tanggal = app(TanggalBisnisOutlet::class)->Hitung($k['Outlet']->Id, now()->subMinutes(5));
        BantuanPersediaan::KunciPeriode($tanggal->format('Y-m'));

        expect(BantuanKasir::KirimRingkas($this, $k['Token'], [$item]))->toBe([['Ditolak', 'PeriodeTerkunci']]);

        BantuanOrganisasi::AturKonteks($k['Tenant']->Id);
        expect(Penjualan::query()->count())->toBe(0)
            ->and(MutasiStok::query()->where('JenisReferensi', JenisReferensiMutasi::Penjualan->value)->count())->toBe(0)
            ->and(SaldoStokPenjualan($minyak->Id, $k['Gudang']->Id))->toBe('10.0000');
    });
});

describe('F-07b stok penjualan (aturan #9–#10)', function (): void {
    it('§18.3 stok tidak cukup tidak menolak: diterima, stok minus, PerluTinjauan StokTidakCukup; invariant SaldoStok = Σ MutasiStok & Σ debit = Σ kredit terjaga', function (): void {
        $k = BantuanPenjualan::Siapkan($this);
        $minyak = BantuanPenjualan::BuatProdukBerstok($k['Gudang'], $k['Pemilik']->Id, jumlah: '2');
        $item = BantuanPenjualan::Item($k, ['Baris' => [['Produk' => $minyak, 'Jumlah' => '5', 'Harga' => '38500.00']]]);

        expect(BantuanKasir::KirimRingkas($this, $k['Token'], [$item]))->toBe([['Diterima', null]]);

        BantuanOrganisasi::AturKonteks($k['Tenant']->Id);
        $p = Penjualan::query()->sole();
        expect($p->PerluTinjauan)->toBeTrue()
            ->and($p->AlasanTinjauan)->toContain('StokTidakCukup')
            ->and($p->AlasanTinjauan)->toContain('Minyak Goreng Sawit')
            ->and(SaldoStokPenjualan($minyak->Id, $k['Gudang']->Id))->toBe('-3.0000')
            ->and($p->TotalHpp)->toBe('150000.00')
            ->and(PemeriksaInvarian::PeriksaSemua($k['Tenant']->Id))->toBe([]);
    });

    it('produk Stok satuan dus (konversi 12), Resep (bahan versi terbaru + susut), Paket (komponen stok + komponen resep), dan pilihan berbahan mengurangi stok satuan dasar; HPP per baris & jurnal per jenis persediaan', function (): void {
        $k = BantuanPenjualan::Siapkan($this);
        $minyak = BantuanPenjualan::BuatProdukBerstok($k['Gudang'], $k['Pemilik']->Id, jumlah: '30');
        $dus = BantuanKomposisi::TambahSatuanProduk($minyak, BantuanKomposisi::Satuan('Dus', 'dus', false), '12');
        $gula = BantuanKomposisi::BuatBahan('Gula Aren Cair Asli Banyumas');
        BantuanPenjualan::IsiStok($k['Gudang'], $gula, '1000', '50', $k['Pemilik']->Id);
        $esKopi = BantuanKomposisi::BuatProdukResep();
        BantuanKomposisi::SimpanResep($esKopi, [[$gula, '15']]);
        BantuanKomposisi::SimpanResep($esKopi, [[$gula, '20', null, '20']]);
        $kelompok = BantuanKomposisi::BuatKelompokPilihan('Tambahan', [['Extra gula aren', '3000', $gula, '10']], 0, 1);
        $extra = Pilihan::query()->where('IdKelompokPilihan', $kelompok->Id)->sole();
        $paket = BantuanKomposisi::BuatPaket('Paket Dapur Hemat Minyak + Es Kopi');
        app(SimpanKomponenPaket::class)->Jalankan($paket, [
            new DataKomponenPaket($minyak->Id, Kuantitas::Dari('1'), null),
            new DataKomponenPaket($esKopi->Id, Kuantitas::Dari('1'), null),
        ]);

        $item = BantuanPenjualan::Item($k, ['Baris' => [
            ['Produk' => $minyak, 'Satuan' => $dus, 'Jumlah' => '1', 'Harga' => '450000.00'],
            ['Produk' => $esKopi, 'Jumlah' => '2', 'Harga' => '25000.00', 'Pilihan' => [$extra]],
            ['Produk' => $paket, 'Jumlah' => '1', 'Harga' => '60000.00'],
        ]]);

        expect(BantuanKasir::KirimRingkas($this, $k['Token'], [$item]))->toBe([['Diterima', null]]);

        BantuanOrganisasi::AturKonteks($k['Tenant']->Id);
        $p = Penjualan::query()->sole();
        $detail = PenjualanDetail::query()->where('IdPenjualan', $p->Id)->orderBy('Urutan')->get();
        $j = (int) $p->IdJurnal;
        $o = $k['Outlet']->Id;

        expect($detail[0]->JumlahDasar)->toBe('12.0000')
            ->and($detail[0]->KonversiKeDasar)->toBe('12.0000')
            ->and($detail[0]->TotalHpp)->toBe('360000.00')
            ->and($detail[0]->HppSatuan)->toBe('360000.000000')
            ->and($detail[1]->TotalHpp)->toBe('3500.00')
            ->and($detail[1]->HargaPilihan)->toBe('3000.00')
            ->and($detail[1]->Bruto)->toBe('56000.00')
            ->and($detail[2]->TotalHpp)->toBe('31250.00')
            ->and($p->TotalHpp)->toBe('394750.00')
            ->and(SaldoStokPenjualan($minyak->Id, $k['Gudang']->Id))->toBe('17.0000')
            ->and(SaldoStokPenjualan($gula->Id, $k['Gudang']->Id))->toBe('905.0000')
            ->and(SaldoPeranJurnalPenjualan($j, PeranAkun::Hpp, $o))->toBe('394750.00')
            ->and(SaldoPeranJurnalPenjualan($j, PeranAkun::PersediaanBarangDagang, $o))->toBe('-390000.00')
            ->and(SaldoPeranJurnalPenjualan($j, PeranAkun::PersediaanBahanBaku, $o))->toBe('-4750.00')
            ->and(PemeriksaInvarian::PeriksaSemua($k['Tenant']->Id))->toBe([]);
    });

    it('pilihan yang dihapus setelah transaksi offline: bahan tidak dikurangi, penjualan diterima dan ditandai PilihanTidakDikenal', function (): void {
        $k = BantuanPenjualan::Siapkan($this);
        $kopi = BantuanKatalog::BuatProduk(['Nama' => 'Kopi Susu Aren', 'Jenis' => JenisProduk::NonStok], '20000.00');
        $item = BantuanPenjualan::Item($k, ['Baris' => [['Produk' => $kopi, 'Jumlah' => '1', 'Harga' => '20000.00']]]);
        $item['Data']['Baris'][0]['Pilihan'] = [['UuidPilihan' => BantuanKasir::Uuid(), 'Nama' => 'Oat milk', 'Harga' => '0.00']];

        expect(BantuanKasir::KirimRingkas($this, $k['Token'], [$item]))->toBe([['Diterima', null]]);

        BantuanOrganisasi::AturKonteks($k['Tenant']->Id);
        expect(Penjualan::query()->sole()->AlasanTinjauan)->toContain('PilihanTidakDikenal');
    });

    it('dokumen lunas tidak bisa diubah atau dihapus (CLAUDE.md #8)', function (): void {
        $k = BantuanPenjualan::Siapkan($this);
        $minyak = BantuanPenjualan::BuatProdukBerstok($k['Gudang'], $k['Pemilik']->Id);
        BantuanKasir::KirimRingkas($this, $k['Token'], [BantuanPenjualan::Item($k, ['Baris' => [['Produk' => $minyak, 'Jumlah' => '1', 'Harga' => '38500.00']]])]);
        BantuanOrganisasi::AturKonteks($k['Tenant']->Id);
        $p = Penjualan::query()->sole();

        expect(fn () => $p->forceFill(['TotalAkhir' => '1.00'])->save())->toThrow(LogicException::class)
            ->and(fn () => Penjualan::query()->sole()->delete())->toThrow(LogicException::class)
            ->and(fn () => PenjualanDetail::query()->firstOrFail()->forceFill(['Jumlah' => '9'])->save())->toThrow(LogicException::class)
            ->and(fn () => PenjualanPembayaran::query()->firstOrFail()->forceFill(['Jumlah' => '1.00'])->save())->toThrow(LogicException::class)
            ->and(fn () => $p->refresh()->forceFill(['Status' => StatusPenjualan::Void])->save())->not->toThrow(LogicException::class);
        unset($k);
    });
});

describe('F-07b data-awal', function (): void {
    it('data-awal memuat pengaturan diskon & pembulatan, outlet, perangkat, profil pajak, tarif pajak nasional + kota outlet, metode bayar aktif fase 1', function (): void {
        $k = BantuanPenjualan::Siapkan($this);
        Outlet::query()->whereKey($k['Outlet']->Id)->update(['KodeKota' => '33.72', 'Alamat' => 'Jl. Slamet Riyadi 12, Solo']);
        BantuanPanduanAwal::TerbitkanTarif('Ppn', null, '12.000000');
        BantuanPanduanAwal::TerbitkanTarif('PbjtMakananMinuman', '33.72', '10.000000');
        BantuanPanduanAwal::TerbitkanTarif('PbjtMakananMinuman', '31.71', '10.000000');
        BantuanOrganisasi::AturKonteks($k['Tenant']->Id);
        BantuanPenjualan::BuatMetode(JenisMetodePembayaran::Ewallet, 'OVO lama', false);

        $respons = $this->withToken($k['Token'])->getJson('/api/pos/v1/data-awal')->assertOk();

        expect($respons->json('Pengaturan'))->toBe([
            'BatasKasKeluar' => '200000.00',
            'ShiftBersama' => false,
            'BatasDiskonManual' => '10.00',
            'BatasDiskonPenyetuju' => '30.00',
            'PembulatanTunai' => null,
            'TutupShiftButa' => true,
            'ToleransiSelisihKas' => '10000.00',
            'BatasHariRetur' => 7,
        ])
            ->and($respons->json('Outlet'))->toBe(['Uuid' => $k['Outlet']->Uuid, 'Kode' => $k['Outlet']->Kode, 'Nama' => $k['Outlet']->Nama, 'Alamat' => 'Jl. Slamet Riyadi 12, Solo', 'Telepon' => null, 'ZonaWaktu' => 'Asia/Jakarta', 'JamTutupBuku' => '04:00'])
            ->and($respons->json('Perangkat'))->toBe(['Uuid' => $k['Perangkat']->Uuid, 'Kode' => $k['Perangkat']->Kode])
            ->and(array_keys($respons->json('ProfilPajak')))->toBe(['Pkp', 'PungutPbjt', 'HargaTermasukPajak', 'BiayaLayanan'])
            ->and(array_column($respons->json('TarifPajak'), 'KodeJenisPajak'))->toBe(['PbjtMakananMinuman', 'Ppn'])
            ->and($respons->json('TarifPajak.1.PengaliDppPembilang'))->toBe(11)
            ->and(array_column($respons->json('MetodePembayaran'), 'Jenis'))->toBe(['Tunai', 'QrisStatis', 'Edc', 'Transfer'])
            ->and($respons->json('MetodePembayaran.1'))->toHaveKeys(['Uuid', 'Jenis', 'Nama', 'NomorRekening', 'NamaPemilikRekening', 'AdaGambarQris', 'Urutan']);
    });
});
