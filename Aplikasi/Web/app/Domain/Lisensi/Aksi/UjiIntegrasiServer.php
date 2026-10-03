<?php

declare(strict_types=1);

namespace App\Domain\Lisensi\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Lisensi\Kontrak\PengaturIntegrasiServer;

/** D-35: uji koneksi integrasi server dari back-office edisi Lisensi; berhasil = langsung aktif (BR-P05.4). */
final class UjiIntegrasiServer
{
    public function __construct(
        private readonly PengaturIntegrasiServer $pengatur,
        private readonly PencatatAudit $audit,
    ) {}

    /**
     * @return array{Berhasil: bool, Pesan: string}
     */
    public function Jalankan(string $jenis): array
    {
        $hasil = $this->pengatur->UjiDanAktifkan($jenis);
        $this->audit->Catat('integrasi.server.uji', nilaiBaru: ['Jenis' => $jenis, 'Berhasil' => $hasil['Berhasil']]);

        return $hasil;
    }
}
