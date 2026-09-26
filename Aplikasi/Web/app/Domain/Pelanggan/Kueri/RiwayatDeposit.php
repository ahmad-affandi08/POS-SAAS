<?php

declare(strict_types=1);

namespace App\Domain\Pelanggan\Kueri;

use App\Domain\Pelanggan\Model\MutasiDeposit;

/** Riwayat buku deposit satu pelanggan (F-16d bagian 1), terbaru dulu, maks. [batas] baris. */
final class RiwayatDeposit
{
    /**
     * @return list<array{Uuid: string, Jenis: string, LabelJenis: string, Jumlah: string, SaldoSetelah: string, NomorSumber: string|null, Tanggal: string, Keterangan: string|null, DibuatPada: string|null}>
     */
    public function Ambil(int $idPelanggan, int $batas = 100): array
    {
        return array_values(MutasiDeposit::query()
            ->where('IdPelanggan', $idPelanggan)
            ->orderByDesc('Id')
            ->limit($batas)
            ->get()
            ->map(fn (MutasiDeposit $m): array => [
                'Uuid' => $m->Uuid,
                'Jenis' => $m->Jenis->value,
                'LabelJenis' => $m->Jenis->AmbilLabel(),
                'Jumlah' => $m->Jumlah,
                'SaldoSetelah' => $m->SaldoSetelah,
                'NomorSumber' => $m->NomorSumber,
                'Tanggal' => $m->Tanggal->toDateString(),
                'Keterangan' => $m->Keterangan,
                'DibuatPada' => $m->DibuatPada?->toIso8601ZuluString(),
            ])->all());
    }
}
