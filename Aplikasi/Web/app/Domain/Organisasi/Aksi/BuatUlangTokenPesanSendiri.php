<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Organisasi\Layanan\PenyediaTokenPesanSendiri;
use App\Domain\Organisasi\Model\Meja;
use Illuminate\Support\Facades\DB;

/**
 * F-17: "Buat ulang QR" meja. Token lama langsung tidak berlaku (QR tercetak lama menampilkan halaman tidak ditemukan),
 * misal setelah QR difoto dan disalahgunakan dari luar restoran. Pesanan yang sudah masuk tidak terpengaruh.
 * Audit `meja.token-pesan-sendiri.buat-ulang` (token tidak dicatat).
 */
final class BuatUlangTokenPesanSendiri
{
    public function __construct(private readonly PencatatAudit $audit) {}

    public function Jalankan(Meja $meja): Meja
    {
        return DB::transaction(function () use ($meja): Meja {
            $meja = Meja::query()->lockForUpdate()->findOrFail($meja->Id);
            $adaLama = $meja->TokenPesanSendiri !== null;
            $meja->forceFill(['TokenPesanSendiri' => PenyediaTokenPesanSendiri::BuatToken()])->save();
            $this->audit->Catat('meja.token-pesan-sendiri.buat-ulang', $meja, nilaiLama: ['AdaToken' => $adaLama], nilaiBaru: ['AdaToken' => true]);

            return $meja;
        });
    }
}
