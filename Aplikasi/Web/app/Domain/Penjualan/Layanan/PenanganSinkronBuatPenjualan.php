<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Layanan;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Nilai\Kuantitas;
use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Bersama\Sinkron\Data\DataKonteksSinkron;
use App\Domain\Bersama\Sinkron\Enum\StatusItemSinkron;
use App\Domain\Bersama\Sinkron\Kontrak\PenanganItemSinkron;
use App\Domain\Karyawan\Layanan\PencatatKomisiPenjualan;
use App\Domain\Kasir\Layanan\ValidasiItemSinkron;
use App\Domain\Pajak\Enum\DasarPengenaanPajak;
use App\Domain\Penjualan\Aksi\TerimaPenjualanPos;
use App\Domain\Penjualan\Data\DataBarisPenjualanPos;
use App\Domain\Penjualan\Data\DataDiskonManual;
use App\Domain\Penjualan\Data\DataPajakPenjualanPos;
use App\Domain\Penjualan\Data\DataPembayaranPenjualanPos;
use App\Domain\Penjualan\Data\DataPenjualanPos;
use App\Domain\Penjualan\Data\DataPromoPenjualanPos;
use App\Domain\Penjualan\Data\DataRingkasanPenjualanPos;
use App\Domain\Penjualan\Enum\ArahPembulatan;
use App\Domain\Penjualan\Enum\KanalPenjualan;
use App\Domain\Penjualan\Kalkulasi\DataPembulatanTunai;
use Brick\Math\BigDecimal;
use Illuminate\Validation\Rule;

/**
 * Item outbox `Penjualan.Buat` (F-07b, PRD "Rincian F-07b"): penjualan lunas dari perangkat. Bentuk `Data`:
 * `{UuidShift, UuidPengguna, Nomor, Kanal?, DibuatPada, HargaTermasukPajak, PersenBiayaLayanan, PembulatanTunai
 * {Kelipatan, Arah}|null, Pajak [{Kode, Tarif, PengaliDppPembilang, PengaliDppPenyebut, DasarPengenaan}], Baris [{Uuid,
 * UuidProduk, UuidProdukSatuan|null, Jumlah, HargaSatuan, HargaPilihan, Pilihan [{UuidPilihan, Nama, Harga}],
 * HargaTermasukPajak|null, KodePajak [..]|null, DiskonManual {Persen|Jumlah}|null, Catatan}], DiskonManualPesanan
 * {Persen|Jumlah}|null, UuidPenyetujuDiskon|null, Pembayaran [{Uuid, UuidMetodePembayaran, Jumlah, Referensi|null}],
 * Ringkasan {Subtotal, TotalPajak, Pembulatan, TotalAkhir, Kembalian}, Catatan, UuidPesananTerbuka?, KirimDapur?, UuidPelanggan?,
 * TukarPoin {Poin, Nilai}|null, Promo [{UuidPromo, Kode, DiskonBaris [{UuidBaris, Jumlah}], DiskonPesanan}]?, Voucher?, UuidPesananPenjualan?}`.
 * `TukarPoin` (F-16b) wajib bersama `UuidPelanggan`; `Promo` (F-16c) = promo yang diterapkan perangkat;
 * `UuidPenyetujuTempo` (F-12) = penyetuju tempo di atas limit / piutang lewat jatuh tempo (BR-12.1); `Voucher` (F-16c
 * bagian 2) = kode voucher yang dipesan online untuk penjualan ini; `UuidPesananPenjualan` (F-12 bagian 2) = pre-order yang
 * diambil (DP dipakai lewat pembayaran bermetode Uang Muka). Uang & jumlah
 * string desimal. `UuidPesananTerbuka` (mode meja) menutup pesanan terbuka; `KirimDapur` (mode cepat) membuat tiket dapur.
 */
final class PenanganSinkronBuatPenjualan implements PenanganItemSinkron
{
    public const JENIS = 'Penjualan.Buat';

    /** Persen biaya layanan 0–100 dengan maks. 2 desimal (kolom DECIMAL(5,2)). */
    private const POLA_PERSEN = '/^\d{1,3}(\.\d{1,2})?$/';

