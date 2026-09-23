<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Katalog\Aksi;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Pengelola\TimInternal\Layanan\PencatatAuditPengelola;
use App\Domain\Pengelola\TimInternal\Model\PenggunaPengelola;
use App\Domain\Tenant\Enum\StatusPaket;
use App\Domain\Tenant\Kueri\HargaPaketBerlaku;
use App\Domain\Tenant\Model\Paket;
use Illuminate\Support\Facades\DB;

/**
 * Mengaktifkan atau mengarsipkan paket (P-04). BR-P04.6: aktif hanya bila ada harga terbit atau harga negosiasi.
 * BR-P04.2: paket diarsipkan tidak bisa dipilih tenant baru; tenant yang sudah memakainya tidak terdampak.
 */
final class UbahStatusPaket
{
    public function __construct(
        private readonly PencatatAuditPengelola $audit,
        private readonly HargaPaketBerlaku $hargaBerlaku,
    ) {}

    public function Jalankan(PenggunaPengelola $pelaku, Paket $paket, StatusPaket $tujuan, ?string $alasan = null): void
    {
        DB::transaction(function () use ($pelaku, $paket, $tujuan, $alasan): void {
            $paket = Paket::query()->lockForUpdate()->findOrFail($paket->Id);
            $asal = $paket->Status;

            if (! $asal->BisaBerubahKe($tujuan)) {
                throw new PelanggaranAturanBisnis('StatusTidakSesuai', "Paket {$asal->AmbilLabel()} tidak bisa diubah menjadi {$tujuan->AmbilLabel()}.");
            }

            if ($tujuan === StatusPaket::Aktif && ! $paket->HargaNegosiasi
                && ! $this->hargaBerlaku->CekAdaHargaBerlaku($paket->Id, now('Asia/Jakarta'))) {
                throw new PelanggaranAturanBisnis(
                    'BR-P04.6',
                    'Paket belum punya harga yang berlaku hari ini. Terbitkan harga (atau tandai harga negosiasi) sebelum mengaktifkan.',
                );
            }

            if ($tujuan === StatusPaket::Diarsipkan && ($alasan === null || trim($alasan) === '')) {
                throw new PelanggaranAturanBisnis('AlasanWajib', 'Tulis alasan pengarsipan paket.', 'Alasan');
            }

            $paket->update([
                'Status' => $tujuan,
                'DiarsipkanPada' => $tujuan === StatusPaket::Diarsipkan ? now() : null,
            ]);

            $this->audit->Catat(
                $tujuan === StatusPaket::Aktif ? 'katalog.paket.aktifkan' : 'katalog.paket.arsipkan',
                $paket,
                nilaiLama: ['Status' => $asal->value],
                nilaiBaru: ['Status' => $tujuan->value],
                alasan: $alasan,
                idPelaku: $pelaku->Id,
            );
        });
    }
}
