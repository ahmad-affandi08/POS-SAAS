<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Organisasi\Data\DataGudang;
use App\Domain\Organisasi\Enum\StatusOrganisasi;
use App\Domain\Organisasi\Kueri\LokasiStokOutlet;
use App\Domain\Organisasi\Model\Gudang;
use App\Domain\Organisasi\Model\Outlet;
use Illuminate\Support\Facades\DB;

/**
 * F-02 langkah 2: tambah atau ubah lokasi stok di satu outlet (misal "Gudang Belakang", "Dapur", "Bar").
 * Kode gudang unik per tenant. Mengubah jenis tidak boleh menghilangkan lokasi stok jual terakhir (BR-02.4).
 */
final class SimpanGudang
{
    private const KOLOM_AUDIT = ['IdOutlet', 'Kode', 'Nama', 'Jenis'];

    public function __construct(
        private readonly LokasiStokOutlet $lokasiStok,
        private readonly PencatatAudit $audit,
    ) {}

    public function Jalankan(Outlet $outlet, ?Gudang $gudang, DataGudang $data): Gudang
    {
        return DB::transaction(function () use ($outlet, $gudang, $data): Gudang {
            $kode = mb_strtoupper(trim($data->kode));
            $dipakai = Gudang::query()->where('Kode', $kode)->when($gudang !== null, fn ($kueri) => $kueri->whereKeyNot($gudang?->Id))->exists();

            if ($dipakai) {
                throw new PelanggaranAturanBisnis('KodeGudangDipakai', "Kode {$kode} sudah dipakai lokasi stok lain. Pilih kode lain.", 'Kode');
            }

            $isian = ['Nama' => trim($data->nama), 'Kode' => $kode, 'Jenis' => $data->jenis];

            if ($gudang === null) {
                if ($outlet->Status !== StatusOrganisasi::Aktif) {
                    throw new PelanggaranAturanBisnis('OutletDiarsipkan', 'Outlet ini diarsipkan. Pulihkan outlet dulu untuk menambah lokasi stok.');
                }

                $gudang = Gudang::query()->create(['IdOutlet' => $outlet->Id, ...$isian]);
                $this->audit->Catat('gudang.buat', $gudang, nilaiBaru: $gudang->only(self::KOLOM_AUDIT));

                return $gudang;
            }

            $gudang = Gudang::query()->lockForUpdate()->findOrFail($gudang->Id);
            $lama = $gudang->only(self::KOLOM_AUDIT);

            if (! $data->jenis->CekLokasiStokJual() && $gudang->Jenis->CekLokasiStokJual() && $gudang->Status === StatusOrganisasi::Aktif
                && $this->lokasiStok->HitungLokasiStokJual($outlet->Id, kecualiIdGudang: $gudang->Id) === 0) {
                throw new PelanggaranAturanBisnis('BR-02.4', 'Setiap outlet wajib punya minimal satu lokasi stok untuk barang jual. Tambah lokasi lain dulu.', 'Jenis');
            }

            $gudang->fill($isian);
            $berubah = array_keys($gudang->getDirty());

            if ($berubah !== []) {
                $gudang->save();
                $this->audit->Catat('gudang.ubah', $gudang, nilaiLama: array_intersect_key($lama, array_flip($berubah)), nilaiBaru: $gudang->only($berubah));
            }

            return $gudang;
        });
    }
}
