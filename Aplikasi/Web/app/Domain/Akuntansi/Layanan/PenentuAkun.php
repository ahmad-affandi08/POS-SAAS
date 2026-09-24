<?php

declare(strict_types=1);

namespace App\Domain\Akuntansi\Layanan;

use App\Domain\Akuntansi\Enum\PeranAkun;
use App\Domain\Akuntansi\Model\Akun;
use App\Domain\Akuntansi\Model\PemetaanAkun;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;

/**
 * Id akun untuk peran akun (§11.1, DesainF05a C.5): pemetaan outlet dulu, lalu pemetaan tenant. Tipe akun harus sama
 * dengan `PeranAkun::AmbilTipeAkun()`; akun bertipe lain dianggap belum dipetakan (`PemetaanAkunBelumAda`). Kunci lama
 * (`PeranAkun::KUNCI_LAMA`) ikut dibaca, kunci baru menang.
 */
final class PenentuAkun
{
    /**
     * @throws PelanggaranAturanBisnis `PemetaanAkunBelumAda` bila peran belum dipetakan atau tipe akunnya salah
     */
    public function AmbilIdAkun(PeranAkun $peran, ?int $idOutlet): int
    {
        $hasil = $this->Cari($peran, $idOutlet);

        if (is_int($hasil)) {
            return $hasil;
        }

        $pesan = $hasil === null
            ? "Akun untuk {$peran->AmbilLabel()} belum dipetakan. Terapkan template sektor di Panduan awal atau minta Akuntan memetakan akun."
            : "Akun {$hasil->Kode} {$hasil->Nama} yang dipetakan untuk {$peran->AmbilLabel()} bertipe {$hasil->Jenis->AmbilLabel()}, seharusnya {$peran->AmbilTipeAkun()->AmbilLabel()}. Minta Akuntan memperbaiki pemetaan akun.";

        throw new PelanggaranAturanBisnis(
            'PemetaanAkunBelumAda',
            $pesan,
            'Umum',
            detail: ['Kunci' => $peran->value, 'Label' => $peran->AmbilLabel()],
        );
    }

    /** Id akun yang siap dipakai, atau null bila peran belum dipetakan / tipe akunnya salah. */
    public function CariIdAkun(PeranAkun $peran, ?int $idOutlet): ?int
    {
        $hasil = $this->Cari($peran, $idOutlet);

        return is_int($hasil) ? $hasil : null;
    }

    /**
     * @return int|Akun|null Id akun bila cocok; model Akun bila tipenya salah; null bila belum dipetakan
     */
    private function Cari(PeranAkun $peran, ?int $idOutlet): int|Akun|null
    {
        $kunci = [$peran->value, ...array_keys(array_filter(PeranAkun::KUNCI_LAMA, fn (string $baru): bool => $baru === $peran->value))];

        $pemetaan = PemetaanAkun::query()
            ->whereIn('Kunci', $kunci)
            ->where(fn ($kueri) => $idOutlet === null
                ? $kueri->whereNull('IdOutlet')
                : $kueri->whereNull('IdOutlet')->orWhere('IdOutlet', $idOutlet))
            ->get(['Kunci', 'IdAkun', 'IdOutlet'])
            ->sortBy(fn (PemetaanAkun $p): int => ($p->IdOutlet === null ? 2 : 0) + ($p->Kunci === $peran->value ? 0 : 1))
            ->first();

        if (! $pemetaan instanceof PemetaanAkun) {
            return null;
        }

        $akun = Akun::query()->whereKey($pemetaan->IdAkun)->first(['Id', 'Kode', 'Nama', 'Jenis']);

        if (! $akun instanceof Akun) {
            return null;
        }

        return $akun->Jenis === $peran->AmbilTipeAkun() ? $akun->Id : $akun;
    }
}
