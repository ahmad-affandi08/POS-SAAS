<?php

declare(strict_types=1);

namespace App\Domain\Pajak\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Pajak\Enum\DasarPengenaanPajak;
use App\Domain\Pajak\Enum\KategoriPajakProduk;
use App\Domain\Pajak\Model\JenisPajak;
use App\Domain\Pajak\Model\KelompokPajak;
use App\Domain\Pajak\Model\KelompokPajakDetail;
use App\Domain\Tenant\Layanan\PenguncianTenant;
use Illuminate\Support\Facades\DB;

/**
 * F-03 §12.2: membuat atau mengubah kelompok pajak tenant beserta kategori pajak produknya.
 * - Nama unik per tenant tanpa beda huruf besar/kecil (`KelompokPajakGanda`).
 * - Detail pajak diganti seluruhnya (Urutan = posisi, mulai 1). Kesesuaian kategori (`KelompokPajakTidakSesuai`):
 *   KenaPpn wajib `Ppn` tanpa `PbjtMakananMinuman`; KenaPbjt wajib `PbjtMakananMinuman` tanpa `Ppn` (§12.1);
 *   BebasPpn & NonPajak tanpa detail; Lainnya tanpa `Ppn`/`PbjtMakananMinuman`. Jenis pajak tidak boleh ganda.
 * - Tidak ada angka tarif yang disalin: tarif dicari dari `TarifPajak` bertanggal saat transaksi (CLAUDE.md #12).
 * - Audit `kelompok-pajak.buat` / `kelompok-pajak.ubah`.
 *
 * Urutan kunci: Tenant → baris kelompok pajak.
 */
final class SimpanKelompokPajak
{
    public const KODE_PPN = 'Ppn';

    public const KODE_PBJT = 'PbjtMakananMinuman';

    public function __construct(
        private readonly KonteksTenant $konteks,
        private readonly PenguncianTenant $penguncian,
        private readonly PencatatAudit $audit,
    ) {}

    /**
     * @param  list<array{KodeJenisPajak: string, DasarPengenaan: DasarPengenaanPajak}>  $pajak
     */
    public function Jalankan(?KelompokPajak $kelompok, string $nama, KategoriPajakProduk $kategori, array $pajak): KelompokPajak
    {
        $idTenant = $this->konteks->Wajib();
        $nama = trim($nama);

        if ($nama === '' || mb_strlen($nama) > 60) {
            throw new PelanggaranAturanBisnis('NamaTidakValid', 'Nama kelompok pajak wajib diisi, maksimal 60 karakter.', 'Nama');
        }

        return DB::transaction(function () use ($idTenant, $kelompok, $nama, $kategori, $pajak): KelompokPajak {
            $this->penguncian->Kunci($idTenant);

            if ($kelompok !== null) {
                $kelompok = KelompokPajak::query()->whereKey($kelompok->Id)->lockForUpdate()->firstOrFail();
            }

            $ganda = KelompokPajak::query()
                ->whereRaw('LOWER(Nama) = ?', [mb_strtolower($nama)])
                ->when($kelompok !== null, fn ($kueri) => $kueri->whereKeyNot($kelompok?->Id))
                ->exists();

            if ($ganda) {
                throw new PelanggaranAturanBisnis('KelompokPajakGanda', 'Nama kelompok pajak ini sudah dipakai.', 'Nama');
            }

            $detailBaru = $this->SiapkanDetail($kategori, $pajak);
            $nilaiLama = $kelompok === null ? null : $this->Ringkas($kelompok);

            if ($kelompok === null) {
                $kelompok = KelompokPajak::query()->create(['Nama' => $nama, 'Kategori' => $kategori]);
            } else {
                $kelompok->fill(['Nama' => $nama, 'Kategori' => $kategori]);
            }

            $detailLama = KelompokPajakDetail::query()->where('IdKelompokPajak', $kelompok->Id)->orderBy('Urutan')->get()
                ->map(fn (KelompokPajakDetail $detail): array => [$detail->IdJenisPajak, $detail->DasarPengenaan->value, $detail->Urutan])->all();
            $detailBerubah = $detailLama !== array_map(fn (array $detail): array => [$detail['IdJenisPajak'], $detail['DasarPengenaan']->value, $detail['Urutan']], $detailBaru);

            if ($detailBerubah) {
                KelompokPajakDetail::query()->where('IdKelompokPajak', $kelompok->Id)->delete();

                foreach ($detailBaru as $detail) {
                    KelompokPajakDetail::query()->create(['IdKelompokPajak' => $kelompok->Id] + $detail);
                }
            }

            if ($nilaiLama === null) {
                $this->audit->Catat('kelompok-pajak.buat', $kelompok, nilaiBaru: $this->Ringkas($kelompok));

                return $kelompok;
            }

            if ($kelompok->isDirty() || $detailBerubah) {
                // `DiubahPada` ikut diperbarui saat hanya detail yang berubah, agar katalog POS delta mengirim ulang.
                $kelompok->isDirty() ? $kelompok->save() : $kelompok->touch();
                $this->audit->Catat('kelompok-pajak.ubah', $kelompok, $nilaiLama, $this->Ringkas($kelompok));
            }

            return $kelompok;
        });
    }

