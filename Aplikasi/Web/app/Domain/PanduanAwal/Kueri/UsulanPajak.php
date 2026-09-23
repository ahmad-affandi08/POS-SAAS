<?php

declare(strict_types=1);

namespace App\Domain\PanduanAwal\Kueri;

use App\Domain\Organisasi\Data\DataOutletRingkas;
use App\Domain\Organisasi\Kueri\ProfilPajakOutlet;
use App\Domain\Pajak\Data\DataTarifBerlaku;
use App\Domain\Pajak\Enum\CakupanPajak;
use App\Domain\Pajak\Kueri\DaftarKelompokPajak;
use App\Domain\Pajak\Kueri\TarifPajakBerlaku;
use App\Domain\PanduanAwal\Data\DataIsiTemplate;
use App\Domain\PanduanAwal\Enum\LangkahPanduan;
use App\Domain\PanduanAwal\Enum\StatusLangkahPanduan;
use App\Domain\PanduanAwal\Layanan\PembacaIsiTemplate;
use App\Domain\Referensi\Kueri\WilayahKota;
use App\Domain\Tenant\Kueri\ProfilTenant;
use Brick\Math\BigDecimal;
use Brick\Math\Exception\MathException;
use Brick\Math\RoundingMode;

/**
 * Usulan pajak langkah 3 panduan awal (F-01). Tidak ada angka tarif di kode (CLAUDE.md #12): tarif PBJT kota outlet
 * dan PPN nasional selalu dibaca dari `TarifPajak` terbit yang berlaku hari ini di zona waktu outlet. Setelah langkah
 * pajak Selesai, nilai tersimpan di `Outlet.ProfilPajak` yang ditampilkan, bukan usulan.
 */
final class UsulanPajak
{
    public const KODE_PBJT = 'PbjtMakananMinuman';

    public const KODE_PPN = 'Ppn';

    public function __construct(
        private readonly ProfilTenant $profilTenant,
        private readonly ProgresPanduan $progres,
        private readonly TemplateTerbit $templateTerbit,
        private readonly PembacaIsiTemplate $pembaca,
        private readonly TarifPajakBerlaku $tarifBerlaku,
        private readonly DaftarKelompokPajak $kelompokPajak,
        private readonly WilayahKota $wilayahKota,
        private readonly ProfilPajakOutlet $profilPajakOutlet,
    ) {}

    /**
     * Bentuk `PropsPajak` tanpa `Progres` (kontrak frontend §E).
     *
     * @return array<string, mixed>
     */
    public function Ambil(DataOutletRingkas $outlet): array
    {
        $pkp = $this->profilTenant->Ambil($outlet->idTenant)['Pkp'];
        $versi = $this->templateTerbit->CariVersi($outlet->idTemplateSektorVersi);
        $isi = $versi === null ? null : $this->pembaca->Baca($versi->Isi);
        $kota = $outlet->kodeKota === null ? null : $this->wilayahKota->Cari($outlet->kodeKota);
        $hari = now($outlet->zonaWaktu);

        $tarifPbjt = $outlet->kodeKota === null ? null : $this->tarifBerlaku->CariData(self::KODE_PBJT, $outlet->kodeKota, $hari);
        $tarifPpn = $this->tarifBerlaku->CariData(self::KODE_PPN, null, $hari);
        $sudah = $this->progres->AmbilStatus(LangkahPanduan::Pajak) === StatusLangkahPanduan::Selesai;
        $usulan = $this->SusunUsulan($isi);

        return [
            'Pkp' => $pkp,
            'Kota' => $kota === null ? null : ['Kode' => $kota['Kode'], 'Nama' => $kota['Nama']],
            'Nilai' => $sudah ? $this->AmbilNilaiTersimpan($outlet->id) : $usulan['Nilai'],
            'SudahDikonfirmasi' => $sudah,
            'TarifPbjt' => $tarifPbjt === null ? null : [...self::PetakanTarif($tarifPbjt), 'BiayaLayananMasukDpp' => $tarifPbjt->biayaLayananMasukDpp],
            'TarifPpn' => $tarifPpn === null ? null : [
                ...self::PetakanTarif($tarifPpn),
                'PengaliDppPembilang' => $tarifPpn->pengaliDppPembilang,
                'PengaliDppPenyebut' => $tarifPpn->pengaliDppPenyebut,
            ],
            'KelompokPajak' => $this->kelompokPajak->Ambil(),
            'AlasanUsulan' => $this->SusunAlasan($pkp, $versi?->TemplateSektor->Nama, $usulan, $kota['Nama'] ?? null, $tarifPbjt !== null),
        ];
    }

