import { router } from '@inertiajs/react';

import PilihanCari from '@/Komponen/Formulir/PilihanCari';
import { KolomBilangan, KolomUang } from '@/Komponen/Laporan/KolomLaporan';
import NavigasiTab, { TautanEkspor } from '@/Komponen/Laporan/NavigasiTab';
import TabelData from '@/Komponen/TabelData/TabelData';
import type { KolomTabel } from '@/Komponen/TabelData/Tipe';
import PemilihTanggal from '@/Komponen/Tanggal/PemilihTanggal';
import { Label } from '@/Komponen/Ui/label';
import { FormatRupiah } from '@/Pustaka/Format';
import { BuatUrlKartuStok, FormatJumlahStok } from '@/Pustaka/FormatPersediaan';
import { FormatTanggal } from '@/Pustaka/FormatWaktu';
import TataLetakAplikasi from '@/TataLetak/TataLetakAplikasi';
import type { BarisStokKritis, NilaiPersediaan, PropsLaporanStok } from '@/Tipe/Laporan';

const alamat = '/kelola/laporan/stok';

type BarisGudang = NilaiPersediaan['PerGudang'][number];
type BarisKategori = NilaiPersediaan['PerKategori'][number];

const kolomGudang: KolomTabel<BarisGudang>[] = [
    {
        id: 'NamaGudang',
        accessorKey: 'NamaGudang',
        header: 'Lokasi stok',
        meta: { label: 'Lokasi stok', prioritas: 'utama', wajib: true },
        cell: ({ row }) => (
            <>
                <span className="block text-teks-utama">{row.original.NamaGudang}</span>
                <span className="block text-label text-teks-sekunder">{row.original.NamaOutlet}</span>
            </>
        ),
    },
    KolomBilangan<BarisGudang>('JumlahProduk', 'Produk', 'penting'),
    KolomUang<BarisGudang>('Nilai', 'Nilai persediaan'),
];

const kolomKategori: KolomTabel<BarisKategori>[] = [
    {
        id: 'NamaKategori',
        accessorKey: 'NamaKategori',
        header: 'Kategori',
        meta: { label: 'Kategori', prioritas: 'utama', wajib: true },
    },
    KolomBilangan<BarisKategori>('JumlahProduk', 'Produk', 'penting'),
    KolomUang<BarisKategori>('Nilai', 'Nilai persediaan'),
];

const kolomKritis: KolomTabel<BarisStokKritis>[] = [
    {
        id: 'NamaProduk',
        accessorKey: 'NamaProduk',
        header: 'Produk',
        meta: { label: 'Produk', prioritas: 'utama', wajib: true },
        cell: ({ row: { original: b } }) => (
            <>
                <span className="block text-teks-utama">{b.NamaProduk}</span>
                {b.Sku ? <span className="block font-mono text-label text-teks-sekunder">{b.Sku}</span> : null}
            </>
        ),
    },
    {
        id: 'NamaGudang',
        accessorKey: 'NamaGudang',
        header: 'Lokasi stok',
        meta: { label: 'Lokasi stok', prioritas: 'penting' },
        cell: ({ row }) =>
            `${row.original.NamaGudang}${row.original.NamaOutlet ? ` · ${row.original.NamaOutlet}` : ''}`,
    },
    {
        id: 'Saldo',
        accessorKey: 'Saldo',
        header: 'Saldo',
        meta: { label: 'Saldo', angka: true, prioritas: 'penting' },
        cell: ({ row }) => FormatJumlahStok(row.original.Saldo, row.original.SimbolSatuan),
    },
    {
        id: 'StokMinimum',
        accessorKey: 'StokMinimum',
        header: 'Minimum',
        meta: { label: 'Minimum', angka: true, prioritas: 'rendah' },
        cell: ({ row }) => FormatJumlahStok(row.original.StokMinimum, row.original.SimbolSatuan),
    },
    {
        id: 'Kekurangan',
        accessorKey: 'Kekurangan',
        header: 'Kurang dari minimum',
        enableSorting: false,
        meta: { label: 'Kurang dari minimum', angka: true, prioritas: 'rendah' },
        cell: ({ row }) => FormatJumlahStok(row.original.Kekurangan, row.original.SimbolSatuan),
    },
];

/**
 * F-14a laporan stok (izin lihat persediaan): nilai persediaan per lokasi stok & kategori pada akhir tanggal
 * tertentu (dari buku stok) dan stok kritis (saldo ≤ batas minimum per lokasi). Posisi & kartu stok per produk ada di
 * menu Persediaan.
 */
