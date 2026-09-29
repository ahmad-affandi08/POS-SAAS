<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Kueri;

use App\Domain\Katalog\Data\DataInfoProdukStok;
use App\Domain\Katalog\Kueri\InfoProdukStok;
use App\Domain\Katalog\Kueri\SatuanProdukJual;
use App\Domain\Organisasi\Kueri\PetaUuidOutlet;
use App\Domain\Pelanggan\Kueri\IdentitasPelanggan;
use App\Domain\Penjualan\Model\PesananGrosir;
use App\Domain\Penjualan\Model\PesananGrosirDetail;

/**
 * Isi ulang formulir draf SO grosir (F-12, §9.7) dari dokumen yang tersimpan: nilai header dan barisnya dalam bentuk
 * yang sama dengan yang dikirim formulir (Uuid, bukan Id), sehingga halaman Ubah memakai satu komponen dengan Buat.
 */
final class IsianFormGrosir
{
    public function __construct(
        private readonly IdentitasPelanggan $identitas,
        private readonly PetaUuidOutlet $petaOutlet,
        private readonly InfoProdukStok $infoProduk,
        private readonly SatuanProdukJual $satuanJual,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function Pesanan(PesananGrosir $pesanan): array
    {
        $baris = PesananGrosirDetail::query()->where('IdPesananGrosir', $pesanan->Id)->orderBy('Urutan')->get();
        $pelanggan = $this->identitas->AmbilNamaBanyak([$pesanan->IdPelanggan])[$pesanan->IdPelanggan] ?? null;
        $idProduk = array_values(array_unique(array_filter($baris->pluck('IdProduk')->all(), 'is_int')));
        $uuidProduk = array_map(fn (DataInfoProdukStok $p): string => $p->uuid, $this->infoProduk->AmbilBanyak($idProduk, true));
        $uuidSatuan = [];

        foreach ($this->satuanJual->AmbilUntukProduk($idProduk) as $daftar) {
            foreach ($daftar as $satuan) {
                $uuidSatuan[$satuan['Id']] = $satuan['Uuid'];
            }
        }

        return [
            'Uuid' => $pesanan->Uuid,
            'Nomor' => $pesanan->Nomor,
            'UuidPelanggan' => $pelanggan['Uuid'] ?? null,
            'NamaPelanggan' => $pelanggan['Nama'] ?? '',
            'UuidOutlet' => $this->petaOutlet->Ambil([$pesanan->IdOutlet])[$pesanan->IdOutlet] ?? null,
            'Tanggal' => $pesanan->Tanggal->format('Y-m-d'),
            'TanggalKirimDiminta' => $pesanan->TanggalKirimDiminta?->format('Y-m-d'),
            'Catatan' => $pesanan->Catatan,
            'Baris' => array_values($baris->map(fn (PesananGrosirDetail $d): array => [
                'UuidProduk' => $uuidProduk[$d->IdProduk] ?? '',
                'UuidProdukSatuan' => $d->IdProdukSatuan === null ? '' : ($uuidSatuan[$d->IdProdukSatuan] ?? ''),
                'NamaProduk' => $d->NamaProduk,
                'SimbolSatuan' => $d->SimbolSatuan,
                'Jumlah' => $d->Jumlah,
                'Diskon' => $d->Diskon,
                'Harga' => $d->Harga,
            ])->all()),
        ];
    }
}
