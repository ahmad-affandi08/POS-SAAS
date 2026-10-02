<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Enum;

/**
 * Golongan obat (PRD §9.5 Apotek, `Produk.GolonganObat`). Dasar: penandaan obat bebas (lingkaran hijau), bebas terbatas
 * (lingkaran biru), dan keras (huruf K, lingkaran merah) menurut PMK 73/2016 & ketentuan BPOM; psikotropika dan
 * narkotika menurut UU 35/2009 dan PMK 3/2015 (peredaran, penyimpanan, pemusnahan, pelaporan).
 *
 * Wajib resep: obat keras (kecuali Obat Wajib Apotek), psikotropika, dan narkotika. Obat Wajib Apotek (OWA) adalah obat
 * keras yang boleh diserahkan apoteker tanpa resep, tetap dicatat. Psikotropika & narkotika selalu dengan resep, tidak
 * pernah OWA.
 */
enum GolonganObat: string
{
    case Bebas = 'Bebas';
    case BebasTerbatas = 'BebasTerbatas';
    case Keras = 'Keras';
    case Psikotropika = 'Psikotropika';
    case Narkotika = 'Narkotika';

    public function AmbilLabel(): string
    {
        return match ($this) {
            self::Bebas => 'Obat bebas',
            self::BebasTerbatas => 'Obat bebas terbatas',
            self::Keras => 'Obat keras',
            self::Psikotropika => 'Psikotropika',
            self::Narkotika => 'Narkotika',
        };
    }

    /** OWA hanya bermakna untuk obat keras; golongan lain selalu dianggap bukan OWA. */
    public function CekBolehObatWajibApotek(): bool
    {
        return $this === self::Keras;
    }

    /** Wajib resep dokter: keras (kecuali OWA), psikotropika, narkotika. */
    public function CekWajibResep(bool $obatWajibApotek): bool
    {
        return match ($this) {
            self::Psikotropika, self::Narkotika => true,
            self::Keras => ! $obatWajibApotek,
            default => false,
        };
    }

    /** Hanya boleh diserahkan apoteker (izin `apotek.obat-keras.jual`): semua obat keras termasuk OWA, psikotropika, narkotika. */
    public function CekWajibApoteker(): bool
    {
        return in_array($this, [self::Keras, self::Psikotropika, self::Narkotika], true);
    }

    /** Golongan yang datanya dipakai untuk data pendukung pelaporan SIPNAP (psikotropika & narkotika). */
    public function CekDilaporkanSipnap(): bool
    {
        return in_array($this, [self::Psikotropika, self::Narkotika], true);
    }

    /**
     * @return list<array{Nilai: string, Label: string}>
     */
    public static function AmbilOpsi(): array
    {
        return array_map(fn (self $g): array => ['Nilai' => $g->value, 'Label' => $g->AmbilLabel()], self::cases());
    }

    /** Wajib resep dari nilai mentah (null = bukan obat). */
    public static function HitungWajibResep(?self $golongan, bool $obatWajibApotek): bool
    {
        return $golongan !== null && $golongan->CekWajibResep($obatWajibApotek);
    }
}
