<?php

declare(strict_types=1);

namespace App\Domain\PanduanAwal\Kueri;

use App\Domain\Organisasi\Model\Outlet;
use App\Domain\PanduanAwal\Layanan\PembacaIsiTemplate;
use App\Domain\Penjualan\Enum\ModeKasir;
use App\Domain\Tenant\Kueri\FiturOutlet;
use App\Domain\Tenant\Kueri\ProfilTenant;
use App\Domain\Tenant\Kueri\SumberFiturTenant;
use App\Domain\Tenant\Layanan\PemeriksaFiturTenant;

/**
 * Data langkah 2 panduan awal (F-01): template terbit yang bisa dipilih, fitur yang tersedia di paket tenant
 * (BR-01.3), template yang sudah diterapkan ke outlet, dan sektor tambahan (usaha campuran).
 */
final class PilihanTemplate
{
    public function __construct(
        private readonly TemplateTerbit $templateTerbit,
        private readonly PembacaIsiTemplate $pembaca,
        private readonly FiturOutlet $fiturOutlet,
        private readonly PemeriksaFiturTenant $pemeriksaFitur,
        private readonly SumberFiturTenant $sumberFitur,
        private readonly ProfilTenant $profilTenant,
    ) {}

    /**
     * Bentuk `PropsSektor` tanpa `Progres` (kontrak frontend §E).
     *
     * @return array<string, mixed>
     */
    public function Ambil(Outlet $outlet): array
    {
        $idTenant = $outlet->IdTenant;
        $template = $this->templateTerbit->AmbilSemua();
        $semuaKunci = [];
        $isiPerKode = [];

        foreach ($template as $baris) {
            $isi = $this->pembaca->Baca($baris['Isi']);
            $isiPerKode[$baris['Kode']] = $isi;
            array_push($semuaKunci, ...$isi->kunciFitur);
        }

        $semuaKunci = array_values(array_unique($semuaKunci));
        $namaFitur = $this->fiturOutlet->AmbilNamaFitur($semuaKunci);
        $tersedia = [];

        foreach ($semuaKunci as $kunci) {
            $tersedia[$kunci] = $this->pemeriksaFitur->CekAktif($idTenant, $kunci);
        }

        $versiTerpilih = $this->templateTerbit->CariVersi($outlet->IdTemplateSektorVersi);
        $sektor = $this->profilTenant->Ambil($idTenant)['Pengaturan']['Sektor'] ?? [];

        return [
            'Template' => array_map(function (array $baris) use ($isiPerKode, $namaFitur, $tersedia): array {
                $isi = $isiPerKode[$baris['Kode']];

                return [
                    'Kode' => $baris['Kode'],
                    'Nama' => $baris['Nama'],
                    'Keterangan' => $baris['Keterangan'],
                    'Versi' => $baris['Versi'],
                    'ModeKasir' => array_values(array_map(
                        fn (string $mode): array => ['Nilai' => $mode, 'Label' => ModeKasir::tryFrom($mode)?->AmbilLabel() ?? $mode],
                        $isi->modeKasir,
                    )),
                    'Fitur' => array_values(array_map(fn (string $kunci): array => [
                        'Kunci' => $kunci,
                        'Nama' => $namaFitur[$kunci] ?? $kunci,
                        'TersediaDiPaket' => $tersedia[$kunci] ?? false,
                    ], $isi->kunciFitur)),
                    'Kategori' => $isi->kategori,
                    'JumlahAkun' => count($isi->akun),
                    'JumlahProdukContoh' => count($isi->produkContoh),
                ];
            }, $template),
            'TemplateTerpilih' => $versiTerpilih === null || $outlet->TemplateSektorDiterapkanPada === null ? null : [
                'Kode' => $versiTerpilih->TemplateSektor->Kode,
                'Nama' => $versiTerpilih->TemplateSektor->Nama,
                'Versi' => $versiTerpilih->Versi,
                'DiterapkanPada' => $outlet->TemplateSektorDiterapkanPada->toIso8601ZuluString(),
            ],
            'SektorLain' => array_values(array_filter(
                is_array($sektor) ? $sektor : [],
                fn (mixed $kode) => is_string($kode) && $kode !== $outlet->TemplateSektor,
            )),
            'NamaPaket' => $this->sumberFitur->AmbilNamaPaket($idTenant),
        ];
    }
}
