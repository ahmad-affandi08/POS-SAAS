<?php

declare(strict_types=1);

namespace App\Domain\Referensi\Kueri;

use App\Domain\Referensi\Enum\JenisReferensiBank;
use App\Domain\Referensi\Model\ReferensiBank;

/**
 * Referensi bank/dompet digital/jaringan EDC (P-02) untuk metode pembayaran tenant (F-01 langkah 5, F-08).
 */
final class ReferensiBankAktif
{
    /**
     * @param  list<JenisReferensiBank>  $jenis  kosong = semua jenis
     * @return list<array{Id: int, Kode: string, Nama: string, Jenis: string}>
     */
    public function Ambil(array $jenis = []): array
    {
        return array_values(ReferensiBank::query()
            ->where('Aktif', true)
            ->when($jenis !== [], fn ($kueri) => $kueri->whereIn('Jenis', array_map(fn (JenisReferensiBank $satu) => $satu->value, $jenis)))
            ->orderBy('Nama')
            ->get()
            ->map(fn (ReferensiBank $bank): array => self::Petakan($bank))
            ->all());
    }

    /**
     * Bank aktif berdasarkan kode; null bila tidak ada atau tidak aktif.
     *
     * @return array{Id: int, Kode: string, Nama: string, Jenis: string}|null
     */
    public function CariKode(string $kode): ?array
    {
        $bank = ReferensiBank::query()->where('Kode', $kode)->where('Aktif', true)->first();

        return $bank === null ? null : self::Petakan($bank);
    }

    /**
     * Nama bank per Id, termasuk yang sudah tidak aktif (untuk menampilkan metode pembayaran lama).
     *
     * @param  list<int>  $id
     * @return array<int, string>
     */
    public function AmbilNama(array $id): array
    {
        if ($id === []) {
            return [];
        }

        /** @var array<int, string> */
        return ReferensiBank::query()->whereKey($id)->pluck('Nama', 'Id')->all();
    }

    /**
     * @return array{Id: int, Kode: string, Nama: string, Jenis: string}
     */
    private static function Petakan(ReferensiBank $bank): array
    {
        return ['Id' => $bank->Id, 'Kode' => $bank->Kode, 'Nama' => $bank->Nama, 'Jenis' => $bank->Jenis->value];
    }
}
