<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\TimInternal\Aksi;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Pengelola\TimInternal\Layanan\PencatatAuditPengelola;
use App\Domain\Pengelola\TimInternal\Model\PenggunaPengelola;
use App\Domain\Pengelola\TimInternal\Model\PeranPengelola;
use App\Domain\Pengelola\TimInternal\Model\UndanganPengelola;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * D-22 (P-01 langkah 3, alternatif undangan): Super Admin menambah anggota tim langsung dengan kata sandi awal yang ia
 * ketik, tanpa perlu email aktif. Akun wajib mengganti kata sandi saat pertama masuk, lalu mengaktifkan 2FA (BR-P01.2).
 * Undangan lama yang belum dipakai untuk email yang sama dibatalkan.
 */
final class TambahAnggotaTim
{
    public function __construct(private readonly PencatatAuditPengelola $audit) {}

    /**
     * @param  list<string>  $kodePeran
     */
    public function Jalankan(PenggunaPengelola $pelaku, string $nama, string $email, string $kataSandi, array $kodePeran): PenggunaPengelola
    {
        $email = Str::lower(trim($email));
        $kodePeran = array_values(array_unique($kodePeran));

        return DB::transaction(function () use ($pelaku, $nama, $email, $kataSandi, $kodePeran): PenggunaPengelola {
            if (PenggunaPengelola::query()->where('Email', $email)->lockForUpdate()->exists()) {
                throw new PelanggaranAturanBisnis('EmailSudahTerdaftar', 'Email ini sudah terdaftar sebagai anggota tim internal.', 'Email');
            }

            $idPeran = PeranPengelola::query()->whereIn('Kode', $kodePeran)->pluck('Id')->all();

            if ($kodePeran === [] || count($idPeran) !== count($kodePeran)) {
                throw new PelanggaranAturanBisnis('PeranTidakDikenal', 'Pilih minimal satu peran yang tersedia.', 'KodePeran');
            }

            $pengguna = PenggunaPengelola::query()->create([
                'Nama' => trim($nama),
                'Email' => $email,
                'KataSandi' => $kataSandi,
                'WajibGantiKataSandi' => true,
                'Aktif' => true,
            ]);
            $pengguna->Peran()->attach($idPeran);

            UndanganPengelola::query()
                ->where('Email', $email)
                ->whereNull('DiterimaPada')
                ->whereNull('DibatalkanPada')
                ->update(['DibatalkanPada' => now()]);

            $this->audit->Catat(
                'tim.anggota.tambah',
                $pengguna,
                nilaiBaru: ['Nama' => $pengguna->Nama, 'Email' => $email, 'KodePeran' => $kodePeran, 'WajibGantiKataSandi' => true],
                idPelaku: $pelaku->Id,
            );

            return $pengguna;
        });
    }
}
