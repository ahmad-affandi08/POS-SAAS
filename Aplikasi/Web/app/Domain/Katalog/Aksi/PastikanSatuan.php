<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Katalog\Data\DataSatuanStandar;
use App\Domain\Katalog\Model\Satuan;
use App\Domain\Referensi\Kueri\SatuanStandarAktif;
use App\Domain\Tenant\Layanan\PenguncianTenant;
use Illuminate\Support\Facades\DB;

/**
 * F-03 impor: satuan tenant untuk teks "pcs"/"Kilogram"/"KG". Urutan pencocokan (tanpa beda huruf besar/kecil):
 * `Satuan.Nama`, `Simbol`, atau `KodeStandar` tenant → satuan standar platform aktif dengan kode itu (disalin lewat
 * `PastikanSatuanStandar`) → satuan buatan tenant baru bila `bolehBuat` (nama = simbol = teks). Selain itu null.
 */
final class PastikanSatuan
{
    public function __construct(
        private readonly KonteksTenant $konteks,
        private readonly PenguncianTenant $penguncian,
        private readonly SatuanStandarAktif $satuanStandar,
        private readonly PastikanSatuanStandar $pastikanStandar,
        private readonly PencatatAudit $audit,
    ) {}

    public function Jalankan(string $namaAtauSimbol, bool $bolehBuat): ?Satuan
    {
        $teks = trim($namaAtauSimbol);

        if ($teks === '' || mb_strlen($teks) > 100) {
            return null;
        }

        return DB::transaction(function () use ($teks, $bolehBuat): ?Satuan {
            $this->penguncian->Kunci($this->konteks->Wajib());
            $kecil = mb_strtolower($teks);
            $ada = Satuan::query()
                ->where(fn ($kueri) => $kueri->whereRaw('LOWER(Nama) = ?', [$kecil])->orWhereRaw('LOWER(Simbol) = ?', [$kecil])->orWhereRaw('LOWER(KodeStandar) = ?', [$kecil]))
                ->orderByRaw('LOWER(Nama) = ? DESC', [$kecil])
                ->orderBy('Id')
                ->first();

            if ($ada !== null) {
                return $ada;
            }

            $standar = $this->satuanStandar->AmbilBerdasarkanKode([mb_strtoupper($teks)])[0] ?? null;

            if ($standar !== null) {
                return Satuan::query()->findOrFail($this->pastikanStandar->Jalankan(new DataSatuanStandar($standar['Kode'], $standar['Nama'], $standar['Simbol'], $standar['BolehDesimal'])));
            }

            if (! $bolehBuat || mb_strlen($teks) > 20) {
                return null;
            }

            $satuan = Satuan::query()->create(['KodeStandar' => null, 'Nama' => $teks, 'Simbol' => $teks, 'BolehDesimal' => false]);
            $this->audit->Catat('satuan.buat', $satuan, null, $satuan->only(['Nama', 'Simbol', 'BolehDesimal']));

            return $satuan;
        });
    }
}
