<?php

declare(strict_types=1);

namespace App\Domain\Dukungan\Layanan;

use App\Domain\Dukungan\Model\NomorUrutTiketDukungan;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Nomor tiket unik platform `TKT-{Tahun}-{6 digit}` (misal TKT-2026-000123), berurut per tahun WIB.
 * Wajib dipanggil di dalam transaksi DB: baris penghitung dikunci sampai transaksi selesai.
 */
final class PembuatNomorTiket
{
    public function Buat(Carbon $waktu): string
    {
        if (NomorUrutTiketDukungan::query()->getConnection()->transactionLevel() === 0) {
            throw new LogicException('PembuatNomorTiket harus dipanggil di dalam transaksi.');
        }

        $tahun = (int) $waktu->copy()->setTimezone('Asia/Jakarta')->format('Y');

        // Baris tahun baru dibuat tanpa bentrok bila dua tiket pertama tahun itu masuk bersamaan.
        NomorUrutTiketDukungan::query()->insertOrIgnore([
            'Tahun' => $tahun, 'NomorTerakhir' => 0, 'DibuatPada' => now(), 'DiubahPada' => now(),
        ]);

        $urut = NomorUrutTiketDukungan::query()->where('Tahun', $tahun)->lockForUpdate()->sole();
        $urut->NomorTerakhir++;
        $urut->save();

        return sprintf('TKT-%d-%06d', $tahun, $urut->NomorTerakhir);
    }
}
