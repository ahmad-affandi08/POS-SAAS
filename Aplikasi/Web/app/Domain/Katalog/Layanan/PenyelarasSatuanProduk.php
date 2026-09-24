<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Layanan;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Nilai\Kuantitas;
use App\Domain\Katalog\Data\DataSatuanProduk;
use App\Domain\Katalog\Enum\EntitasKatalog;
use App\Domain\Katalog\Model\Produk;
use App\Domain\Katalog\Model\ProdukBarcode;
use App\Domain\Katalog\Model\ProdukSatuan;
use App\Domain\Katalog\Model\Satuan;
use Illuminate\Support\Collection;

/**
 * Menyelaraskan satuan produk dan barcodenya dengan isi form/impor (F-03 `SimpanProduk`, BR-03.1):
 * - tepat satu satuan dasar (konversi 1), konversi lain > 0, satuan unik, satu bawaan jual & beli (otomatis dasar);
 * - satuan yang dibuang: harga dihapus lewat Tim 2, barcode & satuan dihapus dengan jejak `PenghapusanKatalog`;
 * - barcode diganti per satuan, unik per tenant; harga awal hanya untuk satuan baru (butuh izin ubah harga).
 * `Periksa()` dulu (tanpa menulis), lalu `Terapkan()` setelah baris produk tersimpan.
 */
final class PenyelarasSatuanProduk
{
    public function __construct(
        private readonly PencatatPenghapusanKatalog $pencatatHapus,
        private readonly PenghubungHargaProduk $harga,
    ) {}

    /**
     * @param  list<DataSatuanProduk>  $satuan
     * @return list<array{Data: DataSatuanProduk, Id: int|null, DefaultJual: bool, DefaultBeli: bool, Barcode: list<string>}>
     */
    public function Periksa(?Produk $produk, int $idSatuanDasar, array $satuan, bool $bolehUbahHarga): array
    {
        $ada = $produk === null ? collect() : ProdukSatuan::query()->where('IdProduk', $produk->Id)->get()->keyBy('Id');
        $idSatuanTenant = Satuan::query()->whereIn('Id', array_map(fn (DataSatuanProduk $s): int => $s->idSatuan, $satuan))->pluck('Id')->all();
        $jumlahDasar = 0;
        $idTerpakai = [];
        $satu = Kuantitas::Dari(1);

        foreach ($satuan as $i => $data) {
            if (! in_array($data->idSatuan, $idSatuanTenant, true)) {
                throw new PelanggaranAturanBisnis('SatuanDasarWajib', 'Satuan tidak ditemukan. Muat ulang halaman lalu pilih lagi.', "Satuan.{$i}.UuidSatuan");
            }

            if (isset($idTerpakai[$data->idSatuan])) {
                throw new PelanggaranAturanBisnis('SatuanProdukGanda', 'Satuan yang sama dipilih lebih dari sekali.', "Satuan.{$i}.UuidSatuan");
            }

            $idTerpakai[$data->idSatuan] = true;

            if ($data->idProdukSatuan !== null && ! $ada->has($data->idProdukSatuan)) {
                throw new PelanggaranAturanBisnis('SatuanProdukGanda', 'Satuan produk ini sudah berubah. Muat ulang halaman lalu simpan lagi.', "Satuan.{$i}.UuidSatuan");
            }

            if ($data->idSatuan === $idSatuanDasar) {
                $jumlahDasar++;

                if (! $data->konversiKeDasar->SamaDengan($satu)) {
                    throw new PelanggaranAturanBisnis('SatuanDasarWajib', 'Isi satuan dasar selalu 1.', "Satuan.{$i}.KonversiKeDasar");
                }
            } elseif ($data->konversiKeDasar->Bandingkan(Kuantitas::Nol()) <= 0) {
                throw new PelanggaranAturanBisnis('KonversiTidakValid', 'Isi satuan harus lebih dari 0, maksimal 4 angka desimal.', "Satuan.{$i}.KonversiKeDasar");
            }

            if ($data->hargaAwal !== [] && ! $bolehUbahHarga && $this->CekSatuanBaru($data, $ada)) {
                throw new PelanggaranAturanBisnis('IzinHargaDiperlukan', 'Anda tidak punya izin mengubah harga jual. Kosongkan harga, atau minta pemilik mengisinya.', "Satuan.{$i}.HargaAwal");
            }
        }

        if ($jumlahDasar !== 1) {
            throw new PelanggaranAturanBisnis('SatuanDasarWajib', 'Produk wajib punya tepat satu baris satuan dasar dengan isi 1.', 'Satuan');
        }

        $rencana = [];
        $jual = $this->TentukanBawaan($satuan, $idSatuanDasar, fn (DataSatuanProduk $s): bool => $s->defaultJual, 'jual');
        $beli = $this->TentukanBawaan($satuan, $idSatuanDasar, fn (DataSatuanProduk $s): bool => $s->defaultBeli, 'beli');

        foreach ($satuan as $i => $data) {
            $rencana[] = [
                'Data' => $data,
                'Id' => $this->CekSatuanBaru($data, $ada) ? null : $data->idProdukSatuan,
                'DefaultJual' => $i === $jual,
                'DefaultBeli' => $i === $beli,
                'Barcode' => $this->PeriksaBarcode($produk, $data->barcode, $i),
            ];
        }

        $this->PastikanBarcodeUnikDiForm($rencana);

        return $rencana;
    }

