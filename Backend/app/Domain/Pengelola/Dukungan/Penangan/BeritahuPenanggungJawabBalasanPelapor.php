<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Dukungan\Penangan;

use App\Domain\Dukungan\Peristiwa\TiketDukunganDibalasPelapor;
use App\Domain\Pengelola\Dukungan\Surel\BalasanPelaporTiketDukungan;
use App\Domain\Pengelola\Operasional\Kueri\PenerimaSurelPeran;
use App\Domain\Pengelola\TimInternal\Enum\PeranPengelolaBawaan;
use App\Domain\Pengelola\TimInternal\Model\PenggunaPengelola;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Mail;

/**
 * P-09: balasan pelapor diberitahukan ke penanggung jawab tiket (bila aktif). Tiket yang dibuka lagi tanpa penanggung
 * jawab aktif diberitahukan ke tim Dukungan.
 */
final class BeritahuPenanggungJawabBalasanPelapor implements ShouldQueue
{
    public bool $afterCommit = true;

    public function __construct(private readonly PenerimaSurelPeran $penerima) {}

    public function handle(TiketDukunganDibalasPelapor $peristiwa): void
    {
        $email = $peristiwa->idPenanggungJawab === null ? null : PenggunaPengelola::query()
            ->whereKey($peristiwa->idPenanggungJawab)
            ->where('Aktif', true)
            ->value('Email');
        $daftarEmail = is_string($email) ? [$email] : ($peristiwa->dibukaLagi
            ? $this->penerima->Ambil(PeranPengelolaBawaan::Dukungan, PeranPengelolaBawaan::SuperAdmin)
            : []);
        $tautan = route('pengelola.dukungan.tiket.tampil', $peristiwa->uuid);

        foreach ($daftarEmail as $alamat) {
            Mail::to($alamat)->send(new BalasanPelaporTiketDukungan($peristiwa->nomor, $peristiwa->judul, $peristiwa->dibukaLagi, $tautan));
        }
    }
}
