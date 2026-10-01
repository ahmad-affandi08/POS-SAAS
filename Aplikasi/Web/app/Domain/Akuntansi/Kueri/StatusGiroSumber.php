<?php

declare(strict_types=1);

namespace App\Domain\Akuntansi\Kueri;

use App\Domain\Akuntansi\Enum\JenisSumberGiro;
use App\Domain\Akuntansi\Enum\StatusGiro;
use App\Domain\Akuntansi\Model\Giro;

/** v3.42: status giro milik sebuah pelunasan piutang/pembayaran hutang (null = dibayar tanpa giro). */
final class StatusGiroSumber
{
    public function Ambil(JenisSumberGiro $jenis, int $idSumber): ?StatusGiro
    {
        return Giro::query()->where('JenisSumber', $jenis->value)->where('IdSumber', $idSumber)->first()?->Status;
    }

    /**
     * Ringkasan giro untuk halaman dokumen asal.
     *
     * @return array{Uuid: string, NomorGiro: string, NamaBank: string, TanggalJatuhTempo: string, Status: string, LabelStatus: string}|null
     */
    public function AmbilRingkas(JenisSumberGiro $jenis, int $idSumber): ?array
    {
        $giro = Giro::query()->where('JenisSumber', $jenis->value)->where('IdSumber', $idSumber)->first();

        return $giro === null ? null : [
            'Uuid' => $giro->Uuid,
            'NomorGiro' => $giro->NomorGiro,
            'NamaBank' => $giro->NamaBank,
            'TanggalJatuhTempo' => $giro->TanggalJatuhTempo->toDateString(),
            'Status' => $giro->Status->value,
            'LabelStatus' => $giro->Status->AmbilLabel(),
        ];
    }
}
