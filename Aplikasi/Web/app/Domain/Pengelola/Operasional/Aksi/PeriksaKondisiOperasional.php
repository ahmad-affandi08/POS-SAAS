<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Operasional\Aksi;

use App\Domain\Pengelola\Operasional\Enum\JenisAlertOperasional;
use App\Domain\Pengelola\Operasional\Kueri\KondisiOperasional;
use App\Domain\Pengelola\Operasional\Kueri\PenerimaSurelPeran;
use App\Domain\Pengelola\Operasional\Model\AlertOperasional;
use App\Domain\Pengelola\Operasional\Surel\AlertOperasionalTerbuka;
use App\Domain\Pengelola\TimInternal\Enum\PeranPengelolaBawaan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * Pemeriksa alert operasional (P-11, BR-P11.1). Setiap masalah membuka satu insiden `AlertOperasional` dan email ke
 * Teknis aktif (bila tidak ada, ke Super Admin) dikirim **sekali per insiden**; insiden ditutup saat kondisi pulih.
 *
 * Dijalankan oleh detak scheduler tiap menit dan oleh `/sehat` (uptime monitor eksternal §20.3), sehingga scheduler
 * yang mati tetap terdeteksi. Email dikirim langsung (bukan antrean), karena antrean ikut berhenti bila scheduler mati.
 */
final class PeriksaKondisiOperasional
{
    private const KUNCI_KUNCI = 'operasional:periksa-kondisi';

    public function __construct(
        private readonly KondisiOperasional $kondisi,
        private readonly PenerimaSurelPeran $penerima,
    ) {}

    /**
     * @return list<string> Kunci alert yang sedang aktif setelah pemeriksaan.
     */
    public function Jalankan(): array
    {
        // Mencegah dua pemeriksa (scheduler & /sehat) membuka insiden atau mengirim email ganda.
        return Cache::lock(self::KUNCI_KUNCI, 30)->get(fn (): array => $this->Periksa()) ?? [];
    }

    /**
     * @return list<string>
     */
    private function Periksa(): array
    {
        $masalah = $this->kondisi->AmbilMasalah();
        $aktif = AlertOperasional::query()->whereNull('SelesaiPada')->orderBy('Id')->get()->keyBy(fn (AlertOperasional $alert) => $alert->Kunci->value);

        foreach ($aktif as $kunci => $alert) {
            if (! array_key_exists($kunci, $masalah)) {
                $alert->update(['SelesaiPada' => now()]);
                $aktif->forget($kunci);
            }
        }

        foreach ($masalah as $kunci => $pesan) {
            $jenis = JenisAlertOperasional::from($kunci);
            $alert = $aktif->get($kunci) ?? DB::transaction(fn () => AlertOperasional::query()->create([
                'Kunci' => $jenis,
                'Tingkat' => $jenis->AmbilTingkat(),
                'Pesan' => Str::limit($pesan, 500),
                'MulaiPada' => now(),
            ]));

            // Email yang gagal terkirim dicoba lagi pada pemeriksaan berikutnya; yang sudah terkirim tidak diulang.
            if ($alert->EmailTerkirimPada === null && $this->KirimEmail($alert)) {
                $alert->update(['EmailTerkirimPada' => now()]);
            }
        }

        return array_keys($masalah);
    }

    private function KirimEmail(AlertOperasional $alert): bool
    {
        $penerima = $this->penerima->Ambil(PeranPengelolaBawaan::Teknis, PeranPengelolaBawaan::SuperAdmin);

        if ($penerima === []) {
            return false;
        }

        try {
            foreach ($penerima as $email) {
                Mail::to($email)->send(new AlertOperasionalTerbuka(
                    $alert->Kunci->AmbilLabel(),
                    $alert->Tingkat->value,
                    $alert->Pesan,
                    $alert->MulaiPada->copy()->setTimezone('Asia/Jakarta')->format('d-m-Y H:i').' WIB',
                    route('pengelola.operasional.dasbor'),
                ));
            }

            return true;
        } catch (Throwable $galat) {
            Log::error('Alert operasional gagal terkirim.', ['Kunci' => $alert->Kunci->value, 'Pesan' => Str::limit($galat->getMessage(), 300)]);

            return false;
        }
    }
}
