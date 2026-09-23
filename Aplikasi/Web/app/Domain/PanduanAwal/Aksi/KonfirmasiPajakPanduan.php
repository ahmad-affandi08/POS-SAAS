<?php

declare(strict_types=1);

namespace App\Domain\PanduanAwal\Aksi;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Organisasi\Aksi\SimpanProfilPajakOutlet;
use App\Domain\Organisasi\Data\DataProfilPajakOutlet;
use App\Domain\Organisasi\Model\Outlet;
use App\Domain\PanduanAwal\Data\DataPajakPanduan;
use App\Domain\PanduanAwal\Enum\LangkahPanduan;
use App\Domain\PanduanAwal\Enum\StatusLangkahPanduan;
use App\Domain\Tenant\Kueri\ProfilTenant;
use Illuminate\Support\Facades\DB;

/**
 * F-01 langkah 3: pemilik mengonfirmasi usulan pajak (PBJT, service charge, harga termasuk pajak). Status PKP
 * mengikuti profil usaha (langkah 1). PBJT butuh kota outlet karena tarifnya per daerah; tarif selalu dari
 * `TarifPajak` bertanggal (CLAUDE.md #12), tidak disimpan di outlet. Tarif kota yang belum ada di master tidak
 * menghalangi penyimpanan (H4).
 */
final class KonfirmasiPajakPanduan
{
    public function __construct(
        private readonly KonteksTenant $konteks,
        private readonly ProfilTenant $profilTenant,
        private readonly SimpanProfilPajakOutlet $simpanProfilPajak,
        private readonly TandaiLangkahPanduan $tandai,
    ) {}

    public function Jalankan(Outlet $outlet, DataPajakPanduan $data): void
    {
        if ($data->pungutPbjt && $outlet->KodeKota === null) {
            throw new PelanggaranAturanBisnis('KotaBelumDiisi', 'Isi kota outlet di langkah Profil usaha dulu. Tarif PBJT mengikuti kota.', 'PungutPbjt');
        }

        $pkp = $this->profilTenant->Ambil($this->konteks->Wajib())['Pkp'];

        DB::transaction(function () use ($outlet, $data, $pkp): void {
            $this->simpanProfilPajak->Jalankan($outlet, new DataProfilPajakOutlet(
                pkp: $pkp,
                pungutPbjt: $data->pungutPbjt,
                biayaLayananAktif: $data->biayaLayananAktif,
                persenBiayaLayanan: $data->biayaLayananAktif ? $data->persenBiayaLayanan : '0',
                hargaTermasukPajak: $data->hargaTermasukPajak,
            ));
            $this->tandai->Jalankan(LangkahPanduan::Pajak, StatusLangkahPanduan::Selesai, $outlet->Id);
        });
    }
}
