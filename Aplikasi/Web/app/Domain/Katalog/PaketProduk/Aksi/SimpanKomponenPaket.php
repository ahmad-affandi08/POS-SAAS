<?php

declare(strict_types=1);

namespace App\Domain\Katalog\PaketProduk\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Nilai\Kuantitas;
use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Katalog\Enum\EntitasKatalog;
use App\Domain\Katalog\Enum\JenisProduk;
use App\Domain\Katalog\Layanan\PencatatPenghapusanKatalog;
use App\Domain\Katalog\Model\Produk;
use App\Domain\Katalog\PaketProduk\Data\DataKomponenPaket;
use App\Domain\Katalog\PaketProduk\Model\PaketProdukDetail;
use App\Domain\Tenant\Layanan\PenguncianTenant;
use Brick\Math\BigDecimal;
use Brick\Math\Exception\MathException;
use Illuminate\Support\Facades\DB;

/**
 * Mengganti komponen produk paket/bundel (F-03 C.4, H5).
 * - Hanya produk jenis Paket (`JenisTidakMendukung`).
 * - 1–50 komponen: milik tenant ini, boleh jadi komponen (`CekBolehKomponenPaket`: bisa dijual dan bukan paket;
 *   anak varian boleh), bukan paket itu sendiri, tidak ganda, jumlah > 0 (`KomponenTidakValid`).
 * - `AlokasiHarga` (persen) kosong semua (otomatis) atau terisi semua dengan jumlah tepat 100
 *   (`AlokasiHargaTidakValid`).
 * Baris diganti per komponen (Uuid baris tetap untuk komponen yang sama); baris yang dilepas dicatat jejaknya.
 * Idempoten: kiriman sama tidak mengubah apa pun.
 */
final class SimpanKomponenPaket
{
    public const MAKSIMAL_KOMPONEN = 50;

    public function __construct(
        private readonly PencatatAudit $audit,
        private readonly PenguncianTenant $penguncian,
        private readonly PencatatPenghapusanKatalog $penghapusan,
        private readonly KonteksTenant $konteks,
    ) {}

    /**
     * @param  list<DataKomponenPaket>  $komponen
     */
    public function Jalankan(Produk $paket, array $komponen): void
    {
        DB::transaction(function () use ($paket, $komponen): void {
            $this->penguncian->Kunci($this->konteks->Wajib());
            $paket = Produk::query()->lockForUpdate()->findOrFail($paket->Id);

            if ($paket->Jenis !== JenisProduk::Paket) {
                throw new PelanggaranAturanBisnis('JenisTidakMendukung', 'Komponen hanya untuk produk jenis Paket/bundel.', 'Komponen');
            }

            $baris = $this->SusunBaris($paket, $komponen);
            $lama = PaketProdukDetail::query()->where('IdProdukPaket', $paket->Id)->orderBy('Urutan')->lockForUpdate()->get()->keyBy('IdProdukKomponen');
            $sebelum = $lama->values()->map(fn (PaketProdukDetail $detail): array => [
                'IdProdukKomponen' => $detail->IdProdukKomponen,
                'Jumlah' => $detail->Jumlah,
                'AlokasiHarga' => $detail->AlokasiHarga,
            ])->all();
            $idBaru = array_column($baris, 'IdProdukKomponen');

            foreach ($lama as $idKomponen => $detail) {
                if (! in_array($idKomponen, $idBaru, true)) {
                    $this->penghapusan->Catat(EntitasKatalog::PaketProdukDetail, $detail->Uuid);
                    $detail->delete();
                }
            }

            foreach ($baris as $urutan => $isian) {
                $detail = $lama->get($isian['IdProdukKomponen']);

                if ($detail instanceof PaketProdukDetail) {
                    $detail->fill([...$isian, 'Urutan' => $urutan])->save();

                    continue;
                }

                PaketProdukDetail::query()->create(['IdProdukPaket' => $paket->Id, ...$isian, 'Urutan' => $urutan]);
            }

            if ($sebelum !== $baris) {
                $this->audit->Catat('produk.komponen.ubah', $paket, nilaiLama: ['Komponen' => $sebelum], nilaiBaru: ['Komponen' => $baris]);
            }
        });
    }

