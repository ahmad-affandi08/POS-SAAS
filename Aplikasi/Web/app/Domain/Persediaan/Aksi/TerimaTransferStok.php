<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Aksi;

use App\Domain\Akuntansi\Enum\JenisSumberJurnal;
use App\Domain\Akuntansi\Enum\PeranAkun;
use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Dokumen\Layanan\PencatatRiwayatStatus;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Nilai\Kuantitas;
use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Katalog\Data\DataInfoProdukStok;
use App\Domain\Katalog\Enum\PelacakanProduk;
use App\Domain\Katalog\Kueri\InfoProdukStok;
use App\Domain\Organisasi\Kueri\InfoGudang;
use App\Domain\Persediaan\Data\DataBarisMutasi;
use App\Domain\Persediaan\Data\DataBatchMasuk;
use App\Domain\Persediaan\Data\DataDokumenMutasi;
use App\Domain\Persediaan\Data\DataTerimaTransfer;
use App\Domain\Persediaan\Enum\JenisMutasi;
use App\Domain\Persediaan\Enum\JenisReferensiMutasi;
use App\Domain\Persediaan\Enum\ModeNilaiMutasi;
use App\Domain\Persediaan\Enum\StatusTransferStok;
use App\Domain\Persediaan\Layanan\Hpp\AritmetikaHpp;
use App\Domain\Persediaan\Layanan\PemeriksaLokasiDokumen;
use App\Domain\Persediaan\Layanan\PencatatJurnalPersediaan;
use App\Domain\Persediaan\Layanan\PengunciSaldoStok;
use App\Domain\Persediaan\Layanan\PenyusunJurnalPersediaan;
use App\Domain\Persediaan\Model\TransferStok;
use App\Domain\Persediaan\Model\TransferStokDetail;
use App\Domain\Tenant\Kueri\PengaturanPersediaanTenant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Menerima transfer stok, boleh sebagian (F-05b, J-05.3). Per baris: `TransferKeluar` dari lokasi dalam perjalanan
 * sebesar nilai kirim proporsional (sisa terakhir = seluruh sisa nilai), lalu `TransferMasuk` ke lokasi tujuan
 * sebesar nilai yang benar-benar keluar dari lokasi dalam perjalanan (HPP kirim). Jurnal Dr Persediaan tujuan /
 * Cr Persediaan Dalam Perjalanan (kunci `Terima{n}`). Semua sisa diterima = status Diterima; selain itu
 * DiterimaSebagian (sisa ditutup lewat `TutupTransferStok`). Jumlah melebihi sisa = `JumlahMelebihiSisa`.
 */
final class TerimaTransferStok
{
    public function __construct(
        private readonly PengaturanPersediaanTenant $pengaturan,
        private readonly PemeriksaLokasiDokumen $pemeriksaLokasi,
        private readonly InfoProdukStok $infoProduk,
        private readonly InfoGudang $infoGudang,
        private readonly PengunciSaldoStok $pengunciSaldo,
        private readonly CatatMutasiStok $catatMutasi,
        private readonly PenyusunJurnalPersediaan $penyusunJurnal,
        private readonly PencatatJurnalPersediaan $pencatatJurnal,
        private readonly PencatatRiwayatStatus $riwayat,
        private readonly PencatatAudit $audit,
    ) {}

    /**
     * @param  list<DataTerimaTransfer>  $terima
     */
    public function Jalankan(TransferStok $transfer, array $terima, CarbonImmutable $tanggal, int $idPengguna): TransferStok
    {
        return DB::transaction(fn (): TransferStok => $this->Terima($transfer->Id, $terima, $tanggal, $idPengguna), max(1, (int) config('persediaan.PercobaanTransaksi', 3)));
    }

