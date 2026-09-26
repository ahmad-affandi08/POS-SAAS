<?php

declare(strict_types=1);

namespace App\Domain\Pemenuhan\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Karyawan\Kueri\JadwalStafReservasi;
use App\Domain\Organisasi\Kueri\ZonaWaktuOutlet;
use App\Domain\Pemenuhan\Kueri\PengaturanReservasiTenant;
use App\Domain\Pemenuhan\Kueri\SlotReservasi;
use App\Domain\Pemenuhan\Model\Reservasi;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * F-07 mode service: pindah jadwal reservasi yang masih Menunggu/Dikonfirmasi ke tanggal/jam/staf lain yang kosong
 * (slot dihitung tanpa reservasi ini sendiri). Pengingat H-1 dikirim ulang untuk jadwal baru. Audit nilai lama/baru.
 */
final class JadwalkanUlangReservasi
{
    public function __construct(
        private readonly JadwalStafReservasi $staf,
        private readonly SlotReservasi $slot,
        private readonly PengaturanReservasiTenant $pengaturan,
        private readonly ZonaWaktuOutlet $zona,
        private readonly PencatatAudit $audit,
    ) {}

    public function Jalankan(int $idTenant, Reservasi $reservasi, string $tanggal, string $jam, ?string $uuidStaf, int $idPengguna): Reservasi
    {
        if (! in_array($reservasi->Status->value, ['Menunggu', 'Dikonfirmasi'], true)) {
            throw new PelanggaranAturanBisnis('TidakBisaDijadwalkanUlang', 'Hanya reservasi yang belum datang yang bisa dipindah jadwalnya.', 'Status', 409);
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal) !== 1 || preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $jam) !== 1) {
            throw new PelanggaranAturanBisnis('WaktuTidakValid', 'Tanggal atau jam tidak valid.', 'Jam');
        }

        $durasi = intdiv($reservasi->SelesaiPada->getTimestamp() - $reservasi->MulaiPada->getTimestamp(), 60);
        $idStaf = $uuidStaf === null || $uuidStaf === '' ? null
            : ($this->staf->CariStaf($uuidStaf) ?? throw new PelanggaranAturanBisnis('StafTidakDitemukan', 'Staf tidak ditemukan.', 'UuidStaf'))['Id'];
        $atur = $this->pengaturan->Ambil();
        $mulai = CarbonImmutable::parse("{$tanggal} {$jam}", $this->zona->Ambil($reservasi->IdOutlet));
        $kosong = function () use ($reservasi, $tanggal, $jam, $durasi, $idStaf, $atur): array {
            foreach ($this->slot->Hitung($reservasi->IdOutlet, $tanggal, $durasi, $idStaf, $atur, CarbonImmutable::now()->subMinutes(5), $reservasi->Id) as $s) {
                if ($s['Jam'] === $jam) {
                    return array_column($s['Staf'], 'Id');
                }
            }

            return [];
        };

        foreach ($kosong() as $idKaryawan) {
            try {
                $hasil = Cache::lock("reservasi-staf:{$idTenant}:{$idKaryawan}", 10)->block(5, function () use ($kosong, $idKaryawan, $reservasi, $mulai, $durasi, $idPengguna): ?Reservasi {
                    if (! in_array($idKaryawan, $kosong(), true)) {
                        return null;
                    }

                    return DB::transaction(function () use ($idKaryawan, $reservasi, $mulai, $durasi, $idPengguna): Reservasi {
                        $r = Reservasi::query()->whereKey($reservasi->Id)->lockForUpdate()->firstOrFail();
                        $lama = ['MulaiPada' => $r->MulaiPada->toIso8601String(), 'IdKaryawan' => $r->IdKaryawan];
                        $r->setAttribute('MulaiPada', $mulai->utc());
                        $r->setAttribute('SelesaiPada', $mulai->addMinutes($durasi)->utc());
                        $r->IdKaryawan = $idKaryawan;
                        $r->PengingatTerkirimPada = null;
                        $r->save();
                        $this->audit->Catat('reservasi.jadwal-ulang', $r, $lama, ['MulaiPada' => $mulai->toIso8601String(), 'IdKaryawan' => $idKaryawan], idPengguna: $idPengguna);

                        return $r;
                    });
                });
            } catch (LockTimeoutException) {
                $hasil = null;
            }

            if ($hasil !== null) {
                return $hasil;
            }
        }

        throw new PelanggaranAturanBisnis('SlotTidakTersedia', 'Jam ini sudah tidak tersedia. Pilih jam lain.', 'Jam', 409);
    }
}
