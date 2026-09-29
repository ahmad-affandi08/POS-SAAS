<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Tugas;

use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Integrasi\Push\Layanan\PengirimFcm;
use App\Domain\Organisasi\Model\NotifikasiPengguna;
use App\Domain\Organisasi\Model\PerangkatPengguna;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/** Mengantar satu notifikasi persisten ke seluruh pemasangan aktif pengguna. */
final class KirimNotifikasiPushTugas implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public int $idTenant, public int $idNotifikasi) {}

    public function handle(KonteksTenant $konteks, PengirimFcm $fcm): void
    {
        $sebelumnya = $konteks->Ambil();
        $konteks->Atur($this->idTenant);

        try {
            $notifikasi = NotifikasiPengguna::query()->whereKey($this->idNotifikasi)->first();

            if ($notifikasi === null || $notifikasi->DikirimPada !== null) {
                return;
            }

            $berhasil = 0;
            $aktif = false;
            $pesan = null;

            foreach (PerangkatPengguna::query()->where('IdPengguna', $notifikasi->IdPengguna)->where('Aktif', true)->get() as $perangkat) {
                $hasil = $fcm->Kirim($perangkat->Token, $notifikasi->Judul, $notifikasi->Isi, [
                    'Jenis' => $notifikasi->Jenis->value,
                    'UuidNotifikasi' => $notifikasi->Uuid,
                    ...array_map('strval', $notifikasi->Data ?? []),
                ]);
                $aktif = $aktif || $hasil->aktif;
                $pesan ??= $hasil->pesan;

                if ($hasil->berhasil) {
                    $berhasil++;
                } elseif ($hasil->tokenTidakBerlaku) {
                    $perangkat->Aktif = false;
                    $perangkat->save();
                }
            }

            if ($berhasil > 0) {
                $notifikasi->fill(['DikirimPada' => now(), 'GagalPada' => null, 'PesanGalat' => null])->save();
            } elseif ($aktif) {
                $notifikasi->fill(['GagalPada' => now(), 'PesanGalat' => mb_substr($pesan ?? 'Push gagal dikirim.', 0, 500)])->save();
            }
        } finally {
            $sebelumnya === null ? $konteks->Kosongkan() : $konteks->Atur($sebelumnya);
        }
    }
}
