<?php

declare(strict_types=1);

namespace App\Domain\Karyawan\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Karyawan\Enum\CakupanTargetPenjualan;
use App\Domain\Karyawan\Enum\StatusKaryawan;
use App\Domain\Karyawan\Model\Karyawan;
use App\Domain\Karyawan\Model\TargetPenjualan;
use App\Domain\Organisasi\Kueri\PetaUuidOutlet;
use Illuminate\Support\Facades\DB;

/**
 * Target penjualan bulanan (F-18 bagian 3, EMP-05): simpan = buat atau ganti nilai target satu sasaran (outlet yang
 * boleh diakses pengguna, atau karyawan aktif) di satu periode `YYYY-MM`; nilai > 0. Hapus target. Audit
 * `target-penjualan.simpan|hapus`. Target tidak memengaruhi stok, jurnal, atau komisi.
 */
final class SimpanTargetPenjualan
{
    public function __construct(private readonly PetaUuidOutlet $outlet, private readonly PencatatAudit $audit) {}

    /**
     * @param  list<int>|null  $idOutletBoleh  null = semua outlet
     */
    public function Jalankan(string $periode, CakupanTargetPenjualan $cakupan, string $uuidSasaran, Uang $nilai, int $idPengguna, ?array $idOutletBoleh): TargetPenjualan
    {
        if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $periode) !== 1) {
            throw new PelanggaranAturanBisnis('PeriodeTidakValid', 'Periode harus berformat tahun-bulan, misal 2026-10.', 'Periode');
        }

        if ($nilai->BernilaiNol() || $nilai->BernilaiNegatif()) {
            throw new PelanggaranAturanBisnis('NilaiTidakValid', 'Target harus lebih dari 0.', 'Nilai');
        }

        [$idOutlet, $idKaryawan] = $this->CariSasaran($cakupan, $uuidSasaran, $idOutletBoleh);
        $kunci = $cakupan->value.':'.($idOutlet ?? $idKaryawan);

        return DB::transaction(function () use ($periode, $cakupan, $idOutlet, $idKaryawan, $kunci, $nilai, $idPengguna): TargetPenjualan {
            $target = TargetPenjualan::query()->where('Periode', $periode)->where('KunciSasaran', $kunci)->lockForUpdate()->first();
            $lama = $target?->Nilai;
            $target ??= new TargetPenjualan([
                'Periode' => $periode,
                'Cakupan' => $cakupan,
                'IdOutlet' => $idOutlet,
                'IdKaryawan' => $idKaryawan,
                'KunciSasaran' => $kunci,
                'DibuatOleh' => $idPengguna,
            ]);
            $target->Nilai = $nilai->KeString();
            $target->save();
            $this->audit->Catat('target-penjualan.simpan', $target, nilaiLama: $lama === null ? null : ['Nilai' => $lama], nilaiBaru: ['Periode' => $periode, 'Sasaran' => $kunci, 'Nilai' => $target->Nilai]);

            return $target;
        });
    }

    public function Hapus(TargetPenjualan $target): void
    {
        DB::transaction(function () use ($target): void {
            $this->audit->Catat('target-penjualan.hapus', $target, nilaiLama: ['Periode' => $target->Periode, 'Sasaran' => $target->KunciSasaran, 'Nilai' => $target->Nilai]);
            $target->delete();
        });
    }

    /**
     * @param  list<int>|null  $idOutletBoleh
     * @return array{0: int|null, 1: int|null}
     */
    private function CariSasaran(CakupanTargetPenjualan $cakupan, string $uuid, ?array $idOutletBoleh): array
    {
        if ($cakupan === CakupanTargetPenjualan::Outlet) {
            $id = $this->outlet->AmbilIdDariUuid([$uuid])[$uuid] ?? null;

            if ($id === null || ($idOutletBoleh !== null && ! in_array($id, $idOutletBoleh, true))) {
                throw new PelanggaranAturanBisnis('OutletTidakDitemukan', 'Pilih outlet.', 'Sasaran');
            }

            return [$id, null];
        }

        $id = Karyawan::query()->where('Uuid', $uuid)->where('Status', StatusKaryawan::Aktif->value)->value('Id');

        if ($id === null) {
            throw new PelanggaranAturanBisnis('KaryawanTidakAktif', 'Pilih karyawan yang aktif.', 'Sasaran');
        }

        return [null, (int) $id];
    }
}
