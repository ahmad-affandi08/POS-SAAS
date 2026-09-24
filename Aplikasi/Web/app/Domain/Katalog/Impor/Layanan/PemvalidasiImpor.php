<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Impor\Layanan;

use App\Domain\Katalog\Enum\JenisProduk;
use App\Domain\Katalog\Impor\Data\DataOpsiImpor;
use App\Domain\Katalog\Impor\Enum\AksiBarisImpor;
use App\Domain\Katalog\Impor\Enum\ModeImpor;
use App\Domain\Katalog\Impor\Enum\StatusBarisImpor;
use App\Domain\Katalog\Impor\Enum\StatusImporProduk;
use App\Domain\Katalog\Impor\Model\ImporProduk;
use App\Domain\Katalog\Impor\Model\ImporProdukBaris;
use App\Domain\Katalog\Model\Produk;
use App\Domain\Katalog\Model\ProdukBarcode;
use Closure;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Validasi impor produk (F-03 BR-03.6), dijalankan `ValidasiImporProdukTugas`.
 *
 * Fase A (per baris): berkas dibaca ulang secara streaming; baris dengan `NomorBaris` ≤ baris tersimpan terakhir
 * dilewati (lanjutan setelah anggaran waktu habis). Setiap baris diurai `ValidatorBarisImpor` lalu disimpan per 500
 * (`insertOrIgnore` + indeks unik → aman bila dua tugas berjalan). Lebih dari `katalog.Impor.MaksimalBaris` →
 * Gagal `BarisTerlaluBanyak`.
 *
 * Fase B (antar baris, per potongan 1000): SKU ganda / barcode ganda / produk sama tanpa SKU di berkas → semua baris
 * itu Galat; lalu rencana tiap baris valid: SKU (atau nama untuk produk tanpa SKU, nama induk + nilai varian untuk
 * varian) sudah ada → `Perbarui` (mode TambahDanPerbarui) atau `Dilewati` (TambahSaja), selain itu `Buat`. Barcode
 * milik produk lain di database → Galat. Produk baru yang bisa dijual tanpa kelompok pajak (sel, atau bawaan) → Galat.
 * Hasil fase B dihitung ulang dari data, sehingga menjalankannya lagi memberi hasil sama.
 */
final class PemvalidasiImpor
{
    private const UKURAN_SISIP = 500;

    private const UKURAN_POTONGAN_RENCANA = 1000;

    public function __construct(
        private readonly PembacaBerkasTabel $pembaca,
        private readonly PembacaPresetImpor $presetImpor,
        private readonly ValidatorBarisImpor $validator,
        private readonly PenyimpanBerkasImpor $penyimpan,
    ) {}

    /** True bila validasi selesai (Pratinjau atau Gagal); false bila anggaran waktu habis dan perlu dilanjutkan. */
    public function Jalankan(ImporProduk $impor, int $batasDetik): bool
    {
        $mulai = hrtime(true);
        $habis = fn (): bool => hrtime(true) - $mulai >= $batasDetik * 1_000_000_000;

        $selesaiFaseA = $this->penyimpan->DenganPathLokal($impor->PathBerkas, fn (string $path): ?bool => $this->JalankanFaseA($impor, $path, $habis));

        if ($selesaiFaseA === false) {
            return true;
        }

        if ($selesaiFaseA === null) {
            return false;
        }

        $this->JalankanFaseB($impor);

        return true;
    }