    /**
     * @param  list<DataKomponenPaket>  $komponen
     * @return list<array{IdProdukKomponen: int, Jumlah: string, AlokasiHarga: string|null}>
     */
    private function SusunBaris(Produk $paket, array $komponen): array
    {
        if ($komponen === [] || count($komponen) > self::MAKSIMAL_KOMPONEN) {
            throw new PelanggaranAturanBisnis('KomponenTidakValid', 'Isi 1 sampai '.self::MAKSIMAL_KOMPONEN.' komponen paket.', 'Komponen');
        }

        $daftarProduk = Produk::query()->whereKey(array_map(fn (DataKomponenPaket $baris): int => $baris->idProdukKomponen, $komponen))->get(['Id', 'Nama', 'Jenis'])->keyBy('Id');
        $terpakai = [];
        $baris = [];
        $jumlahTerisi = 0;
        $totalAlokasi = BigDecimal::zero();

        foreach ($komponen as $i => $isian) {
            $produk = $daftarProduk->get($isian->idProdukKomponen);

            if (! $produk instanceof Produk || ! $produk->Jenis->CekBolehKomponenPaket() || $produk->Id === $paket->Id) {
                throw new PelanggaranAturanBisnis('KomponenTidakValid', 'Komponen harus produk yang bisa dijual dan bukan paket.', "Komponen.{$i}.UuidProdukKomponen");
            }

            if (isset($terpakai[$produk->Id])) {
                throw new PelanggaranAturanBisnis('KomponenTidakValid', "{$produk->Nama} tercantum lebih dari sekali. Gabungkan jumlahnya.", "Komponen.{$i}.UuidProdukKomponen");
            }

            $terpakai[$produk->Id] = true;

            if ($isian->jumlah->Bandingkan(Kuantitas::Nol()) <= 0) {
                throw new PelanggaranAturanBisnis('KomponenTidakValid', "Jumlah {$produk->Nama} harus lebih dari 0.", "Komponen.{$i}.Jumlah");
            }

            $alokasi = null;

            if ($isian->alokasiHarga !== null && trim($isian->alokasiHarga) !== '') {
                $alokasi = $this->UraiPersen($isian->alokasiHarga, "Komponen.{$i}.AlokasiHarga");
                $totalAlokasi = $totalAlokasi->plus($alokasi);
                $jumlahTerisi++;
            }

            $baris[] = [
                'IdProdukKomponen' => $produk->Id,
                'Jumlah' => $isian->jumlah->KeString(),
                'AlokasiHarga' => $alokasi === null ? null : (string) $alokasi,
            ];
        }

        if ($jumlahTerisi > 0 && ($jumlahTerisi !== count($baris) || ! $totalAlokasi->isEqualTo(100))) {
            throw new PelanggaranAturanBisnis('AlokasiHargaTidakValid', 'Kosongkan semua alokasi harga (otomatis) atau isi semuanya dengan jumlah tepat 100%.', 'Komponen');
        }

        return $baris;
    }

    private function UraiPersen(string $nilai, string $bidang): BigDecimal
    {
        try {
            $persen = BigDecimal::of(trim($nilai));
        } catch (MathException) {
            throw new PelanggaranAturanBisnis('AlokasiHargaTidakValid', 'Alokasi harga harus berupa angka persen.', $bidang);
        }

        if ($persen->getScale() > 6 || $persen->isNegative() || $persen->isGreaterThan(100)) {
            throw new PelanggaranAturanBisnis('AlokasiHargaTidakValid', 'Alokasi harga harus 0 sampai 100 persen, maksimal 6 desimal.', $bidang);
        }

        return $persen->toScale(6);
    }
}
