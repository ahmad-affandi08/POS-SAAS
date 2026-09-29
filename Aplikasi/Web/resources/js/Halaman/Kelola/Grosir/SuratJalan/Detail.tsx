import { Link } from '@inertiajs/react';
import { useState } from 'react';

import {
    AlamatGrosir,
    DialogAlasanGrosir,
    JurnalDokumenGrosir,
    KartuKeteranganGrosir,
    KeteranganGrosir,
    LabelStatusGrosir,
    RingkasanNilaiGrosir,
    RiwayatGrosirDokumen,
} from '@/Komponen/Grosir/BagianDokumenGrosir';
import Panel from '@/Komponen/Kelola/Panel';
import TabelData from '@/Komponen/TabelData/TabelData';
import type { KolomTabel } from '@/Komponen/TabelData/Tipe';
import { Button } from '@/Komponen/Ui/button';
import Pemberitahuan from '@/Komponen/Umpan/Pemberitahuan';
import { FormatPersen, FormatRupiah } from '@/Pustaka/Format';
import { FormatJumlahStok } from '@/Pustaka/FormatPersediaan';
import { FormatTanggal } from '@/Pustaka/FormatWaktu';
import TataLetakAplikasi from '@/TataLetak/TataLetakAplikasi';
import type { BarisDetailSuratJalan, PropsDetailSuratJalan } from '@/Tipe/Grosir';

const alamat = `${AlamatGrosir}/surat-jalan`;

const kolom: KolomTabel<BarisDetailSuratJalan>[] = [
    {
        id: 'NamaProduk',
        accessorFn: (b) => `${b.NamaProduk} ${b.Sku ?? ''}`,
        header: 'Produk',
        meta: { label: 'Produk', prioritas: 'utama', wajib: true },
        cell: ({ row: { original: b } }) => (
            <span className="flex flex-col">
                <span className="font-semibold break-words">{b.NamaProduk}</span>
                <span className="font-mono text-keterangan text-teks-sekunder">{b.Sku ?? 'Tanpa SKU'}</span>
            </span>
        ),
    },
    {
        id: 'Jumlah',
        header: 'Diserahkan',
        enableSorting: false,
        meta: { label: 'Jumlah diserahkan', angka: true, prioritas: 'penting' },
        cell: ({ row: { original: b } }) => FormatJumlahStok(b.Jumlah, b.SimbolSatuan),
    },
    {
        id: 'Harga',
        header: 'Harga',
        enableSorting: false,
        meta: { label: 'Harga per satuan', angka: true, prioritas: 'penting' },
        cell: ({ row }) => FormatRupiah(row.original.Harga),
    },
    {
        id: 'Subtotal',
        header: 'Subtotal',
        enableSorting: false,
        meta: { label: 'Subtotal', angka: true, prioritas: 'penting' },
        cell: ({ row }) => FormatRupiah(row.original.Subtotal),
    },
    {
        id: 'TotalHpp',
        header: 'HPP',
        enableSorting: false,
        meta: { label: 'HPP', angka: true, prioritas: 'rendah' },
        cell: ({ row }) => FormatRupiah(row.original.TotalHpp),
    },
];

/**
 * Detail surat jalan grosir (F-12, §9.7, BR-12.2, J-12.1). Dokumen ini tidak bisa diedit: di sinilah stok keluar dan
 * HPP, pendapatan, serta PPN keluaran diakui, jadi koreksinya lewat pembatalan yang membalik semuanya (J-12.3).
 */
