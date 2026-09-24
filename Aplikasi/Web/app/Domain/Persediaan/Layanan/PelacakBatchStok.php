<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Layanan;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Nilai\Kuantitas;
use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Persediaan\Data\DataBatchMasuk;
use App\Domain\Persediaan\Model\BatchStok;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Str;

/**
 * Kunci & pembaruan BatchStok (kunci L4) untuk mutasi produk ber-pelacakan Batch (DesainF05a C.4, F-05g).
 * - Hanya dipanggil di dalam transaksi mesin buku stok, setelah SaldoStok terkunci (L3). Pemanggil mengurutkan
 *   pemanggilan per (IdProduk, IdGudang, NomorBatch) agar urutan kunci global terjaga.
 * - Batch tidak pernah minus, juga saat produk/tenant `BolehMinus` (BR-05.2): `StokBatchTidakCukup`.
 * - Batch yang sama (per produk & lokasi stok) wajib berkedaluwarsa sama: `BatchKedaluwarsaBerbeda`.
 */
final class PelacakBatchStok
{
    public function __construct(private readonly KonteksTenant $konteks) {}

    /**
     * Batch untuk mutasi masuk: dibuat bila belum ada (upsert, langsung kunci X), lalu dibaca `FOR UPDATE`.
     * `hppSatuan` hanya disimpan saat batch pertama kali dibuat (informasi HPP penerimaan pertama).
     */
    public function KunciMasuk(int $idProduk, int $idGudang, DataBatchMasuk $batch, ?BigDecimal $hppSatuan = null): BatchStok
    {
        $nomor = trim($batch->nomorBatch);

        if ($nomor === '' || mb_strlen($nomor) > 60) {
            throw new PelanggaranAturanBisnis('BatchTidakDikenal', 'Nomor batch wajib diisi, maksimal 60 karakter.', 'NomorBatch');
        }

        BatchStok::query()->upsert(
            [[
                'Uuid' => (string) Str::ulid(),
                'IdTenant' => $this->konteks->Wajib(),
                'IdProduk' => $idProduk,
                'IdGudang' => $idGudang,
                'NomorBatch' => $nomor,
                'TanggalKedaluwarsa' => $batch->tanggalKedaluwarsa?->toDateString(),
                'JumlahSisa' => Kuantitas::Nol()->KeString(),
                'HppSatuan' => $hppSatuan?->toScale(6, RoundingMode::HalfUp)->__toString(),
            ]],
            ['IdTenant', 'IdProduk', 'IdGudang', 'NomorBatch'],
            ['DiubahPada'],
        );

        $baris = BatchStok::query()
            ->where('IdProduk', $idProduk)
            ->where('IdGudang', $idGudang)
            ->where('NomorBatch', $nomor)
            ->lockForUpdate()
            ->firstOrFail();

        $tersimpan = $baris->TanggalKedaluwarsa?->toDateString();
        $diminta = $batch->tanggalKedaluwarsa?->toDateString();

        if ($tersimpan !== $diminta) {
            throw new PelanggaranAturanBisnis(
                'BatchKedaluwarsaBerbeda',
                'Batch '.$baris->NomorBatch.' sudah tercatat dengan kedaluwarsa '.($tersimpan ?? 'kosong').', bukan '.($diminta ?? 'kosong').'. Periksa lagi nomor batch atau tanggal kedaluwarsanya.',
                'TanggalKedaluwarsa',
                detail: ['UuidBatchStok' => $baris->Uuid, 'NomorBatch' => $baris->NomorBatch, 'TanggalKedaluwarsa' => $tersimpan, 'TanggalDiminta' => $diminta],
            );
        }

        return $baris;
    }

    /** Batch untuk mutasi keluar: dikunci `FOR UPDATE`; wajib milik produk & lokasi stok yang sama. */
    public function KunciKeluar(int $idBatchStok, int $idProduk, int $idGudang): BatchStok
    {
        $baris = BatchStok::query()->whereKey($idBatchStok)->lockForUpdate()->first();

        if ($baris === null || (int) $baris->IdProduk !== $idProduk || (int) $baris->IdGudang !== $idGudang) {
            throw new PelanggaranAturanBisnis('BatchTidakDikenal', 'Batch tidak ditemukan di lokasi stok ini. Muat ulang lalu pilih batch lagi.', 'IdBatchStok');
        }

        return $baris;
    }

    /** Menambah/mengurangi `JumlahSisa` batch yang sudah dikunci; hasil tidak boleh minus. */
    public function Terapkan(BatchStok $batch, Kuantitas $delta): void
    {
        $sisa = Kuantitas::Dari($batch->JumlahSisa);
        $baru = $sisa->Tambah($delta);

        if ($baru->BernilaiNegatif()) {
            throw new PelanggaranAturanBisnis(
                'StokBatchTidakCukup',
                'Stok batch '.$batch->NomorBatch.' tidak cukup: tersedia '.self::Ringkas($sisa).', dibutuhkan '.self::Ringkas($delta->Negasi()).'.',
                'NomorBatch',
                detail: ['UuidBatchStok' => $batch->Uuid, 'NomorBatch' => $batch->NomorBatch, 'Tersedia' => $sisa->KeString(), 'Diminta' => $delta->Negasi()->KeString()],
            );
        }

        $batch->JumlahSisa = $baru->KeString();
        $batch->save();
    }

    private static function Ringkas(Kuantitas $jumlah): string
    {
        return (string) $jumlah->KeDesimal()->strippedOfTrailingZeros();
    }
}
