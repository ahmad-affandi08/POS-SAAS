<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\TemplateSektor\Aksi;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\PanduanAwal\Enum\StatusTemplateSektor;
use App\Domain\PanduanAwal\Model\TemplateSektor;
use App\Domain\PanduanAwal\Model\TemplateSektorVersi;
use App\Domain\Pengelola\TemplateSektor\Data\DataTemplateSektor;
use App\Domain\Pengelola\TemplateSektor\Layanan\ValidatorTemplate;
use App\Domain\Pengelola\TimInternal\Layanan\PencatatAuditPengelola;
use App\Domain\Pengelola\TimInternal\Model\PenggunaPengelola;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Membuat template sektor baru beserta draf versi 1 (P-03 langkah 1). Isi awal disalin dari versi terbaru template
 * dasar (bila dipilih) agar COA dan pemetaan tidak diketik ulang; tanpa dasar, isi dimulai kosong.
 */
final class BuatTemplateSektor
{
    /** Kode sektor §5.1: tiga huruf kelompok, tanda hubung, tiga huruf sektor, misal FNB-RST. */
    public const POLA_KODE = '/^[A-Z]{3}-[A-Z]{3}$/';

    public function __construct(
        private readonly PencatatAuditPengelola $audit,
        private readonly ValidatorTemplate $validator,
    ) {}

    public function Jalankan(PenggunaPengelola $pelaku, DataTemplateSektor $data): TemplateSektor
    {
        if (preg_match(self::POLA_KODE, $data->kode) !== 1) {
            throw new PelanggaranAturanBisnis('KodeTidakValid', 'Kode template berformat tiga huruf, tanda hubung, tiga huruf, misal FNB-RST.', 'Kode');
        }

        try {
            return DB::transaction(function () use ($pelaku, $data): TemplateSektor {
                if (TemplateSektor::query()->where('Kode', $data->kode)->exists()) {
                    throw new PelanggaranAturanBisnis('KodeSudahAda', "Template {$data->kode} sudah ada.", 'Kode');
                }

                $isi = $data->kodeTemplateDasar === null ? self::AmbilIsiKosong() : self::AmbilIsiDasar($data->kodeTemplateDasar);
                $template = TemplateSektor::query()->create([
                    'Kode' => $data->kode,
                    'Nama' => $data->nama,
                    'Keterangan' => $data->keterangan,
                ]);
                $template->Versi()->create([
                    'Versi' => 1,
                    'Status' => StatusTemplateSektor::Draf,
                    'Isi' => $isi,
                    'HasilValidasi' => $this->validator->Validasi($isi),
                    'DivalidasiPada' => now(),
                ]);

                $this->audit->Catat(
                    'template.buat',
                    $template,
                    nilaiBaru: [...$template->only(['Kode', 'Nama', 'Keterangan']), 'KodeTemplateDasar' => $data->kodeTemplateDasar],
                    idPelaku: $pelaku->Id,
                );

                return $template;
            });
        } catch (UniqueConstraintViolationException) {
            throw new PelanggaranAturanBisnis('KodeSudahAda', 'Kode template ini baru saja dipakai. Muat ulang halaman lalu pakai kode lain.', 'Kode');
        }
    }

    /**
     * @return array<string, mixed>
     */
    public static function AmbilIsiKosong(): array
    {
        return [
            'ModeKasir' => [],
            'ModeKasirDefault' => null,
            'KunciFitur' => [],
            'Akun' => [],
            'PemetaanAkun' => (object) [],
            'Kategori' => [],
            'KodeSatuan' => [],
            'KelompokPajak' => [],
            'Pengaturan' => [
                'PembulatanTunai' => ['Kelipatan' => 100, 'Arah' => 'Bawah'],
                'PersenBiayaLayanan' => '0',
                'BiayaLayananMasukDpp' => false,
                'StokBolehMinus' => false,
                'MetodeHpp' => 'RataRata',
                'HargaTermasukPajak' => true,
            ],
            'StasiunDapur' => [],
            'AlasanVoid' => [],
            'AlasanPenyesuaian' => [],
            'LaporanUnggulan' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function AmbilIsiDasar(string $kode): array
    {
        $kueri = TemplateSektorVersi::query()->whereHas('TemplateSektor', fn ($template) => $template->where('Kode', $kode));
        // Utamakan versi terbit; bila belum ada, pakai versi terbaru (misal draf pertama).
        $versi = (clone $kueri)->where('Status', StatusTemplateSektor::Terbit->value)->first()
            ?? $kueri->orderByDesc('Versi')->first();

        if ($versi === null) {
            throw new PelanggaranAturanBisnis('TemplateDasarTidakAda', "Template dasar {$kode} tidak ditemukan.", 'KodeTemplateDasar');
        }

        return $versi->Isi;
    }
}
