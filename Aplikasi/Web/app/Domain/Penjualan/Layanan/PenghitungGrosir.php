<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Layanan;

use App\Domain\Bersama\Nilai\Kuantitas;
use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Organisasi\Data\DataProfilPajakOutlet;
use App\Domain\Organisasi\Kueri\ProfilPajakOutlet;
use App\Domain\Organisasi\Kueri\TanggalBisnisOutlet;
use App\Domain\Pajak\Enum\KategoriJenisPajak;
use App\Domain\Pajak\Kueri\DaftarKelompokPajak;
use App\Domain\Pajak\Kueri\TarifPajakBerlaku;
use App\Domain\Penjualan\Data\HasilHitungGrosir;
use App\Domain\Penjualan\Kalkulasi\DataBarisKalkulasi;
use App\Domain\Penjualan\Kalkulasi\DataKalkulasi;
use App\Domain\Penjualan\Kalkulasi\DataPajakKalkulasi;
use App\Domain\Penjualan\Kalkulasi\DataPotongan;
use App\Domain\Penjualan\Kalkulasi\HasilPajakKalkulasi;
use App\Domain\Penjualan\Kalkulasi\MesinKalkulasi;
use Carbon\CarbonImmutable;

/**
 * Angka dokumen grosir (F-12, §9.7) dihitung dengan **mesin kalkulasi F-07a yang sama dengan kasir**, bukan rumus
 * kedua: aturan inklusif/eksklusif, pembulatan pajak, dan pengali DPP nilai lain harus identik dengan penjualan ritel,
 * kalau tidak satu tenant bisa melaporkan PPN berbeda untuk barang yang sama hanya karena jalur jualnya berbeda.
 *
 * Bedanya dengan kasir, dan alasannya:
 * - **tanpa biaya layanan** — itu praktik resto per tamu, bukan penjualan grosir;
 * - **tanpa promo otomatis** — promo F-16c dirancang untuk transaksi ritel; harga grosir datang dari daftar harga
 *   bertingkat & tier pelanggan (X8) lewat price engine;
 * - **tanpa pembulatan tunai** — grosir dibayar transfer/tempo lewat faktur, bukan uang tunai di laci.
 *
 * Tarif diambil dari `TarifPajak` terbit yang berlaku pada tanggal bisnis outlet (CLAUDE.md #12), lalu di-snapshot di
 * dokumen. Outlet bukan PKP atau tarif belum terbit = PPN tidak dihitung.
 */
final class PenghitungGrosir
{
    public function __construct(
        private readonly ProfilPajakOutlet $profilPajak,
        private readonly DaftarKelompokPajak $kelompokPajak,
        private readonly TarifPajakBerlaku $tarifBerlaku,
        private readonly TanggalBisnisOutlet $tanggalBisnis,
        private readonly MesinKalkulasi $mesin = new MesinKalkulasi,
    ) {}

