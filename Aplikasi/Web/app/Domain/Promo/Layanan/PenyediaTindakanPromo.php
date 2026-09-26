<?php

declare(strict_types=1);

namespace App\Domain\Promo\Layanan;

use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Bersama\Tindakan\Data\DataButirTindakan;
use App\Domain\Bersama\Tindakan\Data\DataKonteksTindakan;
use App\Domain\Bersama\Tindakan\Enum\TingkatTindakan;
use App\Domain\Bersama\Tindakan\Kontrak\PenyediaTindakan;
use App\Domain\Promo\Enum\StatusKlaimPromo;
use App\Domain\Promo\Model\KlaimPromoPemasok;

/**
 * Kotak Tindakan domain Promo (D-23 C, izin lihat `pelanggan.lihat`): klaim promo ke pemasok yang masih terbuka lebih
 * dari [HARI_KLAIM] hari (selesai sendiri saat diterima/dipotong dari hutang).
 */
final class PenyediaTindakanPromo implements PenyediaTindakan
{
    public const HARI_KLAIM = 30;

    public function Kumpulkan(DataKonteksTindakan $konteks): array
    {
        if (! $konteks->CekIzin('pelanggan.lihat')) {
            return [];
        }

        $kueri = KlaimPromoPemasok::query()
            ->where('Status', StatusKlaimPromo::Terbuka->value)
            ->where('TanggalBisnis', '<', $konteks->hariIni->subDays(self::HARI_KLAIM)->toDateString())
            ->when($konteks->idOutletBoleh !== null, fn ($k) => $k->whereIn('IdOutlet', $konteks->idOutletBoleh));
        $jumlah = (clone $kueri)->count();
        $total = Uang::Dari((string) ((clone $kueri)->sum('Jumlah') ?: '0'));

        return [new DataButirTindakan(
            'klaim-pemasok.terbuka-lama',
            'Promo',
            TingkatTindakan::Info,
            'Klaim promo ke pemasok belum diterima',
            'Terbuka lebih dari '.self::HARI_KLAIM.' hari, total '.$total->FormatRupiah().'. Tagih pemasok atau potong dari hutang.',
            $jumlah,
            '/kelola/promo/klaim-pemasok',
            'Lihat klaim',
        )];
    }

    public function AmbilJenisDokumen(): array
    {
        return [];
    }

    public function SaringDokumen(string $jenisDokumen, array $uuid): array
    {
        return [];
    }
}
