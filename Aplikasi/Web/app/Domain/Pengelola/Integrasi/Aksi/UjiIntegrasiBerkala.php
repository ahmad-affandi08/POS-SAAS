<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Integrasi\Aksi;

use App\Domain\Pengelola\Integrasi\Enum\JenisIntegrasi;
use App\Domain\Pengelola\Integrasi\Enum\LingkunganIntegrasi;
use App\Domain\Pengelola\Integrasi\Model\KonfigurasiIntegrasi;
use App\Domain\Pengelola\Integrasi\Penguji\PenyaringPesan;
use App\Domain\Pengelola\Integrasi\Surel\IntegrasiGagal;
use App\Domain\Pengelola\TimInternal\Enum\PeranPengelolaBawaan;
use App\Domain\Pengelola\TimInternal\Model\PenggunaPengelola;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * Uji berkala integrasi aktif di lingkungan server ini (P-05 langkah 4, BR-P05.3). Alert email dikirim sekali saat
 * status berubah menjadi gagal, ke Teknis aktif (atau Super Admin bila belum ada Teknis). Banner di Platform
 * Pengelola dibaca dari status yang disimpan, sehingga tetap tampil walau email sendiri yang gagal.
 */
final class UjiIntegrasiBerkala
{
    public function __construct(private readonly UjiKoneksiIntegrasi $uji) {}

    /**
     * @return array{Diuji: int, BaruGagal: int}
     */
    public function Jalankan(): array
    {
        $daftar = KonfigurasiIntegrasi::query()
            ->where('Lingkungan', LingkunganIntegrasi::AmbilSaatIni()->value)
            ->where('Aktif', true)
            ->whereIn('Jenis', array_map(fn (JenisIntegrasi $jenis): string => $jenis->value, JenisIntegrasi::AmbilJenisPlatform()))
            ->orderBy('Id')
            ->get();
        $baruGagal = [];

        foreach ($daftar as $konfigurasi) {
            $hasil = $this->uji->Jalankan($konfigurasi);

            if ($hasil['BaruGagal']) {
                $baruGagal[] = ['Label' => $konfigurasi->Jenis->AmbilLabel(), 'Pesan' => $hasil['Hasil']->pesan];
            }
        }

        if ($baruGagal !== []) {
            $this->KirimAlert($baruGagal);
        }

        return ['Diuji' => $daftar->count(), 'BaruGagal' => count($baruGagal)];
    }

    /**
     * @param  list<array{Label: string, Pesan: string}>  $gagal
     */
    private function KirimAlert(array $gagal): void
    {
        $penerima = self::AmbilPenerima(PeranPengelolaBawaan::Teknis) ?: self::AmbilPenerima(PeranPengelolaBawaan::SuperAdmin);
        $lingkungan = LingkunganIntegrasi::AmbilSaatIni()->value;

        foreach ($penerima as $email) {
            try {
                Mail::to($email)->send(new IntegrasiGagal($gagal, $lingkungan));
            } catch (Throwable $galat) {
                // Integrasi email bisa jadi yang sedang gagal; banner tetap menjadi penanda utama.
                Log::error('Alert integrasi gagal terkirim.', ['Pesan' => Str::limit($galat->getMessage(), PenyaringPesan::PANJANG_MAKSIMAL)]);
            }
        }
    }

    /**
     * @return list<string>
     */
    private static function AmbilPenerima(PeranPengelolaBawaan $peran): array
    {
        return array_values(PenggunaPengelola::query()
            ->where('Aktif', true)
            ->whereHas('Peran', fn (Builder $kueri) => $kueri->where('Kode', $peran->value))
            ->orderBy('Id')
            ->pluck('Email')
            ->all());
    }
}
