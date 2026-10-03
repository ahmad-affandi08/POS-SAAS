<?php

declare(strict_types=1);

namespace App\Domain\Lisensi\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Lisensi\Kontrak\PengaturIntegrasiServer;

/** D-35: Owner edisi Lisensi mematikan integrasi server (misal pindah penyedia WhatsApp). */
final class NonaktifkanIntegrasiServer
{
    public function __construct(
        private readonly PengaturIntegrasiServer $pengatur,
        private readonly PencatatAudit $audit,
    ) {}

    public function Jalankan(string $jenis): void
    {
        $this->pengatur->Nonaktifkan($jenis);
        $this->audit->Catat('integrasi.server.nonaktifkan', nilaiLama: ['Aktif' => true], nilaiBaru: ['Jenis' => $jenis, 'Aktif' => false]);
    }
}
