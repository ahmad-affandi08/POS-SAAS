import { Link, router, usePage } from '@inertiajs/react';
import { useState, type FormEvent, type ReactNode } from 'react';

import BidangPilihan from '@/Komponen/Formulir/BidangPilihan';
import BidangTeks from '@/Komponen/Formulir/BidangTeks';
import KotakCentang from '@/Komponen/Formulir/KotakCentang';
import DialogFormulir from '@/Komponen/Tindakan/DialogFormulir';

import FormulirPelanggan, { AlamatPelanggan } from '@/Komponen/Pelanggan/FormulirPelanggan';
import LencanaPenjualan from '@/Komponen/Penjualan/LencanaPenjualan';
import TabelData from '@/Komponen/TabelData/TabelData';
import type { KolomTabel } from '@/Komponen/TabelData/Tipe';
import { Button } from '@/Komponen/Ui/button';
import { Card } from '@/Komponen/Ui/card';
import LabelStatus from '@/Komponen/Umpan/LabelStatus';
import { FormatRupiah } from '@/Pustaka/Format';
import { FormatTanggal, FormatTanggalWaktu } from '@/Pustaka/FormatWaktu';
import { BandingkanDesimal } from '@/Pustaka/HitungDesimal';
import TataLetakAplikasi from '@/TataLetak/TataLetakAplikasi';
import type { PropsBersamaAplikasi } from '@/Tipe/Aplikasi';
import type { MutasiPoin, PropsDetailPelanggan, RiwayatBelanja } from '@/Tipe/Pelanggan';

