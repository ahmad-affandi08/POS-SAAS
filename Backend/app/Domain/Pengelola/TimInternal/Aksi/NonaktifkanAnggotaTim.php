<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\TimInternal\Aksi;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Pengelola\TimInternal\Enum\PeranPengelolaBawaan;
use App\Domain\Pengelola\TimInternal\Kueri\SuperAdminAktif;
use App\Domain\Pengelola\TimInternal\Layanan\PencatatAuditPengelola;
use App\Domain\Pengelola\TimInternal\Model\PenggunaPengelola;
use App\Domain\Pengelola\TimInternal\Model\UndanganPengelola;
use Illuminate\Support\Facades\DB;

/**
 * P-01 langkah 6: anggota yang keluar dinonaktifkan, tidak dihapus. Riwayat audit tetap ada.
 * Sesi langsung terputus karena perantara PastikanPenggunaPengelola memeriksa status Aktif di setiap request.
 */
final class NonaktifkanAnggotaTim
{
    public function __construct(
        private readonly SuperAdminAktif $superAdminAktif,
        private readonly PencatatAuditPengelola $audit,
    ) {}

    public function Jalankan(PenggunaPengelola $pelaku, PenggunaPengelola $anggota, string $alasan): void
    {
        DB::transaction(function () use ($pelaku, $anggota, $alasan): void {
            $this->superAdminAktif->KunciPerubahan();
            $anggota->refresh();
            $anggota->LupakanIzin();

            if (! $anggota->Aktif) {
                throw new PelanggaranAturanBisnis('AnggotaSudahNonaktif', 'Anggota ini sudah nonaktif.');
            }

            if ($anggota->PunyaPeran(PeranPengelolaBawaan::SuperAdmin)
                && $this->superAdminAktif->Hitung(kunci: true) <= (int) config('pengelola.MinimalSuperAdminAktif')) {
                throw new PelanggaranAturanBisnis(
                    'BR-P01.1',
                    'Super Admin ini tidak bisa dinonaktifkan: minimal 2 Super Admin aktif harus tetap ada. Tambahkan Super Admin lain dulu.',
                );
            }

            $anggota->update(['Aktif' => false, 'DinonaktifkanPada' => now()]);

            // Undangan yang dikirim akun ini dan belum diterima ikut dicabut (P-01 langkah 6).
            $undanganDicabut = UndanganPengelola::query()
                ->where('IdPenggunaPengelolaPengundang', $anggota->Id)
                ->whereNull('DiterimaPada')
                ->whereNull('DibatalkanPada')
                ->update(['DibatalkanPada' => now()]);

            $this->audit->Catat(
                'tim.anggota.nonaktifkan',
                $anggota,
                nilaiLama: ['Aktif' => true],
                nilaiBaru: ['Aktif' => false, 'UndanganDicabut' => $undanganDicabut],
                alasan: $alasan,
                idPelaku: $pelaku->Id,
            );
        });
    }
}
