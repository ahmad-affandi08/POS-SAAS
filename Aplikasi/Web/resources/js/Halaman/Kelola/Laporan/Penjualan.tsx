import TabelData from '@/Komponen/TabelData/TabelData';
import type { HasilTabel, KolomTabel } from '@/Komponen/TabelData/Tipe';
import { KolomAngkaPenjualan, KolomBilangan, KolomQty, KolomUang } from '@/Komponen/Laporan/KolomLaporan';
import NavigasiTab, { TautanEkspor } from '@/Komponen/Laporan/NavigasiTab';
import PetaPanasJam from '@/Komponen/Laporan/PetaPanasJam';
import SaringLaporan from '@/Komponen/Laporan/SaringLaporan';
import Pemberitahuan from '@/Komponen/Umpan/Pemberitahuan';
import { FormatRupiah } from '@/Pustaka/Format';
import { FormatTanggal } from '@/Pustaka/FormatWaktu';
import TataLetakAplikasi from '@/TataLetak/TataLetakAplikasi';
import type {
    BarisDiskonLaporan,
    BarisHarian,
    BarisKanalLaporan,
    BarisKasirLaporan,
    BarisKategoriLaporan,
    BarisMetodeLaporan,
    BarisProdukLaporan,
    IsiJam,
    PropsLaporanPenjualan,
    TabLaporanPenjualan,
} from '@/Tipe/Laporan';

const alamat = '/kelola/laporan/penjualan';

const daftarTab: { nilai: TabLaporanPenjualan; label: string }[] = [
    { nilai: 'harian', label: 'Ringkasan harian' },
    { nilai: 'produk', label: 'Per produk' },
    { nilai: 'kategori', label: 'Per kategori' },
    { nilai: 'jam', label: 'Per jam' },
    { nilai: 'kasir', label: 'Per kasir' },
    { nilai: 'kanal', label: 'Per kanal' },
    { nilai: 'metode', label: 'Metode bayar' },
    { nilai: 'diskon', label: 'Diskon' },
];

const kolomHarian: KolomTabel<BarisHarian>[] = [
    {
        id: 'Tanggal',
        accessorKey: 'Tanggal',
        header: 'Tanggal',
        meta: { label: 'Tanggal', prioritas: 'utama', wajib: true, kelasSel: 'whitespace-nowrap' },
        cell: ({ row }) => FormatTanggal(row.original.Tanggal),
    },
    ...KolomAngkaPenjualan<BarisHarian>(),
    KolomUang<BarisHarian>('Pajak', 'Pajak', 'rendah'),
    KolomBilangan<BarisHarian>('JumlahTransaksi', 'Transaksi', 'penting'),
    KolomUang<BarisHarian>('RataRataKeranjang', 'Rata-rata keranjang', 'rendah'),
];

const kolomProduk: KolomTabel<BarisProdukLaporan>[] = [
    {
        id: 'NamaProduk',
        accessorKey: 'NamaProduk',
        header: 'Produk',
        meta: { label: 'Produk', prioritas: 'utama', wajib: true },
    },
    KolomQty<BarisProdukLaporan>('Qty', 'Qty'),
    ...KolomAngkaPenjualan<BarisProdukLaporan>(),
    KolomBilangan<BarisProdukLaporan>('JumlahTransaksi', 'Transaksi'),
];

const kolomKategori: KolomTabel<BarisKategoriLaporan>[] = [
    {
        id: 'NamaKategori',
        accessorKey: 'NamaKategori',
        header: 'Kategori',
        meta: { label: 'Kategori', prioritas: 'utama', wajib: true },
    },
    KolomBilangan<BarisKategoriLaporan>('JumlahProduk', 'Produk'),
    KolomQty<BarisKategoriLaporan>('Qty', 'Qty'),
    ...KolomAngkaPenjualan<BarisKategoriLaporan>(),
];