    /**
     * @param  Closure(): bool  $habis
     * @return bool|null true = selesai, false = impor digagalkan, null = anggaran waktu habis
     */
    private function JalankanFaseA(ImporProduk $impor, string $path, Closure $habis): ?bool
    {
        $preset = $this->presetImpor->Ambil($impor->Sumber);
        $opsi = DataOpsiImpor::DariArray($impor->Opsi ?? []);
        $pembaca = $impor->AmbilOpsiPembaca();
        $pemetaan = $impor->Pemetaan ?? [];
        $maksimal = (int) config('katalog.Impor.MaksimalBaris', 20000);
        $terakhir = (int) ImporProdukBaris::query()->where('IdImporProduk', $impor->Id)->max('NomorBaris');
        $jumlah = ImporProdukBaris::query()->where('IdImporProduk', $impor->Id)->count();
        $judul = array_column($impor->KolomSumber ?? [], 'Judul', 'Indeks');
        $penampung = [];

        foreach ($this->pembaca->BacaBaris($path, $impor->Format, $pembaca['PemisahCsv'], $pembaca['Lembar']) as $nomor => $sel) {
            if ($nomor <= $pembaca['BarisJudul'] || $nomor <= $terakhir) {
                continue;
            }

            $jumlah++;

            if ($jumlah > $maksimal) {
                $this->Gagalkan($impor, 'Berkas berisi lebih dari '.PenguraiNilaiImpor::FormatRibuan($maksimal).' baris produk. Bagi berkas menjadi beberapa bagian.');

                return false;
            }

            $hasil = $this->validator->Validasi($sel, $pemetaan, $preset, $opsi);
            $asli = [];

            foreach ($judul as $indeks => $namaKolom) {
                $asli[$namaKolom] = $sel[$indeks] ?? '';
            }

            $penampung[] = [
                'IdTenant' => $impor->IdTenant,
                'IdImporProduk' => $impor->Id,
                'NomorBaris' => $nomor,
                'Status' => $hasil['Galat'] === [] ? StatusBarisImpor::Valid->value : StatusBarisImpor::Galat->value,
                'Data' => self::KeJson($hasil['Data']),
                'DataAsli' => self::KeJson($asli),
                'Galat' => $hasil['Galat'] === [] ? null : self::KeJson($hasil['Galat']),
                'DibuatPada' => now(),
                'DiubahPada' => now(),
            ];

            if (count($penampung) >= self::UKURAN_SISIP) {
                ImporProdukBaris::query()->insertOrIgnore($penampung);
                $penampung = [];

                if ($habis()) {
                    return null;
                }
            }
        }

        if ($penampung !== []) {
            ImporProdukBaris::query()->insertOrIgnore($penampung);
        }

        return true;
    }

    private function JalankanFaseB(ImporProduk $impor): void
    {
        $opsi = DataOpsiImpor::DariArray($impor->Opsi ?? []);
        $pajakInduk = $this->TandaiGanda($impor);

        ImporProdukBaris::query()
            ->where('IdImporProduk', $impor->Id)
            ->whereIn('Status', [StatusBarisImpor::Valid->value, StatusBarisImpor::Dilewati->value])
            ->chunkById(self::UKURAN_POTONGAN_RENCANA, /** @param Collection<int, ImporProdukBaris> $potongan */ function (Collection $potongan) use ($opsi, $pajakInduk): void {
                $this->RencanakanPotongan($potongan, $opsi, $pajakInduk);
            }, 'Id');

        DB::transaction(function () use ($impor): void {
            $impor = ImporProduk::query()->whereKey($impor->Id)->lockForUpdate()->firstOrFail();

            if ($impor->Status !== StatusImporProduk::Memvalidasi) {
                return;
            }

            $jumlah = self::HitungStatus($impor->Id);
            $impor->fill([
                'JumlahBaris' => array_sum($jumlah),
                'JumlahValid' => $jumlah[StatusBarisImpor::Valid->value] ?? 0,
                'JumlahGalat' => $jumlah[StatusBarisImpor::Galat->value] ?? 0,
                'JumlahDilewati' => $jumlah[StatusBarisImpor::Dilewati->value] ?? 0,
                'DivalidasiPada' => now(),
                'PesanGalat' => null,
            ]);
            $impor->UbahStatus(StatusImporProduk::Pratinjau);
            $impor->save();
        });
    }

