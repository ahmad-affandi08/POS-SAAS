<?php

declare(strict_types=1);

namespace App\Console\Perintah;

use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Organisasi\Kueri\KeanggotaanPengguna;
use App\Domain\Pemenuhan\Enum\StatusReservasi;
use App\Domain\Pemenuhan\Kueri\PengaturanReservasiTenant;
use App\Domain\Pemenuhan\Model\Reservasi;
use App\Domain\Pemenuhan\Tugas\KirimPengingatReservasiTugas;
use Illuminate\Console\Command;

/**
 * F-07 mode service: tiap jam mengantrekan pengingat WhatsApp untuk reservasi yang mulai 20–28 jam lagi (H-1) dan belum
 * diingatkan, di tenant yang mengaktifkan pengingat.
 */
final class KirimPengingatReservasiPerintah extends Command
{
    protected $signature = 'reservasi:kirim-pengingat {--tenant=* : Id tenant (kosong = semua)}';

    protected $description = 'Mengantrekan pengingat reservasi H-1 lewat WhatsApp (F-07 mode service).';

    public function handle(KeanggotaanPengguna $keanggotaan, KonteksTenant $konteks, PengaturanReservasiTenant $pengaturan): int
    {
        $diminta = array_values(array_filter(array_map(
            fn (mixed $nilai): int => is_scalar($nilai) ? (int) $nilai : 0,
            (array) $this->option('tenant'),
        ), fn (int $id): bool => $id > 0));
        $semua = $keanggotaan->AmbilSemuaIdTenant();
        $daftar = $diminta === [] ? $semua : array_values(array_intersect(array_unique($diminta), $semua));
        $sebelumnya = $konteks->Ambil();
        $antre = 0;

        try {
            foreach ($daftar as $idTenant) {
                $konteks->Atur($idTenant);

                if (! $pengaturan->Ambil()->PengingatAktif) {
                    continue;
                }

                $id = Reservasi::query()
                    ->whereIn('Status', [StatusReservasi::Menunggu->value, StatusReservasi::Dikonfirmasi->value])
                    ->whereNull('PengingatTerkirimPada')
                    ->whereBetween('MulaiPada', [now()->addHours(20), now()->addHours(28)])
                    ->pluck('Id');

                foreach ($id as $idReservasi) {
                    KirimPengingatReservasiTugas::dispatch($idTenant, (int) $idReservasi);
                    $antre++;
                }
            }
        } finally {
            $sebelumnya === null ? $konteks->Kosongkan() : $konteks->Atur($sebelumnya);
        }

        $this->line(count($daftar)." tenant diperiksa, {$antre} pengingat reservasi diantrekan.");

        return self::SUCCESS;
    }
}
