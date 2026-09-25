import { Link, router } from '@inertiajs/react';
import { useState, type ReactNode } from 'react';

import FormulirPelanggan, { AlamatPelanggan } from '@/Komponen/Pelanggan/FormulirPelanggan';
import LencanaPenjualan from '@/Komponen/Penjualan/LencanaPenjualan';
import TabelData from '@/Komponen/TabelData/TabelData';
import type { KolomTabel } from '@/Komponen/TabelData/Tipe';
import { Button } from '@/Komponen/Ui/button';
import { Card } from '@/Komponen/Ui/card';
import LabelStatus from '@/Komponen/Umpan/LabelStatus';
import { FormatRupiah } from '@/Pustaka/Format';
import { FormatTanggal, FormatTanggalWaktu } from '@/Pustaka/FormatWaktu';
import TataLetakAplikasi from '@/TataLetak/TataLetakAplikasi';
import type { PropsDetailPelanggan, RiwayatBelanja } from '@/Tipe/Pelanggan';

const labelStatus: Record<RiwayatBelanja['Status'], string> = {
    Lunas: 'Lunas',
    Void: 'Void',
    DireturSebagian: 'Diretur sebagian',
    Diretur: 'Diretur',
};

function Nilai({ label, children }: { label: string; children: ReactNode }) {
    return (
        <div className="flex flex-col gap-0.5">
            <dt className="text-label text-teks-sekunder">{label}</dt>
            <dd className="text-isi break-words text-teks-utama">{children}</dd>
        </div>
    );
}

/** F-16a CRM-01: profil pelanggan, ringkasan belanja, dan 50 transaksi terakhir. */
export default function HalamanDetailPelanggan({ Pelanggan: p, Riwayat, Izin }: PropsDetailPelanggan) {
    const [ubah, AturUbah] = useState(false);

    const kolom: KolomTabel<RiwayatBelanja>[] = [
        {
            id: 'Nomor',
            accessorKey: 'Nomor',
            header: 'Nomor',
            meta: { label: 'Nomor', prioritas: 'utama', wajib: true, kelasSel: 'font-mono break-all' },
            cell: ({ row: { original: r } }) =>
                Izin.LihatPenjualan ? (
                    <Link href={`/kelola/penjualan/${r.Uuid}`} className="text-brand underline">
                        {r.Nomor}
                    </Link>
                ) : (
                    r.Nomor
                ),
        },
        {
            id: 'DibuatPada',
            accessorKey: 'DibuatPada',
            header: 'Waktu',
            meta: { label: 'Waktu', prioritas: 'penting' },
            cell: ({ row }) => FormatTanggalWaktu(row.original.DibuatPada),
        },
        {
            id: 'Status',
            accessorKey: 'Status',
            header: 'Status',
            meta: { label: 'Status', prioritas: 'penting' },
            cell: ({ row }) => (
                <LencanaPenjualan
                    status={row.original.Status}
                    label={labelStatus[row.original.Status]}
                    perluTinjauan={false}
                />
            ),
        },
        {
            id: 'TotalAkhir',
            accessorKey: 'TotalAkhir',
            header: 'Total',
            meta: { label: 'Total', prioritas: 'penting', angka: true },
            cell: ({ row }) => FormatRupiah(row.original.TotalAkhir),
        },
    ];

    return (
        <TataLetakAplikasi judul={p.Nama}>
            <div className="flex flex-wrap items-center gap-2">
                <Link href={AlamatPelanggan} className="text-brand underline">
                    Kembali ke daftar pelanggan
                </Link>
                {p.Status === 'Aktif' ? (
                    <LabelStatus jenis="sukses" teks="Aktif" />
                ) : (
                    <LabelStatus jenis="netral" teks="Diarsipkan" />
                )}
            </div>
            {Izin.Kelola ? (
                <div className="flex flex-wrap gap-2">
                    <Button onClick={() => AturUbah(true)}>Ubah pelanggan</Button>
                    {p.Status === 'Aktif' ? (
                        <Button
                            variant="outline"
                            onClick={() =>
                                router.post(`${AlamatPelanggan}/${p.Uuid}/arsipkan`, {}, { preserveScroll: true })
                            }
                        >
                            Arsipkan
                        </Button>
                    ) : (
                        <Button
                            variant="outline"
                            onClick={() =>
                                router.post(`${AlamatPelanggan}/${p.Uuid}/pulihkan`, {}, { preserveScroll: true })
                            }
                        >
                            Pulihkan
                        </Button>
                    )}
                </div>
            ) : null}

            <div className="grid gap-4 lg:grid-cols-[2fr_1fr]">
                <Card className="gap-4 rounded-panel p-4 shadow-none">
                    <dl className="grid gap-4 sm:grid-cols-2">
                        <Nilai label="No. HP/WA">
                            <span className="font-mono">{p.NoHp}</span>
                        </Nilai>
                        <Nilai label="Email">{p.Email ?? '—'}</Nilai>
                        <Nilai label="Tanggal lahir">{p.TanggalLahir ? FormatTanggal(p.TanggalLahir) : '—'}</Nilai>
                        <Nilai label="Info promo">{p.SetujuPemasaran ? 'Setuju menerima' : 'Tidak setuju'}</Nilai>
                        <Nilai label="Tag">{p.Tag.length > 0 ? p.Tag.join(', ') : '—'}</Nilai>
                        <Nilai label="Terdaftar">{FormatTanggalWaktu(p.DibuatPada)}</Nilai>
                        <Nilai label="Alamat">{p.Alamat ?? '—'}</Nilai>
                        <Nilai label="Catatan">{p.Catatan ?? '—'}</Nilai>
                    </dl>
                </Card>
                <Card className="gap-3 rounded-panel p-4 shadow-none">
                    <h2 className="text-judul-kecil text-teks-utama">Ringkasan belanja</h2>
                    <dl className="grid gap-3">
                        <Nilai label="Jumlah transaksi">{p.JumlahTransaksi.toLocaleString('id-ID')}</Nilai>
                        <Nilai label="Total belanja (sebelum retur, tanpa void)">
                            <span className="tabular-nums">{FormatRupiah(p.TotalBelanja)}</span>
                        </Nilai>
                        <Nilai label="Terakhir belanja">
                            {p.TerakhirPada ? FormatTanggalWaktu(p.TerakhirPada) : '—'}
                        </Nilai>
                    </dl>
                </Card>
            </div>

            <h2 className="text-judul-kecil text-teks-utama">Riwayat belanja</h2>
            <TabelData
                id="pelanggan-riwayat"
                label={`Riwayat belanja ${p.Nama}`}
                kolom={kolom}
                sumber={{ mode: 'lokal', data: Riwayat }}
                ambilIdBaris={(r) => r.Uuid}
                urutBawaan="-DibuatPada"
                kosong={{ judul: 'Belum ada transaksi atas nama pelanggan ini.' }}
            />

            {ubah ? <FormulirPelanggan pelanggan={p} saatTutup={() => AturUbah(false)} /> : null}
        </TataLetakAplikasi>
    );
}
