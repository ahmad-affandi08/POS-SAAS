<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Penjualan\Enum\JenisPemenuhanOnline;
use App\Domain\Penjualan\Enum\StatusPenjualan;
use App\Domain\Penjualan\Enum\StatusPesananOnline;
use App\Domain\Penjualan\Model\Penjualan;
use App\Domain\Penjualan\Model\PesananOnline;
use Illuminate\Support\Facades\DB;

final class TautkanPenjualanPesananOnline
{
    public function __construct(private readonly PencatatAudit $audit) {}

    public function Jalankan(int $idOutlet, string $uuidPesanan, string $uuidPenjualan): PesananOnline
    {
        $pesanan = PesananOnline::query()->where('Uuid', $uuidPesanan)->where('IdOutlet', $idOutlet)->firstOrFail();
        $penjualan = Penjualan::query()->where('Uuid', $uuidPenjualan)->where('IdOutlet', $idOutlet)->firstOrFail();
        if ($penjualan->Status !== StatusPenjualan::Lunas) {
            throw new PelanggaranAturanBisnis('PenjualanBelumLunas', 'Penjualan POS belum lunas.', 'UuidPenjualan');
        }
        if ($pesanan->IdPenjualan !== null && $pesanan->IdPenjualan !== $penjualan->Id) {
            throw new PelanggaranAturanBisnis('SudahDitautkan', 'Pesanan sudah ditautkan ke penjualan lain.', 'UuidPenjualan', 409);
        }
        if ($pesanan->IdPenjualan === $penjualan->Id) {
            return $pesanan;
        }
        if ($pesanan->JenisPemenuhan === JenisPemenuhanOnline::AmbilSendiri && $pesanan->Status !== StatusPesananOnline::Siap) {
            throw new PelanggaranAturanBisnis('PesananBelumSiap', 'Pesanan ambil sendiri harus berstatus Siap sebelum ditagihkan.', 'UuidPesananOnline', 409);
        }

        DB::transaction(function () use ($pesanan, $penjualan): void {
            $pesanan->IdPenjualan = $penjualan->Id;
            if ($pesanan->JenisPemenuhan === JenisPemenuhanOnline::AmbilSendiri) {
                $pesanan->UbahStatus(StatusPesananOnline::Selesai);
                $pesanan->SelesaiPada = now();
            }
            $pesanan->save();
            $this->audit->Catat('pesanan-online.tautkan-penjualan', $pesanan, ['IdPenjualan' => null], ['IdPenjualan' => $penjualan->Id, 'NomorPenjualan' => $penjualan->Nomor], idPengguna: $penjualan->IdPengguna);
        });

        return $pesanan->refresh();
    }
}