    /**
     * @param  list<array{KodeJenisPajak: string, DasarPengenaan: DasarPengenaanPajak}>  $pajak
     * @return list<array{IdJenisPajak: int, DasarPengenaan: DasarPengenaanPajak, Urutan: int}>
     */
    private function SiapkanDetail(KategoriPajakProduk $kategori, array $pajak): array
    {
        $kode = array_map(fn (array $detail): string => $detail['KodeJenisPajak'], $pajak);
        $idJenis = $kode === [] ? [] : JenisPajak::query()->whereIn('Kode', array_values(array_unique($kode)))->pluck('Id', 'Kode')->all();
        $hasil = [];

        foreach (array_values($pajak) as $i => $detail) {
            $id = $idJenis[$detail['KodeJenisPajak']] ?? null;

            if (! is_int($id)) {
                throw new PelanggaranAturanBisnis('KelompokPajakTidakSesuai', 'Jenis pajak ini tidak dikenal.', "Pajak.{$i}.KodeJenisPajak");
            }

            if (in_array($detail['KodeJenisPajak'], array_slice($kode, 0, $i), true)) {
                throw new PelanggaranAturanBisnis('KelompokPajakTidakSesuai', 'Jenis pajak yang sama tidak boleh dipilih dua kali.', "Pajak.{$i}.KodeJenisPajak");
            }

            $hasil[] = ['IdJenisPajak' => $id, 'DasarPengenaan' => $detail['DasarPengenaan'], 'Urutan' => $i + 1];
        }

        $adaPpn = in_array(self::KODE_PPN, $kode, true);
        $adaPbjt = in_array(self::KODE_PBJT, $kode, true);
        $pesan = match ($kategori) {
            KategoriPajakProduk::KenaPpn => ! $adaPpn || $adaPbjt ? 'Kategori Kena PPN wajib berisi PPN dan tidak boleh berisi PB1 (PBJT).' : null,
            KategoriPajakProduk::KenaPbjt => ! $adaPbjt || $adaPpn ? 'Kategori Kena PB1 wajib berisi PB1 (PBJT) dan tidak boleh berisi PPN.' : null,
            KategoriPajakProduk::BebasPpn, KategoriPajakProduk::NonPajak => $pajak !== [] ? 'Kategori ini tidak boleh berisi pajak.' : null,
            KategoriPajakProduk::Lainnya => $adaPpn || $adaPbjt ? 'Kategori Pajak lain tidak boleh berisi PPN atau PB1 (PBJT). Pilih kategori Kena PPN atau Kena PB1.' : null,
        };

        if ($pesan !== null) {
            throw new PelanggaranAturanBisnis('KelompokPajakTidakSesuai', $pesan, 'Pajak');
        }

        return $hasil;
    }

    /**
     * @return array{Nama: string, Kategori: string|null, Pajak: list<array{KodeJenisPajak: string, DasarPengenaan: string}>}
     */
    private function Ringkas(KelompokPajak $kelompok): array
    {
        $pajak = [];

        foreach (KelompokPajakDetail::query()->where('IdKelompokPajak', $kelompok->Id)->with('JenisPajak')->orderBy('Urutan')->get() as $detail) {
            $pajak[] = ['KodeJenisPajak' => $detail->JenisPajak->Kode, 'DasarPengenaan' => $detail->DasarPengenaan->value];
        }

        return ['Nama' => $kelompok->Nama, 'Kategori' => $kelompok->Kategori?->value, 'Pajak' => $pajak];
    }
}
