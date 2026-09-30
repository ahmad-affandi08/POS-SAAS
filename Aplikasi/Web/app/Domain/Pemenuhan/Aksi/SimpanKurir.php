<?php

declare(strict_types=1);

namespace App\Domain\Pemenuhan\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Pelanggan\Layanan\NomorHp;
use App\Domain\Pemenuhan\Model\Kurir;

final class SimpanKurir
{
    public function __construct(private readonly PencatatAudit $audit) {}

    /** @param array<string, mixed> $data */
    public function Jalankan(array $data, int $idPengguna, ?Kurir $kurir = null): Kurir
    {
        $noHp = isset($data['NoHp']) && trim((string) $data['NoHp']) !== '' ? NomorHp::Normalisasi((string) $data['NoHp']) : null;
        if (isset($data['NoHp']) && trim((string) $data['NoHp']) !== '' && $noHp === null) {
            throw new PelanggaranAturanBisnis('NomorHpTidakValid', 'Nomor HP kurir tidak valid.', 'NoHp');
        }
        $baris = $kurir ?? new Kurir;
        $lama = $kurir?->only(['Nama', 'Jenis', 'NamaPenyedia', 'Status']);
        $baris->fill([...$data, 'NoHp' => $noHp])->save();
        $this->audit->Catat($kurir === null ? 'kurir.buat' : 'kurir.ubah', $baris, $lama, $baris->only(['Nama', 'Jenis', 'NamaPenyedia', 'Status']), idPengguna: $idPengguna);

        return $baris;
    }
}
