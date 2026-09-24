<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Kelola\Kasir;

use App\Domain\Kasir\Enum\StatusShift;
use App\Domain\Kasir\Kueri\DaftarShift;
use App\Domain\Kasir\Kueri\DetailShift;
use App\Domain\Organisasi\Kueri\PetaUuidOutlet;
use App\Http\Kontroler\Kelola\DasarKelolaKontroler;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Halaman shift kasir (baca saja, F-06), izin `laporan.penjualan.lihat`. Shift tenant lain atau di outlet di luar
 * akses pelaku = 404.
 */
final class ShiftKontroler extends DasarKelolaKontroler
{
    public function Daftar(Request $permintaan, DaftarShift $daftar, PetaUuidOutlet $outlet): Response
    {
        $status = StatusShift::tryFrom($permintaan->string('status')->toString());
        $uuidOutlet = trim($permintaan->string('outlet')->toString());
        $saring = [
            'UuidOutlet' => $uuidOutlet === '' ? null : $uuidOutlet,
            'Status' => $status?->value,
            'Dari' => self::AmbilTanggal($permintaan, 'dari'),
            'Sampai' => self::AmbilTanggal($permintaan, 'sampai'),
            'PerluTinjauan' => $permintaan->boolean('tinjauan'),
        ];

        return Inertia::render('Kelola/Kasir/Shift/Daftar', [
            'Shift' => $daftar->Ambil($saring, $this->IdOutletBoleh(), max(1, $permintaan->integer('halaman', 1))),
            'Saring' => $saring,
            'OpsiOutlet' => array_map(fn (array $o): array => ['Uuid' => $o['Uuid'], 'Nama' => $o['Nama']], $outlet->AmbilRingkas($this->IdOutletBoleh())),
            'OpsiStatus' => array_map(fn (StatusShift $s): array => ['Nilai' => $s->value, 'Label' => $s->AmbilLabel()], StatusShift::cases()),
        ]);
    }

    public function Detail(string $shift, DetailShift $detail): Response
    {
        $props = $detail->Ambil($shift, $this->IdOutletBoleh());
        abort_if($props === null, 404);

        return Inertia::render('Kelola/Kasir/Shift/Detail', $props);
    }

    /** Tautan sumber jurnal mutasi kas → halaman shift pemiliknya. */
    public function MutasiKas(string $mutasiKas, DetailShift $detail): RedirectResponse
    {
        $uuidShift = $detail->CariUuidShiftDariMutasi($mutasiKas, $this->IdOutletBoleh());
        abort_if($uuidShift === null, 404);

        return to_route('kelola.kasir.shift.detail', ['shift' => $uuidShift]);
    }

    private static function AmbilTanggal(Request $permintaan, string $kunci): string
    {
        $nilai = trim($permintaan->string($kunci)->toString());

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $nilai) === 1 && checkdate((int) substr($nilai, 5, 2), (int) substr($nilai, 8, 2), (int) substr($nilai, 0, 4))
            ? $nilai
            : '';
    }
}
