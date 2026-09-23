<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Referensi\Aksi;

use App\Domain\Pengelola\Referensi\Surel\PengingatHariLibur;
use App\Domain\Pengelola\TimInternal\Enum\PeranPengelolaBawaan;
use App\Domain\Pengelola\TimInternal\Model\PenggunaPengelola;
use App\Domain\Referensi\Kueri\HariLiburTerbit;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Mail;

/**
 * BR-P02.4: mulai 1 November, setiap hari mengingatkan anggota Konten & Legal (atau Super Admin bila belum ada)
 * sampai hari libur tahun berikutnya terbit. Setelah 1 Desember pengingat ditandai terlambat.
 */
final class KirimPengingatHariLibur
{
    public const BULAN_MULAI = 11;

    public function __construct(private readonly HariLiburTerbit $hariLiburTerbit) {}

    /**
     * @return int jumlah penerima
     */
    public function Jalankan(CarbonInterface $sekarang): int
    {
        $hariIni = $sekarang->copy()->setTimezone('Asia/Jakarta');
        $tahunDepan = $hariIni->year + 1;

        if ($hariIni->month < self::BULAN_MULAI || $this->hariLiburTerbit->CekSudahTerbit($tahunDepan)) {
            return 0;
        }

        $penerima = $this->AmbilPenerima(PeranPengelolaBawaan::KontenLegal);

        if ($penerima === []) {
            $penerima = $this->AmbilPenerima(PeranPengelolaBawaan::SuperAdmin);
        }

        $terlambat = $hariIni->month === 12;

        foreach ($penerima as $email) {
            Mail::to($email)->send(new PengingatHariLibur($tahunDepan, $terlambat));
        }

        return count($penerima);
    }

    /**
     * @return list<string>
     */
    private function AmbilPenerima(PeranPengelolaBawaan $peran): array
    {
        return array_values(PenggunaPengelola::query()
            ->where('Aktif', true)
            ->whereHas('Peran', fn (Builder $kueri) => $kueri->where('Kode', $peran->value))
            ->orderBy('Id')
            ->pluck('Email')
            ->all());
    }
}