    /** Persen diskon manual 0–100 dengan maks. 6 desimal (mesin kalkulasi menghitung eksak). */
    private const POLA_PERSEN_DISKON = '/^\d{1,3}(\.\d{1,6})?$/';

    /** Tarif pajak persen dengan maks. 6 desimal (kolom `TarifPajak.Tarif`). */
    private const POLA_TARIF = '/^\d{1,3}(\.\d{1,6})?$/';

    /** Jumlah barang positif, maks. 14 digit bulat & 4 desimal (DECIMAL(18,4)). */
    private const POLA_JUMLAH = '/^\d{1,14}(\.\d{1,4})?$/';

    /** Uang bertanda (pembulatan bisa negatif). */
    private const POLA_UANG_BERTANDA = '/^-?\d{1,16}(\.\d{1,2})?$/';

    public function __construct(private readonly TerimaPenjualanPos $terima) {}

    public function AmbilJenis(): string
    {
        return self::JENIS;
    }

    public function Proses(string $uuid, array $data, DataKonteksSinkron $konteks): StatusItemSinkron
    {
        $uang = 'regex:'.ValidasiItemSinkron::POLA_UANG;
        $persen = 'regex:'.self::POLA_PERSEN;
        $persenDiskon = 'regex:'.self::POLA_PERSEN_DISKON;

        $valid = ValidasiItemSinkron::Validasi($data, [
            'UuidShift' => ['required', 'string', 'ulid'],
            'UuidPengguna' => ['required', 'string', 'ulid'],
            'Nomor' => ['required', 'string', 'max:80'],
            'Kanal' => ['sometimes', 'nullable', 'string', Rule::enum(KanalPenjualan::class)],
            'DibuatPada' => ['required', 'string', 'regex:'.ValidasiItemSinkron::POLA_WAKTU, 'date'],
            'HargaTermasukPajak' => ['required', 'boolean'],
            'PersenBiayaLayanan' => ['required', 'string', $persen],
            'PembulatanTunai' => ['sometimes', 'nullable', 'array'],
            'PembulatanTunai.Kelipatan' => ['required_with:PembulatanTunai', 'integer', 'min:1', 'max:1000'],
            'PembulatanTunai.Arah' => ['required_with:PembulatanTunai', 'string', Rule::enum(ArahPembulatan::class)],
            'Pajak' => ['sometimes', 'array', 'max:10'],
            'Pajak.*.Kode' => ['required', 'string', 'max:50', 'distinct'],
            'Pajak.*.Tarif' => ['required', 'string', 'regex:'.self::POLA_TARIF],
            'Pajak.*.PengaliDppPembilang' => ['required', 'integer', 'min:1', 'max:1000'],
            'Pajak.*.PengaliDppPenyebut' => ['required', 'integer', 'min:1', 'max:1000'],
            'Pajak.*.DasarPengenaan' => ['required', 'string', Rule::enum(DasarPengenaanPajak::class)],
            'Baris' => ['required', 'array', 'min:1', 'max:500'],
            'Baris.*.Uuid' => ['required', 'string', 'ulid', 'distinct'],
            'Baris.*.UuidProduk' => ['required', 'string', 'ulid'],
            'Baris.*.UuidProdukSatuan' => ['nullable', 'string', 'ulid'],
            'Baris.*.Jumlah' => ['required', 'string', 'regex:'.self::POLA_JUMLAH],
            'Baris.*.HargaSatuan' => ['required', 'string', $uang],
            'Baris.*.HargaPilihan' => ['sometimes', 'nullable', 'string', $uang],
            'Baris.*.Pilihan' => ['sometimes', 'nullable', 'array', 'max:30'],
            'Baris.*.Pilihan.*.UuidPilihan' => ['required', 'string', 'ulid'],
            'Baris.*.Pilihan.*.Nama' => ['required', 'string', 'max:100'],
            'Baris.*.Pilihan.*.Harga' => ['required', 'string', $uang],
            'Baris.*.HargaTermasukPajak' => ['sometimes', 'nullable', 'boolean'],
            'Baris.*.KodePajak' => ['sometimes', 'nullable', 'array', 'max:10'],
            'Baris.*.KodePajak.*' => ['string', 'max:50'],
            'Baris.*.DiskonManual' => ['sometimes', 'nullable', 'array'],
            'Baris.*.DiskonManual.Persen' => ['sometimes', 'nullable', 'string', $persenDiskon],
            'Baris.*.DiskonManual.Jumlah' => ['sometimes', 'nullable', 'string', $uang],
            'Baris.*.Catatan' => ['sometimes', 'nullable', 'string', 'max:255'],
            // F-18: staf yang melayani baris (komisi).
            'Baris.*.Staf' => ['sometimes', 'nullable', 'array', 'max:'.PencatatKomisiPenjualan::MAKS_STAF_PER_BARIS],
            'Baris.*.Staf.*' => ['string', 'ulid', 'distinct'],
            'DiskonManualPesanan' => ['sometimes', 'nullable', 'array'],
            'DiskonManualPesanan.Persen' => ['sometimes', 'nullable', 'string', $persenDiskon],
            'DiskonManualPesanan.Jumlah' => ['sometimes', 'nullable', 'string', $uang],
            'UuidPenyetujuDiskon' => ['sometimes', 'nullable', 'string', 'ulid'],
            'Pembayaran' => ['required', 'array', 'min:1', 'max:10'],
            'Pembayaran.*.Uuid' => ['required', 'string', 'ulid', 'distinct'],
            'Pembayaran.*.UuidMetodePembayaran' => ['required', 'string', 'ulid'],
            'Pembayaran.*.Jumlah' => ['required', 'string', $uang],
            'Pembayaran.*.Referensi' => ['sometimes', 'nullable', 'string', 'max:100'],
            'Ringkasan' => ['required', 'array'],
            'Ringkasan.Subtotal' => ['required', 'string', $uang],
            'Ringkasan.TotalPajak' => ['required', 'string', $uang],
            'Ringkasan.Pembulatan' => ['required', 'string', 'regex:'.self::POLA_UANG_BERTANDA],
            'Ringkasan.TotalAkhir' => ['required', 'string', $uang],
            'Ringkasan.Kembalian' => ['required', 'string', $uang],
            'Catatan' => ['sometimes', 'nullable', 'string', 'max:500'],
            'UuidPesananTerbuka' => ['sometimes', 'nullable', 'string', 'ulid'],
            'KirimDapur' => ['sometimes', 'boolean'],
            'UuidPelanggan' => ['sometimes', 'nullable', 'string', 'ulid', 'required_with:TukarPoin'],
            'TukarPoin' => ['sometimes', 'nullable', 'array'],
            'TukarPoin.Poin' => ['required_with:TukarPoin', 'integer', 'min:1', 'max:10000000'],
            'TukarPoin.Nilai' => ['required_with:TukarPoin', 'string', $uang],
            'UuidPenyetujuTempo' => ['sometimes', 'nullable', 'string', 'ulid'],
            'Voucher' => ['sometimes', 'nullable', 'string', 'max:30'],
            'UuidPesananPenjualan' => ['sometimes', 'nullable', 'string', 'ulid'],
            'Promo' => ['sometimes', 'array', 'max:20'],
            'Promo.*.UuidPromo' => ['required', 'string', 'ulid', 'distinct'],
            'Promo.*.Kode' => ['required', 'string', 'max:30'],
            'Promo.*.DiskonBaris' => ['present', 'array', 'max:500'],
            'Promo.*.DiskonBaris.*.UuidBaris' => ['required', 'string', 'ulid'],
            'Promo.*.DiskonBaris.*.Jumlah' => ['required', 'string', $uang],
            'Promo.*.DiskonPesanan' => ['required', 'string', $uang],
        ]);
        $tukarPoin = is_array($valid['TukarPoin'] ?? null) ? $valid['TukarPoin'] : null;

        $pembulatan = is_array($valid['PembulatanTunai'] ?? null)
            ? new DataPembulatanTunai((int) $valid['PembulatanTunai']['Kelipatan'], ArahPembulatan::from((string) $valid['PembulatanTunai']['Arah']))
            : null;
        $kanal = is_string($valid['Kanal'] ?? null) ? KanalPenjualan::from($valid['Kanal']) : KanalPenjualan::BawaPulang;

        return $this->terima->Jalankan(new DataPenjualanPos(
            uuid: strtoupper($uuid),
            idPerangkat: $konteks->idPerangkat,
            uuidShift: strtoupper((string) $valid['UuidShift']),
            uuidPengguna: strtoupper((string) $valid['UuidPengguna']),
            nomor: trim((string) $valid['Nomor']),
            kanal: $kanal,
            dibuatPada: ValidasiItemSinkron::AmbilWaktu((string) $valid['DibuatPada']),
            hargaTermasukPajak: (bool) $valid['HargaTermasukPajak'],
            persenBiayaLayanan: BigDecimal::of((string) $valid['PersenBiayaLayanan']),
            pembulatanTunai: $pembulatan,
            pajak: array_values(array_map(fn (array $p): DataPajakPenjualanPos => new DataPajakPenjualanPos(
                (string) $p['Kode'],
                BigDecimal::of((string) $p['Tarif']),
                (int) $p['PengaliDppPembilang'],
                (int) $p['PengaliDppPenyebut'],
                DasarPengenaanPajak::from((string) $p['DasarPengenaan']),
            ), (array) ($valid['Pajak'] ?? []))),
            baris: self::AmbilBaris((array) $valid['Baris']),
            diskonManualPesanan: self::AmbilDiskon($valid['DiskonManualPesanan'] ?? null, 'DiskonManualPesanan'),
            uuidPenyetujuDiskon: is_string($valid['UuidPenyetujuDiskon'] ?? null) ? strtoupper($valid['UuidPenyetujuDiskon']) : null,
            pembayaran: array_values(array_map(fn (array $b): DataPembayaranPenjualanPos => new DataPembayaranPenjualanPos(
                strtoupper((string) $b['Uuid']),
                strtoupper((string) $b['UuidMetodePembayaran']),
                Uang::Dari((string) $b['Jumlah']),
                self::AmbilTeks($b['Referensi'] ?? null),
            ), (array) $valid['Pembayaran'])),
            ringkasan: new DataRingkasanPenjualanPos(
                Uang::Dari((string) $valid['Ringkasan']['Subtotal']),
                Uang::Dari((string) $valid['Ringkasan']['TotalPajak']),
                Uang::Dari((string) $valid['Ringkasan']['Pembulatan']),
                Uang::Dari((string) $valid['Ringkasan']['TotalAkhir']),
                Uang::Dari((string) $valid['Ringkasan']['Kembalian']),
            ),
            catatan: self::AmbilTeks($valid['Catatan'] ?? null),
            uuidPesananTerbuka: is_string($valid['UuidPesananTerbuka'] ?? null) ? strtoupper($valid['UuidPesananTerbuka']) : null,
            kirimDapur: (bool) ($valid['KirimDapur'] ?? false),
            uuidPelanggan: is_string($valid['UuidPelanggan'] ?? null) ? strtoupper($valid['UuidPelanggan']) : null,
            poinDitukar: $tukarPoin === null ? 0 : (int) $tukarPoin['Poin'],
            nilaiTukarPoin: $tukarPoin === null ? null : Uang::Dari((string) $tukarPoin['Nilai']),
            promo: self::AmbilPromo((array) ($valid['Promo'] ?? [])),
            uuidPenyetujuTempo: is_string($valid['UuidPenyetujuTempo'] ?? null) ? strtoupper($valid['UuidPenyetujuTempo']) : null,
            kodeVoucher: self::AmbilTeks($valid['Voucher'] ?? null),
            uuidPesananPenjualan: is_string($valid['UuidPesananPenjualan'] ?? null) ? strtoupper($valid['UuidPesananPenjualan']) : null,
        ));
    }