    /**
     * @param  list<DataTerimaTransfer>  $terima
     */
    private function Terima(int $idTransfer, array $terima, CarbonImmutable $tanggal, int $idPengguna): TransferStok
    {
        $this->pengaturan->AmbilDenganKunciBaca();
        $transfer = TransferStok::query()->whereKey($idTransfer)->lockForUpdate()->firstOrFail();

        if (! $transfer->Status->CekDalamPerjalanan()) {
            throw new PelanggaranAturanBisnis('StatusTidakSesuai', "Transfer berstatus {$transfer->Status->AmbilLabel()} tidak bisa diterima.");
        }

        $tujuan = $this->pemeriksaLokasi->AmbilLokasi($transfer->IdGudangTujuan, 'UuidGudangTujuan');
        $this->pemeriksaLokasi->PastikanBukanMasaDepan($tanggal, $tujuan->idOutlet);

        if ($tanggal->format('Y-m-d') < $transfer->Tanggal->format('Y-m-d')) {
            throw new PelanggaranAturanBisnis('TanggalSebelumKirim', 'Tanggal terima tidak boleh sebelum tanggal kirim '.$transfer->Tanggal->format('d/m/Y').'.', 'Tanggal');
        }

        $idTransit = (int) $transfer->IdGudangTransit;
        $detail = $transfer->Detail()->orderBy('Urutan')->get()->keyBy('Urutan');
        $produk = $this->infoProduk->AmbilBanyak(array_values(array_unique($detail->pluck('IdProduk')->all())), true);
        $jumlah = $this->PeriksaJumlah($terima, $detail->all(), $produk);

        if ($jumlah === []) {
            throw new PelanggaranAturanBisnis('BarisKosong', 'Isi jumlah diterima minimal satu barang.', 'Baris');
        }

        $pasangan = [];

        foreach ($detail as $urutan => $d) {
            if (isset($jumlah[$urutan])) {
                $pasangan[] = [$d->IdProduk, $idTransit];
                $pasangan[] = [$d->IdProduk, $tujuan->id];
            }
        }

        $this->pengunciSaldo->Kunci($pasangan);
        $n = $transfer->JumlahPenerimaan + 1;

        $keluar = $this->catatMutasi->Jalankan($this->Dokumen($transfer, $tanggal, $idPengguna, array_map(function (int $urutan) use ($detail, $jumlah, $idTransit, $n): DataBarisMutasi {
            /** @var TransferStokDetail $d */
            $d = $detail[$urutan];
            $r = $jumlah[$urutan];
            $sisa = self::Sisa($d);
            $sisaNilai = Uang::Dari($d->NilaiKirim)->Kurangi(Uang::Dari($d->NilaiDiterima));
            $nilai = $r->SamaDengan($sisa)
                ? AritmetikaHpp::AmbilMaksimum($sisaNilai, Uang::Nol())
                : AritmetikaHpp::AmbilMinimum(AritmetikaHpp::Nilai($r, AritmetikaHpp::Hpp(Uang::Dari($d->NilaiKirim), Kuantitas::Dari($d->JumlahDikirim))), AritmetikaHpp::AmbilMaksimum($sisaNilai, Uang::Nol()));

            return new DataBarisMutasi(
                kunciBaris: "T{$n}/{$d->Id}",
                idProduk: $d->IdProduk,
                idGudang: $idTransit,
                jenisMutasi: JenisMutasi::TransferKeluar,
                jumlah: $r->Negasi(),
                modeNilai: ModeNilaiMutasi::Ditentukan,
                nilai: $nilai,
                idReferensiDetail: $d->Id,
                idBatchStok: $d->IdBatchStokTransit,
                idNomorSeri: $d->IdNomorSeri,
            );
        }, array_keys($jumlah))));

        $masuk = $this->catatMutasi->Jalankan($this->Dokumen($transfer, $tanggal, $idPengguna, array_map(function (int $urutan) use ($detail, $jumlah, $keluar, $produk, $tujuan, $n): DataBarisMutasi {
            /** @var TransferStokDetail $d */
            $d = $detail[$urutan];
            $hasil = $keluar->baris["T{$n}/{$d->Id}"];
            $pelacakan = $produk[$d->IdProduk]->pelacakan;

            return new DataBarisMutasi(
                kunciBaris: "M{$n}/{$d->Id}",
                idProduk: $d->IdProduk,
                idGudang: $tujuan->id,
                jenisMutasi: JenisMutasi::TransferMasuk,
                jumlah: $jumlah[$urutan],
                modeNilai: ModeNilaiMutasi::Ditentukan,
                nilai: AritmetikaHpp::AmbilMutlak($hasil->totalHpp),
                hppSatuan: $hasil->hppSatuan,
                idReferensiDetail: $d->Id,
                batchMasuk: $pelacakan === PelacakanProduk::Batch ? new DataBatchMasuk((string) $d->NomorBatch, $d->TanggalKedaluwarsa === null ? null : CarbonImmutable::parse($d->TanggalKedaluwarsa->format('Y-m-d'))) : null,
                nomorSeriMasuk: $pelacakan === PelacakanProduk::Seri ? $d->NomorSeri : null,
            );
        }, array_keys($jumlah))));

        $totalDiterima = Uang::Dari($transfer->TotalNilaiDiterima);
        $semuaDiterima = true;

        foreach ($detail as $urutan => $d) {
            if (isset($jumlah[$urutan])) {
                $nilai = AritmetikaHpp::AmbilMutlak($keluar->baris["T{$n}/{$d->Id}"]->totalHpp);
                $d->JumlahDiterima = Kuantitas::Dari($d->JumlahDiterima)->Tambah($jumlah[$urutan])->KeString();
                $d->NilaiDiterima = Uang::Dari($d->NilaiDiterima)->Tambah($nilai)->KeString();
                $d->save();
                $totalDiterima = $totalDiterima->Tambah($nilai);
            }

            $semuaDiterima = $semuaDiterima && AritmetikaHpp::CekNol(self::Sisa($d));
        }

        $gudang = $this->infoGudang->AmbilBanyak([$idTransit, $tujuan->id]);
        $barisJurnal = array_map(fn ($h): array => [$h, PeranAkun::SelisihHpp], [...array_values($keluar->baris), ...array_values($masuk->baris)]);
        $jurnal = $this->pencatatJurnal->Posting(
            JenisSumberJurnal::TransferStok,
            $transfer->Id,
            $transfer->Uuid,
            (string) $transfer->Nomor,
            $tanggal,
            "Transfer {$transfer->Nomor} diterima di {$tujuan->nama} (penerimaan {$n})",
            $this->penyusunJurnal->Susun($barisJurnal, $produk, $gudang, $tujuan->idOutlet),
            $idPengguna,
            'Terima'.$n,
        );

        $statusAwal = $transfer->Status;
        $statusBaru = $semuaDiterima ? StatusTransferStok::Diterima : StatusTransferStok::DiterimaSebagian;

        if ($statusBaru !== $statusAwal) {
            $transfer->UbahStatus($statusBaru);
        }

        $transfer->fill([
            'JumlahPenerimaan' => $n,
            'TotalNilaiDiterima' => $totalDiterima->KeString(),
            'DiterimaPada' => now(),
            'DiubahOleh' => $idPengguna,
        ]);

        if ($semuaDiterima) {
            $transfer->DitutupOleh = $idPengguna;
            $transfer->DitutupPada = now();
        }

        $transfer->save();

        if ($statusBaru !== $statusAwal) {
            $this->riwayat->Catat(TransferStok::JENIS_DOKUMEN, $transfer->Id, $statusAwal->value, $statusBaru->value, $idPengguna);
        }

        $this->audit->Catat('transfer-stok.terima', $transfer, ['Status' => $statusAwal->value], [
            'Status' => $statusBaru->value,
            'Penerimaan' => $n,
            'Tanggal' => $tanggal->format('Y-m-d'),
            'Baris' => array_map(fn (int $u): array => ['Urutan' => $u, 'Jumlah' => $jumlah[$u]->KeString()], array_keys($jumlah)),
            'NomorJurnal' => $jurnal?->nomor,
        ], idPengguna: $idPengguna);

        return $transfer;
    }

