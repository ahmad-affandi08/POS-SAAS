<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * §25 no. 16(a): nama peran akun Indonesia `PiutangSettlement` → `PiutangPencairan`, `Waste` → `SusutPersediaan`.
 * Hanya versi template berstatus Draf yang diubah. Versi Terbit/Usang tidak disentuh (BR-P03.4) dan tetap dibaca
 * lewat alias kunci lama.
 */
return new class extends Migration
{
    public const GANTI = ['PiutangSettlement' => 'PiutangPencairan', 'Waste' => 'SusutPersediaan'];

    public function up(): void
    {
        $this->Ganti(self::GANTI);
    }

    public function down(): void
    {
        $this->Ganti(array_flip(self::GANTI));
    }

    /**
     * @param  array<string, string>  $ganti
     */
    private function Ganti(array $ganti): void
    {
        $daftar = DB::table('TemplateSektorVersi')->where('Status', 'Draf')->get(['Id', 'Isi']);

        foreach ($daftar as $baris) {
            $isi = is_string($baris->Isi) ? json_decode($baris->Isi, true) : null;

            if (! is_array($isi) || ! is_array($isi['PemetaanAkun'] ?? null)) {
                continue;
            }

            $pemetaan = [];
            $berubah = false;

            foreach ($isi['PemetaanAkun'] as $kunci => $kode) {
                $kunciBaru = $ganti[$kunci] ?? $kunci;
                $berubah = $berubah || $kunciBaru !== $kunci;

                // Bila kunci baru sudah ada, kunci baru yang menang (sama dengan PeranAkun::NormalisasiPemetaan).
                if ($kunciBaru !== $kunci && array_key_exists($kunciBaru, $isi['PemetaanAkun'])) {
                    continue;
                }

                $pemetaan[$kunciBaru] = $kode;
            }

            if ($berubah) {
                $isi['PemetaanAkun'] = $pemetaan;
                DB::table('TemplateSektorVersi')->where('Id', $baris->Id)->update(['Isi' => json_encode($isi, JSON_UNESCAPED_UNICODE)]);
            }
        }
    }
};
