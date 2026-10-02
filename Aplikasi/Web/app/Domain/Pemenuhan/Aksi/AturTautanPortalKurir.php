<?php

declare(strict_types=1);

namespace App\Domain\Pemenuhan\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Pemenuhan\Enum\StatusKurir;
use App\Domain\Pemenuhan\Model\Kurir;
use Illuminate\Support\Str;

/**
 * F-10 (v3.49): membuat (atau membuat ulang) tautan portal kurir, atau mencabutnya. Token 40 karakter acak; yang lama
 * langsung tidak berlaku. Kurir yang diarsipkan tidak bisa diberi tautan. Tokennya sendiri tidak masuk log audit.
 */
final class AturTautanPortalKurir
{
    public const PANJANG_TOKEN = 40;

    public function __construct(private readonly PencatatAudit $audit) {}

    /** @return string|null token baru, atau null bila dicabut */
    public function Jalankan(Kurir $kurir, bool $buat, int $idPengguna): ?string
    {
        if ($buat && $kurir->Status !== StatusKurir::Aktif) {
            throw new PelanggaranAturanBisnis('KurirTidakAktif', 'Kurir yang diarsipkan tidak bisa diberi tautan portal.', 'Kurir');
        }

        $token = $buat ? Str::random(self::PANJANG_TOKEN) : null;
        $kurir->forceFill([
            'TokenPortal' => $token,
            'HashTokenPortal' => $token === null ? null : self::Hash($token),
            'TokenPortalDibuatPada' => $token === null ? null : now(),
        ])->save();
        $this->audit->Catat($buat ? 'kurir.portal.buat' : 'kurir.portal.cabut', $kurir, null, ['Nama' => $kurir->Nama], idPengguna: $idPengguna);

        return $token;
    }

    public static function Hash(string $token): string
    {
        return hash('sha256', $token);
    }
}
