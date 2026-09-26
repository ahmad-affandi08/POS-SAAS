<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Kueri;

use App\Domain\Organisasi\Data\DataMejaPesanSendiri;
use App\Domain\Organisasi\Enum\StatusOrganisasi;
use App\Domain\Organisasi\Model\Meja;
use App\Domain\Organisasi\Model\Outlet;

/**
 * F-17 Self-Order QR Meja: meja dari token QR (di dalam konteks tenant, lewat `MilikTenant`). Token yang sudah diganti
 * ("Buat ulang QR") tidak dikenal lagi.
 */
final class MejaPesanSendiri
{
    public const POLA_TOKEN = '[A-Za-z0-9]{32}';

    public function CariDariToken(string $token): ?DataMejaPesanSendiri
    {
        if (preg_match('/^'.self::POLA_TOKEN.'$/', $token) !== 1) {
            return null;
        }

        $meja = Meja::query()->where('TokenPesanSendiri', $token)->first();
        $outlet = $meja === null ? null : Outlet::query()->whereKey($meja->IdOutlet)->first();

        if ($meja === null || $outlet === null) {
            return null;
        }

        return new DataMejaPesanSendiri(
            idMeja: $meja->Id,
            uuidMeja: $meja->Uuid,
            namaMeja: $meja->Nama,
            idOutlet: $outlet->Id,
            kodeOutlet: $outlet->Kode,
            namaOutlet: $outlet->Nama,
            zonaWaktu: $outlet->ZonaWaktu,
            bisaDipesan: $meja->Status === StatusOrganisasi::Aktif && $outlet->Status === StatusOrganisasi::Aktif && $outlet->PesanSendiriAktif,
        );
    }
}