export default function HalamanDetailSuratJalan({
    SuratJalan,
    Baris,
    Jurnal,
    Riwayat,
    Izin,
    Tindakan,
}: PropsDetailSuratJalan) {
    const [batalkan, AturBatalkan] = useState(false);

    return (
        <TataLetakAplikasi judul={`Surat jalan ${SuratJalan.Nomor}`}>
            <div className="flex flex-wrap items-center gap-3">
                <h1 className="text-judul font-semibold break-all text-teks-utama">{SuratJalan.Nomor}</h1>
                <LabelStatusGrosir status={SuratJalan.Status} label={SuratJalan.LabelStatus} />
            </div>

            {SuratJalan.AlasanBatal !== null ? (
                <Pemberitahuan jenis="bahaya" judul="Surat jalan dibatalkan">
                    {SuratJalan.AlasanBatal}
                </Pemberitahuan>
            ) : null}
            {SuratJalan.NomorFaktur === null && SuratJalan.Status === 'Diposting' ? (
                <Pemberitahuan jenis="peringatan" judul="Belum difakturkan">
                    Barangnya sudah diserahkan dan nilainya ada di akun Piutang Belum Difakturkan. Faktur Pajak gabungan
                    dibuat paling lama akhir bulan penyerahan.
                </Pemberitahuan>
            ) : null}

            {Tindakan.Batalkan ? (
                <div className="flex flex-wrap gap-2">
                    <Button
                        variant="outline"
                        onClick={() => AturBatalkan(true)}
                        className="h-8 text-bahaya pointer-coarse:h-11"
                    >
                        Batalkan surat jalan
                    </Button>
                </div>
            ) : null}

            <KartuKeteranganGrosir>
                <KeteranganGrosir label="Pelanggan">{SuratJalan.NamaPelanggan}</KeteranganGrosir>
                <KeteranganGrosir label="Outlet penjual">{SuratJalan.KodeOutlet}</KeteranganGrosir>
                <KeteranganGrosir label="Diserahkan">{FormatTanggal(SuratJalan.Tanggal)}</KeteranganGrosir>
                <KeteranganGrosir label="Pesanan">
                    {SuratJalan.UuidPesanan === null ? (
                        '—'
                    ) : (
                        <Link
                            href={`${AlamatGrosir}/pesanan/${SuratJalan.UuidPesanan}`}
                            className="font-mono text-brand underline"
                        >
                            {SuratJalan.NomorPesanan}
                        </Link>
                    )}
                </KeteranganGrosir>
                <KeteranganGrosir label="Faktur">
                    {SuratJalan.UuidFaktur === null ? (
                        'Belum difakturkan'
                    ) : (
                        <Link
                            href={`${AlamatGrosir}/faktur/${SuratJalan.UuidFaktur}`}
                            className="font-mono text-brand underline"
                        >
                            {SuratJalan.NomorFaktur}
                        </Link>
                    )}
                </KeteranganGrosir>
                <KeteranganGrosir label="Tarif PPN">
                    {SuratJalan.TarifPpn === null ? 'Tanpa PPN' : FormatPersen(SuratJalan.TarifPpn)}
                </KeteranganGrosir>
                <KeteranganGrosir label="Pengirim">{SuratJalan.NamaPengirim ?? '—'}</KeteranganGrosir>
                <KeteranganGrosir label="Kendaraan">{SuratJalan.NomorKendaraan ?? '—'}</KeteranganGrosir>
                <KeteranganGrosir label="Penerima">{SuratJalan.NamaPenerima ?? '—'}</KeteranganGrosir>
            </KartuKeteranganGrosir>

            <Panel judul="Barang yang diserahkan">
                <TabelData
                    id="grosir-surat-jalan-baris"
                    label={`Barang surat jalan ${SuratJalan.Nomor}`}
                    kolom={kolom}
                    sumber={{ mode: 'lokal', data: Baris }}
                    ambilIdBaris={(b) => String(b.Urutan)}
                    kosong={{ judul: 'Surat jalan ini tidak punya baris.' }}
                />
                <RingkasanNilaiGrosir
                    baris={[
                        { label: 'Subtotal', nilai: SuratJalan.Subtotal },
                        { label: 'Diskon', nilai: SuratJalan.Diskon },
                        { label: 'Dasar pengenaan pajak', nilai: SuratJalan.DasarPengenaanPajak },
                        { label: 'Pajak', nilai: SuratJalan.Pajak },
                        { label: 'Total', nilai: SuratJalan.Total, tebal: true },
                        { label: 'HPP diakui', nilai: SuratJalan.TotalHpp },
                    ]}
                />
            </Panel>

            <JurnalDokumenGrosir jurnal={Jurnal} izin={Izin} />
            <RiwayatGrosirDokumen riwayat={Riwayat} />

            {batalkan ? (
                <DialogAlasanGrosir
                    judul="Batalkan surat jalan"
                    keterangan="Stok dikembalikan dan jurnal pengakuannya dibalik. Surat jalan yang sudah difakturkan tidak bisa dibatalkan."
                    labelAksi="Batalkan surat jalan"
                    alamat={`${alamat}/${SuratJalan.Uuid}/batalkan`}
                    saatTutup={() => AturBatalkan(false)}
                />
            ) : null}
        </TataLetakAplikasi>
    );
}
