<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Kueri;

use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Tenant\Enum\StatusLangganan;
use App\Domain\Tenant\Enum\StatusTagihanLangganan;
use App\Domain\Tenant\Model\Langganan;
use App\Domain\Tenant\Model\TagihanLangganan;
use Carbon\CarbonImmutable;
use Closure;

/**
 * Status langganan tenant aktif untuk banner back-office dan pembatasan saat `Ditangguhkan` (F-00, BR-00.7).
 * `BatasTenggangPada` = saat langganan `Tertunggak` akan ditangguhkan (akhir periode + masa tenggang).
 */
final class RingkasanLanggananTenant
{
    public function __construct(private readonly KonteksTenant $konteks) {}

    /**
     * @return array{
     *     Status: StatusLangganan,
     *     PeriodeSelesai: CarbonImmutable|null,
     *     BatasTenggangPada: CarbonImmutable|null,
     *     NamaPaket: string,
     *     KodePaket: string,
     *     TagihanTertunda: array{Uuid: string, Nomor: string, Total: string, JatuhTempoPada: string}|null
     * }|null
     */
    public function Ambil(int $idTenant): ?array
    {
        $langganan = Langganan::query()
            ->where('IdTenant', $idTenant)
            ->with('Paket:Id,Nama,Kode')
            ->first(['Id', 'IdTenant', 'IdPaket', 'Status', 'PeriodeSelesai']);

        if ($langganan === null) {
            return null;
        }

        $periodeSelesai = $langganan->PeriodeSelesai === null ? null : CarbonImmutable::instance($langganan->PeriodeSelesai);

        // `TagihanLangganan` milik tenant (scope `MilikTenant`). Banner dibagikan ke semua halaman Inertia, termasuk
        // halaman publik & 404 yang tidak melewati identifikasi tenant, jadi konteksnya dipasang sementara di sini.
        $tagihanTerbuka = $this->DalamKonteksTenant($idTenant, fn () => TagihanLangganan::query()
            ->where('IdTenant', $idTenant)
            ->whereIn('Status', [StatusTagihanLangganan::Terbit, StatusTagihanLangganan::JatuhTempo])
            ->latest('Id')
            ->first(['Uuid', 'Nomor', 'Total', 'JatuhTempoPada']));

        return [
            'Status' => $langganan->Status,
            'PeriodeSelesai' => $periodeSelesai,
            'BatasTenggangPada' => $langganan->Status === StatusLangganan::Tertunggak && $periodeSelesai !== null
                ? $periodeSelesai->addDays((int) config('tagihan.HariMasaTenggang'))
                : null,
            'NamaPaket' => $langganan->Paket->Nama ?? 'Dasar',
            'KodePaket' => $langganan->Paket->Kode ?? '',
            'TagihanTertunda' => $tagihanTerbuka === null ? null : [
                'Uuid' => $tagihanTerbuka->Uuid,
                'Nomor' => $tagihanTerbuka->Nomor,
                'Total' => $tagihanTerbuka->Total,
                'JatuhTempoPada' => $tagihanTerbuka->JatuhTempoPada->toIso8601ZuluString(),
            ],
        ];
    }

    public function CekDitangguhkan(int $idTenant): bool
    {
        return Langganan::query()
            ->where('IdTenant', $idTenant)
            ->where('Status', StatusLangganan::Ditangguhkan->value)
            ->exists();
    }

    /**
     * @template T
     *
     * @param  Closure(): T  $kerja
     * @return T
     */
    private function DalamKonteksTenant(int $idTenant, Closure $kerja): mixed
    {
        $asal = $this->konteks->Ambil();

        if ($asal === $idTenant) {
            return $kerja();
        }

        $this->konteks->Atur($idTenant);

        try {
            return $kerja();
        } finally {
            if ($asal === null) {
                $this->konteks->Kosongkan();
            } else {
                $this->konteks->Atur($asal);
            }
        }
    }
}
