<?php

declare(strict_types=1);

namespace App\Domain\Pemenuhan\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Pemenuhan\Model\PengaturanReservasi;
use Illuminate\Support\Facades\DB;

/**
 * Simpan pengaturan reservasi (F-07 mode service): reservasi online, konfirmasi otomatis, interval slot 10–120 menit,
 * jeda antar layanan 0–60 menit, batas hari ke depan 1–180, minimal menit sebelum 0–2880, pengingat H-1. Audit
 * `reservasi.pengaturan`.
 *
 * @phpstan-type Masukan array{OnlineAktif: bool, KonfirmasiOtomatis: bool, IntervalSlotMenit: int, JedaMenit: int, BatasHariKeDepan: int, MinimalMenitSebelum: int, PengingatAktif: bool}
 */
final class SimpanPengaturanReservasi
{
    /** @var array<string, array{0: int, 1: int, 2: string}> */
    public const RENTANG = [
        'IntervalSlotMenit' => [10, 120, 'Interval slot 10–120 menit.'],
        'JedaMenit' => [0, 60, 'Jeda antar layanan 0–60 menit.'],
        'BatasHariKeDepan' => [1, 180, 'Batas hari ke depan 1–180 hari.'],
        'MinimalMenitSebelum' => [0, 2880, 'Pemesanan paling cepat 0–2.880 menit sebelumnya.'],
    ];

    public function __construct(private readonly PencatatAudit $audit) {}

    /**
     * @param  Masukan  $data
     */
    public function Jalankan(array $data, int $idPengguna): void
    {
        foreach (self::RENTANG as $kunci => [$min, $maks, $pesan]) {
            if ($data[$kunci] < $min || $data[$kunci] > $maks) {
                throw new PelanggaranAturanBisnis('PengaturanTidakValid', $pesan, $kunci);
            }
        }

        DB::transaction(function () use ($data, $idPengguna): void {
            $p = PengaturanReservasi::query()->lockForUpdate()->first() ?? new PengaturanReservasi;
            $lama = array_intersect_key($p->only(array_keys($data)), $data);
            $p->fill($data);
            $p->save();

            if ($lama != $data) {
                $this->audit->Catat('reservasi.pengaturan', $p, $lama, $data, idPengguna: $idPengguna);
            }
        });
    }
}
