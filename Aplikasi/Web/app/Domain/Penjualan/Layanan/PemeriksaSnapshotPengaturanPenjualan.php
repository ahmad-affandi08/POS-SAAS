<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Layanan;

use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Katalog\Data\DataProdukPenjualan;
use App\Domain\Organisasi\Data\DataOutletPenjualan;
use App\Domain\Organisasi\Data\DataProfilPajakOutlet;
use App\Domain\Organisasi\Kueri\ProfilPajakOutlet;
use App\Domain\Pajak\Enum\KategoriJenisPajak;
use App\Domain\Pajak\Kueri\DaftarKelompokPajak;
use App\Domain\Pajak\Kueri\TarifPajakBerlaku;
use App\Domain\Penjualan\Data\DataPenjualanPos;
use App\Domain\Penjualan\Kalkulasi\DataPembulatanTunai;
use App\Domain\Tenant\Data\DataPengaturanKasir;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;

/**
 * PRD v1.46 "Tindak lanjut tinjauan" (a): mencocokkan snapshot pengaturan penjualan POS dengan pengaturan yang berlaku
 * di server pada tanggal bisnis. Beda tidak menolak penjualan (uang sudah diterima, §18.3), tetapi menjadi alasan
 * tinjauan:
 * - `PengaturanBerbeda`: `HargaTermasukPajak` dokumen & per baris efektif (baris ?? dokumen) vs `Produk.HargaTermasukPajak`
 *   ?? `Outlet.ProfilPajak.HargaTermasukPajak`; `PersenBiayaLayanan` vs biaya layanan outlet (0 bila tidak aktif);
 *   `PembulatanTunai` vs pengaturan kasir tenant.
 * - `PajakBerbeda`: himpunan kode pajak per baris (baris ?? semua pajak dokumen) vs jenis pajak kelompok pajak produk
 *   yang memenuhi profil pajak outlet menurut kategori `JenisPajak` (Ppn hanya bila PKP, Pbjt hanya bila memungut
 *   PBJT, Lainnya selalu) dan punya tarif terbit yang berlaku (sama dengan aplikasi yang melewati pajak tanpa tarif).
 */
final class PemeriksaSnapshotPengaturanPenjualan
{
    /** Batas nama produk yang disebut dalam satu alasan tinjauan. */
    private const MAKS_CONTOH = 3;

    public function __construct(
        private readonly ProfilPajakOutlet $profilPajak,
        private readonly DaftarKelompokPajak $kelompokPajak,
        private readonly TarifPajakBerlaku $tarifBerlaku,
    ) {}

    /**
     * @param  array<string, DataProdukPenjualan>  $produk  kunci = Uuid produk
     * @return array<string, string> kode alasan → alasan tinjauan (`Kode: keterangan`)
     */
    public function Periksa(DataPenjualanPos $data, array $produk, DataOutletPenjualan $outlet, DataPengaturanKasir $pengaturan, CarbonImmutable $tanggalBisnis): array
    {
        $profil = $this->profilPajak->Ambil($outlet->idOutlet) ?? new DataProfilPajakOutlet(false, false, false, '0.00', false);
        $tinjauan = [];

        $pengaturanBerbeda = $this->PeriksaPengaturan($data, $produk, $profil, $pengaturan);

        if ($pengaturanBerbeda !== []) {
            $tinjauan['PengaturanBerbeda'] = 'PengaturanBerbeda: '.implode('; ', $pengaturanBerbeda);
        }

        $pajakBerbeda = $this->PeriksaPajak($data, $produk, $profil, $outlet, $tanggalBisnis);

        if ($pajakBerbeda !== []) {
            $tinjauan['PajakBerbeda'] = 'PajakBerbeda: '.implode('; ', $pajakBerbeda);
        }

        return $tinjauan;
    }

    /**
     * @param  array<string, DataProdukPenjualan>  $produk
     * @return list<string>
     */
    private function PeriksaPengaturan(DataPenjualanPos $data, array $produk, DataProfilPajakOutlet $profil, DataPengaturanKasir $pengaturan): array
    {
        $beda = [];

        if ($data->hargaTermasukPajak !== $profil->hargaTermasukPajak) {
            $beda[] = 'harga di perangkat '.self::LabelTermasukPajak($data->hargaTermasukPajak).', pengaturan outlet '.self::LabelTermasukPajak($profil->hargaTermasukPajak);
        }

        $barisBeda = [];

        foreach ($data->baris as $baris) {
            $p = $produk[$baris->uuidProduk];
            $perangkat = $baris->hargaTermasukPajak ?? $data->hargaTermasukPajak;
            $seharusnya = $p->hargaTermasukPajak ?? $profil->hargaTermasukPajak;

            // Beda yang hanya berasal dari pengaturan dokumen sudah disebut di atas.
            if ($perangkat !== $seharusnya && ($baris->hargaTermasukPajak !== null || $p->hargaTermasukPajak !== null)) {
                $barisBeda[$p->nama] = $p->nama.' ('.self::LabelTermasukPajak($perangkat).', seharusnya '.self::LabelTermasukPajak($seharusnya).')';
            }
        }

        if ($barisBeda !== []) {
            $beda[] = 'harga per produk '.self::Ringkas(array_values($barisBeda));
        }

        $persenLayanan = $profil->biayaLayananAktif ? BigDecimal::of($profil->persenBiayaLayanan) : BigDecimal::zero();

        if (! $data->persenBiayaLayanan->isEqualTo($persenLayanan)) {
            $beda[] = 'biaya layanan di perangkat '.self::FormatPersen($data->persenBiayaLayanan).', pengaturan outlet '.self::FormatPersen($persenLayanan);
        }

        $pembulatan = $pengaturan->pembulatanTunai;
        $sama = $data->pembulatanTunai === null
            ? $pembulatan === null
            : $pembulatan !== null && $pembulatan['Kelipatan'] === $data->pembulatanTunai->kelipatan && $pembulatan['Arah'] === $data->pembulatanTunai->arah;

        if (! $sama) {
            $beda[] = 'pembulatan tunai di perangkat '.self::LabelPembulatan($data->pembulatanTunai).', pengaturan kasir '
                .self::LabelPembulatan($pembulatan === null ? null : new DataPembulatanTunai($pembulatan['Kelipatan'], $pembulatan['Arah']));
        }

        return $beda;
    }

