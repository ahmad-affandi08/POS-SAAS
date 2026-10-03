<?php

declare(strict_types=1);

namespace App\Domain\Lisensi\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Lisensi\Kontrak\PengaturIntegrasiServer;

/**
 * D-35: Owner edisi Lisensi menyimpan penyedia email/WhatsApp/penyimpanan server tokonya dari back-office. Jejaknya
 * di log audit tenant tanpa nilai kredensial (BR-P05.6), karena konsol pengelola tidak ada di edisi ini.
 */
final class SimpanIntegrasiServer
{
    public function __construct(
        private readonly PengaturIntegrasiServer $pengatur,
        private readonly PencatatAudit $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $pengaturan
     * @param  array<string, mixed>  $kredensial
     */
    public function Jalankan(string $jenis, string $penyedia, array $pengaturan, array $kredensial): void
    {
        $nilaiBaru = $this->pengatur->Simpan($jenis, $penyedia, $pengaturan, $kredensial);
        $this->audit->Catat('integrasi.server.simpan', nilaiBaru: $nilaiBaru);
    }
}
