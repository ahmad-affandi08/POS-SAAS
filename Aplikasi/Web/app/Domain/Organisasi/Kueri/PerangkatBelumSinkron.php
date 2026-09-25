<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Kueri;

use App\Domain\Organisasi\Enum\JenisPerangkat;
use App\Domain\Organisasi\Model\Perangkat;
use Carbon\CarbonInterface;

/**
 * F-15 tutup harian: perangkat penjual (kasir & pelayan) aktif di outlet yang belum menghubungi server sejak `$batas`,
 * sehingga outbox offline-nya mungkin masih menyimpan transaksi hari itu. Perangkat yang diaktifkan setelah `$batas`
 * tidak dihitung.
 */
final class PerangkatBelumSinkron
{
    /**
     * Perangkat penjual aktif per outlet (dimuat sekali, disaring per tanggal dengan `Saring`).
     *
     * @param  list<int>  $idOutlet
     * @return array<int, list<Perangkat>>
     */
    public function AmbilPerOutlet(array $idOutlet): array
    {
        $hasil = [];

        foreach (Perangkat::query()
            ->whereIn('IdOutlet', $idOutlet)
            ->whereIn('Jenis', [JenisPerangkat::Kasir->value, JenisPerangkat::Pelayan->value])
            ->whereNull('DicabutPada')
            ->whereNotNull('DiaktifkanPada')
            ->orderBy('Nama')
            ->get(['IdOutlet', 'Nama', 'Kode', 'DiaktifkanPada', 'TerakhirAktifPada']) as $perangkat) {
            $hasil[$perangkat->IdOutlet][] = $perangkat;
        }

        return $hasil;
    }

    /**
     * @param  list<Perangkat>  $perangkat
     * @return list<array{Nama: string, Kode: string, TerakhirAktifPada: string|null}>
     */
    public static function Saring(array $perangkat, CarbonInterface $batas): array
    {
        $hasil = [];

        foreach ($perangkat as $p) {
            if ($p->DiaktifkanPada !== null && $p->DiaktifkanPada->lt($batas) && ($p->TerakhirAktifPada === null || $p->TerakhirAktifPada->lt($batas))) {
                $hasil[] = ['Nama' => $p->Nama, 'Kode' => $p->Kode, 'TerakhirAktifPada' => $p->TerakhirAktifPada?->toIso8601String()];
            }
        }

        return $hasil;
    }

    /**
     * @return list<array{Nama: string, Kode: string, TerakhirAktifPada: string|null}>
     */
    public function Ambil(int $idOutlet, CarbonInterface $batas): array
    {
        return self::Saring($this->AmbilPerOutlet([$idOutlet])[$idOutlet] ?? [], $batas);
    }
}
