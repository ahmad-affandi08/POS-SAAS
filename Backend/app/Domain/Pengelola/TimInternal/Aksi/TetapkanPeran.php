<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\TimInternal\Aksi;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Pengelola\TimInternal\Enum\PeranPengelolaBawaan;
use App\Domain\Pengelola\TimInternal\Kueri\SuperAdminAktif;
use App\Domain\Pengelola\TimInternal\Layanan\PencatatAuditPengelola;
use App\Domain\Pengelola\TimInternal\Model\PenggunaPengelola;
use App\Domain\Pengelola\TimInternal\Model\PeranPengelola;
use Illuminate\Support\Facades\DB;

/**
 * P-01 langkah 5: menetapkan peran (boleh lebih dari satu). Menegakkan BR-P01.1 saat peran Super Admin dicabut.
 */
final class TetapkanPeran
{
    public function __construct(
        private readonly SuperAdminAktif $superAdminAktif,
        private readonly PencatatAuditPengelola $audit,
    ) {}

    /**
     * @param  list<string>  $kodePeran
     */
    public function Jalankan(PenggunaPengelola $pelaku, PenggunaPengelola $anggota, array $kodePeran, ?string $alasan = null): void
    {
        $kodePeran = array_values(array_unique($kodePeran));

        DB::transaction(function () use ($pelaku, $anggota, $kodePeran, $alasan): void {
            $peran = PeranPengelola::query()->whereIn('Kode', $kodePeran)->get();

            if ($kodePeran === [] || $peran->count() !== count($kodePeran)) {
                throw new PelanggaranAturanBisnis('PeranTidakDikenal', 'Pilih minimal satu peran yang tersedia.', 'KodePeran');
            }

            $kodeLama = $anggota->DaftarKodePeran();
            $superAdmin = PeranPengelolaBawaan::SuperAdmin->value;
            $superAdminDicabut = in_array($superAdmin, $kodeLama, true) && ! in_array($superAdmin, $kodePeran, true);

            if ($superAdminDicabut && $anggota->Aktif
                && $this->superAdminAktif->Hitung(kunci: true) <= (int) config('pengelola.MinimalSuperAdminAktif')) {
                throw new PelanggaranAturanBisnis(
                    'BR-P01.1',
                    'Peran Super Admin tidak bisa dicabut: minimal 2 Super Admin aktif harus tetap ada. Tambahkan Super Admin lain dulu.',
                    'KodePeran',
                );
            }

            $anggota->Peran()->sync($peran->pluck('Id')->all());
            $anggota->LupakanIzin();

            sort($kodeLama);
            $kodeBaru = $kodePeran;
            sort($kodeBaru);

            $this->audit->Catat(
                'tim.peran.tetapkan',
                $anggota,
                nilaiLama: ['KodePeran' => $kodeLama],
                nilaiBaru: ['KodePeran' => $kodeBaru],
                alasan: $alasan,
                idPelaku: $pelaku->Id,
            );
        });
    }
}