const kolomPerJam: KolomTabel<IsiJam['PerJam'][number]>[] = [
    {
        id: 'Jam',
        accessorKey: 'Jam',
        header: 'Jam',
        meta: { label: 'Jam', prioritas: 'utama', wajib: true },
        cell: ({ row }) =>
            `${String(row.original.Jam).padStart(2, '0')}.00–${String(row.original.Jam).padStart(2, '0')}.59`,
    },
    KolomUang<IsiJam['PerJam'][number]>('Bersih', 'Bersih'),
    KolomBilangan<IsiJam['PerJam'][number]>('JumlahTransaksi', 'Transaksi', 'penting'),
];

const kolomKasir: KolomTabel<BarisKasirLaporan>[] = [
    {
        id: 'NamaKasir',
        accessorKey: 'NamaKasir',
        header: 'Kasir',
        meta: { label: 'Kasir', prioritas: 'utama', wajib: true },
    },
    ...KolomAngkaPenjualan<BarisKasirLaporan>(),
    KolomBilangan<BarisKasirLaporan>('JumlahTransaksi', 'Transaksi', 'penting'),
    KolomBilangan<BarisKasirLaporan>('JumlahRetur', 'Retur (dokumen)'),
    KolomUang<BarisKasirLaporan>('RataRataKeranjang', 'Rata-rata keranjang', 'rendah'),
];

const kolomKanal: KolomTabel<BarisKanalLaporan>[] = [
    {
        id: 'LabelKanal',
        accessorKey: 'LabelKanal',
        header: 'Kanal',
        meta: { label: 'Kanal', prioritas: 'utama', wajib: true },
    },
    ...KolomAngkaPenjualan<BarisKanalLaporan>(),
    KolomBilangan<BarisKanalLaporan>('JumlahTransaksi', 'Transaksi', 'penting'),
    KolomUang<BarisKanalLaporan>('RataRataKeranjang', 'Rata-rata keranjang', 'rendah'),
];

const kolomMetode: KolomTabel<BarisMetodeLaporan>[] = [
    {
        id: 'NamaMetode',
        accessorKey: 'NamaMetode',
        header: 'Metode bayar',
        meta: { label: 'Metode bayar', prioritas: 'utama', wajib: true },
    },
    {
        id: 'LabelJenis',
        accessorKey: 'LabelJenis',
        header: 'Jenis',
        meta: { label: 'Jenis', prioritas: 'rendah' },
    },
    KolomUang<BarisMetodeLaporan>('Diterima', 'Diterima', 'rendah'),
    KolomUang<BarisMetodeLaporan>('Refund', 'Refund retur', 'rendah'),
    KolomUang<BarisMetodeLaporan>('Bersih', 'Bersih'),
    KolomBilangan<BarisMetodeLaporan>('JumlahTransaksi', 'Transaksi', 'penting'),
];

const kolomDiskon: KolomTabel<BarisDiskonLaporan>[] = [
    {
        id: 'NamaKasir',
        accessorKey: 'NamaKasir',
        header: 'Kasir',
        meta: { label: 'Kasir', prioritas: 'utama', wajib: true },
    },
    KolomBilangan<BarisDiskonLaporan>('JumlahTransaksi', 'Transaksi'),
    KolomBilangan<BarisDiskonLaporan>('JumlahBerdiskon', 'Berdiskon', 'penting'),
    KolomBilangan<BarisDiskonLaporan>('JumlahDisetujui', 'Disetujui penyetuju'),
    KolomUang<BarisDiskonLaporan>('DiskonBaris', 'Diskon baris', 'rendah'),
    KolomUang<BarisDiskonLaporan>('DiskonPesanan', 'Diskon pesanan', 'rendah'),
    KolomUang<BarisDiskonLaporan>('TotalDiskon', 'Total diskon'),
    KolomUang<BarisDiskonLaporan>('Kotor', 'Kotor', 'rendah'),
];

const kosong = { judul: 'Belum ada penjualan pada periode dan saring ini.' };

