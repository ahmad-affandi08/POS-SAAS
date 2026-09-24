<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Layanan;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Organisasi\Data\DataAnggotaOutlet;
use App\Domain\Organisasi\Enum\IzinTenant;
use App\Domain\Organisasi\Kueri\AnggotaOutlet;
use App\Domain\Tenant\Data\DataPengaturanKasir;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * BR-07.3 diskon manual (F-07b). Persen efektif = diskon ÷ bruto baris (diskon baris) atau ÷ subtotal (diskon
 * pesanan), dari hitung ulang server. Aturan:
 * - Pemilik (kasir atau penyetuju) tanpa batas.
 * - Kasir ber-izin `penjualan.diskon.manual` boleh sampai `BatasDiskonManual` tanpa penyetuju.
 * - Kasir ber-izin `penjualan.diskon.setujui` (supervisor ke atas) boleh menyetujui sendiri sampai
 *   `BatasDiskonPenyetuju`.
 * - Selain itu wajib `UuidPenyetujuDiskon`: anggota outlet ber-izin `penjualan.diskon.setujui` (PIN diperiksa di
 *   perangkat; server memeriksa kewenangannya) sampai `BatasDiskonPenyetuju`.
 * Galat: `TanpaIzin` (kasir tanpa izin diskon dan tanpa penyetuju), `DiskonMelebihiBatas`, `PenyetujuTidakBerwenang`.
 */
final class PemeriksaDiskonPenjualan
{
    public function __construct(private readonly AnggotaOutlet $anggota) {}

    /**
     * @param  list<array{0: Uang, 1: Uang}>  $diskon  pasangan [dasar (bruto/subtotal), diskon manual] yang diberikan
     * @return DataAnggotaOutlet|null penyetuju yang disimpan (null bila tidak ada/tidak diperlukan)
     */
    public function Periksa(array $diskon, DataAnggotaOutlet $kasir, ?string $uuidPenyetuju, int $idTenant, int $idOutlet, DataPengaturanKasir $pengaturan): ?DataAnggotaOutlet
    {
        $maks = BigDecimal::zero();

        foreach ($diskon as [$dasar, $jumlah]) {
            if ($jumlah->BernilaiNol() || $dasar->BernilaiNol()) {
                continue;
            }

            $persen = BigDecimal::of($jumlah->KeString())->multipliedBy(100)->dividedBy($dasar->KeString(), 6, RoundingMode::HalfUp);
            $maks = $persen->isGreaterThan($maks) ? $persen : $maks;
        }

        $penyetuju = $uuidPenyetuju === null ? null : $this->CariPenyetuju($uuidPenyetuju, $idTenant, $idOutlet);

        if ($maks->isZero() || $kasir->pemilik) {
            return $penyetuju;
        }

        $bolehSendiri = ($kasir->CekIzin(IzinTenant::PenjualanDiskonManual->value) && $maks->isLessThanOrEqualTo($pengaturan->batasDiskonManual))
            || ($kasir->CekIzin(IzinTenant::PenjualanDiskonSetujui->value) && $maks->isLessThanOrEqualTo($pengaturan->batasDiskonPenyetuju));

        if ($bolehSendiri) {
            return $penyetuju;
        }

        $detail = [
            'PersenDiskon' => (string) $maks->toScale(2, RoundingMode::HalfUp),
            'BatasDiskonManual' => (string) $pengaturan->batasDiskonManual,
            'BatasDiskonPenyetuju' => (string) $pengaturan->batasDiskonPenyetuju,
        ];

        if ($penyetuju === null) {
            if (! $kasir->CekIzin(IzinTenant::PenjualanDiskonManual->value) && $maks->isLessThanOrEqualTo($pengaturan->batasDiskonManual)) {
                throw new PelanggaranAturanBisnis('TanpaIzin', "{$kasir->nama} tidak punya izin memberi diskon manual. Minta supervisor menyetujui dengan PIN.", 'DiskonManual', 403, $detail);
            }

            throw new PelanggaranAturanBisnis('DiskonMelebihiBatas', "Diskon {$detail['PersenDiskon']}% melebihi batas kasir {$detail['BatasDiskonManual']}% dan wajib disetujui supervisor dengan PIN.", 'UuidPenyetujuDiskon', 422, $detail);
        }

        if (! $penyetuju->pemilik && $maks->isGreaterThan($pengaturan->batasDiskonPenyetuju)) {
            throw new PelanggaranAturanBisnis('DiskonMelebihiBatas', "Diskon {$detail['PersenDiskon']}% melebihi batas persetujuan {$detail['BatasDiskonPenyetuju']}%. Hanya Pemilik yang bisa menyetujui diskon sebesar ini.", 'UuidPenyetujuDiskon', 422, $detail);
        }

        return $penyetuju;
    }

    private function CariPenyetuju(string $uuid, int $idTenant, int $idOutlet): DataAnggotaOutlet
    {
        $penyetuju = $this->anggota->Cari($idTenant, $uuid, $idOutlet);

        if ($penyetuju === null || ! $penyetuju->CekIzin(IzinTenant::PenjualanDiskonSetujui->value)) {
            throw new PelanggaranAturanBisnis('PenyetujuTidakBerwenang', 'Penyetuju tidak punya izin menyetujui diskon di outlet ini.', 'UuidPenyetujuDiskon', 403);
        }

        return $penyetuju;
    }
}