const kolomPoin: KolomTabel<MutasiPoin>[] = [
    {
        id: 'DibuatPada',
        accessorKey: 'DibuatPada',
        header: 'Waktu',
        meta: { label: 'Waktu', prioritas: 'utama', wajib: true },
        cell: ({ row }) => FormatTanggalWaktu(row.original.DibuatPada),
    },
    {
        id: 'LabelJenis',
        accessorKey: 'LabelJenis',
        header: 'Jenis',
        meta: { label: 'Jenis', prioritas: 'penting' },
        cell: ({ row: { original: m } }) => (
            <span className="flex flex-col">
                <span>{m.LabelJenis}</span>
                {m.Keterangan ? <span className="text-keterangan text-teks-sekunder">{m.Keterangan}</span> : null}
            </span>
        ),
    },
    {
        id: 'Poin',
        accessorKey: 'Poin',
        header: 'Poin',
        meta: { label: 'Poin', prioritas: 'penting', angka: true },
        cell: ({ row }) => `${row.original.Poin > 0 ? '+' : ''}${row.original.Poin.toLocaleString('id-ID')}`,
    },
    {
        id: 'KedaluwarsaPada',
        accessorKey: 'KedaluwarsaPada',
        header: 'Berlaku sampai',
        meta: { label: 'Berlaku sampai', prioritas: 'rendah' },
        cell: ({ row: { original: m } }) =>
            m.KedaluwarsaPada
                ? `${FormatTanggal(m.KedaluwarsaPada)} (sisa ${(m.Sisa ?? 0).toLocaleString('id-ID')})`
                : '—',
    },
];

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
export default function HalamanDetailPelanggan({
    Pelanggan: p,
    Riwayat,
    RiwayatPoin,
    OpsiTier,
    LoyaltiBerlaku,
    Kredit,
    Izin,
}: PropsDetailPelanggan) {
    const { props } = usePage<PropsBersamaAplikasi>();
    const galat = props.errors;
    const [ubah, AturUbah] = useState(false);
    const [dialog, AturDialog] = useState<'tier' | 'poin' | null>(null);
    const [tier, AturTier] = useState({
        Uuid: OpsiTier.find((t) => t.Nilai === p.Tier?.Kode)?.Uuid ?? '',
        Tetap: p.TierTetap,
    });
    const [penyesuaian, AturPenyesuaian] = useState({ Poin: '', Alasan: '' });
    const [memproses, AturMemproses] = useState(false);
    const opsiKirim = {
        preserveScroll: true,
        onStart: () => AturMemproses(true),
        onFinish: () => AturMemproses(false),
        onSuccess: () => AturDialog(null),
    };

    const SimpanTier = (peristiwa: FormEvent) => {
        peristiwa.preventDefault();
        router.post(
            `${AlamatPelanggan}/${p.Uuid}/tier`,
            { UuidTier: tier.Uuid === '' ? null : tier.Uuid, TierTetap: tier.Tetap },
            opsiKirim,
        );
    };

    const SimpanPoin = (peristiwa: FormEvent) => {
        peristiwa.preventDefault();
        router.post(
            `${AlamatPelanggan}/${p.Uuid}/poin`,
            { Poin: Number.parseInt(penyesuaian.Poin || '0', 10), Alasan: penyesuaian.Alasan },
            opsiKirim,
        );
    };

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

            <Card className="gap-3 rounded-panel p-4 shadow-none">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <h2 className="text-judul-kecil text-teks-utama">Kredit & piutang</h2>
                    {Kredit && BandingkanDesimal(Kredit.SisaPiutang, '0') > 0 ? (
                        <Link href={`/kelola/piutang?saring[Pelanggan]=${p.Uuid}`} className="text-brand underline">
                            Lihat piutang
                        </Link>
                    ) : null}
                </div>
                <dl className="grid gap-4 sm:grid-cols-4">
                    <Nilai label="Limit kredit">
                        {p.LimitKredit ? (
                            <span className="tabular-nums">{FormatRupiah(p.LimitKredit)}</span>
                        ) : (
                            'Tidak boleh tempo'
                        )}
                    </Nilai>
                    <Nilai label="Termin">{`${p.TerminHari.toLocaleString('id-ID')} hari`}</Nilai>
                    <Nilai label="Sisa piutang">
                        <span className="tabular-nums">{FormatRupiah(Kredit?.SisaPiutang ?? '0')}</span>
                    </Nilai>
                    <Nilai label="Lewat jatuh tempo">
                        {Kredit && Kredit.HariLewatJatuhTempo > 0
                            ? `${Kredit.HariLewatJatuhTempo.toLocaleString('id-ID')} hari`
                            : 'Tidak ada'}
                    </Nilai>
                </dl>
            </Card>

            <Card className="gap-3 rounded-panel p-4 shadow-none">
                <h2 className="text-judul-kecil text-teks-utama">Tier & poin</h2>
                {LoyaltiBerlaku ? null : (
                    <p className="text-isi text-teks-sekunder">
                        Loyalti belum aktif: poin tidak bertambah dari belanja. Aktifkan di Pengaturan loyalti.
                    </p>
                )}
                <dl className="grid gap-4 sm:grid-cols-3">
                    <Nilai label="Tier">
                        {p.Tier
                            ? `${p.Tier.Nama}${p.TierTetap ? ' (dikunci, tidak dievaluasi otomatis)' : ''}`
                            : 'Belum ada tier'}
                    </Nilai>
                    <Nilai label="Saldo poin">
                        <span className="tabular-nums">{p.SaldoPoin.toLocaleString('id-ID')}</span>
                    </Nilai>
                </dl>
                {Izin.Kelola ? (
                    <div className="flex flex-wrap gap-2">
                        <Button variant="outline" onClick={() => AturDialog('tier')}>
                            Atur tier
                        </Button>
                        <Button variant="outline" onClick={() => AturDialog('poin')}>
                            Sesuaikan poin
                        </Button>
                    </div>
                ) : null}
            </Card>

            <h2 className="text-judul-kecil text-teks-utama">Riwayat poin</h2>
            <TabelData
                id="pelanggan-riwayat-poin"
                label={`Riwayat poin ${p.Nama}`}
                kolom={kolomPoin}
                sumber={{ mode: 'lokal', data: RiwayatPoin }}
                ambilIdBaris={(m) => String(m.Id)}
                urutBawaan="-DibuatPada"
                kosong={{ judul: 'Belum ada mutasi poin.' }}
            />

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

            {dialog === 'tier' ? (
                <DialogFormulir
                    judul={`Atur tier ${p.Nama}`}
                    jenis="panel"
                    galatUmum={galat.Umum}
                    saatTutup={() => AturDialog(null)}
                >
                    <form onSubmit={SimpanTier} className="flex flex-col gap-4" aria-label="Formulir tier pelanggan">
                        <BidangPilihan
                            label="Tier"
                            nilai={tier.Uuid}
                            kosong="Tanpa tier"
                            opsi={OpsiTier.map((t) => ({ Nilai: t.Uuid, Label: t.Label }))}
                            saatBerubah={(nilai) => AturTier({ ...tier, Uuid: nilai })}
                            galat={galat.UuidTier}
                        />
                        <KotakCentang
                            label="Kunci tier (tidak diubah evaluasi otomatis, misal reseller)"
                            nilai={tier.Tetap}
                            saatBerubah={(nilai) => AturTier({ ...tier, Tetap: nilai })}
                        />
                        <div className="flex flex-wrap justify-end gap-2">
                            <Button type="button" variant="outline" onClick={() => AturDialog(null)}>
                                Batal
                            </Button>
                            <Button type="submit" disabled={memproses}>
                                Simpan tier
                            </Button>
                        </div>
                    </form>
                </DialogFormulir>
            ) : null}

            {dialog === 'poin' ? (
                <DialogFormulir
                    judul={`Sesuaikan poin ${p.Nama}`}
                    jenis="panel"
                    galatUmum={galat.Umum}
                    saatTutup={() => AturDialog(null)}
                >
                    <form onSubmit={SimpanPoin} className="flex flex-col gap-4" aria-label="Formulir penyesuaian poin">
                        <BidangTeks
                            label="Poin (+ tambah, − kurangi)"
                            nilai={penyesuaian.Poin}
                            saatBerubah={(nilai) =>
                                AturPenyesuaian({ ...penyesuaian, Poin: nilai.replace(/[^0-9-]/g, '') })
                            }
                            galat={galat.Poin}
                            keterangan={`Saldo sekarang ${p.SaldoPoin.toLocaleString('id-ID')} poin. Maksimal 100.000 per penyesuaian.`}
                            inputMode="numeric"
                            maxLength={7}
                            required
                        />
                        <BidangTeks
                            label="Alasan"
                            nilai={penyesuaian.Alasan}
                            saatBerubah={(nilai) => AturPenyesuaian({ ...penyesuaian, Alasan: nilai })}
                            galat={galat.Alasan}
                            maxLength={255}
                            required
                        />
                        <div className="flex flex-wrap justify-end gap-2">
                            <Button type="button" variant="outline" onClick={() => AturDialog(null)}>
                                Batal
                            </Button>
                            <Button type="submit" disabled={memproses}>
                                Simpan penyesuaian
                            </Button>
                        </div>
                    </form>
                </DialogFormulir>
            ) : null}
        </TataLetakAplikasi>
    );
}
