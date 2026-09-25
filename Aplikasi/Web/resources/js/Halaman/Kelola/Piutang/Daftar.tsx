import { Link, router } from '@inertiajs/react';

import { AmbilJenisUmur } from '@/Komponen/Pembelian/AturanPembelian';
import { KolomUang } from '@/Komponen/Pembelian/DaftarPembelian';
import TabelData from '@/Komponen/TabelData/TabelData';
import type { DefinisiSaring, HasilTabel, KolomTabel } from '@/Komponen/TabelData/Tipe';
import { ItemAksiBaris } from '@/Komponen/Tindakan/MenuAksiBaris';
import { Button } from '@/Komponen/Ui/button';
import LabelStatus from '@/Komponen/Umpan/LabelStatus';
import { FormatRupiah } from '@/Pustaka/Format';
import { FormatTanggal } from '@/Pustaka/FormatWaktu';
import type { BarisPiutang, PropsDaftarPiutang, RingkasanPiutang } from '@/Tipe/Piutang';

import { FormatHariLewat } from '@/Halaman/Kelola/Pembelian/Hutang/Daftar';
import { AlamatPiutang, HalamanDaftarPiutang, LabelStatusPiutang } from '@/Komponen/Piutang/BagianPiutang';

const kolom: KolomTabel<BarisPiutang>[] = [
    {
        id: 'Nomor',
        accessorKey: 'Nomor',
        header: 'Nomor penjualan',
        meta: { label: 'Nomor penjualan', prioritas: 'utama', wajib: true },
        cell: ({ row: { original: p } }) => <span className="font-mono font-semibold break-all">{p.Nomor}</span>,
    },
    {
        id: 'Pelanggan',
        header: 'Pelanggan',
        enableSorting: false,
        meta: { label: 'Pelanggan', prioritas: 'penting' },
        cell: ({ row: { original: p } }) =>
            p.UuidPelanggan ? (
                <Link href={`/kelola/pelanggan/${p.UuidPelanggan}`} className="break-words text-brand underline">
                    {p.NamaPelanggan}
                </Link>
            ) : (
                'Tanpa pelanggan'
            ),
    },
    {
        id: 'Tanggal',
        accessorKey: 'Tanggal',
        header: 'Tanggal',
        meta: { label: 'Tanggal', prioritas: 'rendah', kelasSel: 'whitespace-nowrap' },
        cell: ({ row }) => FormatTanggal(row.original.Tanggal),
    },
    {
        id: 'JatuhTempo',
        accessorKey: 'JatuhTempo',
        header: 'Jatuh tempo',
        meta: { label: 'Jatuh tempo', prioritas: 'penting', kelasSel: 'whitespace-nowrap' },
        cell: ({ row: { original: p } }) => (
            <span className="flex flex-col">
                <span>{FormatTanggal(p.JatuhTempo)}</span>
                <span className="text-keterangan text-teks-sekunder">{FormatHariLewat(p.HariLewat)}</span>
            </span>
        ),
    },
    {
        id: 'Umur',
        header: 'Umur',
        enableSorting: false,
        meta: { label: 'Umur piutang', prioritas: 'penting' },
        cell: ({ row: { original: p } }) => <LabelStatus jenis={AmbilJenisUmur(p.Umur)} teks={p.LabelUmur} />,
    },
    {
        id: 'Status',
        header: 'Status',
        enableSorting: false,
        meta: { label: 'Status', prioritas: 'rendah' },
        cell: ({ row: { original: p } }) => <LabelStatusPiutang status={p.Status} label={p.LabelStatus} />,
    },
    KolomUang('Jumlah', 'Jumlah', (p) => p.Jumlah, true),
    KolomUang('Sisa', 'Sisa piutang', (p) => p.Sisa),
];

