<?php

declare(strict_types=1);

namespace App\Domain\Pemenuhan\Kueri;

use App\Domain\Pemenuhan\Model\PengaturanReservasi;

/** Pengaturan reservasi tenant aktif (F-07 mode service); tanpa baris = nilai bawaan model. */
final class PengaturanReservasiTenant
{
    public function Ambil(): PengaturanReservasi
    {
        return PengaturanReservasi::query()->first() ?? new PengaturanReservasi;
    }

    /**
     * @return array{OnlineAktif: bool, KonfirmasiOtomatis: bool, IntervalSlotMenit: int, JedaMenit: int, BatasHariKeDepan: int, MinimalMenitSebelum: int, PengingatAktif: bool}
     */
    public function AmbilLarik(): array
    {
        $p = $this->Ambil();

        return [
            'OnlineAktif' => $p->OnlineAktif,
            'KonfirmasiOtomatis' => $p->KonfirmasiOtomatis,
            'IntervalSlotMenit' => $p->IntervalSlotMenit,
            'JedaMenit' => $p->JedaMenit,
            'BatasHariKeDepan' => $p->BatasHariKeDepan,
            'MinimalMenitSebelum' => $p->MinimalMenitSebelum,
            'PengingatAktif' => $p->PengingatAktif,
        ];
    }
}
