<?php

declare(strict_types=1);

namespace App\Domain\Akuntansi\Aksi;

use App\Domain\Akuntansi\Enum\JenisSumberJurnal;
use App\Domain\Akuntansi\Enum\StatusAsetTetap;
use App\Domain\Akuntansi\Layanan\PenyediaAkunAsetTetap;
use App\Domain\Akuntansi\Model\AsetTetap;
use App\Domain\Akuntansi\Model\PenyusutanAset;
use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use Illuminate\Support\Facades\DB;

/**
 * Membatalkan aset tetap yang salah catat (FIN-10): hanya aset aktif yang **belum pernah disusutkan**; jurnal
 * perolehannya dibalik hari ini. Aset yang sudah disusutkan dikoreksi lewat pelepasan. Audit `aset-tetap.batal`.
 */
final class BatalkanAsetTetap
{
    public const PANJANG_ALASAN_MINIMAL = 5;

    public function __construct(
        private readonly BalikkanJurnal $balikkan,
        private readonly PenyediaAkunAsetTetap $bantuan,
        private readonly PencatatAudit $audit,
    ) {}

    public function Jalankan(AsetTetap $aset, string $alasan, int $idPengguna): AsetTetap
    {
        $alasan = trim($alasan);

        if (mb_strlen($alasan) < self::PANJANG_ALASAN_MINIMAL) {
            throw new PelanggaranAturanBisnis('AlasanWajib', 'Tulis alasan membatalkan, minimal '.self::PANJANG_ALASAN_MINIMAL.' karakter.', 'Alasan');
        }

        return DB::transaction(function () use ($aset, $alasan, $idPengguna): AsetTetap {
            $aset = AsetTetap::query()->whereKey($aset->Id)->lockForUpdate()->firstOrFail();

            if ($aset->Status !== StatusAsetTetap::Aktif || PenyusutanAset::query()->where('IdAsetTetap', $aset->Id)->exists()) {
                throw new PelanggaranAturanBisnis('AsetTidakBisaDibatalkan', 'Hanya aset yang belum pernah disusutkan yang bisa dibatalkan. Untuk aset lain, catat pelepasan.');
            }

            if ($aset->IdJurnal !== null) {
                $this->balikkan->Jalankan($aset->IdJurnal, $this->bantuan->HariIni(), 'Pembatalan aset tetap: '.$alasan, JenisSumberJurnal::AsetTetap, $aset->Id, 'Pembatalan', $idPengguna);
            }

            $aset->fill(['Status' => StatusAsetTetap::Dibatalkan, 'DibatalkanPada' => now(), 'AlasanBatal' => mb_substr($alasan, 0, 500)])->save();
            $this->audit->Catat('aset-tetap.batal', $aset, nilaiBaru: ['Alasan' => $alasan], idPengguna: $idPengguna);

            return $aset;
        });
    }
}
