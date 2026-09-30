<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Aksi;

use App\Domain\Akuntansi\Aksi\PostingJurnal;
use App\Domain\Akuntansi\Data\DataJurnal;
use App\Domain\Akuntansi\Enum\JenisSumberJurnal;
use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Dokumen\Enum\JenisDokumenBernomor;
use App\Domain\Bersama\Dokumen\Enum\StatusDokumenTerposting;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Nilai\Kuantitas;
use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Katalog\Kueri\InfoProdukStok;
use App\Domain\Organisasi\Kueri\OutletPenjualan;
use App\Domain\Pelanggan\Layanan\PencatatPiutangPenjualan;
use App\Domain\Penjualan\Data\DataBarisReturGrosir;
use App\Domain\Penjualan\Data\DataReturGrosir;
use App\Domain\Penjualan\Enum\KondisiBarangRetur;
use App\Domain\Penjualan\Layanan\PenghitungGrosir;
use App\Domain\Penjualan\Layanan\PenomorGrosir;
use App\Domain\Penjualan\Layanan\PenyusunJurnalGrosir;
use App\Domain\Penjualan\Model\ReturGrosir;
use App\Domain\Penjualan\Model\ReturGrosirDetail;
use App\Domain\Penjualan\Model\SuratJalan;
use App\Domain\Penjualan\Model\SuratJalanDetail;
use App\Domain\Persediaan\Aksi\CatatMutasiStok;
use App\Domain\Persediaan\Data\DataBarisMutasi;
use App\Domain\Persediaan\Data\DataDokumenMutasi;
use App\Domain\Persediaan\Enum\JenisMutasi;
use App\Domain\Persediaan\Enum\JenisReferensiMutasi;
use App\Domain\Persediaan\Enum\ModeNilaiMutasi;
use App\Domain\Persediaan\Layanan\PemeriksaLokasiDokumen;
use App\Domain\Persediaan\Layanan\PengunciSaldoStok;
use App\Domain\Persediaan\Layanan\PetaAkunPersediaan;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Retur grosir & nota kredit (F-12, §9.7, **BR-12.7**, J-12.4): barang kembali dari pembeli grosir.
 *
 * **Returnya mengacu ke surat jalan**, bukan ke SO maupun faktur, karena surat jalanlah yang memindahkan stok dan
 * mengakui pendapatan (BR-12.2) — jadi itulah yang dibalik sebagian. Harga, diskon, HPP, dan tarif pajaknya diambil
 * dari snapshot penyerahan itu: yang dibalik peristiwa yang sudah terjadi, bukan harga atau tarif hari ini.
 *
 * Semua akibatnya sinkron di satu transaksi (CLAUDE.md #10):
 * 1. stok masuk kembali pada **HPP saat keluar** (`JenisMutasi::ReturPenjualan`), ke lokasi asal bila `LayakJual` atau
 *    ke lokasi Rusak outlet bila `Rusak` (tidak ada lokasi Rusak → tetap diposting ke lokasi asal + `PerluTinjauan`,
 *    karena barangnya memang sudah kembali dan menolak dokumennya hanya akan membuat stok tidak pernah cocok);
 * 2. jurnal J-12.4: Dr Retur Penjualan + Dr PPN Keluaran (kontra) + Dr Persediaan / Cr piutang + Cr HPP;
 * 3. **nota kredit**: bila surat jalannya sudah difakturkan, sisa tagihan `Piutang` fakturnya dikurangi;
 * 4. `JumlahDiretur` baris surat jalan bertambah.
 *
 * **SO tidak diubah.** `JumlahTerkirim` tetap, karena penyerahannya memang pernah terjadi — sama seperti retur
 * pembelian yang tidak mengubah PO-nya. Retur adalah dokumen sesudahnya, bukan pembatalan pengiriman.
 *
 * Batas yang diketahui: bila faktur sudah (hampir) dibayar sehingga sisa tagihannya lebih kecil dari nilai retur,
 * dokumennya **ditolak** (`SisaPiutangTidakCukup`) — itu kasus refund uang, bukan pengurangan tagihan, dan memotong
 * sebisanya hanya akan menyembunyikan kewajiban toko di dokumen yang salah. Refund kas menyusul bila diminta.
 */
final class BuatReturGrosir
{
    public const PANJANG_ALASAN_MINIMAL = 5;

    public function __construct(
        private readonly PemeriksaLokasiDokumen $pemeriksaLokasi,
        private readonly OutletPenjualan $outletPenjualan,
        private readonly PenghitungGrosir $penghitung,
        private readonly PenomorGrosir $penomor,
        private readonly PengunciSaldoStok $pengunciSaldo,
        private readonly InfoProdukStok $infoProduk,
        private readonly PetaAkunPersediaan $petaAkunPersediaan,
        private readonly CatatMutasiStok $catatMutasi,
        private readonly PenyusunJurnalGrosir $penyusunJurnal,
        private readonly PostingJurnal $postingJurnal,
        private readonly PencatatPiutangPenjualan $pencatatPiutang,
        private readonly PencatatAudit $audit,
    ) {}

    /**
     * @throws PelanggaranAturanBisnis AlasanWajib, ReturTanpaBaris, SuratJalanTidakDitemukan, SuratJalanTidakAktif,
     *                                 BarisTidakDikenal, BarisGanda, JumlahReturTidakValid, JumlahReturMelebihiSisa,
     *                                 TanggalReturSebelumPenyerahan, TanggalMasaDepan, SisaPiutangTidakCukup
     */
    public function Jalankan(DataReturGrosir $data, int $idPengguna): ReturGrosir
    {
        $alasan = trim($data->alasan);

        if (mb_strlen($alasan) < self::PANJANG_ALASAN_MINIMAL || mb_strlen($alasan) > 255) {
            throw new PelanggaranAturanBisnis(
                'AlasanWajib',
                'Alasan retur wajib diisi, '.self::PANJANG_ALASAN_MINIMAL.' sampai 255 karakter.',
                'Alasan',
            );
        }

        if ($data->baris === []) {
            throw new PelanggaranAturanBisnis('ReturTanpaBaris', 'Retur harus punya minimal satu barang.', 'Baris');
        }

        return DB::transaction(
            fn (): ReturGrosir => $this->Buat($data, $alasan, $idPengguna),
            max(1, (int) config('persediaan.PercobaanTransaksi', 3)),
        );
    }

    private function Buat(DataReturGrosir $data, string $alasan, int $idPengguna): ReturGrosir
    {
        $suratJalan = SuratJalan::query()->with('Outlet')->where('Uuid', $data->uuidSuratJalan)->lockForUpdate()->first()
            ?? throw new PelanggaranAturanBisnis('SuratJalanTidakDitemukan', 'Surat jalan tidak ditemukan.');

        if ($suratJalan->Status !== StatusDokumenTerposting::Diposting) {
            throw new PelanggaranAturanBisnis(
                'SuratJalanTidakAktif',
                "Surat jalan {$suratJalan->Nomor} sudah dibatalkan, jadi tidak ada penyerahan yang bisa diretur.",
            );
        }

        $tanggal = CarbonImmutable::parse($data->tanggal->format('Y-m-d'));
        $this->pemeriksaLokasi->PastikanBukanMasaDepan($tanggal, $suratJalan->IdOutlet);
        $tanggalKirim = $suratJalan->Tanggal->format('Y-m-d');

        if ($tanggal->format('Y-m-d') < $tanggalKirim) {
            throw new PelanggaranAturanBisnis(
                'TanggalReturSebelumPenyerahan',
                "Tanggal retur tidak boleh sebelum penyerahannya ({$tanggalKirim}).",
                'Tanggal',
            );
        }

        $detailKirim = SuratJalanDetail::query()
            ->where('IdSuratJalan', $suratJalan->Id)
            ->orderBy('Urutan')
            ->lockForUpdate()
            ->get()
            ->keyBy('Urutan');
        $idGudangRusak = $this->outletPenjualan->AmbilIdGudangRusak($suratJalan->IdOutlet);
        $baris = $this->SusunBaris($data->baris, $detailKirim->all(), $suratJalan->IdGudang, $idGudangRusak);
        $adaRusakTanpaLokasi = $idGudangRusak === null
            && array_filter($data->baris, fn (DataBarisReturGrosir $b): bool => $b->kondisi === KondisiBarangRetur::Rusak) !== [];

        // Tarif pajaknya diambil pada tanggal PENYERAHAN, bukan tanggal retur: yang dibalik adalah PPN yang sudah
        // terutang saat barang diserahkan (UU PPN Pasal 11 ayat 1), jadi tarifnya harus tarif yang sama.
        $hasil = $this->penghitung->Hitung($suratJalan->IdOutlet, $suratJalan->Outlet->KodeKota, array_map(
            fn (array $b): array => [
                'Jumlah' => $b['Jumlah'],
                'HargaSatuan' => $b['Harga'],
                'Diskon' => $b['Diskon'],
                'IdKelompokPajak' => $b['IdKelompokPajak'],
                'HargaTermasukPajak' => $b['HargaTermasukPajak'],
            ],
            $baris,
        ), CarbonImmutable::parse($tanggalKirim));

        $this->pengunciSaldo->Kunci($this->PasanganSaldo($baris));
        $mengurangiPiutang = $suratJalan->IdFakturPenjualan !== null;

        $retur = ReturGrosir::query()->create([
            'Nomor' => $this->penomor->AmbilNomorOutlet(JenisDokumenBernomor::ReturGrosir, $tanggal, $suratJalan->IdOutlet),
            'IdSuratJalan' => $suratJalan->Id,
            'IdPelanggan' => $suratJalan->IdPelanggan,
            'IdOutlet' => $suratJalan->IdOutlet,
            'IdFakturPenjualan' => $suratJalan->IdFakturPenjualan,
            'Tanggal' => $tanggal->toDateString(),
            'Alasan' => $alasan,
            'MengurangiPiutang' => $mengurangiPiutang,
            'TarifPpn' => $hasil->tarifPpn,
            'PengaliDppPembilang' => $hasil->pengaliDppPembilang,
            'PengaliDppPenyebut' => $hasil->pengaliDppPenyebut,
            'Subtotal' => $hasil->subtotal->KeString(),
            'Diskon' => $hasil->diskon->KeString(),
            'DasarPengenaanPajak' => $hasil->dasarPengenaanPajak->KeString(),
            'Pajak' => $hasil->pajak->KeString(),
            'Total' => $hasil->total->KeString(),
            'RincianPajak' => array_map(fn (Uang $jumlah): string => $jumlah->KeString(), $hasil->rincianPajak),
            'Catatan' => $data->catatan,
            'DibuatOleh' => $idPengguna,
            'DiubahOleh' => $idPengguna,
        ]);

        if ($mengurangiPiutang) {
            $halangan = $this->pencatatPiutang->KurangiFaktur(
                (int) $suratJalan->IdFakturPenjualan,
                $hasil->total,
                $idPengguna,
                "Retur grosir {$retur->Nomor}",
            );

            if ($halangan !== null) {
                throw new PelanggaranAturanBisnis('SisaPiutangTidakCukup', $halangan);
            }
        }

        $detailRetur = [];

        foreach ($baris as $urutan => $satuBaris) {
            $detailRetur[$satuBaris['Urutan']] = ReturGrosirDetail::query()->create([
                'IdReturGrosir' => $retur->Id,
                'Urutan' => $urutan + 1,
                'IdSuratJalanDetail' => $satuBaris['IdSuratJalanDetail'],
                'IdProduk' => $satuBaris['IdProduk'],
                'NamaProduk' => $satuBaris['NamaProduk'],
                'Sku' => $satuBaris['Sku'],
                'SimbolSatuan' => $satuBaris['SimbolSatuan'],
                'Konversi' => $satuBaris['Konversi'],
                'Jumlah' => $satuBaris['Jumlah']->KeString(),
                'JumlahDasar' => $satuBaris['JumlahDasar']->KeString(),
                'Kondisi' => $satuBaris['Kondisi'],
                'IdGudang' => $satuBaris['IdGudang'],
                'Harga' => $satuBaris['Harga']->KeString(),
                'Diskon' => $satuBaris['Diskon']->KeString(),
                'Subtotal' => $satuBaris['Harga']->Kali($satuBaris['Jumlah']->KeString())->KeString(),
                'HargaTermasukPajak' => $satuBaris['HargaTermasukPajak'],
                'IdKelompokPajak' => $satuBaris['IdKelompokPajak'],
                'HppSatuan' => $satuBaris['HppSatuan'],
            ]);
        }

        $hasilMutasi = $this->catatMutasi->Jalankan(new DataDokumenMutasi(
            jenisReferensi: JenisReferensiMutasi::ReturGrosir,
            idReferensi: $retur->Id,
            uuidReferensi: $retur->Uuid,
            nomorReferensi: $retur->Nomor,
            tanggalBisnis: $tanggal,
            idPengguna: $idPengguna,
            idPerangkat: null,
            baris: array_values(array_map(fn (array $b): DataBarisMutasi => new DataBarisMutasi(
                kunciBaris: 'R/'.$b['Urutan'],
                idProduk: $b['IdProduk'],
                idGudang: $b['IdGudang'],
                jenisMutasi: JenisMutasi::ReturPenjualan,
                jumlah: $b['JumlahDasar'],
                modeNilai: ModeNilaiMutasi::Ditentukan,
                nilai: $b['NilaiHpp'],
                hppSatuan: BigDecimal::of($b['HppSatuan']),
                idReferensiDetail: $detailRetur[$b['Urutan']]->Id,
            ), $baris)),
        ));

        $produk = $this->infoProduk->AmbilBanyak(array_values(array_unique(array_column($baris, 'IdProduk'))), true);
        $perubahanPersediaan = [];
        $totalHpp = Uang::Nol();

        foreach ($baris as $satuBaris) {
            $mutasi = $hasilMutasi->baris['R/'.$satuBaris['Urutan']];
            $detail = $detailRetur[$satuBaris['Urutan']];
            $detail->TotalHpp = $mutasi->totalHpp->KeString();
            $detail->save();
            $totalHpp = $totalHpp->Tambah($mutasi->totalHpp);
            $peran = $this->petaAkunPersediaan->UntukJenis($produk[$satuBaris['IdProduk']]->jenis)->value;
            $perubahanPersediaan[$peran] = ($perubahanPersediaan[$peran] ?? Uang::Nol())->Tambah($mutasi->totalHpp);
        }

        $jurnal = $this->postingJurnal->Jalankan(new DataJurnal(
            jenisSumber: JenisSumberJurnal::ReturGrosir,
            idSumber: $retur->Id,
            uuidSumber: $retur->Uuid,
            nomorSumber: $retur->Nomor,
            tanggal: $tanggal,
            keterangan: mb_substr("Retur grosir {$retur->Nomor} atas surat jalan {$suratJalan->Nomor}", 0, 255),
            baris: $this->penyusunJurnal->BarisReturGrosir(
                $hasil->total,
                $hasil->diskon,
                $hasil->dasarPengenaanPajak->Tambah($hasil->diskon),
                $hasil->rincianPajak,
                $perubahanPersediaan,
                $retur->IdOutlet,
                $mengurangiPiutang,
            ),
            idPengguna: $idPengguna,
        ));

        foreach ($baris as $satuBaris) {
            $detail = $detailKirim[$satuBaris['Urutan']]
                ?? throw new LogicException("Baris surat jalan urutan {$satuBaris['Urutan']} hilang setelah disusun.");
            $detail->JumlahDiretur = $detail->AmbilJumlahDiretur()->Tambah($satuBaris['Jumlah'])->KeString();
            $detail->save();
        }

        $retur->fill([
            'TotalHpp' => $totalHpp->KeString(),
            'IdJurnal' => $jurnal->idJurnal,
            'PerluTinjauan' => $adaRusakTanpaLokasi,
            'AlasanTinjauan' => $adaRusakTanpaLokasi
                ? 'LokasiRusakTidakAda: barang rusak dikembalikan ke lokasi asal karena outlet belum punya lokasi stok Rusak'
                : null,
        ])->save();

        $this->audit->Catat('grosir.retur-buat', $retur, nilaiBaru: [
            'Nomor' => $retur->Nomor,
            'NomorSuratJalan' => $suratJalan->Nomor,
            'JumlahBaris' => count($baris),
            'Total' => $retur->Total,
            'TotalHpp' => $retur->TotalHpp,
            'MengurangiPiutang' => $mengurangiPiutang,
            'NomorJurnal' => $jurnal->nomor,
            'Alasan' => $alasan,
        ], idPengguna: $idPengguna);

        return $retur->load('Detail');
    }

    /**
     * @param  list<DataBarisReturGrosir>  $diminta
     * @param  array<int, SuratJalanDetail>  $detailKirim  berkunci Urutan
     * @return list<array{Urutan: int, IdSuratJalanDetail: int, IdProduk: int, NamaProduk: string, Sku: string|null, SimbolSatuan: string, Konversi: string, Jumlah: Kuantitas, JumlahDasar: Kuantitas, Kondisi: KondisiBarangRetur, IdGudang: int, Harga: Uang, Diskon: Uang, HargaTermasukPajak: bool|null, IdKelompokPajak: int|null, HppSatuan: string, NilaiHpp: Uang}>
     */
    private function SusunBaris(array $diminta, array $detailKirim, int $idGudangAsal, ?int $idGudangRusak): array
    {
        $hasil = [];
        $sudah = [];

        foreach ($diminta as $baris) {
            $detail = $detailKirim[$baris->urutanSuratJalan] ?? null;

            if ($detail === null) {
                throw new PelanggaranAturanBisnis('BarisTidakDikenal', "Baris nomor {$baris->urutanSuratJalan} tidak ada di surat jalan ini.", 'Baris');
            }

            if (isset($sudah[$baris->urutanSuratJalan])) {
                throw new PelanggaranAturanBisnis('BarisGanda', "Baris nomor {$baris->urutanSuratJalan} diretur dua kali dalam satu dokumen.", 'Baris');
            }

            $sudah[$baris->urutanSuratJalan] = true;

            if ($baris->jumlah->Bandingkan(Kuantitas::Nol()) <= 0) {
                throw new PelanggaranAturanBisnis('JumlahReturTidakValid', "Jumlah retur {$detail->NamaProduk} harus lebih dari nol.", 'Baris');
            }

            $sisa = $detail->AmbilSisaRetur();

            if ($baris->jumlah->Bandingkan($sisa) > 0) {
                throw new PelanggaranAturanBisnis(
                    'JumlahReturMelebihiSisa',
                    "Jumlah retur {$detail->NamaProduk} melebihi yang diserahkan dan belum diretur (sisa {$sisa->KeString()} {$detail->SimbolSatuan}).",
                    'Baris',
                    detail: ['Urutan' => $detail->Urutan, 'Sisa' => $sisa->KeString()],
                );
            }

            $jumlahDasar = $baris->jumlah->Kali($detail->Konversi);
            $hppSatuan = BigDecimal::of($detail->HppSatuan);
            $hasil[] = [
                'Urutan' => $detail->Urutan,
                'IdSuratJalanDetail' => $detail->Id,
                'IdProduk' => $detail->IdProduk,
                'NamaProduk' => $detail->NamaProduk,
                'Sku' => $detail->Sku,
                'SimbolSatuan' => $detail->SimbolSatuan,
                'Konversi' => $detail->Konversi,
                'Jumlah' => $baris->jumlah,
                'JumlahDasar' => $jumlahDasar,
                'Kondisi' => $baris->kondisi,
                'IdGudang' => $baris->kondisi === KondisiBarangRetur::Rusak && $idGudangRusak !== null ? $idGudangRusak : $idGudangAsal,
                'Harga' => $detail->AmbilHarga(),
                'Diskon' => $this->AlokasikanDiskon($detail, $baris->jumlah, $sisa),
                'HargaTermasukPajak' => $detail->HargaTermasukPajak,
                'IdKelompokPajak' => $detail->IdKelompokPajak,
                'HppSatuan' => $detail->HppSatuan,
                // Nilai stok yang kembali = HPP saat barang keluar, bukan HPP berjalan hari ini.
                'NilaiHpp' => Uang::Dari($hppSatuan->multipliedBy($jumlahDasar->KeDesimal())->toScale(Uang::SKALA, RoundingMode::HalfUp)),
            ];
        }

        return $hasil;
    }

    /**
     * Diskon baris surat jalan dibagi sebanding jumlah retur; retur yang **menutup** baris menerima seluruh sisa
     * diskonnya, supaya Σ diskon retur tepat sama dengan diskon barisnya bila seluruhnya dikembalikan.
     */
    private function AlokasikanDiskon(SuratJalanDetail $detail, Kuantitas $jumlahRetur, Kuantitas $sisa): Uang
    {
        $diskon = $detail->AmbilDiskon();

        if ($diskon->BernilaiNol()) {
            return Uang::Nol();
        }

        $terpakai = Uang::Nol();

        foreach (ReturGrosirDetail::query()
            ->where('IdSuratJalanDetail', $detail->Id)
            ->whereIn('IdReturGrosir', ReturGrosir::query()->where('Status', StatusDokumenTerposting::Diposting->value)->select('Id'))
            ->get() as $sudah) {
            $terpakai = $terpakai->Tambah($sudah->AmbilDiskon());
        }

        if ($jumlahRetur->SamaDengan($sisa)) {
            return $diskon->Kurangi($terpakai);
        }

        return $diskon->Kali(
            $jumlahRetur->KeDesimal()->dividedBy($detail->AmbilJumlah()->KeDesimal(), 10, RoundingMode::HalfUp),
            RoundingMode::HalfUp,
        );
    }

    /**
     * Pasangan produk-lokasi yang dikunci, tanpa duplikat dan urut IdProduk (aturan akuntansi & stok).
     *
     * @param  list<array{IdProduk: int, IdGudang: int}>  $baris
     * @return list<array{0: int, 1: int}>
     */
    private function PasanganSaldo(array $baris): array
    {
        $pasangan = [];

        foreach ($baris as $satuBaris) {
            $pasangan[$satuBaris['IdProduk'].':'.$satuBaris['IdGudang']] = [$satuBaris['IdProduk'], $satuBaris['IdGudang']];
        }

        $pasangan = array_values($pasangan);
        usort($pasangan, fn (array $a, array $b): int => $a <=> $b);

        return $pasangan;
    }
}
