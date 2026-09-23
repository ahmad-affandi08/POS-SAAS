<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Kueri;

use App\Domain\Tenant\Enum\StatusPaket;
use App\Domain\Tenant\Model\Paket;

/**
 * Apakah halaman daftar boleh menerima pendaftaran (F-00 prasyarat, BR-P06.2, BR-00.4, BR-00.6). Alasan teknis
 * hanya untuk log/tim; calon tenant cukup melihat "pendaftaran belum dibuka".
 */
final class StatusPendaftaran
{
    public function __construct(private readonly DokumenLegalBerlaku $dokumenLegalBerlaku) {}

    /**
     * @return list<string> Alasan pendaftaran ditutup (kosong = dibuka).
     */
    public function AmbilAlasanDitutup(): array
    {
        $alasan = [];

        foreach ($this->dokumenLegalBerlaku->AmbilKekuranganRegistrasi(now()) as $jenis) {
            $alasan[] = "{$jenis->AmbilLabel()} belum berlaku.";
        }

        $paketBawaan = Paket::query()
            ->where('Kode', (string) config('tenant.KodePaketTrialBawaan'))
            ->where('Status', StatusPaket::Aktif->value)
            ->exists();

        if (! $paketBawaan) {
            $alasan[] = 'Paket trial bawaan belum aktif.';
        }

        if (app()->isProduction() && blank(config('integrasi.Turnstile.KunciRahasia'))) {
            $alasan[] = 'CAPTCHA belum aktif.';
        }

        return $alasan;
    }
}
