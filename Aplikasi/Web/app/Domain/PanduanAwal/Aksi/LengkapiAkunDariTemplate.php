<?php

declare(strict_types=1);

namespace App\Domain\PanduanAwal\Aksi;

use App\Domain\Akuntansi\Aksi\TambahkanAkunTemplate;
use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\PanduanAwal\Kueri\TemplateTerbit;
use App\Domain\PanduanAwal\Layanan\PembacaIsiTemplate;
use Illuminate\Support\Facades\DB;

/**
 * Audit kemudahan pakai #12 ("Perbaiki otomatis"): melengkapi bagan akun & pemetaan peran akun tingkat tenant dari
 * template sektor, supaya posting stok/pembelian tidak buntu karena `PemetaanAkunBelumAda`. Sumber: versi template
 * yang pernah diterapkan ke outlet ([idVersiTemplate]); bila tidak ada, template umum `RTL-GEN`, lalu template terbit
 * pertama. Memakai `TambahkanAkunTemplate` yang aditif: akun yang kodenya sudah ada dan pemetaan yang sudah diatur
 * (termasuk pilihan Akuntan) tidak pernah ditimpa. Audit `akun.pemetaan.lengkapi-otomatis`.
 */
final class LengkapiAkunDariTemplate
{
    public const KODE_TEMPLATE_CADANGAN = 'RTL-GEN';

    public function __construct(
        private readonly TemplateTerbit $templateTerbit,
        private readonly PembacaIsiTemplate $pembaca,
        private readonly TambahkanAkunTemplate $tambahAkun,
        private readonly PencatatAudit $audit,
    ) {}

    /**
     * @return array{Template: string, Akun: list<string>, Pemetaan: list<string>}
     */
    public function Jalankan(?int $idVersiTemplate): array
    {
        $versi = $this->templateTerbit->CariVersi($idVersiTemplate)
            ?? $this->templateTerbit->Cari(self::KODE_TEMPLATE_CADANGAN)
            ?? (($kode = $this->templateTerbit->AmbilKode()[0] ?? null) === null ? null : $this->templateTerbit->Cari($kode));

        if ($versi === null) {
            throw new PelanggaranAturanBisnis('TemplateTidakAda', 'Belum ada template akun yang bisa dipakai. Minta Akuntan memetakan akun di menu Akuntansi › Pemetaan akun.');
        }

        return DB::transaction(function () use ($versi): array {
            $isi = $this->pembaca->Baca($versi->Isi);
            $hasil = $this->tambahAkun->Jalankan($isi->akun, $isi->pemetaanAkun);
            $nama = $versi->loadMissing('TemplateSektor')->TemplateSektor->Nama;

            if ($hasil['Akun'] !== [] || $hasil['Pemetaan'] !== []) {
                $this->audit->Catat('akun.pemetaan.lengkapi-otomatis', nilaiBaru: [
                    'IdTemplateSektorVersi' => $versi->Id,
                    'Akun' => $hasil['Akun'],
                    'Pemetaan' => $hasil['Pemetaan'],
                ]);
            }

            return ['Template' => $nama, 'Akun' => $hasil['Akun'], 'Pemetaan' => $hasil['Pemetaan']];
        });
    }
}
