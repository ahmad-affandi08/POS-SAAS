<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\TimInternal\Enum;

/**
 * Tujuh peran internal bawaan (P-01 langkah 2, PRD §19.3). Nilai = kolom `PeranPengelola.Kode`.
 *
 * Super Admin memegang semua izin ("semua menu pengelola"). Izin peran lain ditambahkan bersama flow
 * yang menjadi cakupannya. P-02: Konten & Legal mengajukan, Keuangan meninjau data master regulasi.
 * P-04: Keuangan menyusun paket & mengusulkan harga, Super Admin menyetujui.
 */
enum PeranPengelolaBawaan: string
{
    case SuperAdmin = 'SuperAdmin';
    case Keuangan = 'Keuangan';
    case Dukungan = 'Dukungan';
    case Teknis = 'Teknis';
    case KontenLegal = 'KontenLegal';
    case MitraPenjualan = 'MitraPenjualan';
    case Analis = 'Analis';

    public function AmbilNama(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Admin',
            self::Keuangan => 'Keuangan',
            self::Dukungan => 'Dukungan',
            self::Teknis => 'Teknis',
            self::KontenLegal => 'Konten & Legal',
            self::MitraPenjualan => 'Mitra & Penjualan',
            self::Analis => 'Analis',
        };
    }

    /**
     * @return list<IzinPengelola>
     */
    public function AmbilIzin(): array
    {
        return match ($this) {
            self::SuperAdmin => IzinPengelola::cases(),
            self::KontenLegal => [
                IzinPengelola::ReferensiLihat,
                IzinPengelola::ReferensiWilayahKelola,
                IzinPengelola::ReferensiBankKelola,
                IzinPengelola::ReferensiSatuanKelola,
                IzinPengelola::ReferensiTarifPajakAjukan,
                IzinPengelola::ReferensiHariLiburAjukan,
                IzinPengelola::KatalogLihat,
            ],
            self::Keuangan => [
                IzinPengelola::ReferensiLihat,
                IzinPengelola::ReferensiTarifPajakSetujui,
                IzinPengelola::ReferensiHariLiburSetujui,
                // §19.3: Keuangan mengusulkan paket & harga; persetujuan oleh Super Admin (BR-P04.5).
                IzinPengelola::KatalogLihat,
                IzinPengelola::KatalogPaketAjukan,
                IzinPengelola::KatalogAddonKelola,
                IzinPengelola::KatalogKuponKelola,
            ],
            self::Dukungan, self::Teknis, self::MitraPenjualan, self::Analis => [IzinPengelola::ReferensiLihat, IzinPengelola::KatalogLihat],
        };
    }
}