export default function HalamanLaporanStok({ Saring, OpsiGudang, Nilai, Kritis }: PropsLaporanStok) {
    const query = { tanggal: Saring.Tanggal, gudang: Saring.Gudang };
    const Terapkan = (ubah: Record<string, string>) => {
        const baru = Object.fromEntries(
            Object.entries({ ...query, tab: Saring.Tab, ...ubah }).filter(([, nilai]) => nilai !== ''),
        );
        router.get(alamat, baru, { preserveScroll: true, preserveState: true });
    };

    return (
        <TataLetakAplikasi judul="Laporan stok">
            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4" role="group" aria-label="Saring laporan stok">
                {Saring.Tab === 'nilai' ? (
                    <PemilihTanggal
                        label="Posisi pada tanggal"
                        nilai={Saring.Tanggal}
                        tanpaKosongkan
                        saatBerubah={(tanggal) => Terapkan({ tanggal })}
                    />
                ) : null}
                {OpsiGudang.length > 1 ? (
                    <div className="flex flex-col gap-1">
                        <Label htmlFor="saring-laporan-gudang" className="text-label font-semibold text-teks-utama">
                            Lokasi stok
                        </Label>
                        <PilihanCari
                            id="saring-laporan-gudang"
                            label="Lokasi stok"
                            nilai={Saring.Gudang}
                            opsi={OpsiGudang}
                            kosong="Semua lokasi stok"
                            saatBerubah={(gudang) => Terapkan({ gudang })}
                        />
                    </div>
                ) : null}
            </div>

            <div className="flex flex-wrap items-end justify-between gap-2">
                <NavigasiTab
                    label="Jenis laporan stok"
                    alamat={alamat}
                    query={query}
                    tabAktif={Saring.Tab}
                    tab={[
                        { nilai: 'nilai', label: 'Nilai persediaan' },
                        { nilai: 'kritis', label: 'Stok kritis' },
                    ]}
                />
                <TautanEkspor alamat={`${alamat}/ekspor`} query={{ ...query, tab: Saring.Tab }} />
            </div>

            {Nilai ? (
                <>
                    <dl className="grid grid-cols-2 gap-3 rounded-panel border border-garis bg-permukaan p-4 md:max-w-xl">
                        <div>
                            <dt className="text-label text-teks-sekunder">
                                Nilai persediaan {FormatTanggal(Saring.Tanggal)}
                            </dt>
                            <dd className="text-subjudul font-semibold text-teks-utama tabular-nums">
                                {FormatRupiah(Nilai.Total.Nilai)}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-label text-teks-sekunder">Produk bersaldo</dt>
                            <dd className="text-subjudul font-semibold text-teks-utama tabular-nums">
                                {String(Nilai.Total.JumlahProduk)}
                            </dd>
                        </div>
                    </dl>
                    <section aria-labelledby="judul-per-gudang" className="flex flex-col gap-2">
                        <h2 id="judul-per-gudang" className="text-subjudul font-semibold text-teks-utama">
                            Per lokasi stok
                        </h2>
                        <TabelData
                            id="laporan-stok-gudang"
                            label="Nilai persediaan per lokasi stok"
                            kolom={kolomGudang}
                            sumber={{ mode: 'lokal', data: Nilai.PerGudang }}
                            ambilIdBaris={(b) => b.Kunci}
                            urutBawaan="-Nilai"
                            cari={false}
                            kosong={{ ilustrasi: 'Laporan', judul: 'Belum ada stok pada tanggal ini.' }}
                        />
                    </section>
                    <section aria-labelledby="judul-per-kategori" className="flex flex-col gap-2">
                        <h2 id="judul-per-kategori" className="text-subjudul font-semibold text-teks-utama">
                            Per kategori
                        </h2>
                        <TabelData
                            id="laporan-stok-kategori"
                            label="Nilai persediaan per kategori"
                            kolom={kolomKategori}
                            sumber={{ mode: 'lokal', data: Nilai.PerKategori }}
                            ambilIdBaris={(b) => b.Kunci}
                            urutBawaan="-Nilai"
                            cari="Cari kategori"
                            kosong={{ judul: 'Belum ada stok pada tanggal ini.' }}
                        />
                    </section>
                </>
            ) : null}

            {Kritis ? (
                <section aria-labelledby="judul-kritis" className="flex flex-col gap-2">
                    <h2 id="judul-kritis" className="text-subjudul font-semibold text-teks-utama">
                        Stok kritis ({String(Kritis.Jumlah)})
                    </h2>
                    <p className="text-label text-teks-sekunder">
                        Produk yang saldonya sama dengan atau di bawah batas minimum lokasi stoknya. Atur batas minimum
                        di halaman produk.
                    </p>
                    <TabelData
                        id="laporan-stok-kritis"
                        label="Stok kritis"
                        kolom={kolomKritis}
                        sumber={{ mode: 'lokal', data: Kritis.Baris }}
                        ambilIdBaris={(b) => b.Kunci}
                        cari="Cari produk"
                        alamatDetail={(b) => BuatUrlKartuStok(b.UuidProduk, b.UuidGudang)}
                        kosong={{
                            judul: 'Tidak ada stok kritis. Semua produk berbatas minimum masih di atas batasnya.',
                        }}
                    />
                </section>
            ) : null}
        </TataLetakAplikasi>
    );
}
