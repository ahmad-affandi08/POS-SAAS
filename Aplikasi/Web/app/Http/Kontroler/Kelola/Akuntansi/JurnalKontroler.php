<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Kelola\Akuntansi;

use App\Domain\Akuntansi\Enum\JenisSumberJurnal;
use App\Domain\Akuntansi\Kueri\DaftarJurnal;
use App\Domain\Akuntansi\Kueri\DetailJurnal;
use App\Http\Kontroler\Kelola\DasarKelolaKontroler;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Halaman jurnal (baca saja) `laporan.keuangan.lihat` (DesainF05a D, H-13). Jurnal tenant lain atau yang memuat baris
 * di luar outlet akses pengguna = 404.
 */
final class JurnalKontroler extends DasarKelolaKontroler
{
    public function __construct(
        private readonly DaftarJurnal $daftar,
        private readonly DetailJurnal $detail,
    ) {}

    public function Daftar(Request $permintaan): Response
    {
        $jenis = JenisSumberJurnal::tryFrom($permintaan->string('jenis')->toString());
        $saring = [
            'Kata' => mb_substr(trim($permintaan->string('kata')->toString()), 0, 100),
            'Dari' => self::AmbilTanggal($permintaan, 'dari'),
            'Sampai' => self::AmbilTanggal($permintaan, 'sampai'),
            'JenisSumber' => $jenis?->value,
        ];

        return Inertia::render('Kelola/Akuntansi/Jurnal/Daftar', [
            'Jurnal' => $this->daftar->Ambil($saring, max(1, $permintaan->integer('halaman', 1)), $this->IdOutletBoleh()),
            'Saring' => $saring,
            'OpsiJenisSumber' => array_map(
                fn (JenisSumberJurnal $j): array => ['Nilai' => $j->value, 'Label' => $j->AmbilLabel()],
                JenisSumberJurnal::cases(),
            ),
        ]);
    }

    public function Detail(string $jurnal): Response
    {
        $props = $this->detail->Ambil($jurnal, $this->IdOutletBoleh());
        abort_if($props === null, 404);

        return Inertia::render('Kelola/Akuntansi/Jurnal/Detail', $props);
    }

    /** Tanggal `YYYY-MM-DD` dari query; format lain diabaikan (kosong = tanpa batas). */
    private static function AmbilTanggal(Request $permintaan, string $kunci): string
    {
        $nilai = trim($permintaan->string($kunci)->toString());

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $nilai) === 1 && checkdate((int) substr($nilai, 5, 2), (int) substr($nilai, 8, 2), (int) substr($nilai, 0, 4))
            ? $nilai
            : '';
    }
}