    /**
     * @return array{Nilai: array{PungutPbjt: bool, BiayaLayananAktif: bool, PersenBiayaLayanan: string, HargaTermasukPajak: bool}, PbjtDariTemplate: bool}
     */
    private function SusunUsulan(?DataIsiTemplate $isi): array
    {
        $kodeJenis = [];

        foreach ($isi === null ? [] : $isi->kelompokPajak as $kelompok) {
            foreach ($kelompok->detail as $detail) {
                $kodeJenis[] = $detail['KodeJenisPajak'];
            }
        }

        $pungutPbjt = array_filter(
            $this->kelompokPajak->AmbilJenisPajak($kodeJenis),
            fn (array $jenis) => $jenis['Cakupan'] === CakupanPajak::Daerah->value,
        ) !== [];
        $persen = self::AmbilPersen($isi?->pengaturan->persenBiayaLayanan);

        return [
            'Nilai' => [
                'PungutPbjt' => $pungutPbjt,
                'BiayaLayananAktif' => $persen !== null && $persen->isPositive(),
                'PersenBiayaLayanan' => (string) ($persen ?? BigDecimal::zero())->toScale(2, RoundingMode::Down),
                'HargaTermasukPajak' => $isi?->pengaturan->hargaTermasukPajak ?? false,
            ],
            'PbjtDariTemplate' => $pungutPbjt,
        ];
    }

    /**
     * @return array{PungutPbjt: bool, BiayaLayananAktif: bool, PersenBiayaLayanan: string, HargaTermasukPajak: bool}
     */
    private function AmbilNilaiTersimpan(int $idOutlet): array
    {
        $profil = $this->profilPajakOutlet->Ambil($idOutlet);

        if ($profil === null) {
            return ['PungutPbjt' => false, 'BiayaLayananAktif' => false, 'PersenBiayaLayanan' => '0.00', 'HargaTermasukPajak' => false];
        }

        return [
            'PungutPbjt' => $profil->pungutPbjt,
            'BiayaLayananAktif' => $profil->biayaLayananAktif,
            'PersenBiayaLayanan' => $profil->persenBiayaLayanan,
            'HargaTermasukPajak' => $profil->hargaTermasukPajak,
        ];
    }

    /**
     * @param  array{Nilai: array{PungutPbjt: bool, BiayaLayananAktif: bool, PersenBiayaLayanan: string, HargaTermasukPajak: bool}, PbjtDariTemplate: bool}  $usulan
     * @return list<string>
     */
    private function SusunAlasan(bool $pkp, ?string $namaTemplate, array $usulan, ?string $namaKota, bool $adaTarifPbjt): array
    {
        $alasan = [];

        if ($namaTemplate !== null && $usulan['PbjtDariTemplate']) {
            $alasan[] = "Template {$namaTemplate} memakai PBJT makanan & minuman.";
        }

        if ($namaKota !== null && $usulan['PbjtDariTemplate']) {
            $alasan[] = $adaTarifPbjt
                ? "Tarif PBJT mengikuti peraturan daerah {$namaKota}."
                : "Tarif PBJT {$namaKota} belum tersedia. Anda tetap bisa menyimpan; PBJT belum dihitung sampai tarifnya tersedia.";
        }

        $alasan[] = $pkp
            ? 'Usaha Anda PKP, jadi barang kena PPN dipungut PPN.'
            : 'Usaha Anda belum PKP, jadi PPN tidak dipungut. Ubah di langkah Profil usaha bila sudah dikukuhkan PKP.';

        if ($usulan['Nilai']['BiayaLayananAktif']) {
            $alasan[] = "Template {$namaTemplate} mengusulkan biaya layanan {$usulan['Nilai']['PersenBiayaLayanan']}%.";
        }

        return $alasan;
    }

    /**
     * @return array{Tarif: string, BerlakuMulai: string, NomorDasarHukum: string|null}
     */
    private static function PetakanTarif(DataTarifBerlaku $tarif): array
    {
        return ['Tarif' => $tarif->tarif, 'BerlakuMulai' => $tarif->berlakuMulai, 'NomorDasarHukum' => $tarif->nomorDasarHukum];
    }

    private static function AmbilPersen(mixed $nilai): ?BigDecimal
    {
        if (! is_string($nilai) && ! is_int($nilai)) {
            return null;
        }

        try {
            return BigDecimal::of($nilai);
        } catch (MathException) {
            return null;
        }
    }
}