function IsiTab({ tab, isi }: { tab: TabLaporanPenjualan; isi: PropsLaporanPenjualan['Isi'] }) {
    switch (tab) {
        case 'produk':
            return (
                <TabelData
                    id="laporan-penjualan-produk"
                    label="Penjualan per produk"
                    kolom={kolomProduk}
                    sumber={{ mode: 'server', alamat, awal: isi as HasilTabel<BarisProdukLaporan> }}
                    ambilIdBaris={(b) => String(b.IdProduk)}
                    urutBawaan="-Bersih"
                    cari="Cari nama produk"
                    ekspor={{ alamat: `${alamat}/ekspor`, label: 'Ekspor CSV' }}
                    kosong={kosong}
                />
            );
        case 'kategori':
            return (
                <TabelData
                    id="laporan-penjualan-kategori"
                    label="Penjualan per kategori"
                    kolom={kolomKategori}
                    sumber={{ mode: 'lokal', data: isi as BarisKategoriLaporan[] }}
                    ambilIdBaris={(b) => b.Kunci}
                    urutBawaan="-Bersih"
                    cari="Cari kategori"
                    kosong={kosong}
                />
            );
        case 'jam': {
            const jam = isi as IsiJam;

            return (
                <div className="flex flex-col gap-4">
                    <section aria-labelledby="judul-heatmap" className="flex flex-col gap-2">
                        <h2 id="judul-heatmap" className="text-subjudul font-semibold text-teks-utama">
                            Penjualan bersih per hari & jam
                        </h2>
                        <p className="text-label text-teks-sekunder">
                            Jam lokal outlet saat transaksi dibuat. Retur tidak mengurangi heatmap.
                        </p>
                        <PetaPanasJam sel={jam.Sel} />
                    </section>
                    <TabelData
                        id="laporan-penjualan-jam"
                        label="Penjualan per jam"
                        kolom={kolomPerJam}
                        sumber={{ mode: 'lokal', data: jam.PerJam }}
                        ambilIdBaris={(b) => String(b.Jam)}
                        urutBawaan="Jam"
                        cari={false}
                        kosong={kosong}
                    />
                </div>
            );
        }
        case 'kasir':
            return (
                <TabelData
                    id="laporan-penjualan-kasir"
                    label="Penjualan per kasir"
                    kolom={kolomKasir}
                    sumber={{ mode: 'lokal', data: isi as BarisKasirLaporan[] }}
                    ambilIdBaris={(b) => b.Kunci}
                    urutBawaan="-Bersih"
                    cari="Cari nama kasir"
                    kosong={kosong}
                />
            );
        case 'kanal':
            return (
                <TabelData
                    id="laporan-penjualan-kanal"
                    label="Penjualan per kanal"
                    kolom={kolomKanal}
                    sumber={{ mode: 'lokal', data: isi as BarisKanalLaporan[] }}
                    ambilIdBaris={(b) => b.Kunci}
                    urutBawaan="-Bersih"
                    cari={false}
                    kosong={kosong}
                />
            );
        case 'metode':
            return (
                <TabelData
                    id="laporan-penjualan-metode"
                    label="Penjualan per metode bayar"
                    kolom={kolomMetode}
                    sumber={{ mode: 'lokal', data: isi as BarisMetodeLaporan[] }}
                    ambilIdBaris={(b) => b.Kunci}
                    urutBawaan="-Bersih"
                    cari="Cari metode bayar"
                    kosong={kosong}
                />
            );
        case 'diskon':
            return (
                <TabelData
                    id="laporan-penjualan-diskon"
                    label="Diskon per kasir"
                    kolom={kolomDiskon}
                    sumber={{ mode: 'lokal', data: isi as BarisDiskonLaporan[] }}
                    ambilIdBaris={(b) => b.Kunci}
                    urutBawaan="-TotalDiskon"
                    cari="Cari nama kasir"
                    kosong={{
                        ilustrasi: 'Laporan',
                        judul: 'Belum ada penjualan berdiskon pada periode dan saring ini.',
                    }}
                />
            );
        default:
            return (
                <TabelData
                    id="laporan-penjualan-harian"
                    label="Ringkasan penjualan harian"
                    kolom={kolomHarian}
                    sumber={{ mode: 'lokal', data: isi as BarisHarian[] }}
                    ambilIdBaris={(b) => b.Tanggal}
                    urutBawaan="Tanggal"
                    cari={false}
                    kosong={kosong}
                />
            );
    }
}

