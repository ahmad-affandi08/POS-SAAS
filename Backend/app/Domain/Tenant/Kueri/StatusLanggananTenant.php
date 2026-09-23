<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Kueri;

use App\Domain\Tenant\Enum\StatusLangganan;
use App\Domain\Tenant\Model\Langganan;

/**
 * Status langganan untuk aplikasi POS (F-00 state machine, F-02b). `Ditangguhkan` dan `Berhenti` mengunci POS;
 * tenant yang memilih turun ke paket Gratis (status `Gratis`) boleh berjualan lagi dalam batas paket Gratis.
 * Tanpa baris langganan dianggap terkunci (gagal tertutup).
 */
final class StatusLanggananTenant
{
    public function Ambil(int $idTenant): ?StatusLangganan
    {
        $status = Langganan::query()->where('IdTenant', $idTenant)->value('Status');

        return match (true) {
            $status instanceof StatusLangganan => $status,
            is_string($status) => StatusLangganan::tryFrom($status),
            default => null,
        };
    }

    public function CekBolehBertransaksiPos(?StatusLangganan $status): bool
    {
        return $status !== null && ! in_array($status, [StatusLangganan::Ditangguhkan, StatusLangganan::Berhenti], true);
    }
}
