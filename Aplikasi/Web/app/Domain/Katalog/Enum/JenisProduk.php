<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Enum;

/**
 * Jenis produk (PRD §15.3 `Produk.Jenis`, F-03). F-01 hanya membuat Stok, NonStok, dan Jasa (produk awal).
 * Aturan per jenis (DesainF03 C.1) dipakai Aksi katalog, halaman, impor, resep, pilihan, dan paket.
 */
enum JenisProduk: string
{
    case Stok = 'Stok';
    case IndukVarian = 'IndukVarian';
    case Resep = 'Resep';
    case Produksi = 'Produksi';
    case Paket = 'Paket';
    case Jasa = 'Jasa';
    case NonStok = 'NonStok';
    case BahanBaku = 'BahanBaku';
    case Konsinyasi = 'Konsinyasi';

    public function AmbilLabel(): string
    {
        return match ($this) {
            self::Stok => 'Barang stok',
            self::IndukVarian => 'Induk varian',
            self::Resep => 'Resep',
            self::Produksi => 'Produksi',
            self::Paket => 'Paket/bundel',
            self::Jasa => 'Jasa',
            self::NonStok => 'Tanpa stok',
            self::BahanBaku => 'Bahan baku',
            self::Konsinyasi => 'Konsinyasi',
        };
    }

    /** Jenis yang boleh dipakai produk contoh template sektor (P-03) dan produk cepat (F-01). */
    public function CekBolehProdukAwal(): bool
    {
        return in_array($this, [self::Stok, self::NonStok, self::Jasa], true);
    }

    /** Produk ini sendiri punya saldo stok (IndukVarian: anak-anaknya; Resep: bahannya yang dikurangi). */
    public function CekPunyaStok(): bool
    {
        return in_array($this, [self::Stok, self::Produksi, self::BahanBaku, self::Konsinyasi], true);
    }

    /** Bisa dijual di POS (IndukVarian tampil sebagai ubin grup; BahanBaku tidak pernah tampil di POS). */
    public function CekBisaDijual(): bool
    {
        return ! in_array($this, [self::IndukVarian, self::BahanBaku], true);
    }

    /** `Pelacakan` Batch/Seri hanya untuk jenis ini. */
    public function CekBolehPelacakan(): bool
    {
        return $this->CekPunyaStok();
    }

    public function CekBolehResep(): bool
    {
        return in_array($this, [self::Resep, self::Produksi], true);
    }

    /** Punya komponen paket (`PaketProdukDetail`). */
    public function CekBolehKomponen(): bool
    {
        return $this === self::Paket;
    }

    /** Boleh dipasangi kelompok pilihan (modifier). IndukVarian: berlaku untuk semua anak. */
    public function CekBolehPilihan(): bool
    {
        return $this !== self::BahanBaku;
    }

    /** Dihitung ke batas paket `BatasSku` (BR-P04.3, H9). */
    public function CekDihitungBatasSku(): bool
    {
        return $this !== self::IndukVarian;
    }

    /** Boleh menjadi bahan resep atau bahan pilihan. */
    public function CekBolehBahan(): bool
    {
        return in_array($this, [self::BahanBaku, self::Stok, self::Produksi], true);
    }

    /** Boleh menjadi jenis anak varian. */
    public function CekBolehAnakVarian(): bool
    {
        return in_array($this, [self::Stok, self::Produksi, self::Konsinyasi, self::Jasa, self::NonStok, self::Resep], true);
    }

    /** Boleh menjadi komponen sebuah paket (anak varian boleh). */
    public function CekBolehKomponenPaket(): bool
    {
        return $this->CekBisaDijual() && $this !== self::Paket;
    }

    /**
     * Aturan jenis untuk halaman (tipe FE `AturanJenisProduk`).
     *
     * @return array{Nilai: string, Label: string, PunyaStok: bool, BisaDijual: bool, BolehPelacakan: bool, BolehResep: bool, BolehKomponen: bool, BolehPilihan: bool, DihitungBatasSku: bool}
     */
    public function AmbilAturan(): array
    {
        return [
            'Nilai' => $this->value,
            'Label' => $this->AmbilLabel(),
            'PunyaStok' => $this->CekPunyaStok(),
            'BisaDijual' => $this->CekBisaDijual(),
            'BolehPelacakan' => $this->CekBolehPelacakan(),
            'BolehResep' => $this->CekBolehResep(),
            'BolehKomponen' => $this->CekBolehKomponen(),
            'BolehPilihan' => $this->CekBolehPilihan(),
            'DihitungBatasSku' => $this->CekDihitungBatasSku(),
        ];
    }

    /**
     * @return list<array{Nilai: string, Label: string, PunyaStok: bool, BisaDijual: bool, BolehPelacakan: bool, BolehResep: bool, BolehKomponen: bool, BolehPilihan: bool, DihitungBatasSku: bool}>
     */
    public static function AmbilDaftarAturan(): array
    {
        return array_map(fn (self $jenis): array => $jenis->AmbilAturan(), self::cases());
    }
}
