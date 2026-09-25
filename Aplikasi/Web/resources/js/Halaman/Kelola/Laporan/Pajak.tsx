import { KolomBilangan, KolomUang } from '@/Komponen/Laporan/KolomLaporan';
import { TautanEkspor } from '@/Komponen/Laporan/NavigasiTab';
import SaringLaporan from '@/Komponen/Laporan/SaringLaporan';
import TabelData from '@/Komponen/TabelData/TabelData';
import type { KolomTabel } from '@/Komponen/TabelData/Tipe';
import Pemberitahuan from '@/Komponen/Umpan/Pemberitahuan';
import { FormatPersen } from '@/Pustaka/Format';
import TataLetakAplikasi from '@/TataLetak/TataLetakAplikasi';
import type { BarisPajakLaporan, PropsLaporanPajak } from '@/Tipe/Laporan';

const alamat = '/kelola/laporan/pajak';
const namaBulan = [
    'Januari',
    'Februari',
    'Maret',
    'April',
    'Mei',
    'Juni',
    'Juli',
    'Agustus',
    'September',
    'Oktober',
    'November',
    'Desember',
];

/** "2026-10" → "Oktober 2026". */
export function FormatBulan(bulan: string): string {
    const [tahun = '', nomor = ''] = bulan.split('-');

    return `${namaBulan[Number(nomor) - 1] ?? nomor} ${tahun}`;
}

function BuatKolom(denganOutlet: boolean): KolomTabel<BarisPajakLaporan>[] {
    return [
        {
            id: 'Bulan',
            accessorKey: 'Bulan',
            header: 'Bulan',
            meta: { label: 'Bulan', prioritas: 'utama', wajib: true, kelasSel: 'whitespace-nowrap' },
            cell: ({ row }) => FormatBulan(row.original.Bulan),
        },
        ...(denganOutlet
            ? [
                  {
                      id: 'NamaOutlet',
                      accessorKey: 'NamaOutlet',
                      header: 'Outlet',
                      meta: { label: 'Outlet', prioritas: 'penting' as const },
                  },
              ]
            : []),
        {
            id: 'NamaJenisPajak',
            accessorKey: 'NamaJenisPajak',
            header: 'Jenis pajak',
            meta: { label: 'Jenis pajak', prioritas: 'penting' },
            cell: ({ row }) => `${row.original.NamaJenisPajak} ${FormatPersen(row.original.Tarif)}%`,
        },
        KolomUang<BarisPajakLaporan>('Dpp', 'DPP', 'rendah'),
        KolomUang<BarisPajakLaporan>('Pajak', 'Pajak', 'rendah'),
        KolomUang<BarisPajakLaporan>('PajakRetur', 'Pajak retur', 'rendah'),
        KolomUang<BarisPajakLaporan>('DppBersih', 'DPP bersih', 'penting'),
        KolomUang<BarisPajakLaporan>('PajakBersih', 'Pajak bersih', 'penting'),
        KolomBilangan<BarisPajakLaporan>('JumlahTransaksi', 'Transaksi'),
    ];
}

const kolomPbjt = BuatKolom(true);
const kolomPpn = BuatKolom(false);

/**
 * F-14a laporan pajak (izin laporan keuangan): PB1/PBJT per outlet per bulan per tarif (TAX-04) dan PPN keluaran per
 * bulan (TAX-05 dasar), dari pajak penjualan dikurangi pajak retur. Angka untuk bahan pelaporan; bukan pengganti
 * e-Faktur/SPT.
 */
export default function HalamanLaporanPajak({ Saring, Peringatan, OpsiOutlet, Pbjt, Ppn }: PropsLaporanPajak) {
    const query = { dari: Saring.Dari, sampai: Saring.Sampai, outlet: Saring.Outlet };

    return (
        <TataLetakAplikasi judul="Laporan pajak">
            <p className="max-w-3xl text-isi text-teks-sekunder">
                Pajak keluaran dari penjualan yang diterima server, dikelompokkan per bulan tanggal bisnis. Penjualan
                yang di-void tidak dihitung; pajak retur mengurangi pada bulan returnya.
            </p>

            <SaringLaporan
                alamat={alamat}
                query={query}
                maksHari={366}
                pilihan={
                    OpsiOutlet.length > 1
                        ? [{ kunci: 'outlet', label: 'Outlet', kosong: 'Semua outlet', opsi: OpsiOutlet }]
                        : []
                }
            />

            {Peringatan ? <Pemberitahuan jenis="peringatan">{Peringatan}</Pemberitahuan> : null}

            <section aria-labelledby="judul-pbjt" className="flex flex-col gap-2">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <h2 id="judul-pbjt" className="text-subjudul font-semibold text-teks-utama">
                        PB1/PBJT per outlet
                    </h2>
                    <TautanEkspor
                        alamat={`${alamat}/ekspor`}
                        query={{ ...query, jenis: 'pbjt' }}
                        label="Ekspor PB1/PBJT"
                    />
                </div>
                <TabelData
                    id="laporan-pajak-pbjt"
                    label="PB1/PBJT per outlet per bulan"
                    kolom={kolomPbjt}
                    sumber={{ mode: 'lokal', data: Pbjt }}
                    ambilIdBaris={(b) => b.Kunci}
                    urutBawaan="Bulan"
                    cari="Cari outlet atau jenis pajak"
                    kosong={{ ilustrasi: 'Laporan', judul: 'Tidak ada PB1/PBJT pada periode ini.' }}
                />
            </section>

            <section aria-labelledby="judul-ppn" className="flex flex-col gap-2">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <h2 id="judul-ppn" className="text-subjudul font-semibold text-teks-utama">
                        PPN keluaran
                    </h2>
                    <TautanEkspor alamat={`${alamat}/ekspor`} query={{ ...query, jenis: 'ppn' }} label="Ekspor PPN" />
                </div>
                <TabelData
                    id="laporan-pajak-ppn"
                    label="PPN keluaran per bulan"
                    kolom={kolomPpn}
                    sumber={{ mode: 'lokal', data: Ppn }}
                    ambilIdBaris={(b) => b.Kunci}
                    urutBawaan="Bulan"
                    cari={false}
                    kosong={{ judul: 'Tidak ada PPN keluaran pada periode ini.' }}
                />
            </section>
        </TataLetakAplikasi>
    );
}
