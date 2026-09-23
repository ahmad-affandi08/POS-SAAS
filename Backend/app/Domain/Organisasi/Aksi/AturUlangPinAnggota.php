<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Organisasi\Enum\StatusKeanggotaan;
use App\Domain\Organisasi\Kueri\AksesPengguna;
use App\Domain\Organisasi\Layanan\PenjagaPin;
use App\Domain\Organisasi\Model\OutletPengguna;
use App\Domain\Organisasi\Model\TenantPengguna;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * F-02 langkah 4: Pemilik/Admin/Manajer (izin `pengguna.pin.atur`) mengatur ulang PIN kasir anggota lain, misal
 * kasir lupa PIN. Pelaku menetapkan PIN baru lalu memberitahukannya ke anggota; PIN lama tidak pernah bisa dilihat.
 * - PIN sendiri diatur lewat halaman PIN (bukan di sini).
 * - Hanya Pemilik yang bisa mengatur ulang PIN Pemilik.
 * - Pelaku yang aksesnya terbatas outlet hanya untuk anggota yang semua outletnya ada di outlet pelaku.
 */
final class AturUlangPinAnggota
{
    public function __construct(
        private readonly KonteksTenant $konteks,
        private readonly AksesPengguna $akses,
        private readonly PenjagaPin $penjaga,
        private readonly PencatatAudit $audit,
    ) {}

    public function Jalankan(int $idPelaku, TenantPengguna $anggota, string $pin): void
    {
        $idTenant = $this->konteks->Wajib();

        if ($anggota->IdPengguna === $idPelaku) {
            throw new PelanggaranAturanBisnis('UbahDiriSendiri', 'Atur PIN Anda sendiri di bagian "PIN saya".', 'Pin');
        }

        if ($anggota->Status !== StatusKeanggotaan::Aktif) {
            throw new PelanggaranAturanBisnis('AnggotaNonaktif', 'Aktifkan kembali anggota ini sebelum mengatur PIN-nya.', 'Pin');
        }

        $pelaku = $this->akses->Ambil($idTenant, $idPelaku);

        if ($anggota->Pemilik && ($pelaku === null || ! $pelaku['Pemilik'])) {
            throw new PelanggaranAturanBisnis('HanyaPemilik', 'Hanya Pemilik yang bisa mengatur ulang PIN Pemilik lain.', 'Pin');
        }

        $outletPelaku = $this->akses->AmbilIdOutlet($idTenant, $idPelaku);

        if ($outletPelaku !== null) {
            $outletAnggota = array_map('intval', OutletPengguna::query()->where('IdPengguna', $anggota->IdPengguna)->pluck('IdOutlet')->all());

            if ($anggota->Pemilik || $anggota->SemuaOutlet || $outletAnggota === [] || array_diff($outletAnggota, $outletPelaku) !== []) {
                throw new PelanggaranAturanBisnis('DiLuarOutletAnda', 'Anda hanya bisa mengatur PIN anggota yang bertugas di outlet yang Anda kelola.', 'Pin');
            }
        }

        $this->penjaga->PastikanKuat($pin);

        DB::transaction(function () use ($anggota, $pin): void {
            $baris = TenantPengguna::query()->lockForUpdate()->findOrFail($anggota->Id);
            $baris->HashPin = Hash::make($pin);
            $baris->save();

            $this->audit->Catat('pengguna.pin.atur-ulang', $baris, nilaiBaru: ['IdPengguna' => $baris->IdPengguna]);
        });
    }
}
