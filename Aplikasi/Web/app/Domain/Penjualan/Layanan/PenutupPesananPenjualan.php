<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Layanan;

use App\Domain\Bersama\Dokumen\Layanan\PencatatRiwayatStatus;
use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Penjualan\Enum\JenisMetodePembayaran;
use App\Domain\Penjualan\Enum\StatusPesananPenjualan;
use App\Domain\Penjualan\Model\PenjualanPembayaran;
use App\Domain\Penjualan\Model\PesananPenjualan;
use Carbon\CarbonImmutable;

/**
 * Pengambilan pre-order (F-12 bagian 2) di transaksi DB penjualan: `Tandai` mencatat DP yang dipakai penjualan
 * (metode Uang Muka), status `Diambil`, dan rujukan penjualan; `Batalkan` (void penjualan) mengembalikan DP yang dipakai
 * ke pesanan dan statusnya ke `Siap`. Keadaan yang berubah sejak kasir mengambil (offline) tidak menolak penjualan;
 * masalahnya dikembalikan untuk tinjauan `UangMukaBermasalah`.
 */
final class PenutupPesananPenjualan
{
    public function __construct(private readonly PencatatRiwayatStatus $riwayat) {}

    /**
     * @return array{0: PesananPenjualan|null, 1: list<string>} [pesanan terkunci, masalah]
     */
    public function Cari(?string $uuid, int $idOutlet): array
    {
        if ($uuid === null) {
            return [null, []];
        }

        $pesanan = PesananPenjualan::query()->where('Uuid', $uuid)->lockForUpdate()->first();

        if ($pesanan === null) {
            return [null, ['pesanan pre-order tidak dikenal server']];
        }

        $masalah = [];

        if ($pesanan->IdOutlet !== $idOutlet) {
            $masalah[] = "pre-order {$pesanan->Nomor} milik outlet lain";
        }

        if (! $pesanan->Status->CekTerbuka()) {
            $masalah[] = "pre-order {$pesanan->Nomor} sudah {$pesanan->Status->AmbilLabel()}";
        }

        return [$pesanan, $masalah];
    }

    /**
     * @return list<string> masalah
     */
    public function Tandai(PesananPenjualan $pesanan, int $idPenjualan, Uang $uangMukaDipakai, CarbonImmutable $waktu, int $idPengguna): array
    {
        $masalah = [];
        $sisa = $pesanan->AmbilSisaUangMuka();

        if ($uangMukaDipakai->Bandingkan($sisa) > 0) {
            $masalah[] = "uang muka dipakai {$uangMukaDipakai->FormatRupiah()} melebihi sisa DP {$pesanan->Nomor} {$sisa->FormatRupiah()}";
        }

        $asal = $pesanan->Status;
        $pesanan->UangMukaTerpakai = Uang::Dari($pesanan->UangMukaTerpakai)->Tambah($uangMukaDipakai)->KeString();

        if ($asal->CekTerbuka()) {
            $pesanan->fill(['Status' => StatusPesananPenjualan::Diambil, 'IdPenjualan' => $idPenjualan, 'DiambilPada' => $waktu]);
        }

        $pesanan->save();

        if ($asal !== $pesanan->Status) {
            $this->riwayat->Catat(PesananPenjualan::JENIS_DOKUMEN, $pesanan->Id, $asal->value, $pesanan->Status->value, $idPengguna);
        }

        return $masalah;
    }

    /** Void penjualan pengambilan: DP yang dipakai kembali menjadi sisa pesanan, pesanan bisa diambil ulang. */
    public function Batalkan(int $idPenjualan, int $idPengguna): void
    {
        $dipakai = Uang::Dari((string) PenjualanPembayaran::query()
            ->where('IdPenjualan', $idPenjualan)
            ->where('JenisMetode', JenisMetodePembayaran::UangMuka->value)
            ->sum('Jumlah'));
        $pesanan = PesananPenjualan::query()->where('IdPenjualan', $idPenjualan)->lockForUpdate()->first();

        if ($pesanan === null) {
            return;
        }

        $asal = $pesanan->Status;
        $pesanan->UangMukaTerpakai = Uang::Dari($pesanan->UangMukaTerpakai)->Kurangi($dipakai)->KeString();
        $pesanan->Status = StatusPesananPenjualan::Siap;
        $pesanan->SiapPada ??= $pesanan->DiambilPada;
        $pesanan->IdPenjualan = null;
        $pesanan->DiambilPada = null;
        $pesanan->save();

        if ($asal !== $pesanan->Status) {
            $this->riwayat->Catat(PesananPenjualan::JENIS_DOKUMEN, $pesanan->Id, $asal->value, $pesanan->Status->value, $idPengguna, 'Penjualan pengambilan di-void');
        }
    }
}
