<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Enum;

/**
 * Status keanggotaan pengguna di satu tenant (`TenantPengguna.Status`). Anggota nonaktif tidak bisa memilih
 * tenant ini dan sesinya langsung terputus (F-02). Undangan yang belum diterima disimpan di `UndanganPengguna`.
 */
enum StatusKeanggotaan: string
{
    case Aktif = 'Aktif';
    case Nonaktif = 'Nonaktif';

    public function BisaBerubahKe(self $tujuan): bool
    {
        return $this !== $tujuan;
    }
}
