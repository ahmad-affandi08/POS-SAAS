<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Layanan;

use App\Domain\Akuntansi\Data\HasilPostingJurnal;
use App\Domain\Akuntansi\Enum\JenisSumberJurnal;
use App\Domain\Akuntansi\Enum\PeranAkun;
use App\Domain\Bersama\Nilai\Kuantitas;
use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Katalog\Data\DataInfoProdukStok;
use App\Domain\Katalog\Enum\PelacakanProduk;
use App\Domain\Organisasi\Data\DataInfoGudang;
use App\Domain\Organisasi\Kueri\InfoGudang;
use App\Domain\Persediaan\Aksi\CatatMutasiStok;
use App\Domain\Persediaan\Data\DataBarisDokumenStok;
use App\Domain\Persediaan\Data\DataBarisMutasi;
use App\Domain\Persediaan\Data\DataBatchMasuk;
use App\Domain\Persediaan\Data\DataDokumenMutasi;
use App\Domain\Persediaan\Enum\JenisMutasi;
use App\Domain\Persediaan\Enum\JenisReferensiMutasi;
use App\Domain\Persediaan\Enum\ModeNilaiMutasi;
use App\Domain\Persediaan\Enum\StatusPenyesuaianStok;
use App\Domain\Persediaan\Layanan\Hpp\AritmetikaHpp;
use App\Domain\Persediaan\Model\PenyesuaianStok;
use App\Domain\Persediaan\Model\PenyesuaianStokDetail;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

/**
 * Posting penyesuaian stok F-05b (dipakai `AjukanPenyesuaianStok` di bawah batas dan `SetujuiPenyesuaianStok`), di
 * dalam transaksi & kunci dokumen pemanggil: baris masuk = `PenyesuaianMasuk` bernilai jumlah × harga modal (J-05.5,
 * Cr Selisih HPP); baris keluar = `Susut`/`PenyesuaianKeluar` sesuai alasan, dinilai HPP berjalan (J-05.4, Dr
 * Susut & Barang Rusak atau Selisih HPP). Nomor `PS/…` diambil di sini; `Nilai` baris = perubahan nilai sebenarnya.
 */
final class PemostingPenyesuaianStok
{
    public function __construct(
        private readonly PemeriksaLokasiDokumen $pemeriksaLokasi,
        private readonly PemeriksaBarisStok $pemeriksaBaris,
        private readonly InfoGudang $infoGudang,
        private readonly PenomorDokumenPersediaan $penomor,
        private readonly CatatMutasiStok $catatMutasi,
        private readonly PenyusunJurnalPersediaan $penyusunJurnal,
        private readonly PencatatJurnalPersediaan $pencatatJurnal,
    ) {}

    /**
     * Memeriksa ulang isi dokumen & mengunci SaldoStok pasangannya (L3). Mengembalikan lokasi, produk, dan baris.
     *
     * @return array{0: DataInfoGudang, 1: array<int, DataInfoProdukStok>, 2: Collection<int, PenyesuaianStokDetail>, 3: list<DataBarisDokumenStok>}
     */
    public function Siapkan(PenyesuaianStok $dokumen, PengunciSaldoStok $pengunci): array
    {
        $gudang = $this->pemeriksaLokasi->AmbilLokasi($dokumen->IdGudang);
        $detail = $dokumen->Detail()->orderBy('Urutan')->get();
        $baris = array_values($detail->map(fn (PenyesuaianStokDetail $d): DataBarisDokumenStok => new DataBarisDokumenStok(
            idProduk: $d->IdProduk,
            jumlah: Kuantitas::Dari($d->Jumlah),
            hppSatuan: $d->HppSatuan === null ? null : BigDecimal::of($d->HppSatuan),
            idBatchStok: Kuantitas::Dari($d->Jumlah)->BernilaiNegatif() ? $d->IdBatchStok : null,
            nomorBatch: Kuantitas::Dari($d->Jumlah)->BernilaiNegatif() ? null : $d->NomorBatch,
            tanggalKedaluwarsa: Kuantitas::Dari($d->Jumlah)->BernilaiNegatif() || $d->TanggalKedaluwarsa === null ? null : CarbonImmutable::parse($d->TanggalKedaluwarsa->format('Y-m-d')),
            idNomorSeri: Kuantitas::Dari($d->Jumlah)->BernilaiNegatif() ? $d->IdNomorSeri : null,
            nomorSeri: Kuantitas::Dari($d->Jumlah)->BernilaiNegatif() ? null : $d->NomorSeri,
        ))->all());
        $pengunci->Kunci(array_values($detail->map(fn (PenyesuaianStokDetail $d): array => [$d->IdProduk, $gudang->id])->all()));
        $produk = $this->pemeriksaBaris->Periksa($baris, $gudang->id, false, $dokumen->KodeAlasan->CekBolehMasuk());

        return [$gudang, $produk, $detail, $baris];
    }

