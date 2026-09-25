<?php

declare(strict_types=1);

namespace App\Domain\Pelanggan\Kueri;

use App\Domain\Bersama\Tabel\Layanan\PenerapKueriTabel;
use App\Domain\Pelanggan\Enum\StatusPelanggan;
use App\Domain\Pelanggan\Layanan\NomorHp;
use App\Domain\Pelanggan\Model\Pelanggan;

/**
 * Cari pelanggan aktif dari POS (F-16a, `GET /api/pos/v1/pelanggan?kata=`): minimal 3 karakter; cocok nama atau nomor
 * HP (ketik sebagian angka). Maks. 20 hasil. Nomor HP dikirim tersamar (data pribadi tidak disimpan di perangkat).
 */
final class CariPelangganPos
{
    public const BATAS = 20;

    public const PANJANG_MINIMAL = 3;

    /**
     * @return list<array{Uuid: string, Nama: string, NoHp: string}>
     */
    public function Cari(string $kata): array
    {
        $kata = trim($kata);

        if (mb_strlen($kata) < self::PANJANG_MINIMAL) {
            return [];
        }

        $angka = (string) preg_replace('/\D+/', '', $kata);
        $cariHp = $angka !== '' && strlen($angka) >= self::PANJANG_MINIMAL && preg_match('/^[0-9+() .-]+$/', $kata) === 1;
        $pola = $cariHp ? PenerapKueriTabel::PolaCari(NomorHp::Normalisasi($kata) ?? ltrim($angka, '0')) : PenerapKueriTabel::PolaCari($kata);

        return array_values(Pelanggan::query()
            ->where('Status', StatusPelanggan::Aktif->value)
            ->where($cariHp ? 'NoHp' : 'Nama', 'like', $pola)
            ->orderBy('Nama')
            ->orderBy('Id')
            ->limit(self::BATAS)
            ->get(['Uuid', 'Nama', 'NoHp'])
            ->map(fn (Pelanggan $p): array => ['Uuid' => $p->Uuid, 'Nama' => $p->Nama, 'NoHp' => NomorHp::Samarkan($p->NoHp)])
            ->all());
    }
}
