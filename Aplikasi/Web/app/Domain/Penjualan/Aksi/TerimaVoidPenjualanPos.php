<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Aksi;

use App\Domain\Akuntansi\Aksi\BalikkanJurnal;
use App\Domain\Akuntansi\Enum\JenisSumberJurnal;
use App\Domain\Akuntansi\Layanan\PenjagaKunciPeriode;
use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Dokumen\Layanan\PencatatRiwayatStatus;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Nilai\Kuantitas;
use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Bersama\Sinkron\Enum\StatusItemSinkron;
use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Karyawan\Layanan\PencatatKomisiPenjualan;
use App\Domain\Kasir\Kueri\InfoShift;
use App\Domain\Organisasi\Kueri\TanggalBisnisOutlet;
use App\Domain\Pelanggan\Layanan\PencatatPiutangPenjualan;
use App\Domain\Pelanggan\Layanan\PencatatPoinPenjualan;
use App\Domain\Penjualan\Data\DataVoidPenjualanPos;
use App\Domain\Penjualan\Enum\JenisMetodePembayaran;
use App\Domain\Penjualan\Enum\StatusPenjualan;
use App\Domain\Penjualan\Layanan\PemeriksaPelakuPascaPenjualan;
use App\Domain\Penjualan\Model\Penjualan;
use App\Domain\Penjualan\Model\PenjualanPembayaran;
use App\Domain\Penjualan\Model\VoidPenjualan;
use App\Domain\Penjualan\Peristiwa\PenjualanDivoid;
use App\Domain\Persediaan\Aksi\CatatMutasiStok;
use App\Domain\Persediaan\Data\DataBarisMutasi;
use App\Domain\Persediaan\Data\DataDokumenMutasi;
use App\Domain\Persediaan\Enum\JenisMutasi;
use App\Domain\Persediaan\Enum\JenisReferensiMutasi;
use App\Domain\Persediaan\Enum\ModeNilaiMutasi;
use App\Domain\Persediaan\Kueri\MutasiDokumen;
use App\Domain\Promo\Layanan\PemakaiVoucher;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * F-09 fase 1 (PRD "Rincian F-09 fase 1"): void seluruh penjualan dari item outbox `Penjualan.Void`. Satu transaksi DB
 * per item; stok & jurnal dibalik di transaksi yang sama (aturan #9–#10, J-09.1).
 *
 * Urutan pemeriksaan: (1) Uuid sama → `Duplikat` bila penjualannya sama, selain itu `UuidSudahDipakai`; (2) penjualan
 * ada di outlet perangkat (`PenjualanTidakDitemukan`); sudah di-void → `SudahDivoid`; (3) `VoidTidakDiizinkan` bila
 * penjualan dari perangkat lain, sudah diretur (sebagian/penuh), atau shift penjualannya sudah ditutup sebelum
 * `DivoidPada`; waktu di luar rentang wajar → `WaktuTidakValid`; (4) kasir & penyetuju ber-izin `penjualan.void`
 * (`KasirTidakDitemukan`/`TanpaIzin`/`PenyetujuTidakBerwenang`); (5) periode terbuka (`PeriodeTerkunci`).
 *
 * Efek: `VoidPenjualan` (snapshot nominal, refund tunai = tunai bersih yang keluar dari laci shift, refund non-tunai =
 * refund manual BR-09.2), status penjualan `Void` (riwayat status), baris mutasi pembalik
 * (`JenisReferensiMutasi::VoidPenjualan`, `IdMutasiAsal` = mutasi penjualan, nilai = HPP keluar asal), dan jurnal
 * pembalik jurnal penjualan (`BalikkanJurnal`, sumber `Penjualan` kunci `Void`, sehingga tampil di detail penjualan).
 */
final class TerimaVoidPenjualanPos
{
    private const TOLERANSI_JAM_DETIK = 600;

    public const KUNCI_JURNAL = 'Void';

    public function __construct(
        private readonly KonteksTenant $konteks,
        private readonly InfoShift $infoShift,
        private readonly PemeriksaPelakuPascaPenjualan $pelaku,
        private readonly TanggalBisnisOutlet $tanggalBisnis,
        private readonly PenjagaKunciPeriode $penjagaPeriode,
        private readonly MutasiDokumen $mutasiDokumen,
        private readonly CatatMutasiStok $catatMutasi,
        private readonly BalikkanJurnal $balikkanJurnal,
        private readonly PencatatRiwayatStatus $riwayat,
        private readonly PencatatAudit $audit,
        private readonly PencatatPoinPenjualan $poin,
        private readonly PencatatPiutangPenjualan $piutang,
        private readonly PencatatKomisiPenjualan $komisi,
        private readonly PemakaiVoucher $voucher,
    ) {}

    public function Jalankan(DataVoidPenjualanPos $data): StatusItemSinkron
    {
        if ($data->divoidPada->greaterThan(CarbonImmutable::now()->addSeconds(self::TOLERANSI_JAM_DETIK))) {
            throw new PelanggaranAturanBisnis('WaktuTidakValid', 'Waktu void ada di masa depan. Periksa jam perangkat.', 'DivoidPada');
        }

        try {
            return DB::transaction(fn (): StatusItemSinkron => $this->Proses($data));
        } catch (QueryException $galat) {
            if (($galat->errorInfo[1] ?? null) === 1062) {
                if (str_contains($galat->getMessage(), 'UniqVoidPenjualanIdTenantIdPenjualan')) {
                    throw self::GalatSudahDivoid();
                }

                throw self::GalatUuidDipakai();
            }

            throw $galat;
        }
    }

    private function Proses(DataVoidPenjualanPos $data): StatusItemSinkron
    {
        $idTenant = $this->konteks->Wajib();

        // (1) Idempotensi per Uuid.
        $lama = VoidPenjualan::query()->where('Uuid', $data->uuid)->lockForUpdate()->first();

        if ($lama !== null) {
            $uuidPenjualanLama = Penjualan::query()->whereKey($lama->IdPenjualan)->value('Uuid');

            if ($uuidPenjualanLama === $data->uuidPenjualan) {
                return StatusItemSinkron::Duplikat;
            }

            throw self::GalatUuidDipakai();
        }

        // (2) Penjualan di outlet perangkat (kunci baris dokumen, L2).
        $penjualan = Penjualan::query()->where('Uuid', $data->uuidPenjualan)->lockForUpdate()->first();

        if ($penjualan === null || $penjualan->IdOutlet !== $data->idOutlet) {
            throw new PelanggaranAturanBisnis('PenjualanTidakDitemukan', 'Penjualan yang akan di-void tidak ditemukan di outlet ini.', 'UuidPenjualan', 404);
        }

        if ($penjualan->Status === StatusPenjualan::Void) {
            throw self::GalatSudahDivoid();
        }

        // (3) Syarat void fase 1.
        $this->PastikanBolehVoid($data, $penjualan);

        // (4) Pelaku.
        $kasir = $this->pelaku->CariKasir($idTenant, $data->uuidPengguna, $penjualan->IdOutlet);
        $penyetuju = $this->pelaku->CariPenyetuju($idTenant, $data->uuidPenyetuju, $penjualan->IdOutlet);

        // (5) Periode.
        $tanggalBisnis = $this->tanggalBisnis->Hitung($penjualan->IdOutlet, $data->divoidPada);
        $this->penjagaPeriode->PastikanTerbuka($tanggalBisnis);

        [$refundTunai, $refundNonTunai] = $this->HitungRefund($penjualan);

        $void = VoidPenjualan::query()->create([
            'Uuid' => $data->uuid,
            'IdPenjualan' => $penjualan->Id,
            'IdOutlet' => $penjualan->IdOutlet,
            'IdShift' => $penjualan->IdShift,
            'IdPerangkat' => $data->idPerangkat,
            'Alasan' => mb_substr($data->alasan, 0, 255),
            'DivoidOleh' => $kasir->id,
            'DisetujuiOleh' => $penyetuju->id,
            'DivoidPada' => $data->divoidPada,
            'TanggalBisnis' => $tanggalBisnis->toDateString(),
            'Nominal' => $penjualan->TotalAkhir,
            'RefundTunai' => $refundTunai->KeString(),
            'RefundNonTunai' => $refundNonTunai->KeString(),
            'DiterimaPada' => CarbonImmutable::now(),
        ]);

        $penjualan->Status = StatusPenjualan::Void;
        $penjualan->save();

        $this->BalikkanStok($penjualan, $tanggalBisnis, $kasir->id, $data->idPerangkat);

        if ($penjualan->IdJurnal !== null) {
            $void->IdJurnal = $this->balikkanJurnal->Jalankan(
                $penjualan->IdJurnal,
                $tanggalBisnis,
                mb_substr("Void penjualan {$penjualan->Nomor}: {$data->alasan}", 0, 255),
                JenisSumberJurnal::Penjualan,
                $penjualan->Id,
                self::KUNCI_JURNAL,
                $kasir->id,
            )->idJurnal;
            $void->save();
        }

        $this->riwayat->Catat(Penjualan::JENIS_DOKUMEN, $penjualan->Id, StatusPenjualan::Lunas->value, StatusPenjualan::Void->value, $kasir->id, $data->alasan);
        $this->audit->Catat('penjualan.void', $penjualan, ['Status' => StatusPenjualan::Lunas->value], [
            'Status' => StatusPenjualan::Void->value,
            'Nomor' => $penjualan->Nomor,
            'Nominal' => $penjualan->TotalAkhir,
            'RefundTunai' => $refundTunai->KeString(),
            'Alasan' => $data->alasan,
            'DisetujuiOleh' => $penyetuju->nama,
        ], idPengguna: $kasir->id);

        // F-16b: poin dari penjualan ini dibalik di transaksi yang sama.
        $this->poin->BalikVoid($penjualan->Id);
        // F-12: piutang penjualan tempo dibatalkan (jurnal pembalik sudah mengkredit Piutang Usaha).
        $this->piutang->Batalkan($penjualan->Id, $kasir->id);
        // F-18: komisi penjualan yang di-void dibatalkan penuh.
        $this->komisi->Batalkan($penjualan->Id);

        // F-16c bagian 2: voucher penjualan yang di-void dilepas dan bisa dipakai lagi.
        $this->voucher->Lepaskan($penjualan->Id);

        // F-14a: void mengeluarkan penjualan dari tanggal bisnisnya; ringkasan dihitung ulang di antrean setelah commit.
        PenjualanDivoid::dispatch($penjualan->IdTenant, $penjualan->IdOutlet, $penjualan->TanggalBisnis->toDateString(), $penjualan->Id);

        return StatusItemSinkron::Diterima;
    }

    /**
     * Fase 1: hanya penjualan dari perangkat ini, belum diretur, dan shift penjualannya belum ditutup pada `DivoidPada`
     * (dikunci bersama sehingga serial dengan tutup shift F-11).
     */
    private function PastikanBolehVoid(DataVoidPenjualanPos $data, Penjualan $penjualan): void
    {
        if ($penjualan->IdPerangkat !== $data->idPerangkat) {
            throw new PelanggaranAturanBisnis('VoidTidakDiizinkan', "Penjualan {$penjualan->Nomor} dibuat di perangkat lain. Void hanya bisa dari perangkat dan shift yang sama; gunakan retur.", 'UuidPenjualan');
        }

        if ($penjualan->Status !== StatusPenjualan::Lunas) {
            throw new PelanggaranAturanBisnis('VoidTidakDiizinkan', "Penjualan {$penjualan->Nomor} sudah {$penjualan->Status->AmbilLabel()} sehingga tidak bisa di-void.", 'UuidPenjualan');
        }

        if ($data->divoidPada->lessThan(CarbonImmutable::parse($penjualan->DibuatOfflinePada)->subSeconds(self::TOLERANSI_JAM_DETIK))) {
            throw new PelanggaranAturanBisnis('WaktuTidakValid', 'Waktu void lebih awal dari waktu penjualan.', 'DivoidPada');
        }

        // F-12: piutang yang sudah dibayar sebagian tidak bisa dibatalkan lewat void.
        $masalahPiutang = $this->piutang->PeriksaBisaBatal($penjualan->Id);

        if ($masalahPiutang !== null) {
            throw new PelanggaranAturanBisnis('VoidTidakDiizinkan', $masalahPiutang, 'UuidPenjualan');
        }

        $ditutup = $this->infoShift->AmbilWaktuTutup($penjualan->IdShift);

        if ($ditutup !== null && $ditutup->lessThanOrEqualTo($data->divoidPada)) {
            throw new PelanggaranAturanBisnis('VoidTidakDiizinkan', "Shift penjualan {$penjualan->Nomor} sudah ditutup. Gunakan retur untuk mengembalikan barang.", 'UuidPenjualan');
        }
    }

    /**
     * Pengembalian mengikuti pembayaran asal: tunai bersih (diterima − kembalian) keluar dari laci; non-tunai dicatat
     * sebagai refund manual (BR-09.2). Tempo (F-12) bukan refund: piutangnya dibatalkan.
     *
     * @return array{0: Uang, 1: Uang} [refund tunai, refund non-tunai]
     */
    private function HitungRefund(Penjualan $penjualan): array
    {
        $tunai = Uang::Nol();
        $nonTunai = Uang::Nol();

        foreach (PenjualanPembayaran::query()->where('IdPenjualan', $penjualan->Id)->get() as $bayar) {
            if ($bayar->JenisMetode === JenisMetodePembayaran::Tempo) {
                continue;
            }

            if ($bayar->JenisMetode === JenisMetodePembayaran::Tunai) {
                $tunai = $tunai->Tambah(Uang::Dari($bayar->Jumlah))->Kurangi(Uang::Dari($penjualan->Kembalian));
            } else {
                $nonTunai = $nonTunai->Tambah(Uang::Dari($bayar->Jumlah));
            }
        }

        return [$tunai->BernilaiNegatif() ? Uang::Nol() : $tunai, $nonTunai];
    }

    /** Baris pembalik untuk setiap mutasi keluar penjualan, dinilai sebesar HPP keluar asalnya. */
    private function BalikkanStok(Penjualan $penjualan, CarbonImmutable $tanggalBisnis, int $idKasir, int $idPerangkat): void
    {
        $asal = $this->mutasiDokumen->AmbilRingkasan(JenisReferensiMutasi::Penjualan, $penjualan->Id);

        if ($asal === []) {
            return;
        }

        $this->catatMutasi->Jalankan(new DataDokumenMutasi(
            jenisReferensi: JenisReferensiMutasi::VoidPenjualan,
            idReferensi: $penjualan->Id,
            uuidReferensi: $penjualan->Uuid,
            nomorReferensi: $penjualan->Nomor,
            tanggalBisnis: $tanggalBisnis,
            idPengguna: $idKasir,
            idPerangkat: $idPerangkat,
            baris: array_map(fn (array $m): DataBarisMutasi => new DataBarisMutasi(
                kunciBaris: 'V/'.$m['KunciBaris'],
                idProduk: $m['IdProduk'],
                idGudang: $m['IdGudang'],
                jenisMutasi: JenisMutasi::Penjualan,
                jumlah: Kuantitas::Dari($m['Jumlah'])->Negasi(),
                modeNilai: ModeNilaiMutasi::Ditentukan,
                nilai: Uang::Dari(BigDecimal::of($m['TotalHpp'])->abs()),
                hppSatuan: BigDecimal::of($m['HppSatuan'])->abs(),
                idReferensiDetail: $m['IdReferensiDetail'],
                idMutasiAsal: $m['Id'],
            ), $asal),
        ));
    }

    private static function GalatSudahDivoid(): PelanggaranAturanBisnis
    {
        return new PelanggaranAturanBisnis('SudahDivoid', 'Penjualan ini sudah di-void sebelumnya.', 'UuidPenjualan', 409);
    }

    private static function GalatUuidDipakai(): PelanggaranAturanBisnis
    {
        return new PelanggaranAturanBisnis('UuidSudahDipakai', 'Kode unik void ini sudah dipakai data lain. Ulangi void di aplikasi.', 'Uuid');
    }
}