    /**
     * @param  array<int|string, mixed>  $daftar
     * @return list<DataPromoPenjualanPos>
     */
    private static function AmbilPromo(array $daftar): array
    {
        $hasil = [];

        foreach ($daftar as $p) {
            if (! is_array($p)) {
                continue;
            }

            $diskonBaris = [];

            foreach ((array) ($p['DiskonBaris'] ?? []) as $b) {
                if (is_array($b)) {
                    $uuid = strtoupper((string) $b['UuidBaris']);
                    $diskonBaris[$uuid] = ($diskonBaris[$uuid] ?? Uang::Nol())->Tambah(Uang::Dari((string) $b['Jumlah']));
                }
            }

            $hasil[] = new DataPromoPenjualanPos(strtoupper((string) $p['UuidPromo']), (string) $p['Kode'], $diskonBaris, Uang::Dari((string) $p['DiskonPesanan']));
        }

        return $hasil;
    }

    /**
     * @param  array<int|string, mixed>  $daftar
     * @return list<DataBarisPenjualanPos>
     */
    private static function AmbilBaris(array $daftar): array
    {
        $hasil = [];

        foreach (array_values($daftar) as $indeks => $b) {
            if (! is_array($b)) {
                continue;
            }

            $jumlah = Kuantitas::Dari((string) $b['Jumlah']);

            if ($jumlah->KeDesimal()->isZero()) {
                throw new PelanggaranAturanBisnis('DataTidakValid', 'Jumlah barang harus lebih dari 0.', "Baris.{$indeks}.Jumlah");
            }

            $pilihan = [];

            foreach (is_array($b['Pilihan'] ?? null) ? $b['Pilihan'] : [] as $p) {
                $pilihan[] = ['UuidPilihan' => strtoupper((string) $p['UuidPilihan']), 'Nama' => (string) $p['Nama'], 'Harga' => Uang::Dari((string) $p['Harga'])->KeString()];
            }

            $hasil[] = new DataBarisPenjualanPos(
                uuid: strtoupper((string) $b['Uuid']),
                uuidProduk: strtoupper((string) $b['UuidProduk']),
                uuidProdukSatuan: is_string($b['UuidProdukSatuan'] ?? null) ? strtoupper($b['UuidProdukSatuan']) : null,
                jumlah: $jumlah,
                hargaSatuan: Uang::Dari((string) $b['HargaSatuan']),
                hargaPilihan: Uang::Dari(is_string($b['HargaPilihan'] ?? null) ? $b['HargaPilihan'] : '0'),
                pilihan: $pilihan,
                hargaTermasukPajak: is_bool($b['HargaTermasukPajak'] ?? null) ? $b['HargaTermasukPajak'] : null,
                kodePajak: is_array($b['KodePajak'] ?? null) ? array_values(array_map('strval', $b['KodePajak'])) : null,
                diskonManual: self::AmbilDiskon($b['DiskonManual'] ?? null, "Baris.{$indeks}.DiskonManual"),
                catatan: self::AmbilTeks($b['Catatan'] ?? null),
                uuidKaryawan: is_array($b['Staf'] ?? null) ? array_values(array_map(fn ($u): string => strtoupper((string) $u), $b['Staf'])) : [],
            );
        }

        return $hasil;
    }

    private static function AmbilDiskon(mixed $nilai, string $bidang): ?DataDiskonManual
    {
        if (! is_array($nilai)) {
            return null;
        }

        $persen = is_string($nilai['Persen'] ?? null) ? BigDecimal::of($nilai['Persen']) : null;
        $jumlah = is_string($nilai['Jumlah'] ?? null) ? Uang::Dari($nilai['Jumlah']) : null;

        if ($persen === null && $jumlah === null) {
            return null;
        }

        if ($persen !== null && $jumlah !== null) {
            throw new PelanggaranAturanBisnis('DataTidakValid', 'Diskon manual diisi persen atau nominal, tidak keduanya.', $bidang);
        }

        if ($persen !== null && $persen->isGreaterThan(100)) {
            throw new PelanggaranAturanBisnis('DataTidakValid', 'Diskon persen maksimal 100%.', $bidang);
        }

        return new DataDiskonManual($persen, $jumlah);
    }

    private static function AmbilTeks(mixed $nilai): ?string
    {
        if (! is_string($nilai)) {
            return null;
        }

        $teks = trim($nilai);

        return $teks === '' ? null : $teks;
    }
}