    /**
     * @param  list<array{Jumlah: Kuantitas, HargaSatuan: Uang, Diskon: Uang, IdKelompokPajak: int|null, HargaTermasukPajak: bool|null}>  $baris
     * @param  CarbonImmutable|null  $tanggal  tanggal berlakunya tarif; null = tanggal bisnis outlet hari ini. Surat
     *                                         jalan mengisinya dengan tanggal penyerahan, karena itulah saat PPN
     *                                         terutang (UU PPN Pasal 11 ayat 1).
     */
    public function Hitung(int $idOutlet, ?string $kodeKota, array $baris, ?CarbonImmutable $tanggal = null): HasilHitungGrosir
    {
        if ($baris === []) {
            return new HasilHitungGrosir(Uang::Nol(), Uang::Nol(), Uang::Nol(), Uang::Nol(), Uang::Nol());
        }

        $profil = $this->profilPajak->Ambil($idOutlet) ?? new DataProfilPajakOutlet(false, false, false, '0.00', false);
        $tanggal ??= CarbonImmutable::parse($this->tanggalBisnis->Hitung($idOutlet)->format('Y-m-d'));
        $pajakKelompok = $this->kelompokPajak->AmbilJenisPajakPerKelompok(
            array_values(array_unique(array_filter(array_column($baris, 'IdKelompokPajak'), 'is_int'))),
        );
        $pajakDokumen = [];
        $kodeBaris = [];

        foreach ($baris as $satuBaris) {
            $kode = [];

            foreach ($satuBaris['IdKelompokPajak'] === null ? [] : ($pajakKelompok[$satuBaris['IdKelompokPajak']] ?? []) as $jenis) {
                $berlaku = match ($jenis['Kategori']) {
                    KategoriJenisPajak::Ppn => $profil->pkp,
                    KategoriJenisPajak::Pbjt => $profil->pungutPbjt,
                    KategoriJenisPajak::Lainnya => true,
                };
                $tarif = $berlaku ? $this->tarifBerlaku->CariDataOutlet($jenis['Kode'], $kodeKota, $tanggal) : null;

                if ($tarif === null) {
                    continue;
                }

                if (! in_array($jenis['Kode'], $kode, true)) {
                    $kode[] = $jenis['Kode'];
                }

                $pajakDokumen[$jenis['Kode']] ??= new DataPajakKalkulasi(
                    $jenis['Kode'],
                    $tarif->tarif,
                    $jenis['DasarPengenaan'],
                    $tarif->pengaliDppPembilang,
                    $tarif->pengaliDppPenyebut,
                );
            }

            $kodeBaris[] = $kode;
        }

        $hasil = $this->mesin->Hitung(new DataKalkulasi(
            hargaTermasukPajak: $profil->hargaTermasukPajak,
            baris: array_values(array_map(
                fn (array $b, array $kode): DataBarisKalkulasi => new DataBarisKalkulasi(
                    $b['Jumlah'],
                    $b['HargaSatuan'],
                    null,
                    $b['HargaTermasukPajak'],
                    $kode,
                    $b['Diskon']->BernilaiNol() ? [] : [DataPotongan::BuatNominal($b['Diskon'])],
                ),
                $baris,
                $kodeBaris,
            )),
            pajak: array_values($pajakDokumen),
        ));
        $ppn = $this->CariPpn($pajakDokumen);

        return new HasilHitungGrosir(
            subtotal: $hasil->subtotal,
            diskon: $hasil->totalDiskon,
            // DPP = subtotal setelah diskon, dikurangi pajak yang sudah termasuk harga bila harga inklusif.
            dasarPengenaanPajak: $hasil->subtotal->Kurangi($hasil->totalDiskon)
                ->Kurangi($hasil->totalPajak->Kurangi($hasil->totalPajakEksklusif)),
            pajak: $hasil->totalPajak,
            total: $hasil->totalAkhir,
            tarifPpn: $ppn === null ? null : (string) $ppn->tarif,
            pengaliDppPembilang: $ppn?->pengaliDppPembilang,
            pengaliDppPenyebut: $ppn?->pengaliDppPenyebut,
            rincianPajak: array_map(fn (HasilPajakKalkulasi $p): Uang => $p->jumlah, $hasil->pajak),
        );
    }

    /**
     * Snapshot khusus PPN: itu satu-satunya pajak yang kolomnya ada di dokumen grosir, karena faktur penjualan &
     * Faktur Pajak berdasar PPN. Pajak daerah (PBJT) tetap ikut total lewat mesin kalkulasi.
     *
     * @param  array<string, DataPajakKalkulasi>  $pajakDokumen
     */
    private function CariPpn(array $pajakDokumen): ?DataPajakKalkulasi
    {
        foreach ($pajakDokumen as $pajak) {
            if (mb_strtoupper($pajak->kode) === 'PPN') {
                return $pajak;
            }
        }

        return null;
    }
}
