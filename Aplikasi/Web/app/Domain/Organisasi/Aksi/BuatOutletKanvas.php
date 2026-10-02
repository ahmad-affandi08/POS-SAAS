<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Aksi;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Organisasi\Data\DataOutlet;
use App\Domain\Organisasi\Kueri\OutletUtama;
use App\Domain\Organisasi\Model\Merek;
use App\Domain\Organisasi\Model\Outlet;
use App\Domain\Referensi\Enum\ZonaWaktu;
use Illuminate\Support\Facades\DB;

/**
 * Modul Salesman bagian 3 (§9.7, kanvas): pintasan "Tambah kendaraan kanvas". Kendaraan = outlet bertanda `Kanvas`
 * yang lokasi stok Toko-nya adalah bak kendaraan, sehingga penjualan POS, shift (setoran), dan transfer stok (muat &
 * bongkar) berjalan tanpa perubahan mesin.
 *
 * Seluruh pembuatan diserahkan ke `SimpanOutlet` (batas paket BR-02.1, kode unik BR-02.2, lokasi stok Toko BR-02.4,
 * log audit) — pintasan ini hanya mengisi bawaannya: nama "Kanvas {plat}", kode `KNV{n}` pertama yang belum dipakai
 * (termasuk outlet arsip), dan merek, kota/zona waktu, jam tutup buku, serta profil pajak (PKP, PBJT) dari outlet
 * utama. NITKU tidak disalin karena milik tempat usaha, bukan kendaraan.
 */
final class BuatOutletKanvas
{
    private const AWALAN_KODE = 'KNV';

    /** KNV1 … KNV99 (kode outlet 3–5 karakter). */
    private const NOMOR_KODE_MAKSIMAL = 99;

    public function __construct(
        private readonly SimpanOutlet $simpanOutlet,
        private readonly OutletUtama $outletUtama,
    ) {}

    public function Jalankan(string $nomorKendaraan): Outlet
    {
        return DB::transaction(function () use ($nomorKendaraan): Outlet {
            $acuan = $this->outletUtama->CariAktif($this->outletUtama->AmbilId());
            $uuidMerek = $acuan === null
                ? Merek::query()->orderBy('Id')->value('Uuid')
                : Merek::query()->whereKey($acuan->IdMerek)->value('Uuid');

            if (! is_string($uuidMerek)) {
                throw new PelanggaranAturanBisnis('MerekTidakDitemukan', 'Buat merek dulu di menu Outlet sebelum menambah kendaraan kanvas.', 'Merek');
            }

            $plat = trim((string) preg_replace('/\s+/u', ' ', $nomorKendaraan));

            return $this->simpanOutlet->Jalankan(null, new DataOutlet(
                nama: 'Kanvas '.mb_strtoupper($plat),
                kode: $this->CariKodeBebas(),
                uuidMerek: $uuidMerek,
                alamat: null,
                kodeKota: $acuan?->KodeKota,
                zonaWaktu: self::LabelZonaWaktu($acuan?->ZonaWaktu),
                jamTutupBuku: $acuan === null ? '04:00' : $acuan->JamTutupBuku,
                pkp: ($acuan?->ProfilPajak['Pkp'] ?? false) === true,
                nitku: null,
                pungutPbjt: ($acuan?->ProfilPajak['PungutPbjt'] ?? false) === true,
                kanvas: true,
                nomorKendaraan: $plat,
            ));
        });
    }

    private function CariKodeBebas(): string
    {
        $dipakai = array_flip(array_map(
            fn ($kode): string => mb_strtoupper((string) $kode),
            Outlet::query()->where('Kode', 'like', self::AWALAN_KODE.'%')->pluck('Kode')->all(),
        ));

        for ($nomor = 1; $nomor <= self::NOMOR_KODE_MAKSIMAL; $nomor++) {
            $kode = self::AWALAN_KODE.$nomor;

            if (! isset($dipakai[$kode])) {
                return $kode;
            }
        }

        throw new PelanggaranAturanBisnis('BR-02.2', 'Kode kendaraan kanvas KNV1–KNV99 sudah terpakai semua. Tambah outlet lewat menu Outlet dengan kode lain.', 'Kode');
    }

    /** Zona IANA outlet acuan → WIB/WITA/WIT (isian `DataOutlet`); tanpa acuan = WIB. */
    private static function LabelZonaWaktu(?string $zonaIana): string
    {
        foreach (ZonaWaktu::cases() as $zona) {
            if ($zona->AmbilZonaIana() === $zonaIana) {
                return $zona->value;
            }
        }

        return ZonaWaktu::Wib->value;
    }
}
