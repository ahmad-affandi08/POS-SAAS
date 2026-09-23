<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Organisasi\Enum\IzinTenant;
use App\Domain\Organisasi\Kueri\AksesPengguna;
use App\Domain\Organisasi\Model\Peran;
use App\Domain\Organisasi\Model\PeranIzin;
use Illuminate\Support\Facades\DB;

/**
 * Membuat atau mengubah peran kustom (PRD §19.1: Owner dapat membuat peran kustom dari izin granular).
 * - Peran bawaan tidak bisa diubah; salin menjadi peran kustom bila perlu variasi.
 * - Izin khusus Owner (langganan) tidak bisa dimasukkan ke peran kustom.
 * - Anti-eskalasi: pelaku hanya bisa memberi izin yang ia miliki sendiri.
 */
final class SimpanPeran
{
    public function __construct(
        private readonly KonteksTenant $konteks,
        private readonly AksesPengguna $akses,
        private readonly PencatatAudit $audit,
    ) {}

    /**
     * @param  list<string>  $kunciIzin
     */
    public function Jalankan(int $idPelaku, ?Peran $peran, string $nama, ?string $keterangan, array $kunciIzin): Peran
    {
        return DB::transaction(function () use ($idPelaku, $peran, $nama, $keterangan, $kunciIzin): Peran {
            $nama = trim($nama);
            $kunciIzin = $this->ValidasiIzin($idPelaku, $kunciIzin);

            if ($peran?->Bawaan === true) {
                throw new PelanggaranAturanBisnis('PeranBawaan', 'Peran bawaan tidak bisa diubah. Buat peran kustom baru bila perlu variasi.');
            }

            $kembar = Peran::query()->where('Nama', $nama)->when($peran !== null, fn ($kueri) => $kueri->whereKeyNot($peran?->Id))->exists();

            if ($kembar) {
                throw new PelanggaranAturanBisnis('PeranKembar', "Peran {$nama} sudah ada.", 'Nama');
            }

            $isian = ['Nama' => $nama, 'Keterangan' => $keterangan === null || trim($keterangan) === '' ? null : trim($keterangan)];

            if ($peran === null) {
                $peran = Peran::query()->create([...$isian, 'Bawaan' => false]);
                $this->SimpanIzin($peran, $kunciIzin);
                $this->audit->Catat('peran.buat', $peran, nilaiBaru: [...$isian, 'Izin' => $kunciIzin]);

                return $peran;
            }

            $peran = Peran::query()->lockForUpdate()->findOrFail($peran->Id);
            $lama = [...$peran->only(['Nama', 'Keterangan']), 'Izin' => $peran->AmbilKunciIzin()];
            $peran->update($isian);
            $this->SimpanIzin($peran, $kunciIzin);
            $baru = [...$isian, 'Izin' => $kunciIzin];

            if ($lama != $baru) {
                $this->audit->Catat('peran.ubah', $peran, nilaiLama: $lama, nilaiBaru: $baru);
            }

            return $peran;
        });
    }

    /**
     * @param  list<string>  $kunciIzin
     * @return list<string>
     */
    private function ValidasiIzin(int $idPelaku, array $kunciIzin): array
    {
        $izin = [];

        foreach (array_unique($kunciIzin) as $kunci) {
            $izin[] = IzinTenant::tryFrom($kunci) ?? throw new PelanggaranAturanBisnis('IzinTidakDikenal', 'Ada izin yang tidak dikenal.', 'Izin');
        }

        if ($izin === []) {
            throw new PelanggaranAturanBisnis('IzinKosong', 'Pilih minimal satu izin.', 'Izin');
        }

        $milikPelaku = $this->akses->Ambil($this->konteks->Wajib(), $idPelaku);

        foreach ($izin as $satu) {
            if ($satu->CekKhususPemilik()) {
                throw new PelanggaranAturanBisnis('IzinKhususPemilik', "Izin \"{$satu->AmbilLabel()}\" hanya untuk Pemilik dan tidak bisa dimasukkan ke peran kustom.", 'Izin');
            }

            if ($milikPelaku === null || (! $milikPelaku['Pemilik'] && ! in_array($satu->value, $milikPelaku['Izin'], true))) {
                throw new PelanggaranAturanBisnis('IzinMelebihiPelaku', "Anda tidak bisa memberi izin \"{$satu->AmbilLabel()}\" karena Anda sendiri tidak memilikinya.", 'Izin');
            }
        }

        $kunci = array_map(fn (IzinTenant $satu) => $satu->value, $izin);
        sort($kunci);

        return $kunci;
    }

    /**
     * @param  list<string>  $kunciIzin
     */
    private function SimpanIzin(Peran $peran, array $kunciIzin): void
    {
        PeranIzin::query()->where('IdPeran', $peran->Id)->whereNotIn('KunciIzin', $kunciIzin)->delete();
        $ada = PeranIzin::query()->where('IdPeran', $peran->Id)->pluck('KunciIzin')->all();

        foreach (array_diff($kunciIzin, $ada) as $kunci) {
            PeranIzin::query()->create(['IdPeran' => $peran->Id, 'KunciIzin' => $kunci]);
        }

        $peran->unsetRelation('Izin');
        $this->akses->Lupakan();
    }
}