    /**
     * SKU, barcode, produk tanpa SKU (nama), dan varian (induk + nilai) yang muncul lebih dari sekali di berkas.
     * Hanya baris yang lolos fase A yang dibandingkan. Sekalian mencatat baris induk varian eksplisit di berkas
     * (nama → punya kelompok pajak), karena induk baru dibuat dari baris itu.
     *
     * @return array<string, bool>
     */
    private function TandaiGanda(ImporProduk $impor): array
    {
        $peta = ['Sku' => [], 'Barcode' => [], 'Nama' => [], 'Varian' => []];
        $pajakInduk = [];

        ImporProdukBaris::query()
            ->where('IdImporProduk', $impor->Id)
            ->where('Status', StatusBarisImpor::Valid->value)
            ->select(['Id', 'NomorBaris', 'Data'])
            ->chunkById(self::UKURAN_POTONGAN_RENCANA, /** @param Collection<int, ImporProdukBaris> $potongan */ function (Collection $potongan) use (&$peta, &$pajakInduk): void {
                foreach ($potongan as $baris) {
                    if (($baris->Data['JenisAkhir'] ?? null) === JenisProduk::IndukVarian->value && ($baris->Data['NamaInduk'] ?? null) === null) {
                        $pajakInduk[mb_strtolower((string) $baris->Data['Nama'])] ??= ($baris->Data['IdKelompokPajak'] ?? null) !== null;
                    }

                    foreach (self::AmbilKunciGanda($baris->Data) as $jenis => $kunciDaftar) {
                        foreach ($kunciDaftar as $kunci) {
                            $peta[$jenis][$kunci][] = $baris->NomorBaris;
                        }
                    }
                }
            }, 'Id');

        $galat = [];
        $pesan = [
            'Sku' => fn (string $kunci, string $nomor): string => "SKU {$kunci} dipakai lebih dari satu baris (baris {$nomor}).",
            'Barcode' => fn (string $kunci, string $nomor): string => "Barcode {$kunci} dipakai lebih dari satu baris (baris {$nomor}).",
            'Nama' => fn (string $kunci, string $nomor): string => "Produk tanpa SKU dengan nama sama ada di beberapa baris (baris {$nomor}). Isi SKU atau hapus baris ganda.",
            'Varian' => fn (string $kunci, string $nomor): string => "Varian yang sama ada di beberapa baris (baris {$nomor}).",
        ];
        $bidang = ['Sku' => 'SKU', 'Barcode' => 'Barcode', 'Nama' => 'Nama Produk', 'Varian' => 'Varian'];

        foreach ($peta as $jenis => $daftar) {
            foreach ($daftar as $kunci => $nomorBaris) {
                if (count($nomorBaris) < 2) {
                    continue;
                }

                $teksNomor = implode(', ', array_slice($nomorBaris, 0, 10)).(count($nomorBaris) > 10 ? ', …' : '');

                foreach ($nomorBaris as $nomor) {
                    $galat[$nomor][] = ['Bidang' => $bidang[$jenis], 'Pesan' => $pesan[$jenis]((string) $kunci, $teksNomor)];
                }
            }
        }

        foreach (array_chunk($galat, self::UKURAN_SISIP, true) as $potongan) {
            $baris = ImporProdukBaris::query()->where('IdImporProduk', $impor->Id)->whereIn('NomorBaris', array_keys($potongan))->get(['Id', 'NomorBaris', 'Galat']);
            $perubahan = [];

            foreach ($baris as $satu) {
                $perubahan[$satu->Id] = ['Status' => StatusBarisImpor::Galat->value, 'Galat' => self::KeJson([...($satu->Galat ?? []), ...$potongan[$satu->NomorBaris]])];
            }

            self::PerbaruiMassal($perubahan);
        }

        return $pajakInduk;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{Sku: list<string>, Barcode: list<string>, Nama: list<string>, Varian: list<string>}
     */
    private static function AmbilKunciGanda(array $data): array
    {
        $barcode = array_map('strval', (array) ($data['Barcode'] ?? []));

        foreach ((array) ($data['SatuanAlternatif'] ?? []) as $alternatif) {
            $barcode = [...$barcode, ...array_map('strval', (array) ($alternatif['Barcode'] ?? []))];
        }

        $sku = is_string($data['Sku'] ?? null) ? mb_strtolower($data['Sku']) : null;
        $namaInduk = is_string($data['NamaInduk'] ?? null) ? $data['NamaInduk'] : null;

        return [
            'Sku' => $sku === null ? [] : [$sku],
            'Barcode' => array_values(array_unique(array_map('mb_strtolower', $barcode))),
            'Nama' => $sku === null && $namaInduk === null ? [mb_strtolower((string) ($data['Nama'] ?? ''))] : [],
            'Varian' => $namaInduk === null ? [] : [mb_strtolower($namaInduk).'|'.self::KunciAtribut((array) ($data['Varian'] ?? []))],
        ];
    }

    /**
     * @param  Collection<int, ImporProdukBaris>  $potongan
     * @param  array<string, bool>  $pajakInduk  nama induk (huruf kecil) di berkas → punya kelompok pajak
     */
    private function RencanakanPotongan(Collection $potongan, DataOpsiImpor $opsi, array $pajakInduk): void
    {
        $data = $potongan->mapWithKeys(fn (ImporProdukBaris $baris): array => [$baris->Id => $baris->Data])->all();
        $sku = array_values(array_unique(array_filter(array_map(fn (array $d): ?string => is_string($d['Sku'] ?? null) ? $d['Sku'] : null, $data))));
        $namaProduk = array_values(array_unique(array_map(fn (array $d): string => mb_strtolower((string) $d['Nama']), array_filter($data, fn (array $d): bool => ($d['Sku'] ?? null) === null && ($d['NamaInduk'] ?? null) === null))));
        $namaInduk = array_values(array_unique(array_map(fn (array $d): string => mb_strtolower((string) ($d['NamaInduk'] ?? $d['Nama'])), array_filter($data, fn (array $d): bool => ($d['NamaInduk'] ?? null) !== null || ($d['JenisAkhir'] ?? null) === JenisProduk::IndukVarian->value))));
        $barcode = [];

        foreach ($data as $d) {
            $barcode = [...$barcode, ...self::AmbilKunciGanda($d)['Barcode']];
        }

        $produkSku = $sku === [] ? [] : Produk::query()->whereIn('Sku', $sku)->get(['Id', 'Sku'])->mapWithKeys(fn (Produk $p): array => [mb_strtolower((string) $p->Sku) => $p->Id])->all();
        $produkNama = $namaProduk === [] ? [] : Produk::query()
            ->whereNull('IdInduk')->where('Jenis', '!=', JenisProduk::IndukVarian->value)
            ->where(fn ($kueri) => $kueri->whereIn(DB::raw('LOWER(Nama)'), $namaProduk))
            ->orderBy('Id')->get(['Id', 'Nama'])
            ->reduce(fn (array $peta, Produk $p): array => $peta + [mb_strtolower($p->Nama) => $p->Id], []);
        $induk = $namaInduk === [] ? [] : Produk::query()
            ->whereNull('IdInduk')->where('Jenis', JenisProduk::IndukVarian->value)
            ->whereIn(DB::raw('LOWER(Nama)'), $namaInduk)
            ->orderBy('Id')->get(['Id', 'Nama'])
            ->reduce(fn (array $peta, Produk $p): array => $peta + [mb_strtolower($p->Nama) => $p->Id], []);
        $anak = [];

        if ($induk !== []) {
            foreach (Produk::query()->whereIn('IdInduk', array_values($induk))->get(['Id', 'IdInduk', 'AtributVarian']) as $p) {
                $anak[$p->IdInduk.'|'.self::KunciAtribut((array) ($p->AtributVarian ?? []))] = $p->Id;
            }
        }

        $pemilikBarcode = $barcode === [] ? [] : ProdukBarcode::query()->whereIn('Barcode', array_values(array_unique($barcode)))->get(['Barcode', 'IdProduk'])
            ->mapWithKeys(fn (ProdukBarcode $b): array => [mb_strtolower($b->Barcode) => $b->IdProduk])->all();
        $perubahan = [];

        foreach ($potongan as $baris) {
            $d = $baris->Data;
            $skuBaris = is_string($d['Sku'] ?? null) ? mb_strtolower($d['Sku']) : null;
            $namaIndukBaris = is_string($d['NamaInduk'] ?? null) ? mb_strtolower($d['NamaInduk']) : null;
            $jenisAkhir = JenisProduk::tryFrom((string) ($d['JenisAkhir'] ?? '')) ?? $opsi->jenisBawaan;
            $idInduk = $namaIndukBaris === null ? null : ($induk[$namaIndukBaris] ?? null);

            if ($namaIndukBaris !== null) {
                $kunci = 'induk:'.$namaIndukBaris;
                $target = $skuBaris !== null && isset($produkSku[$skuBaris]) ? $produkSku[$skuBaris] : ($idInduk === null ? null : ($anak[$idInduk.'|'.self::KunciAtribut((array) $d['Varian'])] ?? null));
            } elseif ($jenisAkhir === JenisProduk::IndukVarian) {
                $kunci = 'induk:'.mb_strtolower((string) $d['Nama']);
                $target = $skuBaris !== null && isset($produkSku[$skuBaris]) ? $produkSku[$skuBaris] : ($induk[mb_strtolower((string) $d['Nama'])] ?? null);
            } elseif ($skuBaris !== null) {
                $kunci = 'sku:'.$skuBaris;
                $target = $produkSku[$skuBaris] ?? null;
            } else {
                $kunci = 'nama:'.mb_strtolower((string) $d['Nama']);
                $target = $produkNama[mb_strtolower((string) $d['Nama'])] ?? null;
            }

            $galat = [];

            foreach (self::AmbilKunciGanda($d)['Barcode'] as $kode) {
                $pemilik = $pemilikBarcode[$kode] ?? null;

                if ($pemilik !== null && $pemilik !== $target) {
                    $galat[] = ['Bidang' => 'Barcode', 'Pesan' => "Barcode {$kode} sudah dipakai produk lain."];
                }
            }

            $aksi = $target === null ? AksiBarisImpor::Buat : ($opsi->mode === ModeImpor::TambahDanPerbarui ? AksiBarisImpor::Perbarui : AksiBarisImpor::Lewati);
            $perluPajak = $jenisAkhir->CekBisaDijual() || $jenisAkhir === JenisProduk::IndukVarian;

            // Anak varian baru ikut pajak induknya: induk yang sudah ada, atau baris induk di berkas yang berpajak.
            $pajakDariInduk = $namaIndukBaris !== null && ($idInduk !== null || ($pajakInduk[$namaIndukBaris] ?? false));

            if ($aksi === AksiBarisImpor::Buat && $perluPajak && ($d['IdKelompokPajak'] ?? null) === null && $opsi->idKelompokPajakBawaan === null && ! $pajakDariInduk) {
                $galat[] = ['Bidang' => 'Kelompok Pajak', 'Pesan' => 'Kelompok pajak wajib untuk produk yang dijual. Isi kolom Kelompok Pajak atau pilih kelompok pajak bawaan.'];
            }

            $status = match (true) {
                $galat !== [] => StatusBarisImpor::Galat,
                $aksi === AksiBarisImpor::Lewati => StatusBarisImpor::Dilewati,
                default => StatusBarisImpor::Valid,
            };

            $perubahan[$baris->Id] = [
                'Status' => $status->value,
                'Aksi' => $aksi->value,
                'KunciProduk' => mb_substr($kunci, 0, 191),
                'Galat' => $galat === [] ? null : self::KeJson($galat),
            ];
        }

        self::PerbaruiMassal($perubahan);
    }

    /**
     * Kunci atribut varian tanpa memandang urutan & huruf besar/kecil: "ukuran=m|warna=merah".
     *
     * @param  array<int, mixed>  $atribut
     */
    public static function KunciAtribut(array $atribut): string
    {
        $pasangan = [];

        foreach ($atribut as $satu) {
            if (is_array($satu) && isset($satu['Nama'], $satu['Nilai']) && is_string($satu['Nilai'])) {
                $pasangan[] = mb_strtolower(trim((string) $satu['Nama'])).'='.mb_strtolower(trim($satu['Nilai']));
            }
        }

        sort($pasangan);

        return implode('|', $pasangan);
    }

    /**
     * @return array<string, int>
     */
    public static function HitungStatus(int $idImporProduk): array
    {
        return ImporProdukBaris::query()
            ->where('IdImporProduk', $idImporProduk)
            ->selectRaw('Status, COUNT(*) AS Jumlah')
            ->groupBy('Status')
            ->pluck('Jumlah', 'Status')
            ->map(fn (mixed $jumlah): int => (int) $jumlah)
            ->all();
    }

    private function Gagalkan(ImporProduk $impor, string $pesan): void
    {
        DB::transaction(function () use ($impor, $pesan): void {
            $impor = ImporProduk::query()->whereKey($impor->Id)->lockForUpdate()->firstOrFail();

            if ($impor->Status->BisaBerubahKe(StatusImporProduk::Gagal)) {
                $impor->UbahStatus(StatusImporProduk::Gagal);
                $impor->PesanGalat = mb_substr($pesan, 0, 500);
                $impor->save();
            }
        });
    }

    /**
     * Ubah banyak baris sekaligus: `UPDATE … SET Kolom = CASE Id WHEN … END WHERE Id IN (…)` (nilai di-quote PDO).
     *
     * @param  array<int, array<string, string|null>>  $perubahan  Id → kolom → nilai
     */
    private static function PerbaruiMassal(array $perubahan): void
    {
        if ($perubahan === []) {
            return;
        }

        $kolom = array_values(array_intersect(array_keys((array) reset($perubahan)), ['Status', 'Aksi', 'KunciProduk', 'Galat']));
        $set = [];
        $ikatan = [];

        foreach ($kolom as $namaKolom) {
            $set[] = "`{$namaKolom}` = CASE `Id`".str_repeat(' WHEN ? THEN ?', count($perubahan)).' END';

            foreach ($perubahan as $id => $nilai) {
                array_push($ikatan, $id, $nilai[$namaKolom] ?? null);
            }
        }

        $idTenant = (int) ImporProdukBaris::query()->whereKey(array_key_first($perubahan))->value('IdTenant');
        $ikatan = [...$ikatan, now(), $idTenant, ...array_keys($perubahan)];

        // Id berasal dari kueri ber-scope tenant; IdTenant diulang di WHERE sebagai pagar tambahan.
        DB::update('UPDATE `ImporProdukBaris` SET '.implode(', ', $set).', `DiubahPada` = ? WHERE `IdTenant` = ? AND `Id` IN ('.implode(', ', array_fill(0, count($perubahan), '?')).')', $ikatan);
    }

    /**
     * @param  array<mixed>  $nilai
     */
    private static function KeJson(array $nilai): string
    {
        return (string) json_encode($nilai, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}
