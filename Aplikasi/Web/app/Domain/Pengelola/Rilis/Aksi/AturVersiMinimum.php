<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Rilis\Aksi;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Pengelola\Rilis\Kueri\DampakVersiMinimum;
use App\Domain\Pengelola\TimInternal\Layanan\PencatatAuditPengelola;
use App\Domain\Pengelola\TimInternal\Model\PenggunaPengelola;
use App\Domain\Tenant\Enum\KanalRilis;
use App\Domain\Tenant\Enum\StatusRilis;
use App\Domain\Tenant\Model\RilisAplikasi;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * P-10 langkah 6 (§14.6): menjadikan versi rilis Stabil yang aktif sebagai versi minimum platformnya mulai tanggal
 * tertentu. BR-P10.1: berlaku paling cepat 7 hari setelah diumumkan, kecuali perbaikan keamanan (boleh langsung).
 * BR-P10.2: jumlah perangkat di bawah versi itu (dan yang masih punya outbox tertunda) dicatat di audit; perangkat itu
 * tetap boleh mengirim outbox. Versi minimum yang belum berlaku bisa dibatalkan. Audit `rilis.versi-minimum.atur` /
 * `rilis.versi-minimum.batal`.
 */
final class AturVersiMinimum
{
    public const HARI_PENGUMUMAN = 7;

    private const ZONA_WAKTU = 'Asia/Jakarta';

    public function __construct(
        private readonly DampakVersiMinimum $dampak,
        private readonly PencatatAuditPengelola $audit,
    ) {}

    public function Jalankan(PenggunaPengelola $pelaku, RilisAplikasi $rilis, CarbonImmutable $berlakuPada, bool $perbaikanKeamanan, string $alasan): RilisAplikasi
    {
        $alasan = trim($alasan);

        if ($rilis->Status !== StatusRilis::Aktif || $rilis->Kanal !== KanalRilis::Stabil) {
            throw new PelanggaranAturanBisnis('RilisBukanStabilAktif', 'Versi minimum hanya bisa diambil dari rilis Stabil yang aktif.');
        }

        if (mb_strlen($alasan) < UbahStatusRilis::PANJANG_ALASAN_MINIMAL) {
            throw new PelanggaranAturanBisnis('AlasanWajib', 'Tulis alasan menaikkan versi minimum, minimal '.UbahStatusRilis::PANJANG_ALASAN_MINIMAL.' karakter.', 'Alasan');
        }

        // Tanggal dihitung di WIB (zona kerja tim platform); perbaikan keamanan boleh mulai hari ini (berlaku segera).
        $hariIni = CarbonImmutable::now(self::ZONA_WAKTU)->startOfDay();
        $palingCepat = $perbaikanKeamanan ? $hariIni : $hariIni->addDays(self::HARI_PENGUMUMAN);
        $berlakuPada = $berlakuPada->setTimezone(self::ZONA_WAKTU);

        if ($berlakuPada->lt($palingCepat)) {
            throw new PelanggaranAturanBisnis(
                'PengumumanKurangDariTujuhHari',
                'Versi minimum wajib diumumkan paling lambat '.self::HARI_PENGUMUMAN.' hari sebelumnya. Paling cepat berlaku '.$palingCepat->translatedFormat('j F Y').', kecuali perbaikan keamanan.',
                'BerlakuPada',
            );
        }

        if ($berlakuPada->lt(CarbonImmutable::now())) {
            $berlakuPada = CarbonImmutable::now();
        }

        $dampak = $this->dampak->Hitung($rilis->Platform, $rilis->Versi);

        return DB::transaction(function () use ($pelaku, $rilis, $berlakuPada, $perbaikanKeamanan, $alasan, $dampak): RilisAplikasi {
            $lama = ['VersiMinimum' => $rilis->VersiMinimum, 'VersiMinimumBerlakuPada' => $rilis->VersiMinimumBerlakuPada?->toIso8601String()];
            $rilis->fill(['VersiMinimum' => $rilis->Versi, 'VersiMinimumBerlakuPada' => $berlakuPada, 'PerbaikanKeamanan' => $perbaikanKeamanan])->save();
            $this->audit->Catat('rilis.versi-minimum.atur', $rilis, nilaiLama: $lama, nilaiBaru: [
                'VersiMinimum' => $rilis->Versi,
                'BerlakuPada' => $berlakuPada->toIso8601String(),
                'PerbaikanKeamanan' => $perbaikanKeamanan,
                ...$dampak,
            ], alasan: $alasan, idPelaku: $pelaku->Id);

            return $rilis;
        });
    }

    public function Batalkan(PenggunaPengelola $pelaku, RilisAplikasi $rilis): RilisAplikasi
    {
        if ($rilis->VersiMinimum === null) {
            throw new PelanggaranAturanBisnis('VersiMinimumTidakAda', 'Rilis ini tidak menaikkan versi minimum.');
        }

        if ($rilis->VersiMinimumBerlakuPada !== null && $rilis->VersiMinimumBerlakuPada->lte(now())) {
            throw new PelanggaranAturanBisnis('VersiMinimumSudahBerlaku', 'Versi minimum sudah berlaku dan tidak bisa dibatalkan. Perangkat lama tetap boleh mengirim outbox.');
        }

        return DB::transaction(function () use ($pelaku, $rilis): RilisAplikasi {
            $lama = ['VersiMinimum' => $rilis->VersiMinimum, 'VersiMinimumBerlakuPada' => $rilis->VersiMinimumBerlakuPada?->toIso8601String()];
            $rilis->fill(['VersiMinimum' => null, 'VersiMinimumBerlakuPada' => null, 'PerbaikanKeamanan' => false])->save();
            $this->audit->Catat('rilis.versi-minimum.batal', $rilis, nilaiLama: $lama, idPelaku: $pelaku->Id);

            return $rilis;
        });
    }
}
