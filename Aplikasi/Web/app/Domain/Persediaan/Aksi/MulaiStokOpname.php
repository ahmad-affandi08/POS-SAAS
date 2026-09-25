<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Dokumen\Layanan\PencatatRiwayatStatus;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Katalog\Enum\PelacakanProduk;
use App\Domain\Katalog\Kueri\InfoProdukStok;
use App\Domain\Katalog\Kueri\ProdukKategoriStok;
use App\Domain\Persediaan\Enum\StatusNomorSeri;
use App\Domain\Persediaan\Enum\StatusStokOpname;
use App\Domain\Persediaan\Layanan\PemeriksaLokasiDokumen;
use App\Domain\Persediaan\Layanan\PenomorDokumenPersediaan;
use App\Domain\Persediaan\Model\BatchStok;
use App\Domain\Persediaan\Model\MutasiStok;
use App\Domain\Persediaan\Model\NomorSeri;
use App\Domain\Persediaan\Model\SaldoStok;
use App\Domain\Persediaan\Model\StokOpname;
use App\Domain\Persediaan\Model\StokOpnameDetail;
use Brick\Math\BigDecimal;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Memulai stok opname (F-05b, BR-05.3): lokasi stok aktif (bukan dalam perjalanan), seluruh produk atau satu
 * kategori (beserta sub-kategori). Satu opname aktif per lokasi: opname seluruh produk tidak boleh berbarengan
 * dengan opname lain di lokasi itu, opname kategori tidak boleh berbarengan dengan opname seluruh produk atau
 * kategori yang sama (`OpnameAktifSudahAda`). Snapshot = saldo sistem saat mulai (dibaca dengan kunci baca, sehingga
 * `IdMutasiSnapshot` = mutasi terakhir yang sudah masuk saldo): satu baris per produk bersaldo ≠ 0, per batch bersisa
 * untuk produk batch, per nomor seri tersedia untuk produk seri. Transaksi tetap berjalan selama opname.
 * Idempoten per Uuid klien. Audit `stok-opname.mulai`.
 */
final class MulaiStokOpname
{
    public function __construct(
        private readonly KonteksTenant $konteks,
        private readonly PemeriksaLokasiDokumen $pemeriksaLokasi,
        private readonly ProdukKategoriStok $kategori,
        private readonly InfoProdukStok $infoProduk,
        private readonly PenomorDokumenPersediaan $penomor,
        private readonly PencatatRiwayatStatus $riwayat,
        private readonly PencatatAudit $audit,
    ) {}

    public function Jalankan(int $idGudang, ?string $uuidKategori, bool $hitungButa, ?string $catatan, int $idPengguna, ?string $uuid = null): StokOpname
    {
        if ($uuid !== null) {
            $ada = StokOpname::query()->where('Uuid', $uuid)->first();

            if ($ada !== null) {
                return $ada;
            }
        }

        try {
            return DB::transaction(fn (): StokOpname => $this->Mulai($idGudang, $uuidKategori, $hitungButa, $catatan, $idPengguna, $uuid), max(1, (int) config('persediaan.PercobaanTransaksi', 3)));
        } catch (UniqueConstraintViolationException $galat) {
            if (str_contains($galat->getMessage(), 'UniqStokOpnameIdTenantKunciAktif')) {
                throw new PelanggaranAturanBisnis('OpnameAktifSudahAda', 'Masih ada stok opname yang berjalan untuk lokasi dan kategori ini. Selesaikan atau batalkan dulu.', 'UuidGudang');
            }

            throw $galat;
        }
    }