/**
 * F-14a laporan penjualan: saring periode (maks. 92 hari), outlet, kasir, kanal; tab ringkasan harian, per produk,
 * kategori, jam (heatmap), kasir, kanal, metode bayar, dan diskon. Void dikeluarkan; retur mengurangi pada tanggal
 * returnya. Ekspor CSV mengikuti saring.
 */
export default function HalamanLaporanPenjualan(props: PropsLaporanPenjualan) {
    const { Saring, Total } = props;
    const query = {
        dari: Saring.Dari,
        sampai: Saring.Sampai,
        outlet: Saring.Outlet,
        kasir: Saring.Kasir,
        kanal: Saring.Kanal,
    };
    const ringkasan: [string, string][] = [
        ['Penjualan bersih', FormatRupiah(Total.Bersih)],
        ['Laba kotor', FormatRupiah(Total.LabaKotor)],
        ['Transaksi', String(Total.JumlahTransaksi)],
        ['Rata-rata keranjang', FormatRupiah(Total.RataRataKeranjang)],
        ['Kotor', FormatRupiah(Total.Kotor)],
        ['Diskon', FormatRupiah(Total.Diskon)],
        ['Retur', FormatRupiah(Total.Retur)],
        ['Pajak', FormatRupiah(Total.Pajak)],
    ];

    return (
        <TataLetakAplikasi judul="Laporan penjualan">
            <p className="max-w-3xl text-isi text-teks-sekunder">
                Angka dihitung dari penjualan yang sudah diterima server. Penjualan yang di-void tidak dihitung; retur
                mengurangi penjualan pada tanggal returnya. Bersih = kotor − diskon − retur, di luar pajak dan biaya
                layanan.
            </p>

            <SaringLaporan
                alamat={alamat}
                query={{ ...query, tab: Saring.Tab }}
                maksHari={props.MaksHari}
                pilihan={[
                    ...(props.OpsiOutlet.length > 1
                        ? [{ kunci: 'outlet', label: 'Outlet', kosong: 'Semua outlet', opsi: props.OpsiOutlet }]
                        : []),
                    { kunci: 'kasir', label: 'Kasir', kosong: 'Semua kasir', opsi: props.OpsiKasir },
                    { kunci: 'kanal', label: 'Kanal', kosong: 'Semua kanal', opsi: props.OpsiKanal },
                ]}
            />

            {props.Peringatan ? <Pemberitahuan jenis="peringatan">{props.Peringatan}</Pemberitahuan> : null}

            <dl className="grid grid-cols-2 gap-x-4 gap-y-3 rounded-panel border border-garis bg-permukaan p-4 md:grid-cols-4">
                {ringkasan.map(([label, nilai]) => (
                    <div key={label} className="min-w-0">
                        <dt className="text-label text-teks-sekunder">{label}</dt>
                        <dd className="text-subjudul font-semibold break-words text-teks-utama tabular-nums">
                            {nilai}
                        </dd>
                    </div>
                ))}
            </dl>

            <div className="flex flex-col gap-3">
                <div className="flex flex-wrap items-end justify-between gap-2">
                    <NavigasiTab
                        label="Jenis laporan penjualan"
                        alamat={alamat}
                        query={query}
                        tabAktif={Saring.Tab}
                        tab={daftarTab}
                    />
                    {Saring.Tab !== 'produk' ? (
                        <TautanEkspor alamat={`${alamat}/ekspor`} query={{ ...query, tab: Saring.Tab }} />
                    ) : null}
                </div>
                <IsiTab key={`${Saring.Tab}-${JSON.stringify(query)}`} tab={Saring.Tab} isi={props.Isi} />
            </div>
        </TataLetakAplikasi>
    );
}
