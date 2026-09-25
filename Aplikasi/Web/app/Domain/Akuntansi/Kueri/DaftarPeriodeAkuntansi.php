<?php

declare(strict_types=1);

namespace App\Domain\Akuntansi\Kueri;

use App\Domain\Akuntansi\Layanan\PenjagaKunciPeriode;
use App\Domain\Akuntansi\Model\KunciPeriode;
use App\Domain\Kasir\Kueri\ShiftBelumDitutup;
use App\Domain\Organisasi\Kueri\DaftarAnggota;
use Carbon\CarbonImmutable;

/**
 * Daftar periode untuk halaman Tutup buku (F-15): bulan berjalan + 23 bulan sebelumnya (terbaru dulu), status kunci,
 * siapa & kapan dikunci, dan syarat kunci (bulan sudah lewat, shift sampai akhir periode sudah ditutup).
 */
final class DaftarPeriodeAkuntansi
{
    public const JUMLAH_BULAN = 24;

    public function __construct(
        private readonly ShiftBelumDitutup $shift,
        private readonly DaftarAnggota $anggota,
    ) {}

    /**
     * @return list<array{Periode: string, Label: string, Terkunci: bool, DikunciPada: string|null, DikunciOleh: string|null, Berjalan: bool, ShiftBelumDitutup: int}>
     */
    public function Ambil(int $idTenant, CarbonImmutable $bulanIni): array
    {
        $kunci = KunciPeriode::query()->get()->keyBy('Periode');
        $nama = $this->anggota->AmbilNamaPengguna($idTenant, array_values(array_filter($kunci->pluck('DikunciOleh')->all(), 'is_int')));
        $hasil = [];

        for ($i = 0; $i < self::JUMLAH_BULAN; $i++) {
            $awal = $bulanIni->startOfMonth()->subMonthsNoOverflow($i);
            $periode = $awal->format('Y-m');
            /** @var KunciPeriode|null $baris */
            $baris = $kunci->get($periode);
            $hasil[] = [
                'Periode' => $periode,
                'Label' => PenjagaKunciPeriode::FormatPeriode($periode),
                'Terkunci' => $baris !== null,
                'DikunciPada' => $baris?->DikunciPada?->toIso8601String(),
                'DikunciOleh' => $baris?->DikunciOleh === null ? null : ($nama[$baris->DikunciOleh] ?? null),
                'Berjalan' => $i === 0,
                'ShiftBelumDitutup' => $baris === null && $i > 0 ? $this->shift->Hitung($awal->endOfMonth(), dariTanggal: $awal) : 0,
            ];
        }

        return $hasil;
    }
}
