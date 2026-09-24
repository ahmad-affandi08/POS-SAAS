<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Harga\Layanan;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Katalog\Harga\Enum\SumberPerubahanHarga;
use App\Domain\Katalog\Harga\Model\RiwayatHarga;
use Illuminate\Contracts\Auth\Factory as PabrikAutentikasi;

/**
 * Menulis satu baris `RiwayatHarga` (BR-03.3) untuk setiap baris harga yang ditambah, diubah, atau dihapus. Dipanggil
 * Aksi harga di transaksi yang sama. Pengubah = pengguna di konteks audit (`PencatatAudit`: back-office, atau job
 * impor yang mengatur konteks pengguna pemicunya); bila kosong, pengguna back-office yang masuk (guard `web`); null
 * untuk proses tanpa pengguna.
 */
final class PencatatRiwayatHarga
{
    public function __construct(
        private readonly PencatatAudit $audit,
        private readonly PabrikAutentikasi $autentikasi,
    ) {}

    public function Catat(
        int $idProduk,
        int $idProdukSatuan,
        int $idSatuan,
        ?int $idDaftarHarga,
        string $jumlahMinimum,
        ?string $hargaLama,
        ?string $hargaBaru,
        SumberPerubahanHarga $sumber,
    ): RiwayatHarga {
        return RiwayatHarga::query()->create([
            'IdProduk' => $idProduk,
            'IdProdukSatuan' => $idProdukSatuan,
            'IdSatuan' => $idSatuan,
            'IdDaftarHarga' => $idDaftarHarga,
            'JumlahMinimum' => $jumlahMinimum,
            'HargaLama' => $hargaLama,
            'HargaBaru' => $hargaBaru,
            'DiubahOleh' => $this->AmbilIdPengubah(),
            'Sumber' => $sumber,
        ]);
    }

    private function AmbilIdPengubah(): ?int
    {
        $id = $this->audit->AmbilIdPengguna() ?? $this->autentikasi->guard('web')->id();

        return is_int($id) ? $id : null;
    }
}
