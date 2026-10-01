<?php

declare(strict_types=1);

namespace App\Domain\Pelanggan\Aksi;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Pelanggan\Enum\StatusPelanggan;
use App\Domain\Pelanggan\Layanan\NomorHp;
use App\Domain\Pelanggan\Layanan\SandiAkunOnline;
use App\Domain\Pelanggan\Model\KodeMasukPelanggan;
use App\Domain\Pelanggan\Model\Pelanggan;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * F-17 bagian 3: periksa kode masuk WhatsApp. Hanya kode terbaru yang belum dipakai, belum lewat 5 menit, dan belum
 * salah 5 kali yang sah; percobaan kelima yang salah menghanguskannya.
 *
 * Hasil: nomor yang sudah menjadi pelanggan aktif toko → `Pelanggan` (nomornya ditandai terverifikasi). Nomor baru →
 * `TokenDaftar` 15 menit untuk melengkapi nama (`DaftarkanPelangganOnline`); pelanggan baru **tidak** dibuat sebelum
 * pembeli mengisi nama dan menyetujui pemakaian datanya. Pelanggan yang diarsipkan toko tidak bisa masuk.
 */
final class VerifikasiKodeMasukPelanggan
{
    public const BATAS_PERCOBAAN = 5;

    public const MENIT_TOKEN_DAFTAR = 15;

    /**
     * @return array{Pelanggan: Pelanggan|null, TokenDaftar: string|null}
     */
    public function Jalankan(string $noHpMasukan, string $kode): array
    {
        $noHp = NomorHp::Normalisasi($noHpMasukan)
            ?? throw new PelanggaranAturanBisnis('NoHpTidakValid', 'Nomor WhatsApp tidak valid.', 'NoHp');
        $hashNoHp = SandiAkunOnline::BuatHash($noHp);
        $sekarang = CarbonImmutable::now();

        // Percobaan yang salah harus tersimpan walau permintaannya ditolak, jadi pemeriksaan kode punya transaksinya
        // sendiri dan galatnya dilempar sesudahnya.
        [$benar, $sisa] = DB::transaction(function () use ($hashNoHp, $kode, $sekarang): array {
            $baris = KodeMasukPelanggan::query()
                ->where('HashNoHp', $hashNoHp)
                ->whereNull('DipakaiPada')
                ->where('KedaluwarsaPada', '>', $sekarang)
                ->where('Percobaan', '<', self::BATAS_PERCOBAAN)
                ->orderByDesc('Id')
                ->lockForUpdate()
                ->first();

            if ($baris === null) {
                return [null, 0];
            }

            if (preg_match('/^\d{6}$/', $kode) === 1 && hash_equals($baris->HashKode, SandiAkunOnline::BuatHash($kode))) {
                $baris->forceFill(['DipakaiPada' => $sekarang])->save();

                return [$baris, 0];
            }

            $percobaan = $baris->Percobaan + 1;
            $baris->forceFill([
                'Percobaan' => $percobaan,
                ...($percobaan >= self::BATAS_PERCOBAAN ? ['KedaluwarsaPada' => $sekarang] : []),
            ])->save();

            return [false, self::BATAS_PERCOBAAN - $baris->Percobaan];
        });

        if ($benar === null) {
            throw new PelanggaranAturanBisnis('KodeTidakBerlaku', 'Kode sudah tidak berlaku. Minta kode baru.', 'Kode');
        }

        if ($benar === false && $sisa === 0) {
            throw new PelanggaranAturanBisnis('KodeTidakBerlaku', 'Kode salah terlalu sering. Minta kode baru.', 'Kode');
        }

        if ($benar === false) {
            throw new PelanggaranAturanBisnis('KodeSalah', "Kode salah. Sisa {$sisa} kali percobaan.", 'Kode');
        }

        return DB::transaction(function () use ($benar, $noHp, $sekarang): array {
            $pelanggan = Pelanggan::query()->where('NoHp', $noHp)->lockForUpdate()->first();

            if ($pelanggan === null) {
                $token = SandiAkunOnline::BuatToken();
                $benar->forceFill([
                    'HashTokenDaftar' => SandiAkunOnline::BuatHash($token),
                    'TokenDaftarKedaluwarsaPada' => $sekarang->addMinutes(self::MENIT_TOKEN_DAFTAR),
                ])->save();

                return ['Pelanggan' => null, 'TokenDaftar' => $token];
            }

            if ($pelanggan->Status !== StatusPelanggan::Aktif) {
                throw new PelanggaranAturanBisnis('AkunTidakAktif', 'Nomor ini tidak bisa dipakai masuk di toko ini. Silakan hubungi toko.', 'NoHp', 403);
            }

            if ($pelanggan->NoHpTerverifikasiPada === null) {
                $pelanggan->forceFill(['NoHpTerverifikasiPada' => $sekarang])->save();
            }

            return ['Pelanggan' => $pelanggan, 'TokenDaftar' => null];
        });
    }
}
