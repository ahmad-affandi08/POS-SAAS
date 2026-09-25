<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Enum;

/**
 * Kode alasan tinjauan dokumen penjualan/retur POS (F-07b, F-09, PRD v1.46). `AlasanTinjauan` disimpan sebagai
 * `Kode: keterangan; Kode: keterangan`; halaman web menampilkan label manusiawi dari kode ini, bukan kode mesin.
 */
enum KodeAlasanTinjauan: string
{
    case StokTidakCukup = 'StokTidakCukup';
    case PilihanTidakDikenal = 'PilihanTidakDikenal';
    case ProdukDihapus = 'ProdukDihapus';
    case ShiftSudahDitutup = 'ShiftSudahDitutup';
    case IzinBerubah = 'IzinBerubah';
    case DiskonMelebihiBatas = 'DiskonMelebihiBatas';
    case PengaturanBerbeda = 'PengaturanBerbeda';
    case PajakBerbeda = 'PajakBerbeda';
    case LokasiRusakTidakAda = 'LokasiRusakTidakAda';

    public function AmbilLabel(): string
    {
        return match ($this) {
            self::StokTidakCukup => 'Stok tidak cukup saat penjualan diterima',
            self::PilihanTidakDikenal => 'Pilihan produk sudah dihapus',
            self::ProdukDihapus => 'Produk sudah dihapus',
            self::ShiftSudahDitutup => 'Diterima setelah shift ditutup',
            self::IzinBerubah => 'Izin atau outlet kasir berubah',
            self::DiskonMelebihiBatas => 'Diskon melebihi batas yang berlaku',
            self::PengaturanBerbeda => 'Pengaturan kasir di perangkat berbeda',
            self::PajakBerbeda => 'Pajak di perangkat berbeda dengan pengaturan pajak',
            self::LokasiRusakTidakAda => 'Lokasi stok Rusak belum ada',
        };
    }

    /**
     * Urai `AlasanTinjauan` menjadi daftar alasan. Bagian tanpa kode dikenal tetap ditampilkan dengan label umum.
     *
     * @return list<array{Kode: string|null, Label: string, Keterangan: string}>
     */
    public static function Urai(?string $alasan): array
    {
        if ($alasan === null || trim($alasan) === '') {
            return [];
        }

        $hasil = [];

        foreach (preg_split('/;\s*(?=[A-Z][A-Za-z]+:)/', $alasan) ?: [] as $bagian) {
            $bagian = trim($bagian);

            if ($bagian === '') {
                continue;
            }

            $kode = preg_match('/^([A-Z][A-Za-z]+):\s*(.*)$/s', $bagian, $cocok) === 1 ? self::tryFrom($cocok[1]) : null;
            $hasil[] = $kode === null
                ? ['Kode' => null, 'Label' => 'Perlu diperiksa', 'Keterangan' => $bagian]
                : ['Kode' => $kode->value, 'Label' => $kode->AmbilLabel(), 'Keterangan' => trim($cocok[2])];
        }

        return $hasil;
    }
}
