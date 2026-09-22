<?php

declare(strict_types=1);

namespace App\Domain\Bersama\Tenant;

/**
 * Menyimpan tenant yang sedang aktif untuk satu request/job (PRD §13.4).
 *
 * Diisi oleh perantara IdentifikasiTenant (sesi, device token, user token, slug)
 * atau oleh job yang membawa IdTenant. Terdaftar sebagai "scoped" di container.
 */
final class KonteksTenant
{
    private ?int $idTenant = null;

    public function Atur(int $idTenant): void
    {
        $this->idTenant = $idTenant;
    }

    public function Ambil(): ?int
    {
        return $this->idTenant;
    }

    /**
     * @throws TenantBelumDitetapkan
     */
    public function Wajib(): int
    {
        return $this->idTenant ?? throw new TenantBelumDitetapkan;
    }

    public function Kosongkan(): void
    {
        $this->idTenant = null;
    }
}