function Ringkasan({ ringkasan }: { ringkasan: RingkasanPiutang | undefined }) {
    if (ringkasan === undefined) {
        return null;
    }

    return (
        <section aria-label="Ringkasan umur piutang" className="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-6">
            <div className="col-span-2 min-w-0 rounded-panel border border-garis bg-permukaan px-3 py-2 sm:col-span-1">
                <p className="text-label text-teks-sekunder">Total piutang</p>
                <p className="text-subjudul font-semibold tabular-nums">{FormatRupiah(ringkasan.Total)}</p>
            </div>
            {ringkasan.Kelompok.map((k) => (
                <div key={k.Kunci} className="min-w-0 rounded-panel border border-garis bg-permukaan px-3 py-2">
                    <p className="text-label text-teks-sekunder">{k.Label}</p>
                    <p className="font-semibold tabular-nums wrap-anywhere">{FormatRupiah(k.Sisa)}</p>
                    <p className="text-keterangan text-teks-sekunder">{k.Jumlah.toLocaleString('id-ID')} penjualan</p>
                </div>
            ))}
        </section>
    );
}

/** F-12: piutang pelanggan terbuka dari penjualan tempo, umur 0–30/31–60/61–90/>90 hari, dan pintasan pelunasan. */
export default function HalamanDaftarPiutangPelanggan({ Piutang, OpsiUmur, OpsiPelanggan, Izin }: PropsDaftarPiutang) {
    const saring: DefinisiSaring[] = [
        {
            id: 'Umur',
            label: 'Umur piutang',
            jenis: 'pilihanBanyak',
            opsi: OpsiUmur.map((o) => ({ nilai: o.Nilai, label: o.Label })),
        },
        {
            id: 'Pelanggan',
            label: 'Pelanggan',
            jenis: 'pilihan',
            opsi: OpsiPelanggan.map((p) => ({ nilai: p.Uuid, label: p.Nama })),
        },
    ];
    const tombol = Izin.Kelola ? (
        <Button asChild className="h-8 pointer-coarse:h-11">
            <Link href={`${AlamatPiutang}/pelunasan/buat`}>Terima pelunasan</Link>
        </Button>
    ) : null;

    return (
        <HalamanDaftarPiutang
            judul="Piutang pelanggan"
            keterangan="Penjualan tempo yang belum lunas, diurutkan dari jatuh tempo terdekat. Terima pelunasan satu atau beberapa penjualan satu pelanggan sekaligus."
            izin={Izin}
            objek="pelunasan piutang"
        >
            <TabelData
                id="piutang-pelanggan"
                label="Daftar piutang pelanggan"
                kolom={kolom}
                sumber={{ mode: 'server', alamat: AlamatPiutang, awal: Piutang }}
                ambilIdBaris={(p) => p.Uuid}
                urutBawaan="JatuhTempo"
                cari="Cari nomor penjualan atau nama pelanggan"
                saring={saring}
                aksiAlat={tombol}
                ringkasan={(hasil) => (
                    <Ringkasan
                        ringkasan={
                            (hasil as HasilTabel<BarisPiutang, RingkasanPiutang> | undefined)?.Ringkasan ??
                            Piutang.Ringkasan
                        }
                    />
                )}
                labelBaris={(p) => `piutang ${p.Nomor}`}
                {...(Izin.Kelola
                    ? {
                          aksiBaris: (p: BarisPiutang) => (
                              <ItemAksiBaris
                                  aksi={[
                                      {
                                          label: 'Terima pelunasan',
                                          saatPilih: () =>
                                              router.visit(
                                                  `${AlamatPiutang}/pelunasan/buat?pelanggan=${p.UuidPelanggan ?? ''}&piutang=${p.Uuid}`,
                                              ),
                                      },
                                  ]}
                              />
                          ),
                      }
                    : {})}
                kosong={{
                    ilustrasi: 'Pelanggan',
                    judul: 'Tidak ada piutang terbuka. Semua penjualan tempo sudah lunas.',
                }}
            />
        </HalamanDaftarPiutang>
    );
}
