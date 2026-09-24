<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Impor\Layanan;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Katalog\Data\DataSaringProduk;
use App\Domain\Katalog\Impor\Kueri\DataEksporProduk;
use Generator;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Ekspor daftar produk (F-03) ke xlsx/csv dengan saringan aktif, dialirkan per 500 produk; kolom = templat impor
 * (bisa diimpor kembali). Audit `produk.ekspor` (format & saringan). Juga templat impor kosong (judul kolom saja).
 */
final class PenulisEksporProduk
{
    public function __construct(
        private readonly KonteksTenant $konteks,
        private readonly DataEksporProduk $data,
        private readonly PencatatAudit $audit,
    ) {}

    public function Alirkan(DataSaringProduk $saring, string $format): StreamedResponse
    {
        $idTenant = $this->konteks->Wajib();
        $this->audit->Catat('produk.ekspor', null, null, [
            'Format' => $format,
            'Kata' => $saring->kata,
            'IdKategori' => $saring->idKategori,
            'Jenis' => $saring->jenis?->value,
            'Status' => $saring->status === null ? 'Semua' : $saring->status->value,
        ]);

        return PenulisTabel::Alirkan($format, 'produk-'.now()->format('Ymd-His'), DataEksporProduk::AmbilJudul(), function () use ($idTenant, $saring): Generator {
            // Respons dialirkan setelah kontroler selesai: pastikan scope tenant tetap tenant pengekspor.
            $this->konteks->Atur($idTenant);
            set_time_limit(120);

            yield from $this->data->AmbilBaris($saring);
        });
    }

    public function AlirkanTemplat(string $format): StreamedResponse
    {
        return PenulisTabel::Alirkan($format, 'templat-impor-produk', DataEksporProduk::AmbilJudul(), fn (): array => []);
    }
}
