<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Kueri;

use App\Domain\Organisasi\Enum\StatusKeanggotaan;
use App\Domain\Organisasi\Model\OutletPengguna;
use App\Domain\Organisasi\Model\Peran;
use App\Domain\Organisasi\Model\TenantPengguna;

/**
 * Status PIN kasir anggota aktif untuk halaman PIN (F-02b). Hanya "sudah/belum diatur" — hash PIN tidak pernah
 * dikirim ke browser. Daftar dibatasi anggota yang boleh diatur ulang PIN-nya oleh pelaku (sama dengan aturan
 * `AturUlangPinAnggota`); server tetap memeriksa ulang saat menyimpan.
 */
final class DaftarPinAnggota
{
    public function __construct(private readonly AksesPengguna $akses) {}

    public function CekPinDiatur(int $idTenant, int $idPengguna): bool
    {
        return TenantPengguna::query()
            ->where('IdTenant', $idTenant)
            ->where('IdPengguna', $idPengguna)
            ->whereNotNull('HashPin')
            ->exists();
    }

    /**
     * @return list<array{Uuid: string, Nama: string, Email: string, NamaPeran: string|null, PinDiatur: bool}>
     */
    public function Ambil(int $idTenant, int $idPelaku): array
    {
        $pelaku = $this->akses->Ambil($idTenant, $idPelaku);
        $outletPelaku = $this->akses->AmbilIdOutlet($idTenant, $idPelaku);
        $peran = Peran::query()->pluck('Nama', 'Id');
        $outletAnggota = OutletPengguna::query()->get(['IdPengguna', 'IdOutlet'])->groupBy('IdPengguna');

        return array_values(TenantPengguna::query()
            ->where('IdTenant', $idTenant)
            ->where('Status', StatusKeanggotaan::Aktif->value)
            ->where('IdPengguna', '!=', $idPelaku)
            ->with('Pengguna')
            ->get()
            ->filter(function (TenantPengguna $anggota) use ($pelaku, $outletPelaku, $outletAnggota): bool {
                if ($anggota->Pemilik && ! ($pelaku['Pemilik'] ?? false)) {
                    return false;
                }

                if ($outletPelaku === null) {
                    return true;
                }

                $milik = array_map('intval', $outletAnggota->get($anggota->IdPengguna)?->pluck('IdOutlet')->all() ?? []);

                return ! $anggota->Pemilik && ! $anggota->SemuaOutlet && $milik !== [] && array_diff($milik, $outletPelaku) === [];
            })
            ->sortBy(fn (TenantPengguna $anggota) => mb_strtolower($anggota->Pengguna->Nama))
            ->map(fn (TenantPengguna $anggota): array => [
                'Uuid' => $anggota->Pengguna->Uuid,
                'Nama' => $anggota->Pengguna->Nama,
                'Email' => $anggota->Pengguna->Email,
                'NamaPeran' => $anggota->IdPeran === null ? null : (is_string($nama = $peran->get($anggota->IdPeran)) ? $nama : null),
                'PinDiatur' => $anggota->HashPin !== null,
            ])
            ->all());
    }
}
