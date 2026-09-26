<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Aksi;

use App\Domain\Organisasi\Layanan\PencatatAuditAkun;
use App\Domain\Organisasi\Model\Pengguna;
use App\Domain\Organisasi\Model\TokenAksesPengguna;
use Illuminate\Support\Facades\DB;

/**
 * OWN-01: keluar dari Aplikasi Owner = mencabut token yang sedang dipakai. Token yang dicabut langsung ditolak (401).
 * Semua token pengguna ikut dicabut saat kata sandi diatur ulang (`AturUlangKataSandi`, PRD §16).
 */
final class CabutTokenPengguna
{
    public function __construct(private readonly PencatatAuditAkun $auditAkun) {}

    public function Jalankan(TokenAksesPengguna $token, Pengguna $pengguna): void
    {
        DB::transaction(function () use ($token, $pengguna): void {
            $jumlah = TokenAksesPengguna::query()->whereKey($token->Id)->whereNull('DicabutPada')->update(['DicabutPada' => now()]);

            if ($jumlah > 0) {
                $this->auditAkun->Catat('pemilik.keluar', $pengguna, ['NamaPerangkat' => $token->Nama, 'UuidToken' => $token->Uuid]);
            }
        });
    }
}
