<?php

declare(strict_types=1);

namespace App\Domain\Pelanggan\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Pelanggan\Enum\StatusPelanggan;
use App\Domain\Pelanggan\Layanan\NomorHp;
use App\Domain\Pelanggan\Layanan\SandiAkunOnline;
use App\Domain\Pelanggan\Model\KodeMasukPelanggan;
use App\Domain\Pelanggan\Model\Pelanggan;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * F-17 bagian 3: pembeli dengan nomor yang baru terverifikasi melengkapi nama → menjadi `Pelanggan` toko yang sama
 * dengan pelanggan kasir (F-16a), nomornya langsung bertanda terverifikasi. Token daftar sekali pakai dan terikat pada
 * nomor yang diverifikasi; nomor lain tidak bisa didaftarkan dengannya.
 *
 * Bila nomor itu ternyata baru saja didaftarkan kasir di antara verifikasi dan daftar, pelanggan yang ada yang dipakai
 * (datanya tidak ditimpa). Audit `pelanggan.daftar-online` menyimpan nomor tersamar (UU PDP).
 */
final class DaftarkanPelangganOnline
{
    public function __construct(private readonly PencatatAudit $audit) {}

    public function Jalankan(string $tokenDaftar, string $noHpMasukan, string $nama, ?string $email, ?CarbonImmutable $tanggalLahir, bool $setujuPemasaran): Pelanggan
    {
        $noHp = NomorHp::Normalisasi($noHpMasukan)
            ?? throw new PelanggaranAturanBisnis('NoHpTidakValid', 'Nomor WhatsApp tidak valid.', 'NoHp');
        $sekarang = CarbonImmutable::now();

        return DB::transaction(function () use ($tokenDaftar, $noHp, $nama, $email, $tanggalLahir, $setujuPemasaran, $sekarang): Pelanggan {
            $kode = KodeMasukPelanggan::query()->where('HashTokenDaftar', SandiAkunOnline::BuatHash($tokenDaftar))->lockForUpdate()->first();

            if ($kode === null || $kode->HashNoHp !== SandiAkunOnline::BuatHash($noHp)
                || $kode->TokenDaftarKedaluwarsaPada === null || $kode->TokenDaftarKedaluwarsaPada->lessThanOrEqualTo($sekarang)) {
                throw new PelanggaranAturanBisnis('TokenDaftarTidakBerlaku', 'Waktu pendaftaran habis. Masukkan nomor WhatsApp lagi untuk menerima kode baru.', 'Umum');
            }

            $kode->forceFill(['HashTokenDaftar' => null, 'TokenDaftarKedaluwarsaPada' => null])->save();
            $ada = Pelanggan::query()->where('NoHp', $noHp)->lockForUpdate()->first();

            if ($ada !== null) {
                if ($ada->Status !== StatusPelanggan::Aktif) {
                    throw new PelanggaranAturanBisnis('AkunTidakAktif', 'Nomor ini tidak bisa dipakai masuk di toko ini. Silakan hubungi toko.', 'NoHp', 403);
                }

                if ($ada->NoHpTerverifikasiPada === null) {
                    $ada->forceFill(['NoHpTerverifikasiPada' => $sekarang])->save();
                }

                return $ada;
            }

            $baru = Pelanggan::query()->create([
                'Nama' => trim($nama),
                'NoHp' => $noHp,
                'NoHpTerverifikasiPada' => $sekarang,
                'Email' => self::Bersihkan($email),
                'TanggalLahir' => $tanggalLahir?->toDateString(),
                'SetujuPemasaran' => $setujuPemasaran,
                'Tag' => ['Toko online'],
            ]);
            $this->audit->Catat('pelanggan.daftar-online', $baru, nilaiBaru: [
                'Nama' => $baru->Nama, 'NoHp' => NomorHp::Samarkan($noHp), 'SetujuPemasaran' => $setujuPemasaran,
            ]);

            return $baru;
        });
    }

    private static function Bersihkan(?string $teks): ?string
    {
        $teks = $teks === null ? '' : trim($teks);

        return $teks === '' ? null : $teks;
    }
}