    /**
     * @param  list<array{Data: DataSatuanProduk, Id: int|null, DefaultJual: bool, DefaultBeli: bool, Barcode: list<string>}>  $rencana
     */
    public function Terapkan(Produk $produk, array $rencana, string $sumberHarga): void
    {
        $ada = ProdukSatuan::query()->where('IdProduk', $produk->Id)->lockForUpdate()->get()->keyBy('Id');
        $barcodeAda = ProdukBarcode::query()->where('IdProduk', $produk->Id)->lockForUpdate()->get();
        $idDipertahankan = array_values(array_filter(array_map(fn (array $r): ?int => $r['Id'], $rencana)));
        $barcodeDiinginkan = [];

        foreach ($rencana as $r) {
            foreach ($r['Barcode'] as $barcode) {
                $barcodeDiinginkan[mb_strtolower($barcode)] = true;
            }
        }

        // 1) Barcode yang tidak diinginkan lagi dihapus lebih dulu (barcode boleh pindah satuan dalam satu simpan).
        foreach ($barcodeAda as $baris) {
            if (! isset($barcodeDiinginkan[mb_strtolower($baris->Barcode)])) {
                $this->HapusBarcode($baris);
            }
        }

        // 2) Satuan yang dibuang: harga (Tim 2), sisa barcode, lalu satuan.
        foreach ($ada as $id => $baris) {
            if (! in_array($id, $idDipertahankan, true)) {
                $this->HapusSatuan($baris, $sumberHarga);
            }
        }

        // 3) Satuan dipertahankan diperbarui, satuan baru dibuat; harga awal satuan baru dikumpulkan.
        $hargaAwal = [];
        $barcodeSisa = ProdukBarcode::query()->where('IdProduk', $produk->Id)->get()->keyBy(fn (ProdukBarcode $b): string => mb_strtolower($b->Barcode));

        foreach ($rencana as $r) {
            $baris = $r['Id'] === null ? new ProdukSatuan(['IdProduk' => $produk->Id]) : $ada->get($r['Id']);

            if (! $baris instanceof ProdukSatuan) {
                continue;
            }

            $baris->fill([
                'IdSatuan' => $r['Data']->idSatuan,
                'KonversiKeDasar' => $r['Data']->konversiKeDasar->KeString(),
                'DefaultJual' => $r['DefaultJual'],
                'DefaultBeli' => $r['DefaultBeli'],
            ])->save();

            foreach ($r['Barcode'] as $barcode) {
                $lama = $barcodeSisa->get(mb_strtolower($barcode));

                if ($lama === null) {
                    ProdukBarcode::query()->create(['IdProduk' => $produk->Id, 'IdProdukSatuan' => $baris->Id, 'Barcode' => $barcode]);
                } elseif ($lama->IdProdukSatuan !== $baris->Id) {
                    $lama->fill(['IdProdukSatuan' => $baris->Id])->save();
                }
            }

            if ($r['Id'] === null && $r['Data']->hargaAwal !== []) {
                $hargaAwal[$baris->Id] = $r['Data']->hargaAwal;
            }
        }

        if ($hargaAwal !== []) {
            $this->harga->SimpanHargaDasar($produk, $hargaAwal, $sumberHarga);
        }
    }