    /**
     * @param  array<string, DataProdukPenjualan>  $produk
     * @return list<string>
     */
    private function PeriksaPajak(DataPenjualanPos $data, array $produk, DataProfilPajakOutlet $profil, DataOutletPenjualan $outlet, CarbonImmutable $tanggalBisnis): array
    {
        $kodeDokumen = array_values(array_map(fn ($p): string => $p->kode, $data->pajak));
        $idKelompok = array_values(array_unique(array_filter(array_map(fn (DataProdukPenjualan $p): ?int => $p->idKelompokPajak, $produk), 'is_int')));
        $pajakKelompok = $this->kelompokPajak->AmbilJenisPajakPerKelompok($idKelompok);
        $adaTarif = [];
        $semuaKode = $kodeDokumen;
        $pasangan = [];

        foreach ($data->baris as $baris) {
            $p = $produk[$baris->uuidProduk];
            $seharusnya = [];

            foreach ($p->idKelompokPajak === null ? [] : ($pajakKelompok[$p->idKelompokPajak] ?? []) as $jenis) {
                $berlaku = match ($jenis['Kategori']) {
                    KategoriJenisPajak::Ppn => $profil->pkp,
                    KategoriJenisPajak::Pbjt => $profil->pungutPbjt,
                    KategoriJenisPajak::Lainnya => true,
                };
                $adaTarif[$jenis['Kode']] ??= $this->tarifBerlaku->CariDataOutlet($jenis['Kode'], $outlet->kodeKota, $tanggalBisnis) !== null;

                if ($berlaku && $adaTarif[$jenis['Kode']]) {
                    $seharusnya[$jenis['Kode']] = true;
                }
            }

            $perangkat = array_fill_keys($baris->kodePajak ?? $kodeDokumen, true);
            $seharusnya = array_keys($seharusnya);
            $perangkat = array_keys($perangkat);
            sort($seharusnya);
            sort($perangkat);

            if ($seharusnya !== $perangkat) {
                $semuaKode = [...$semuaKode, ...$seharusnya];
                $pasangan[] = [$p->nama, $perangkat, $seharusnya];
            }
        }

        if ($pasangan === []) {
            return [];
        }

        $nama = array_map(fn (array $j): string => $j['Nama'], $this->kelompokPajak->AmbilJenisPajak(array_values(array_unique($semuaKode))));
        $barisBeda = [];

        foreach ($pasangan as [$namaProduk, $perangkat, $seharusnya]) {
            $barisBeda[] = $namaProduk.' (perangkat '.self::LabelPajak($perangkat, $nama).', seharusnya '.self::LabelPajak($seharusnya, $nama).')';
        }

        return ['pajak per produk '.self::Ringkas($barisBeda)];
    }

    /**
     * @param  list<string>  $kode
     * @param  array<string, string>  $nama
     */
    private static function LabelPajak(array $kode, array $nama): string
    {
        return $kode === [] ? 'tanpa pajak' : implode(' + ', array_map(fn (string $k): string => $nama[$k] ?? $k, $kode));
    }

    /**
     * @param  list<string>  $daftar
     */
    private static function Ringkas(array $daftar): string
    {
        $contoh = array_slice($daftar, 0, self::MAKS_CONTOH);
        $sisa = count($daftar) - count($contoh);

        return implode(', ', $contoh).($sisa > 0 ? " dan {$sisa} produk lain" : '');
    }

    private static function LabelTermasukPajak(bool $termasuk): string
    {
        return $termasuk ? 'termasuk pajak' : 'belum termasuk pajak';
    }

    private static function FormatPersen(BigDecimal $persen): string
    {
        return str_replace('.', ',', (string) $persen->strippedOfTrailingZeros()).'%';
    }

    private static function LabelPembulatan(?DataPembulatanTunai $pembulatan): string
    {
        return $pembulatan === null
            ? 'tanpa pembulatan'
            : 'kelipatan '.Uang::Dari((string) $pembulatan->kelipatan)->FormatRupiah().' '.mb_strtolower($pembulatan->arah->AmbilLabel());
    }
}
