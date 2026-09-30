import { router } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';

import Tombol from '@/Komponen/Formulir/Tombol';
import Panel from '@/Komponen/Kelola/Panel';
import TabelData from '@/Komponen/TabelData/TabelData';
import type { KolomTabel } from '@/Komponen/TabelData/Tipe';
import { Input } from '@/Komponen/Ui/input';
import { Label } from '@/Komponen/Ui/label';
import { FormatTanggal } from '@/Pustaka/FormatWaktu';
import TataLetakAplikasi from '@/TataLetak/TataLetakAplikasi';
import type { BarisRiwayatNomorSeri, PropsNomorSeri, UnitNomorSeri } from '@/Tipe/Persediaan';

const alamat = '/kelola/persediaan/nomor-seri';

const kolomUnit: KolomTabel<UnitNomorSeri>[] = [
    {
        id: 'Nomor',
        accessorKey: 'Nomor',
        header: 'Nomor seri',
        meta: { label: 'Nomor seri', prioritas: 'utama', wajib: true },
        cell: ({ row }) => <span className="font-mono">{row.original.Nomor}</span>,
    },
    {
        id: 'NamaProduk',
        accessorKey: 'NamaProduk',
        header: 'Produk',
        meta: { label: 'Produk', prioritas: 'utama' },
    },
    {
        id: 'LabelStatus',
        accessorKey: 'LabelStatus',
        header: 'Status',
        meta: { label: 'Status', prioritas: 'penting' },
    },
    {
        id: 'Keterangan',
        header: 'Keterangan',
        enableSorting: false,
        meta: { label: 'Keterangan', prioritas: 'rendah' },
        cell: ({ row: { original: u } }) =>
            u.Status === 'Terjual' && u.NomorPenjualan
                ? `Terjual ${u.TanggalJual ? FormatTanggal(u.TanggalJual) : ''} · ${u.NomorPenjualan}`
                : (u.NamaGudang ?? '—'),
    },
];

const kolomRiwayat: KolomTabel<BarisRiwayatNomorSeri>[] = [
    {
        id: 'Tanggal',
        accessorKey: 'Tanggal',
        header: 'Tanggal',
        enableSorting: false,
        meta: { label: 'Tanggal', prioritas: 'utama', wajib: true },
        cell: ({ row }) => FormatTanggal(row.original.Tanggal),
    },
    {
        id: 'Jenis',
        accessorKey: 'Jenis',
        header: 'Peristiwa',
        enableSorting: false,
        meta: { label: 'Peristiwa', prioritas: 'utama' },
        cell: ({ row }) => `${row.original.Jenis} (${row.original.Arah === 'Masuk' ? 'masuk stok' : 'keluar stok'})`,
    },
    {
        id: 'NomorDokumen',
        accessorKey: 'NomorDokumen',
        header: 'Dokumen',
        enableSorting: false,
        meta: { label: 'Dokumen', prioritas: 'penting' },
        cell: ({ row }) => <span className="font-mono">{row.original.NomorDokumen ?? '—'}</span>,
    },
    {
        id: 'NamaGudang',
        accessorKey: 'NamaGudang',
        header: 'Lokasi stok',
        enableSorting: false,
        meta: { label: 'Lokasi stok', prioritas: 'rendah' },
        cell: ({ row }) => row.original.NamaGudang ?? '—',
    },
];

/**
 * F-05h: cari nomor seri/IMEI (potongan nomor cukup) lalu pilih satu unit untuk melihat riwayatnya, dari masuk ke
 * stok sampai terjual atau diretur. Dibaca dari buku stok.
 */
