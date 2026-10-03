<?php

declare(strict_types=1);

namespace App\Console\Perintah;

use App\Domain\Lisensi\Enum\EdisiAplikasi;
use App\Domain\Lisensi\Kueri\LisensiBerlaku;
use Illuminate\Console\Command;

/** D-35: menampilkan edisi dan lisensi yang berlaku di server ini. */
final class InfoLisensiPerintah extends Command
{
    protected $signature = 'lisensi:info';

    protected $description = 'Menampilkan edisi aplikasi dan lisensi yang terpasang (D-35).';

    public function handle(LisensiBerlaku $lisensiBerlaku): int
    {
        $this->line('Edisi: '.EdisiAplikasi::AmbilBerjalan()->value);
        $lisensi = $lisensiBerlaku->Ambil();

        if ($lisensi === null) {
            $this->warn('Belum ada lisensi sah yang terpasang.');

            return EdisiAplikasi::CekLisensi() ? self::FAILURE : self::SUCCESS;
        }

        $this->table(['Kolom', 'Nilai'], [
            ['Nomor', $lisensi->nomor],
            ['Pemegang', $lisensi->namaPemegang],
            ['Domain', $lisensi->domain],
            ['Batas outlet', $lisensi->batasOutlet ?? 'tak terbatas'],
            ['Batas perangkat per outlet', $lisensi->batasPerangkatPerOutlet ?? 'tak terbatas'],
            ['Batas pengguna', $lisensi->batasPengguna ?? 'tak terbatas'],
            ['Diterbitkan', $lisensi->diterbitkanPada],
        ]);

        return self::SUCCESS;
    }
}
