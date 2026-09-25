import { AlamatPembelian } from '@/Komponen/Pembelian/BagianDokumenPembelian';
import {
    BuatSaringPembelian,
    HalamanDaftarPembelian,
    KolomNomor,
    KolomPemasok,
    KolomStatus,
    KolomTanggal,
    KolomUang,
    TombolBuat,
} from '@/Komponen/Pembelian/DaftarPembelian';
import TabelData from '@/Komponen/TabelData/TabelData';
import type { KolomTabel } from '@/Komponen/TabelData/Tipe';
import { FormatTanggal } from '@/Pustaka/FormatWaktu';
import type { BarisDaftarFaktur, PropsDaftarFaktur } from '@/Tipe/Pembelian';

const alamat = `${AlamatPembelian}/faktur`;

export const kolomFaktur: KolomTabel<BarisDaftarFaktur>[] = [
    KolomNomor(alamat),
    {
        id: 'NomorFakturPemasok',
        header: 'No. faktur pemasok',
        enableSorting: false,
        meta: { label: 'No. faktur pemasok', prioritas: 'rendah', kelasSel: 'font-mono break-all' },
        cell: ({ row }) => row.original.NomorFakturPemasok,
    },
    KolomTanggal(),
    {
        id: 'JatuhTempo',
        accessorKey: 'JatuhTempo',
        header: 'Jatuh tempo',
        meta: { label: 'Jatuh tempo', prioritas: 'penting', kelasSel: 'whitespace-nowrap' },
        cell: ({ row }) => FormatTanggal(row.original.JatuhTempo),
    },
    KolomPemasok(),
    KolomStatus(),
    KolomUang('Total', 'Total', (f) => f.Total, true),
    KolomUang('Sisa', 'Sisa hutang', (f) => f.Sisa),
];

/** F-04 fase 1: daftar faktur pembelian (3-way matching PO–penerimaan–faktur). */
export default function HalamanDaftarFaktur({ Faktur, OpsiStatus, OpsiPemasok, Izin }: PropsDaftarFaktur) {
    const tombol = <TombolBuat href={`${alamat}/buat`} label="Catat faktur" izin={Izin} />;

    return (
        <HalamanDaftarPembelian
            judul="Faktur pembelian"
            keterangan="Faktur dari pemasok dicocokkan dengan pesanan dan penerimaan barang. Faktur yang disimpan menjadi hutang usaha sesuai termin."
            izin={Izin}
            objek="faktur pembelian"
        >
            <TabelData
                id="pembelian-faktur"
                label="Daftar faktur pembelian"
                kolom={kolomFaktur}
                sumber={{ mode: 'server', alamat, awal: Faktur }}
                ambilIdBaris={(f) => f.Uuid}
                urutBawaan="-Tanggal"
                cari="Cari nomor atau nomor faktur pemasok"
                saring={BuatSaringPembelian(OpsiStatus, OpsiPemasok)}
                alamatDetail={(f) => `${alamat}/${f.Uuid}`}
                aksiAlat={tombol}
                kosong={{
                    ilustrasi: 'Pembelian',
                    judul: 'Belum ada faktur pembelian.',
                }}
            />
        </HalamanDaftarPembelian>
    );
}
