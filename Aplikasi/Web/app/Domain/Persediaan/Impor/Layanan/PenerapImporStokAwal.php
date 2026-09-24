<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Impor\Layanan;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Nilai\Kuantitas;
use App\Domain\Persediaan\Aksi\SimpanStokAwal;
use App\Domain\Persediaan\Data\DataBarisStokAwal;
use App\Domain\Persediaan\Data\DataStokAwal;
use App\Domain\Persediaan\Enum\StatusBarisImporStokAwal;
use App\Domain\Persediaan\Enum\StatusImporStokAwal;
use App\Domain\Persediaan\Enum\SumberStokAwal;
use App\Domain\Persediaan\Model\ImporStokAwal;
use App\Domain\Persediaan\Model\ImporStokAwalBaris;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Penerap impor stok awal (DesainF05a C.7), dijalankan `TerapkanImporStokAwalTugas`. Baris Valid yang belum masuk
 * draf dikelompokkan per lokasi stok (urut IdGudang, lalu NomorBaris) menjadi potongan ≤
 * `persediaan.StokAwal.MaksimalBaris` baris. Setiap potongan = **satu transaksi**: kunci impor, `SimpanStokAwal`
 * (Sumber Impor, Draf, tidak diposting), lalu baris ditandai Diterapkan + `IdStokAwal`. Karena penandaan ada di
 * transaksi yang sama, menjalankan ulang (lanjutkan, worker ganda) tidak pernah membuat draf ganda.
 * Pelanggaran aturan bisnis saat membuat draf (misal produk diarsipkan setelah pratinjau) → impor Gagal dengan
 * pesannya; draf yang sudah dibuat tetap, dan impor bisa dilanjutkan setelah diperbaiki.
 */
final class PenerapImporStokAwal
{
    public function __construct(
        private readonly SimpanStokAwal $simpanStokAwal,
        private readonly PencatatAudit $audit,
    ) {}

    /** True bila selesai (Selesai atau Gagal); false bila anggaran waktu habis dan perlu dilanjutkan. */
    public function Jalankan(ImporStokAwal $impor, int $batasDetik): bool
    {
        $mulai = hrtime(true);
        $maksimalBaris = max(1, (int) config('persediaan.StokAwal.MaksimalBaris', 2000));

        while (true) {
            try {
                $lanjut = DB::transaction(fn (): ?bool => $this->TerapkanSatuPotongan($impor->Id, $maksimalBaris));
            } catch (PelanggaranAturanBisnis $galat) {
                PengirimTugasImporStokAwal::Gagalkan($impor->Id, 'Draf stok awal gagal dibuat: '.$galat->getMessage().' Draf yang sudah dibuat tetap tersimpan; perbaiki lalu klik Lanjutkan impor.');

                return true;
            }

            if ($lanjut === null) {
                return true;
            }

            if (! $lanjut) {
                $this->Selesaikan($impor->Id);

                return true;
            }

            if (hrtime(true) - $mulai >= $batasDetik * 1_000_000_000) {
                return false;
            }
        }
    }

    /**
     * @return bool|null true = satu draf dibuat, false = tidak ada baris tersisa, null = impor tidak lagi Menerapkan
     */
    private function TerapkanSatuPotongan(int $idImpor, int $maksimalBaris): ?bool
    {
        $impor = ImporStokAwal::query()->whereKey($idImpor)->lockForUpdate()->firstOrFail();

        if ($impor->Status !== StatusImporStokAwal::Menerapkan) {
            return null;
        }

        $idGudang = ImporStokAwalBaris::query()
            ->where('IdImporStokAwal', $impor->Id)
            ->where('Status', StatusBarisImporStokAwal::Valid->value)
            ->whereNull('IdStokAwal')
            ->selectRaw("MIN(CAST(JSON_UNQUOTE(JSON_EXTRACT(Data, '$.IdGudang')) AS UNSIGNED)) AS IdGudang")
            ->value('IdGudang');

        if ($idGudang === null) {
            return false;
        }

        $idGudang = (int) $idGudang;
        $baris = ImporStokAwalBaris::query()
            ->where('IdImporStokAwal', $impor->Id)
            ->where('Status', StatusBarisImporStokAwal::Valid->value)
            ->whereNull('IdStokAwal')
            ->whereRaw("CAST(JSON_UNQUOTE(JSON_EXTRACT(Data, '$.IdGudang')) AS UNSIGNED) = ?", [$idGudang])
            ->orderBy('NomorBaris')
            ->limit($maksimalBaris)
            ->get();

        $nomorAwal = (int) $baris->min('NomorBaris');
        $nomorAkhir = (int) $baris->max('NomorBaris');
        $stokAwal = $this->simpanStokAwal->Jalankan(new DataStokAwal(
            uuid: null,
            idGudang: $idGudang,
            tanggal: CarbonImmutable::parse($impor->Tanggal?->toDateString() ?? now()->toDateString())->startOfDay(),
            catatan: mb_substr("Impor {$impor->NamaBerkas} baris {$nomorAwal}–{$nomorAkhir}", 0, 500),
            baris: array_values($baris->map(fn (ImporStokAwalBaris $b): DataBarisStokAwal => self::KeBarisStokAwal($b))->all()),
            sumber: SumberStokAwal::Impor,
            idImpor: $impor->Id,
        ), null);

        ImporStokAwalBaris::query()->whereKey($baris->pluck('Id')->all())->update([
            'Status' => StatusBarisImporStokAwal::Diterapkan->value,
            'IdStokAwal' => $stokAwal->Id,
        ]);
        $impor->JumlahDokumen++;
        $impor->save();

        return true;
    }

    private function Selesaikan(int $idImpor): void
    {
        DB::transaction(function () use ($idImpor): void {
            $impor = ImporStokAwal::query()->whereKey($idImpor)->lockForUpdate()->firstOrFail();

            if ($impor->Status !== StatusImporStokAwal::Menerapkan) {
                return;
            }

            $impor->UbahStatus(StatusImporStokAwal::Selesai);
            $impor->SelesaiPada = now();
            $impor->save();
            $this->audit->Catat('stok-awal.impor.terapkan', $impor, null, [
                'Tahap' => 'Selesai',
                'JumlahDokumen' => $impor->JumlahDokumen,
                'JumlahBarisDiterapkan' => ImporStokAwalBaris::query()->where('IdImporStokAwal', $impor->Id)->where('Status', StatusBarisImporStokAwal::Diterapkan->value)->count(),
            ]);
        });
    }

    private static function KeBarisStokAwal(ImporStokAwalBaris $baris): DataBarisStokAwal
    {
        $data = $baris->Data ?? [];

        return new DataBarisStokAwal(
            idProduk: (int) $data['IdProduk'],
            jumlah: Kuantitas::Dari((string) $data['Jumlah']),
            hppSatuan: BigDecimal::of((string) $data['HargaModal']),
            nomorBatch: is_string($data['NomorBatch'] ?? null) ? $data['NomorBatch'] : null,
            tanggalKedaluwarsa: is_string($data['TanggalKedaluwarsa'] ?? null) ? CarbonImmutable::parse($data['TanggalKedaluwarsa'])->startOfDay() : null,
            nomorSeri: array_values(array_map('strval', (array) ($data['NomorSeri'] ?? []))),
        );
    }
}