    /**
     * @param  array<int, DataInfoProdukStok>  $produk
     * @param  Collection<int, PenyesuaianStokDetail>  $detail
     */
    public function Posting(PenyesuaianStok $dokumen, DataInfoGudang $gudang, array $produk, Collection $detail, int $idPengguna): ?HasilPostingJurnal
    {
        $tanggal = CarbonImmutable::parse($dokumen->Tanggal->format('Y-m-d'));
        $nomor = $this->penomor->AmbilPenyesuaian($tanggal, $gudang->kode);
        $alasan = $dokumen->KodeAlasan;

        $hasil = $this->catatMutasi->Jalankan(new DataDokumenMutasi(
            JenisReferensiMutasi::PenyesuaianStok,
            $dokumen->Id,
            $dokumen->Uuid,
            $nomor,
            $tanggal,
            $idPengguna,
            null,
            array_values($detail->map(function (PenyesuaianStokDetail $d) use ($produk, $gudang, $alasan): DataBarisMutasi {
                $jumlah = Kuantitas::Dari($d->Jumlah);
                $masuk = ! $jumlah->BernilaiNegatif();
                $pelacakan = $produk[$d->IdProduk]->pelacakan;
                $hpp = $d->HppSatuan === null ? null : BigDecimal::of($d->HppSatuan);

                return new DataBarisMutasi(
                    kunciBaris: 'P/'.$d->Id,
                    idProduk: $d->IdProduk,
                    idGudang: $gudang->id,
                    jenisMutasi: $masuk ? JenisMutasi::PenyesuaianMasuk : $alasan->AmbilJenisMutasiKeluar(),
                    jumlah: $jumlah,
                    modeNilai: $masuk ? ModeNilaiMutasi::Ditentukan : ModeNilaiMutasi::Berjalan,
                    nilai: $masuk ? AritmetikaHpp::Nilai($jumlah, $hpp ?? BigDecimal::zero()) : null,
                    hppSatuan: $masuk ? $hpp : null,
                    idReferensiDetail: $d->Id,
                    batchMasuk: $masuk && $pelacakan === PelacakanProduk::Batch
                        ? new DataBatchMasuk((string) $d->NomorBatch, $d->TanggalKedaluwarsa === null ? null : CarbonImmutable::parse($d->TanggalKedaluwarsa->format('Y-m-d')))
                        : null,
                    idBatchStok: ! $masuk && $pelacakan === PelacakanProduk::Batch ? $d->IdBatchStok : null,
                    nomorSeriMasuk: $masuk && $pelacakan === PelacakanProduk::Seri ? $d->NomorSeri : null,
                    idNomorSeri: ! $masuk && $pelacakan === PelacakanProduk::Seri ? $d->IdNomorSeri : null,
                );
            })->all()),
        ));

        $totalMasuk = Uang::Nol();
        $totalKeluar = Uang::Nol();
        $barisJurnal = [];

        foreach ($detail as $d) {
            $h = $hasil->baris['P/'.$d->Id];
            $d->Nilai = $h->totalHpp->KeString();
            $d->save();

            if ($h->jumlah->BernilaiNegatif()) {
                $totalKeluar = $totalKeluar->Tambah(AritmetikaHpp::AmbilMutlak($h->totalHpp));
                $barisJurnal[] = [$h, $alasan->AmbilPeranLawanKeluar()];
            } else {
                $totalMasuk = $totalMasuk->Tambah($h->totalHpp);
                $barisJurnal[] = [$h, PeranAkun::SelisihHpp];
            }
        }

        $jurnal = $this->pencatatJurnal->Posting(
            JenisSumberJurnal::PenyesuaianStok,
            $dokumen->Id,
            $dokumen->Uuid,
            $nomor,
            $tanggal,
            "Penyesuaian stok {$nomor} ({$alasan->AmbilLabel()}) di {$gudang->nama}",
            $this->penyusunJurnal->Susun($barisJurnal, $produk, $this->infoGudang->AmbilBanyak([$gudang->id]), $gudang->idOutlet),
            $idPengguna,
        );

        $dokumen->UbahStatus(StatusPenyesuaianStok::Diposting);
        $dokumen->fill([
            'Nomor' => $nomor,
            'TotalNilaiMasuk' => $totalMasuk->KeString(),
            'TotalNilaiKeluar' => $totalKeluar->KeString(),
            'DipostingOleh' => $idPengguna,
            'DipostingPada' => now(),
            'DiubahOleh' => $idPengguna,
        ]);
        $dokumen->save();

        return $jurnal;
    }
}
