<?php

declare(strict_types=1);

namespace App\Domain\Karyawan\Layanan;

use App\Domain\Bersama\Sinkron\Data\DataKonteksSinkron;
use App\Domain\Bersama\Sinkron\Enum\StatusItemSinkron;
use App\Domain\Bersama\Sinkron\Kontrak\PenanganItemSinkron;
use App\Domain\Karyawan\Aksi\CatatAbsensiKeluarPos;
use App\Domain\Karyawan\Aksi\CatatAbsensiMasukPos;
use App\Domain\Karyawan\Data\DataAbsensiPos;
use App\Domain\Kasir\Layanan\ValidasiItemSinkron;

/**
 * Item outbox absensi (F-18): `Absensi.Masuk` `{UuidPengguna, MasukPada, Swafoto?}` (Uuid item = Uuid absensi) dan
 * `Absensi.Keluar` `{UuidAbsensi, UuidPengguna, KeluarPada, Swafoto?}`. `Swafoto` JPEG base64, null = tanpa kamera.
 */
final class PenanganSinkronAbsensi implements PenanganItemSinkron
{
    public const JENIS_MASUK = 'Absensi.Masuk';

    public const JENIS_KELUAR = 'Absensi.Keluar';

    public function __construct(
        private readonly string $jenis,
        private readonly CatatAbsensiMasukPos $masuk,
        private readonly CatatAbsensiKeluarPos $keluar,
    ) {}

    public function AmbilJenis(): string
    {
        return $this->jenis;
    }

    public function Proses(string $uuid, array $data, DataKonteksSinkron $konteks): StatusItemSinkron
    {
        $kolomWaktu = $this->jenis === self::JENIS_MASUK ? 'MasukPada' : 'KeluarPada';
        $valid = ValidasiItemSinkron::Validasi(['Uuid' => $uuid, ...$data], [
            'Uuid' => ['required', 'string', 'ulid'],
            'UuidPengguna' => ['required', 'string', 'ulid'],
            $kolomWaktu => ['required', 'string', 'regex:'.ValidasiItemSinkron::POLA_WAKTU, 'date'],
            'Swafoto' => ['sometimes', 'nullable', 'string', 'max:'.((int) config('karyawan.UkuranMaksimalSwafotoKb') * 1400)],
            ...($this->jenis === self::JENIS_KELUAR ? ['UuidAbsensi' => ['required', 'string', 'ulid']] : []),
        ]);
        $swafoto = isset($valid['Swafoto']) && $valid['Swafoto'] !== '' ? (string) $valid['Swafoto'] : null;
        $isian = new DataAbsensiPos(
            uuid: strtoupper($uuid),
            idTenant: $konteks->idTenant,
            idOutlet: $konteks->idOutlet,
            idPerangkat: $konteks->idPerangkat,
            uuidPengguna: strtoupper((string) $valid['UuidPengguna']),
            waktu: ValidasiItemSinkron::AmbilWaktu((string) $valid[$kolomWaktu]),
            swafoto: $swafoto,
            uuidAbsensi: isset($valid['UuidAbsensi']) ? strtoupper((string) $valid['UuidAbsensi']) : null,
        );

        return $this->jenis === self::JENIS_MASUK ? $this->masuk->Jalankan($isian) : $this->keluar->Jalankan($isian);
    }
}
