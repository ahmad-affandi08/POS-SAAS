<?php

declare(strict_types=1);

namespace App\Domain\Pemenuhan\Kueri;

use App\Domain\Karyawan\Kueri\JadwalStafReservasi;
use App\Domain\Katalog\Kueri\LayananReservasi;
use App\Domain\Organisasi\Kueri\ZonaWaktuOutlet;
use App\Domain\Pelanggan\Kueri\IdentitasPelanggan;
use App\Domain\Pelanggan\Layanan\NomorHp;
use App\Domain\Pemenuhan\Model\Reservasi;
use Carbon\CarbonImmutable;

/**
 * F-07 mode service bagian 2: reservasi satu tanggal (zona waktu outlet) di outlet perangkat untuk antrian aplikasi
 * kasir, urut jam mulai. Memuat Uuid produk layanan & Uuid staf (karyawan) agar kasir langsung memasukkan layanan ke
 * keranjang dengan staf yang melayani; Uuid pelanggan bila tertaut.
 */
final class ReservasiPos
{
    public function __construct(
        private readonly ZonaWaktuOutlet $zona,
        private readonly LayananReservasi $layanan,
        private readonly JadwalStafReservasi $staf,
        private readonly IdentitasPelanggan $pelanggan,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function Ambil(int $idOutlet, ?string $tanggal): array
    {
        $zona = $this->zona->Ambil($idOutlet);
        $hari = $tanggal === null ? CarbonImmutable::now($zona)->startOfDay() : CarbonImmutable::parse($tanggal, $zona)->startOfDay();
        $baris = Reservasi::query()
            ->where('IdOutlet', $idOutlet)
            ->where('MulaiPada', '>=', $hari->utc())
            ->where('MulaiPada', '<', $hari->addDay()->utc())
            ->orderBy('MulaiPada')
            ->orderBy('Id')
            ->get();
        $namaLayanan = $this->layanan->AmbilNama(array_values(array_unique($baris->pluck('IdProduk')->all())));
        $uuidLayanan = $this->layanan->AmbilUuid(array_values(array_unique($baris->pluck('IdProduk')->all())));
        $pelanggan = $this->pelanggan->AmbilUntukPos(array_values(array_filter(array_unique($baris->pluck('IdPelanggan')->all()), 'is_int')));
        $staf = $this->staf->AmbilRingkas(array_values(array_filter(array_unique($baris->pluck('IdKaryawan')->all()))));

        return array_values($baris->map(fn (Reservasi $r): array => [
            'Uuid' => $r->Uuid,
            'Nomor' => $r->Nomor,
            'MulaiPada' => $r->MulaiPada->toIso8601ZuluString(),
            'SelesaiPada' => $r->SelesaiPada->toIso8601ZuluString(),
            'NamaPelanggan' => $r->NamaPelanggan,
            'NoHp' => NomorHp::Format($r->NoHp),
            // Pelanggan terdaftar (nomor HP tersamar, tier untuk harga) agar langsung dipilih di keranjang.
            'Pelanggan' => $r->IdPelanggan === null ? null : ($pelanggan[$r->IdPelanggan] ?? null),
            'UuidProduk' => $uuidLayanan[$r->IdProduk] ?? null,
            'NamaLayanan' => $namaLayanan[$r->IdProduk] ?? '',
            'UuidStaf' => $r->IdKaryawan === null ? null : ($staf[$r->IdKaryawan]['Uuid'] ?? null),
            'NamaStaf' => $r->IdKaryawan === null ? null : ($staf[$r->IdKaryawan]['Nama'] ?? null),
            'Status' => $r->Status->value,
            'LabelStatus' => $r->Status->AmbilLabel(),
            'Catatan' => $r->Catatan,
        ])->all());
    }
}
