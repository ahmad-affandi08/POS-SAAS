<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Kelola\Kasir;

use App\Domain\Bersama\Tabel\Data\DataPermintaanTabel;
use App\Domain\Kasir\Enum\StatusShift;
use App\Domain\Kasir\Kueri\DaftarShift;
use App\Domain\Kasir\Kueri\DetailShift;
use App\Domain\Organisasi\Kueri\PetaUuidOutlet;
use App\Http\Kontroler\Kelola\DasarKelolaKontroler;
use App\Http\Respons\ResponsTabel;
use Illuminate\Http\JsonResponse;
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
    public function Daftar(Request $permintaan, DaftarShift $daftar, PetaUuidOutlet $outlet): Response|JsonResponse
    {
        $tabel = DataPermintaanTabel::Dari($permintaan->query(), DaftarShift::KOLOM_URUT, DaftarShift::URUT_BAWAAN, DaftarShift::KOLOM_SARING);

        return ResponsTabel::Kirim(
            $permintaan,
            'Kelola/Kasir/Shift/Daftar',
            'Shift',
            fn (): array => $daftar->AmbilTabel($tabel, $this->IdOutletBoleh(), $this->IdTenant()),
            fn (): array => [
                'OpsiOutlet' => array_map(fn (array $o): array => ['Uuid' => $o['Uuid'], 'Nama' => $o['Nama']], $outlet->AmbilRingkas($this->IdOutletBoleh())),
                'OpsiStatus' => array_map(fn (StatusShift $s): array => ['Nilai' => $s->value, 'Label' => $s->AmbilLabel()], StatusShift::cases()),
            ],
        );
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
}
