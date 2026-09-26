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
import type { BarisDaftarPembayaran, PropsDaftarPembayaran } from '@/Tipe/Pembelian';

const alamat = `${AlamatPembelian}/pembayaran`;

const kolom: KolomTabel<BarisDaftarPembayaran>[] = [
    KolomNomor(alamat),
    KolomTanggal(),
    KolomPemasok(),
    {
        id: 'Asal',
        header: 'Asal',
        enableSorting: false,
        meta: { label: 'Asal', prioritas: 'rendah' },
        cell: ({ row }) =>
            row.original.BelanjaStok
                ? 'Belanja stok'
                : row.original.Kompensasi
                  ? 'Potong klaim promo'
                  : 'Pembayaran hutang',
    },
    KolomStatus(),
    KolomUang('Total', 'Jumlah', (p) => p.Total, true),
];

/** F-04 fase 1: daftar pembayaran hutang ke pemasok. */
export default function HalamanDaftarPembayaran({ Pembayaran, OpsiStatus, OpsiPemasok, Izin }: PropsDaftarPembayaran) {
    const tombol = <TombolBuat href={`${alamat}/buat`} label="Bayar hutang" izin={Izin} />;

    return (
        <HalamanDaftarPembelian
            judul="Pembayaran hutang"
            keterangan="Pembayaran ke pemasok dari akun kas atau bank. Satu pembayaran boleh melunasi beberapa faktur, penuh atau sebagian."
            izin={Izin}
            objek="pembayaran hutang"
        >
            <TabelData
                id="pembelian-pembayaran"
                label="Daftar pembayaran hutang"
                kolom={kolom}
                sumber={{ mode: 'server', alamat, awal: Pembayaran }}
                ambilIdBaris={(p) => p.Uuid}
                urutBawaan="-Tanggal"
                cari="Cari nomor atau catatan"
                saring={BuatSaringPembelian(OpsiStatus, OpsiPemasok)}
                alamatDetail={(p) => `${alamat}/${p.Uuid}`}
                aksiAlat={tombol}
                kosong={{
                    ilustrasi: 'Pembelian',
                    judul: 'Belum ada pembayaran hutang.',
                }}
            />
        </HalamanDaftarPembelian>
    );
}
