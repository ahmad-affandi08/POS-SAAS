<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\TimInternal\Layanan;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Pengelola\TimInternal\Model\LogAuditPengelola;

/**
 * Satu-satunya jalan menulis `LogAuditPengelola` (BR-P01.3).
 *
 * Terdaftar sebagai `scoped`: perantara `CatatAuditPengelola` mengisi pelaku & IP per request,
 * sehingga Aksi domain tidak perlu mengenal objek Request.
 */
final class PencatatAuditPengelola
{
    private ?int $idPelaku = null;

    private ?string $ip = null;

    public function AturKonteks(?int $idPelaku, ?string $ip): void
    {
        $this->idPelaku = $idPelaku;
        $this->ip = $ip;
    }

    /**
     * @param  array<string, mixed>|null  $nilaiLama
     * @param  array<string, mixed>|null  $nilaiBaru
     */
    public function Catat(
        string $aksi,
        ?ModelDasar $objek = null,
        ?array $nilaiLama = null,
        ?array $nilaiBaru = null,
        ?string $alasan = null,
        ?int $idPelaku = null,
        ?int $idTenant = null,
    ): LogAuditPengelola {
        $idObjek = $objek?->getKey();

        return LogAuditPengelola::query()->create([
            'IdPenggunaPengelola' => $idPelaku ?? $this->idPelaku,
            'Aksi' => $aksi,
            'JenisObjek' => $objek === null ? null : class_basename($objek),
            'IdObjek' => is_int($idObjek) ? $idObjek : null,
            'IdTenant' => $idTenant,
            'NilaiLama' => $nilaiLama,
            'NilaiBaru' => $nilaiBaru,
            'Alasan' => $alasan,
            'Ip' => $this->ip,
        ]);
    }
}
