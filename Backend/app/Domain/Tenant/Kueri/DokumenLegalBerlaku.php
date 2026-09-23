<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Kueri;

use App\Domain\Tenant\Enum\JenisDokumenLegal;
use App\Domain\Tenant\Enum\StatusDokumenLegal;
use App\Domain\Tenant\Model\DokumenLegal;
use Carbon\CarbonInterface;

/**
 * Versi dokumen legal yang berlaku pada satu tanggal (P-06, BR-P06.2, BR-P06.4). Dipakai registrasi F-00 untuk
 * menampilkan dan mencatat versi yang disetujui.
 */
final class DokumenLegalBerlaku
{
    public function Cari(JenisDokumenLegal $jenis, CarbonInterface $tanggal): ?DokumenLegal
    {
        return DokumenLegal::query()
            ->where('Jenis', $jenis->value)
            ->where('Status', StatusDokumenLegal::Terbit->value)
            ->whereDate('BerlakuMulai', '<=', $tanggal->copy()->setTimezone('Asia/Jakarta')->toDateString())
            ->orderByDesc('BerlakuMulai')
            ->first();
    }

    /**
     * Satu versi yang sudah terbit, termasuk yang masih terjadwal, agar Owner bisa membaca versi yang diumumkan
     * sebelum berlaku (BR-P06.5). Draf tidak pernah tampil (BR-P06.4).
     */
    public function CariVersiTerbit(JenisDokumenLegal $jenis, int $versi): ?DokumenLegal
    {
        return DokumenLegal::query()
            ->where('Jenis', $jenis->value)
            ->where('Versi', $versi)
            ->where('Status', StatusDokumenLegal::Terbit->value)
            ->first();
    }

    /**
     * BR-P06.2: registrasi tenant hanya dibuka bila S&K dan Kebijakan Privasi sudah berlaku.
     *
     * @return list<JenisDokumenLegal> Jenis wajib yang belum berlaku (kosong = siap).
     */
    public function AmbilKekuranganRegistrasi(CarbonInterface $tanggal): array
    {
        return array_values(array_filter(
            JenisDokumenLegal::AmbilWajibRegistrasi(),
            fn (JenisDokumenLegal $jenis) => $this->Cari($jenis, $tanggal) === null,
        ));
    }
}