export default function HalamanNomorSeri({ Saring, Hasil, Detail, BatasHasil }: PropsNomorSeri) {
    const [cari, AturCari] = useState(Saring.Cari);

    const Kirim = (peristiwa: FormEvent) => {
        peristiwa.preventDefault();
        router.get(alamat, cari.trim() === '' ? {} : { cari: cari.trim() }, { preserveScroll: true });
    };

    return (
        <TataLetakAplikasi judul="Nomor seri">
            <form
                onSubmit={Kirim}
                className="flex flex-wrap items-end gap-2"
                role="search"
                aria-label="Cari nomor seri"
            >
                <div className="flex min-w-64 flex-1 flex-col gap-1">
                    <Label htmlFor="cari-nomor-seri" className="text-label font-semibold text-teks-utama">
                        Nomor seri / IMEI
                    </Label>
                    <Input
                        id="cari-nomor-seri"
                        value={cari}
                        onChange={(e) => AturCari(e.target.value)}
                        placeholder="Ketik atau pindai sebagian nomor"
                        autoFocus
                    />
                </div>
                <Tombol type="submit">Cari</Tombol>
            </form>

            {Saring.Cari === '' ? (
                <p className="text-label text-teks-sekunder">
                    Masukkan nomor seri atau IMEI untuk melihat status dan riwayatnya: kapan masuk, kapan dijual, dan
                    apakah pernah diretur.
                </p>
            ) : (
                <section aria-labelledby="judul-hasil-seri" className="flex flex-col gap-2">
                    <h2 id="judul-hasil-seri" className="text-subjudul font-semibold text-teks-utama">
                        Hasil pencarian ({String(Hasil.length)})
                    </h2>
                    {Hasil.length >= BatasHasil ? (
                        <p className="text-label text-teks-sekunder">
                            Menampilkan {String(BatasHasil)} nomor pertama. Persempit pencarian untuk hasil lain.
                        </p>
                    ) : null}
                    <TabelData
                        id="hasil-nomor-seri"
                        label="Hasil pencarian nomor seri"
                        kolom={kolomUnit}
                        sumber={{ mode: 'lokal', data: Hasil }}
                        ambilIdBaris={(u) => u.Uuid}
                        cari={false}
                        alamatDetail={(u) =>
                            `${alamat}?${new URLSearchParams({ cari: Saring.Cari, unit: u.Uuid }).toString()}`
                        }
                        kosong={{ ilustrasi: true, judul: 'Nomor seri tidak ditemukan.' }}
                    />
                </section>
            )}

            {Detail ? (
                <Panel judul={`Riwayat ${Detail.Unit.Nomor}`}>
                    <dl className="grid gap-3 sm:grid-cols-3">
                        <div>
                            <dt className="text-label text-teks-sekunder">Produk</dt>
                            <dd className="text-teks-utama">{Detail.Unit.NamaProduk}</dd>
                        </div>
                        <div>
                            <dt className="text-label text-teks-sekunder">Status</dt>
                            <dd className="text-teks-utama">{Detail.Unit.LabelStatus}</dd>
                        </div>
                        <div>
                            <dt className="text-label text-teks-sekunder">
                                {Detail.Unit.Status === 'Terjual' ? 'Terjual' : 'Lokasi stok'}
                            </dt>
                            <dd className="text-teks-utama">
                                {Detail.Unit.Status === 'Terjual' && Detail.Unit.NomorPenjualan
                                    ? `${Detail.Unit.TanggalJual ? FormatTanggal(Detail.Unit.TanggalJual) : ''} · ${Detail.Unit.NomorPenjualan}`
                                    : (Detail.Unit.NamaGudang ?? '—')}
                            </dd>
                        </div>
                    </dl>
                    <TabelData
                        id="riwayat-nomor-seri"
                        label="Riwayat nomor seri"
                        kolom={kolomRiwayat}
                        sumber={{ mode: 'lokal', data: Detail.Riwayat }}
                        ambilIdBaris={(b) => `${b.Tanggal}-${b.Jenis}-${b.NomorDokumen ?? ''}-${b.Arah}`}
                        cari={false}
                        kosong={{ ilustrasi: true, judul: 'Belum ada riwayat.' }}
                    />
                </Panel>
            ) : null}
        </TataLetakAplikasi>
    );
}
