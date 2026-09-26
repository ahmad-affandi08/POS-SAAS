<?php

declare(strict_types=1);

namespace App\Domain\Pemenuhan\Layanan;

use App\Domain\Bersama\Tindakan\Data\DataButirTindakan;
use App\Domain\Bersama\Tindakan\Data\DataKonteksTindakan;
use App\Domain\Bersama\Tindakan\Data\DataRincianTindakan;
use App\Domain\Bersama\Tindakan\Enum\TingkatTindakan;
use App\Domain\Bersama\Tindakan\Kontrak\PenyediaTindakan;
use App\Domain\Pemenuhan\Enum\StatusReservasi;
use App\Domain\Pemenuhan\Model\Reservasi;

/**
 * Kotak Tindakan untuk reservasi (F-07 mode service, izin `reservasi.kelola`, dibatasi outlet akses): reservasi
 * online yang menunggu konfirmasi toko dan belum lewat jamnya. Selesai sendiri saat dikonfirmasi/dibatalkan.
 */
final class PenyediaTindakanReservasi implements PenyediaTindakan
{
    public function Kumpulkan(DataKonteksTindakan $konteks): array
    {
        if (! $konteks->CekIzin('reservasi.kelola')) {
            return [];
        }

        $kueri = Reservasi::query()
            ->where('Status', StatusReservasi::Menunggu->value)
            ->where('MulaiPada', '>', now())
            ->when($konteks->idOutletBoleh !== null, fn ($k) => $k->whereIn('IdOutlet', $konteks->idOutletBoleh ?: [0]));
        $jumlah = (clone $kueri)->count();

        return [new DataButirTindakan(
            'reservasi.menunggu-konfirmasi',
            'Reservasi',
            TingkatTindakan::Perhatian,
            'Reservasi online menunggu konfirmasi',
            'Konfirmasi atau tolak agar pelanggan tahu jadwalnya.',
            $jumlah,
            '/kelola/reservasi?saring[Status]=Menunggu',
            'Lihat reservasi',
            $jumlah === 0 ? [] : array_values($kueri->orderBy('MulaiPada')->limit(DataButirTindakan::BATAS_RINCIAN)->get()->map(
                fn (Reservasi $r): DataRincianTindakan => new DataRincianTindakan($r->Uuid, $r->Nomor, $r->NamaPelanggan, $r->MulaiPada->toDateString(), '/kelola/reservasi?cari='.rawurlencode($r->Nomor)),
            )->all()),
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
