<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Layanan;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Sinkron\Data\DataKonteksSinkron;
use App\Domain\Bersama\Sinkron\Kontrak\PenjagaAsalItemSinkron;
use App\Domain\Organisasi\Enum\AlasanPemulihanSinkron;
use App\Domain\Organisasi\Model\ItemSinkronPemulihan;
use App\Domain\Organisasi\Model\Perangkat;
use Carbon\CarbonImmutable;
use Symfony\Component\Uid\Ulid;

/**
 * Audit P0 F-01 (BR-02.3, §18): perangkat asal item outbox.
 *
 * - Tanpa `UuidPerangkatAsal` (atau sama dengan pengirim) = perangkat pengirim.
 * - `UuidPerangkatAsal` lain = perangkat lain di tenant yang sama yang pernah diaktifkan (perangkat fisik diaktifkan
 *   ulang, outbox lamanya dikirim dengan token baru): item dikreditkan ke perangkat asal, tidak diklaim pengirim.
 * - Perangkat asal dicabut: hanya item yang dibuat sebelum `DicabutPada` yang diterima. Waktu dibuat diambil dari ULID
 *   item (jam perangkat), sehingga semua item jalur pemulihan ditandai untuk ditinjau di Kotak Tindakan.
 *
 * Perangkat dicache per permintaan (satu batch sinkron).
 */
final class PenjagaAsalSinkronPerangkat implements PenjagaAsalItemSinkron
{
    /** @var array<int, Perangkat|null> */
    private array $perId = [];

    /** @var array<string, Perangkat|null> */
    private array $perUuid = [];

    public function Tentukan(string $uuidItem, ?string $uuidPerangkatAsal, DataKonteksSinkron $pengirim): DataKonteksSinkron
    {
        $kirim = $this->AmbilDariId($pengirim->idPerangkat);
        $uuidAsal = $uuidPerangkatAsal === null ? null : strtoupper($uuidPerangkatAsal);
        $asal = $uuidAsal === null || $kirim?->Uuid === $uuidAsal ? $kirim : $this->AmbilDariUuid($uuidAsal);

        if ($asal === null || $asal->DiaktifkanPada === null) {
            throw new PelanggaranAturanBisnis('PerangkatAsalTidakDikenal', 'Perangkat pembuat data ini tidak dikenal di usaha ini. Hubungi pemilik usaha.', 'UuidPerangkatAsal');
        }

        $dicabutPada = $asal->DicabutPada?->toImmutable();

        if ($dicabutPada !== null && self::AmbilWaktuDibuat($uuidItem)->greaterThan($dicabutPada)) {
            throw new PelanggaranAturanBisnis(
                'DibuatSetelahDicabut',
                'Data ini dibuat setelah perangkat dicabut ('.$dicabutPada->setTimezone('Asia/Jakarta')->format('d/m/Y H.i').' WIB), jadi tidak diterima.',
                'Uuid',
                403,
            );
        }

        return new DataKonteksSinkron(
            $pengirim->idTenant,
            $asal->Id,
            $asal->IdOutlet,
            $dicabutPada,
            pemulihan: $dicabutPada !== null || $asal->Id !== $pengirim->idPerangkat,
        );
    }

    public function CatatPemulihan(string $uuidItem, string $jenis, DataKonteksSinkron $asal, DataKonteksSinkron $pengirim): void
    {
        if (! $asal->pemulihan) {
            return;
        }

        $sekarang = now();
        ItemSinkronPemulihan::query()->insertOrIgnore([
            'Uuid' => strtoupper($uuidItem),
            'IdTenant' => $asal->idTenant,
            'IdPerangkat' => $asal->idPerangkat,
            'IdPerangkatPengirim' => $pengirim->idPerangkat,
            'IdOutlet' => $asal->idOutlet,
            'Jenis' => mb_substr($jenis, 0, 50),
            'Alasan' => ($asal->dicabutPada !== null ? AlasanPemulihanSinkron::PerangkatDicabut : AlasanPemulihanSinkron::PerangkatAsalBerbeda)->value,
            'DibuatPadaKlien' => self::AmbilWaktuDibuat($uuidItem),
            'DibuatPada' => $sekarang,
            'DiubahPada' => $sekarang,
        ]);
    }

    /** Waktu pembuatan item dari ULID-nya (dibuat di perangkat saat transaksi terjadi). */
    public static function AmbilWaktuDibuat(string $uuidItem): CarbonImmutable
    {
        if (! Ulid::isValid($uuidItem)) {
            throw new PelanggaranAturanBisnis('UuidTidakValid', 'Kode unik data tidak valid.', 'Uuid');
        }

        return CarbonImmutable::instance(Ulid::fromString($uuidItem)->getDateTime());
    }

    private function AmbilDariId(int $id): ?Perangkat
    {
        if (! array_key_exists($id, $this->perId)) {
            $this->Simpan(Perangkat::query()->whereKey($id)->first(), id: $id);
        }

        return $this->perId[$id];
    }

    private function AmbilDariUuid(string $uuid): ?Perangkat
    {
        if (! array_key_exists($uuid, $this->perUuid)) {
            $this->Simpan(Perangkat::query()->where('Uuid', $uuid)->first(), uuid: $uuid);
        }

        return $this->perUuid[$uuid];
    }

    private function Simpan(?Perangkat $perangkat, ?int $id = null, ?string $uuid = null): void
    {
        if ($id !== null) {
            $this->perId[$id] = $perangkat;
        }

        if ($uuid !== null) {
            $this->perUuid[$uuid] = $perangkat;
        }

        if ($perangkat !== null) {
            $this->perId[$perangkat->Id] = $perangkat;
            $this->perUuid[$perangkat->Uuid] = $perangkat;
        }
    }
}