    /**
     * @param  list<DataTerimaTransfer>  $terima
     * @param  array<int, TransferStokDetail>  $detail  kunci = Urutan
     * @param  array<int, DataInfoProdukStok>  $produk
     * @return array<int, Kuantitas> Urutan → jumlah (> 0), urut Urutan
     */
    private function PeriksaJumlah(array $terima, array $detail, array $produk): array
    {
        $hasil = [];

        foreach ($terima as $t) {
            if ($t->jumlah->KeDesimal()->isZero()) {
                continue;
            }

            $d = $detail[$t->urutan] ?? null;

            if ($d === null || isset($hasil[$t->urutan])) {
                throw new PelanggaranAturanBisnis('BarisTidakValid', "Baris {$t->urutan} tidak ada di transfer ini atau disebut dua kali.", 'Baris');
            }

            $info = $produk[$d->IdProduk] ?? null;
            $q = $t->jumlah->KeDesimal();

            if ($q->isNegative() || $q->strippedOfTrailingZeros()->getScale() > 4 || ($info !== null && ! $info->bolehDesimal && ! $q->getFractionalPart()->isZero())) {
                throw new PelanggaranAturanBisnis('JumlahTidakValid', "Baris {$t->urutan}: jumlah diterima {$d->NamaProduk} tidak valid.", "Baris.{$t->urutan}.Jumlah");
            }

            if ($t->jumlah->Bandingkan(self::Sisa($d)) > 0) {
                throw new PelanggaranAturanBisnis(
                    'JumlahMelebihiSisa',
                    "Baris {$t->urutan}: {$d->NamaProduk} diterima melebihi sisa dalam perjalanan (".str_replace('.', ',', (string) self::Sisa($d)->KeDesimal()->strippedOfTrailingZeros()).').',
                    "Baris.{$t->urutan}.Jumlah",
                );
            }

            $hasil[$t->urutan] = $t->jumlah;
        }

        ksort($hasil);

        return $hasil;
    }

    private static function Sisa(TransferStokDetail $d): Kuantitas
    {
        return Kuantitas::Dari($d->JumlahDikirim)->Kurangi(Kuantitas::Dari($d->JumlahDiterima))->Kurangi(Kuantitas::Dari($d->JumlahSusut));
    }

    /**
     * @param  list<DataBarisMutasi>  $baris
     */
    private function Dokumen(TransferStok $transfer, CarbonImmutable $tanggal, int $idPengguna, array $baris): DataDokumenMutasi
    {
        return new DataDokumenMutasi(JenisReferensiMutasi::TransferStok, $transfer->Id, $transfer->Uuid, $transfer->Nomor, $tanggal, $idPengguna, null, array_values($baris));
    }
}
