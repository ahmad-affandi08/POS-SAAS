<?php

declare(strict_types=1);

namespace App\Domain\Pajak\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Pajak\Data\DataKelompokPajakTemplate;
use App\Domain\Pajak\Enum\DasarPengenaanPajak;
use App\Domain\Pajak\Enum\KategoriPajakProduk;
use App\Domain\Pajak\Model\JenisPajak;
use App\Domain\Pajak\Model\KelompokPajak;
use App\Domain\Pajak\Model\KelompokPajakDetail;
use App\Domain\Tenant\Layanan\PenguncianTenant;
use Illuminate\Support\Facades\DB;

/**
 * F-01: kelompok pajak template sektor menjadi `KelompokPajak` + `KelompokPajakDetail` tenant. Aditif & idempoten:
 * kelompok dengan nama sama (tanpa beda huruf besar/kecil) dipakai ulang, detail (kelompok, jenis) yang sudah ada
 * tidak diubah. Jenis pajak atau dasar pengenaan yang tidak dikenal dilewati. Tidak ada angka tarif yang disalin:
 * tarif selalu dicari dari `TarifPajak` bertanggal (CLAUDE.md #12). Mengunci baris Tenant sendiri (kirim ganda aman).
 * F-03: `Kategori` kelompok yang masih kosong diisi dari detailnya (`KategoriPajakProduk::TentukanDariKodeJenisPajak`).
 */
final class TambahkanKelompokPajakTemplate
{
    public function __construct(
        private readonly KonteksTenant $konteks,
        private readonly PenguncianTenant $penguncian,
        private readonly PencatatAudit $audit,
    ) {}

    /**
     * @param  list<DataKelompokPajakTemplate>  $daftar
     * @return list<string> nama kelompok yang ditambahkan
     */
    public function Jalankan(array $daftar): array
    {
        return DB::transaction(function () use ($daftar): array {
            // Kunci Tenant sendiri (reentran dalam satu transaksi): aman walau pemanggil belum mengunci.
            $this->penguncian->Kunci($this->konteks->Wajib());
            $kodeJenis = [];

            foreach ($daftar as $kelompok) {
                foreach ($kelompok->detail as $detail) {
                    $kodeJenis[] = $detail['KodeJenisPajak'];
                }
            }

            $idJenis = $kodeJenis === [] ? [] : JenisPajak::query()->whereIn('Kode', array_values(array_unique($kodeJenis)))->pluck('Id', 'Kode')->all();
            $ada = KelompokPajak::query()->get()->keyBy(fn (KelompokPajak $kelompok) => mb_strtolower(trim($kelompok->Nama)));
            $ditambahkan = [];
            $detailBaru = 0;

            foreach ($daftar as $data) {
                $nama = trim($data->nama);

                if ($nama === '') {
                    continue;
                }

                $kelompok = $ada->get(mb_strtolower($nama));

                if (! $kelompok instanceof KelompokPajak) {
                    $kelompok = KelompokPajak::query()->create(['Nama' => $nama]);
                    $ada->put(mb_strtolower($nama), $kelompok);
                    $ditambahkan[] = $nama;
                }

                $jenisAda = KelompokPajakDetail::query()->where('IdKelompokPajak', $kelompok->Id)->pluck('IdJenisPajak')->all();

                foreach ($data->detail as $detail) {
                    $id = $idJenis[$detail['KodeJenisPajak']] ?? null;
                    $dasar = DasarPengenaanPajak::tryFrom($detail['DasarPengenaan']);

                    if (! is_int($id) || $dasar === null || in_array($id, $jenisAda, true)) {
                        continue;
                    }

                    KelompokPajakDetail::query()->create([
                        'IdKelompokPajak' => $kelompok->Id,
                        'IdJenisPajak' => $id,
                        'DasarPengenaan' => $dasar,
                        'Urutan' => $detail['Urutan'],
                    ]);
                    $jenisAda[] = $id;
                    $detailBaru++;
                }

                // F-03: kategori pajak produk diisi dari detailnya (aturan sama dengan migrasi 000124).
                if ($kelompok->Kategori === null) {
                    $kodeDetail = KelompokPajakDetail::query()->where('IdKelompokPajak', $kelompok->Id)->with('JenisPajak')->get()
                        ->map(fn (KelompokPajakDetail $detail): string => $detail->JenisPajak->Kode)->values()->all();
                    $kelompok->fill(['Kategori' => KategoriPajakProduk::TentukanDariKodeJenisPajak($kodeDetail)])->save();
                }
            }

            if ($ditambahkan !== [] || $detailBaru > 0) {
                $this->audit->Catat('kelompok-pajak.tambah-template', nilaiBaru: ['Nama' => $ditambahkan, 'JumlahDetail' => $detailBaru]);
            }

            return $ditambahkan;
        });
    }
}
