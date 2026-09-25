<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Rilis\Aksi;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Pengelola\TimInternal\Layanan\PencatatAuditPengelola;
use App\Domain\Pengelola\TimInternal\Model\PenggunaPengelola;
use App\Domain\Tenant\Enum\KanalRilis;
use App\Domain\Tenant\Enum\StatusRilis;
use App\Domain\Tenant\Model\RilisAplikasi;
use Illuminate\Support\Facades\DB;

/**
 * P-10 langkah 2–5: menerbitkan draf (kanal Beta langsung 100% untuk tenant Uji/Internal; kanal Stabil mulai dari
 * persen rollout yang dipilih), menaikkan/menurunkan persen rollout bertahap (misal 10 → 50 → 100), dan menghentikan
 * rollout yang bermasalah dengan alasan wajib. Rilis yang dihentikan tidak bisa dilanjutkan; catat build perbaikan.
 * Audit `rilis.terbit`, `rilis.rollout.ubah`, `rilis.hentikan`.
 */
final class UbahStatusRilis
{
    public const PANJANG_ALASAN_MINIMAL = 10;

    public function __construct(private readonly PencatatAuditPengelola $audit) {}

    public function Terbitkan(PenggunaPengelola $pelaku, RilisAplikasi $rilis, int $persen): RilisAplikasi
    {
        if ($rilis->Status !== StatusRilis::Draf) {
            throw new PelanggaranAturanBisnis('RilisBukanDraf', 'Hanya draf yang bisa diterbitkan.');
        }

        $persen = $rilis->Kanal === KanalRilis::Beta ? 100 : self::PastikanPersen($persen);

        return DB::transaction(function () use ($pelaku, $rilis, $persen): RilisAplikasi {
            $rilis->fill(['Status' => StatusRilis::Aktif, 'PersenRollout' => $persen, 'DiterbitkanPada' => now()])->save();
            $this->audit->Catat('rilis.terbit', $rilis, nilaiBaru: ['Versi' => $rilis->Versi, 'PersenRollout' => $persen], idPelaku: $pelaku->Id);

            return $rilis;
        });
    }

    public function UbahRollout(PenggunaPengelola $pelaku, RilisAplikasi $rilis, int $persen): RilisAplikasi
    {
        if ($rilis->Status !== StatusRilis::Aktif || $rilis->Kanal !== KanalRilis::Stabil) {
            throw new PelanggaranAturanBisnis('RolloutTidakBisaDiubah', 'Persen rollout hanya untuk rilis Stabil yang aktif.');
        }

        $persen = self::PastikanPersen($persen);

        return DB::transaction(function () use ($pelaku, $rilis, $persen): RilisAplikasi {
            $lama = $rilis->PersenRollout;
            $rilis->fill(['PersenRollout' => $persen])->save();
            $this->audit->Catat('rilis.rollout.ubah', $rilis, nilaiLama: ['PersenRollout' => $lama], nilaiBaru: ['PersenRollout' => $persen], idPelaku: $pelaku->Id);

            return $rilis;
        });
    }

    public function Hentikan(PenggunaPengelola $pelaku, RilisAplikasi $rilis, string $alasan): RilisAplikasi
    {
        $alasan = trim($alasan);

        if ($rilis->Status !== StatusRilis::Aktif) {
            throw new PelanggaranAturanBisnis('RilisTidakAktif', 'Hanya rilis aktif yang bisa dihentikan.');
        }

        if (mb_strlen($alasan) < self::PANJANG_ALASAN_MINIMAL) {
            throw new PelanggaranAturanBisnis('AlasanWajib', 'Tulis alasan menghentikan rilis, minimal '.self::PANJANG_ALASAN_MINIMAL.' karakter.', 'Alasan');
        }

        return DB::transaction(function () use ($pelaku, $rilis, $alasan): RilisAplikasi {
            $rilis->fill(['Status' => StatusRilis::Dihentikan, 'DihentikanPada' => now(), 'AlasanDihentikan' => mb_substr($alasan, 0, 500)])->save();
            $this->audit->Catat('rilis.hentikan', $rilis, nilaiLama: ['PersenRollout' => $rilis->PersenRollout], alasan: $alasan, idPelaku: $pelaku->Id);

            return $rilis;
        });
    }

    private static function PastikanPersen(int $persen): int
    {
        if ($persen < 1 || $persen > 100) {
            throw new PelanggaranAturanBisnis('PersenTidakValid', 'Persen rollout 1 sampai 100.', 'PersenRollout');
        }

        return $persen;
    }
}