    public function HapusSatuan(ProdukSatuan $satuan, string $sumberHarga): void
    {
        $this->harga->HapusHargaSatuan($satuan, $sumberHarga);

        foreach (ProdukBarcode::query()->where('IdProdukSatuan', $satuan->Id)->get() as $barcode) {
            $this->HapusBarcode($barcode);
        }

        $satuan->delete();
        $this->pencatatHapus->Catat(EntitasKatalog::ProdukSatuan, $satuan->Uuid);
    }

    public function HapusBarcode(ProdukBarcode $barcode): void
    {
        $barcode->delete();
        $this->pencatatHapus->Catat(EntitasKatalog::ProdukBarcode, $barcode->Uuid);
    }

    /**
     * @param  Collection<int, ProdukSatuan>  $ada
     */
    private function CekSatuanBaru(DataSatuanProduk $data, Collection $ada): bool
    {
        if ($data->idProdukSatuan === null) {
            return true;
        }

        $baris = $ada->get($data->idProdukSatuan);

        // Satuan tenant diganti pada baris yang sama = satuan lama dibuang, satuan baru dibuat.
        return ! $baris instanceof ProdukSatuan || $baris->IdSatuan !== $data->idSatuan;
    }

    /**
     * @param  list<DataSatuanProduk>  $satuan
     * @param  callable(DataSatuanProduk): bool  $pilih
     */
    private function TentukanBawaan(array $satuan, int $idSatuanDasar, callable $pilih, string $jenis): int
    {
        $terpilih = array_keys(array_filter($satuan, $pilih));

        if (count($terpilih) > 1) {
            throw new PelanggaranAturanBisnis('SatuanProdukGanda', "Pilih satu satuan {$jenis} bawaan saja.", 'Satuan');
        }

        if ($terpilih !== []) {
            return $terpilih[0];
        }

        foreach ($satuan as $i => $data) {
            if ($data->idSatuan === $idSatuanDasar) {
                return $i;
            }
        }

        return 0;
    }

    /**
     * @param  list<string>  $barcode
     * @return list<string>
     */
    private function PeriksaBarcode(?Produk $produk, array $barcode, int $indeksSatuan): array
    {
        $hasil = [];

        foreach ($barcode as $j => $isi) {
            $bidang = "Satuan.{$indeksSatuan}.Barcode.{$j}";
            $normal = AturanProduk::NormalisasiBarcode($isi, $bidang);
            $milikLain = ProdukBarcode::query()
                ->where('Barcode', $normal)
                ->when($produk !== null, fn ($kueri) => $kueri->where('IdProduk', '!=', $produk?->Id))
                ->first();

            if ($milikLain !== null) {
                $nama = Produk::query()->withTrashed()->whereKey($milikLain->IdProduk)->value('Nama');

                throw new PelanggaranAturanBisnis('BR-03.1', "Barcode {$normal} sudah dipakai produk {$nama}.", $bidang);
            }

            $hasil[] = $normal;
        }

        return $hasil;
    }

    /**
     * @param  list<array{Data: DataSatuanProduk, Id: int|null, DefaultJual: bool, DefaultBeli: bool, Barcode: list<string>}>  $rencana
     */
    private function PastikanBarcodeUnikDiForm(array $rencana): void
    {
        $terlihat = [];

        foreach ($rencana as $i => $r) {
            foreach ($r['Barcode'] as $j => $barcode) {
                $kunci = mb_strtolower($barcode);

                if (isset($terlihat[$kunci])) {
                    throw new PelanggaranAturanBisnis('BR-03.1', "Barcode {$barcode} diisi lebih dari sekali.", "Satuan.{$i}.Barcode.{$j}");
                }

                $terlihat[$kunci] = true;
            }
        }
    }
}