    private function Mulai(int $idGudang, ?string $uuidKategori, bool $hitungButa, ?string $catatan, int $idPengguna, ?string $uuid): StokOpname
    {
        $gudang = $this->pemeriksaLokasi->AmbilLokasi($idGudang);
        $kategori = null;

        if ($uuidKategori !== null && $uuidKategori !== '') {
            $kategori = $this->kategori->AmbilKategori($uuidKategori) ?? throw new PelanggaranAturanBisnis('KategoriTidakDikenal', 'Kategori tidak ditemukan.', 'UuidKategori');
        }

        // Kunci rentang opname lokasi ini (next-key lock indeks IdTenant, IdGudang, Status) supaya dua mulai bersamaan berurutan.
        $aktif = StokOpname::query()
            ->where('IdGudang', $gudang->id)
            ->whereIn('Status', [StatusStokOpname::Berlangsung->value, StatusStokOpname::Ditinjau->value])
            ->lockForUpdate()
            ->get();

        foreach ($aktif as $lain) {
            if ($kategori === null || $lain->IdKategori === null || $lain->IdKategori === $kategori['Id']) {
                throw new PelanggaranAturanBisnis(
                    'OpnameAktifSudahAda',
                    "Stok opname {$lain->Nomor} masih berjalan di {$gudang->nama}".($lain->NamaKategori !== null ? " (kategori {$lain->NamaKategori})" : '').'. Selesaikan atau batalkan dulu.',
                    'UuidGudang',
                );
            }
        }

        $idProdukKategori = $kategori === null ? null : $this->kategori->AmbilIdProduk($kategori['Id']);
        $tanggal = $this->pemeriksaLokasi->HariIni($gudang->idOutlet);
        $saldo = SaldoStok::query()
            ->where('IdGudang', $gudang->id)
            ->when($idProdukKategori !== null, fn ($k) => $k->whereIn('IdProduk', $idProdukKategori === [] ? [0] : $idProdukKategori))
            ->orderBy('IdProduk')
            ->sharedLock()
            ->get()
            ->keyBy('IdProduk');
        $produk = $this->infoProduk->AmbilBanyak(array_values(array_map('intval', $saldo->keys()->all())), true);
        $batch = BatchStok::query()->where('IdGudang', $gudang->id)->whereIn('IdProduk', $saldo->keys()->all())->where('JumlahSisa', '<>', 0)
            ->orderBy('IdProduk')->orderBy('NomorBatch')->sharedLock()->get()->groupBy('IdProduk');
        $seri = NomorSeri::query()->where('IdGudang', $gudang->id)->where('Status', StatusNomorSeri::Tersedia->value)->whereIn('IdProduk', $saldo->keys()->all())
            ->orderBy('IdProduk')->orderBy('Nomor')->sharedLock()->get()->groupBy('IdProduk');

        $penandaTenant = (int) MutasiStok::query()->max('Id');
        $nomor = $this->penomor->AmbilOpname($tanggal, $gudang->kode);
        $opname = new StokOpname;

        if ($uuid !== null) {
            $opname->Uuid = $uuid;
        }

        $catatan = $catatan === null ? null : trim($catatan);
        $opname->fill([
            'Nomor' => $nomor,
            'IdGudang' => $gudang->id,
            'IdOutlet' => $gudang->idOutlet,
            'IdKategori' => $kategori['Id'] ?? null,
            'NamaKategori' => $kategori === null ? null : mb_substr($kategori['Nama'], 0, 150),
            'HitungButa' => $hitungButa,
            'Status' => StatusStokOpname::Berlangsung,
            'TanggalSnapshot' => $tanggal->format('Y-m-d'),
            'SnapshotPada' => now(),
            'IdMutasiSnapshot' => $penandaTenant,
            'Catatan' => $catatan === '' ? null : $catatan,
            'DibuatOleh' => $idPengguna,
            'DiubahOleh' => $idPengguna,
        ]);
        $opname->save();

        $idTenant = $this->konteks->Wajib();
        $waktu = now();
        $isi = [];
        $urutan = 0;
        $barisDasar = fn (int $idProduk, string $jumlah, int $penanda): array => [
            'IdTenant' => $idTenant,
            'IdStokOpname' => $opname->Id,
            'IdProduk' => $idProduk,
            'NamaProduk' => mb_substr($produk[$idProduk]->nama ?? '', 0, 150),
            'Sku' => $produk[$idProduk]->sku ?? null,
            'IdBatchStok' => null,
            'NomorBatch' => null,
            'TanggalKedaluwarsa' => null,
            'IdNomorSeri' => null,
            'NomorSeri' => null,
            'DariSnapshot' => true,
            'JumlahSistem' => $jumlah,
            'IdMutasiSnapshot' => $penanda,
            'DibuatPada' => $waktu,
            'DiubahPada' => $waktu,
        ];

        $urutProduk = $saldo->all();
        uasort($urutProduk, fn (SaldoStok $a, SaldoStok $b): int => [$produk[$a->IdProduk]->nama ?? '', $a->IdProduk] <=> [$produk[$b->IdProduk]->nama ?? '', $b->IdProduk]);

        foreach ($urutProduk as $s) {
            $info = $produk[$s->IdProduk] ?? null;
            $penanda = (int) ($s->IdMutasiStokTerakhir ?? 0);

            if ($info === null || ! $info->jenis->CekPunyaStok()) {
                continue;
            }

            if ($info->pelacakan === PelacakanProduk::Batch) {
                foreach ($batch->get($s->IdProduk, collect()) as $b) {
                    $isi[] = ['Urutan' => ++$urutan, ...$barisDasar($s->IdProduk, (string) $b->JumlahSisa, $penanda), 'IdBatchStok' => $b->Id, 'NomorBatch' => $b->NomorBatch, 'TanggalKedaluwarsa' => $b->TanggalKedaluwarsa?->format('Y-m-d')];
                }

                continue;
            }

            if ($info->pelacakan === PelacakanProduk::Seri) {
                foreach ($seri->get($s->IdProduk, collect()) as $n) {
                    $isi[] = ['Urutan' => ++$urutan, ...$barisDasar($s->IdProduk, '1.0000', $penanda), 'IdNomorSeri' => $n->Id, 'NomorSeri' => $n->Nomor];
                }

                continue;
            }

            if (! BigDecimal::of($s->JumlahTersedia)->isZero()) {
                $isi[] = ['Urutan' => ++$urutan, ...$barisDasar($s->IdProduk, (string) $s->JumlahTersedia, $penanda)];
            }
        }

        foreach (array_chunk($isi, 500) as $potongan) {
            StokOpnameDetail::query()->insert($potongan);
        }

        $opname->JumlahBaris = count($isi);
        $opname->save();

        $this->riwayat->Catat(StokOpname::JENIS_DOKUMEN, $opname->Id, null, StatusStokOpname::Berlangsung->value, $idPengguna);
        $this->audit->Catat('stok-opname.mulai', $opname, null, [
            'Nomor' => $nomor,
            'IdGudang' => $gudang->id,
            'NamaGudang' => $gudang->nama,
            'Kategori' => $opname->NamaKategori,
            'HitungButa' => $hitungButa,
            'JumlahBaris' => $opname->JumlahBaris,
        ], idPengguna: $idPengguna);

        return $opname;
    }
}
