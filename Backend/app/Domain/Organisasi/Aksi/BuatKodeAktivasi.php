<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Organisasi\Model\KodeAktivasi;
use App\Domain\Organisasi\Model\Perangkat;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * F-02 langkah 5: buat (ulang) kode aktivasi perangkat. 8 karakter tanpa 0/O/1/I, berlaku 15 menit (konfigurasi),
 * sekali pakai. Kode yang belum dipakai untuk perangkat yang sama langsung dibatalkan, sehingga hanya kode terbaru
 * yang berlaku. Kode asli dikembalikan sekali untuk ditampilkan; yang disimpan hanya hash-nya.
 *
 * Membuat kode untuk perangkat yang sudah aktif = memindahkan/menginstal ulang: aktivasi berikutnya mengganti token
 * lama sehingga instalasi lama keluar (satu instalasi = satu perangkat).
 */
final class BuatKodeAktivasi
{
    public function __construct(private readonly PencatatAudit $audit) {}

    /**
     * @return array{Kode: string, KedaluwarsaPada: Carbon}
     */
    public function Jalankan(Perangkat $perangkat, ?int $idPenggunaPembuat): array
    {
        return DB::transaction(function () use ($perangkat, $idPenggunaPembuat): array {
            $perangkat = Perangkat::query()->lockForUpdate()->findOrFail($perangkat->Id);

            if ($perangkat->CekDicabut()) {
                throw new PelanggaranAturanBisnis('PerangkatDicabut', 'Perangkat ini sudah dicabut. Tambahkan perangkat baru bila ingin memakai aplikasi lagi.');
            }

            KodeAktivasi::query()
                ->where('IdTenant', $perangkat->IdTenant)
                ->where('IdPerangkat', $perangkat->Id)
                ->whereNull('DipakaiPada')
                ->whereNull('DibatalkanPada')
                ->update(['DibatalkanPada' => now()]);

            $kode = $this->BuatKodeUnik();
            $kedaluwarsa = now()->addMinutes((int) config('organisasi.MenitBerlakuKodeAktivasi'));
            KodeAktivasi::query()->create([
                'IdTenant' => $perangkat->IdTenant,
                'IdOutlet' => $perangkat->IdOutlet,
                'IdPerangkat' => $perangkat->Id,
                'HashKode' => KodeAktivasi::BuatHashKode($kode),
                'KedaluwarsaPada' => $kedaluwarsa,
                'IdPenggunaPembuat' => $idPenggunaPembuat,
            ]);

            $this->audit->Catat('perangkat.kode-aktivasi.buat', $perangkat, nilaiBaru: [
                'Kode' => $perangkat->Kode,
                'KedaluwarsaPada' => $kedaluwarsa->toIso8601String(),
            ]);

            return ['Kode' => $kode, 'KedaluwarsaPada' => $kedaluwarsa];
        });
    }

    private function BuatKodeUnik(): string
    {
        for ($percobaan = 0; $percobaan < 5; $percobaan++) {
            $kode = '';

            for ($i = 0; $i < KodeAktivasi::PANJANG; $i++) {
                $kode .= KodeAktivasi::ABJAD[random_int(0, strlen(KodeAktivasi::ABJAD) - 1)];
            }

            if (! KodeAktivasi::query()->where('HashKode', KodeAktivasi::BuatHashKode($kode))->exists()) {
                return $kode;
            }
        }

        throw new RuntimeException('Gagal membuat kode aktivasi unik.');
    }
}
