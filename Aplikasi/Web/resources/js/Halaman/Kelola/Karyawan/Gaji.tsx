import { useForm } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';

import BidangPilihan from '@/Komponen/Formulir/BidangPilihan';
import Tombol from '@/Komponen/Formulir/Tombol';
import TabelData from '@/Komponen/TabelData/TabelData';
import type { DefinisiSaring, KolomTabel } from '@/Komponen/TabelData/Tipe';
import DialogFormulir from '@/Komponen/Tindakan/DialogFormulir';
import { DialogFooter } from '@/Komponen/Ui/dialog';
import LabelStatus from '@/Komponen/Umpan/LabelStatus';
import { FormatRupiah } from '@/Pustaka/Format';
import { FormatTanggal } from '@/Pustaka/FormatWaktu';
import TataLetakAplikasi from '@/TataLetak/TataLetakAplikasi';
import type { BarisRekapGaji, PropsDaftarRekapGaji } from '@/Tipe/Karyawan';

const alamat = '/kelola/karyawan/gaji';

const kolom: KolomTabel<BarisRekapGaji>[] = [
    {
        id: 'Periode',
        accessorKey: 'Periode',
        header: 'Periode',
        meta: { label: 'Periode', prioritas: 'utama', wajib: true, kelasSel: 'text-teks-utama' },
        cell: ({ row }) => row.original.LabelPeriode,
    },
    {
        id: 'Status',
        accessorKey: 'Status',
        header: 'Status',
        enableSorting: false,
        meta: { label: 'Status', prioritas: 'penting' },
        cell: ({ row }) => (
            <LabelStatus
                jenis={row.original.Status === 'Dibayar' ? 'sukses' : 'peringatan'}
                teks={row.original.LabelStatus}
            />
        ),
    },
    {
        id: 'TotalKotor',
        accessorKey: 'TotalKotor',
        header: 'Gaji kotor',
        enableSorting: false,
        meta: { label: 'Gaji kotor', prioritas: 'rendah', angka: true },
        cell: ({ row }) => FormatRupiah(row.original.TotalKotor),
    },
    {
        id: 'TotalPotongan',
        accessorKey: 'TotalPotongan',
        header: 'Potongan',
        enableSorting: false,
        meta: { label: 'Potongan', prioritas: 'rendah', angka: true },
        cell: ({ row }) => FormatRupiah(row.original.TotalPotongan),
    },
    {
        id: 'TotalBersih',
        accessorKey: 'TotalBersih',
        header: 'Gaji bersih',
        meta: { label: 'Gaji bersih', prioritas: 'penting', angka: true },
        cell: ({ row }) => FormatRupiah(row.original.TotalBersih),
    },
    {
        id: 'TanggalBayar',
        accessorKey: 'TanggalBayar',
        header: 'Tanggal bayar',
        enableSorting: false,
        meta: { label: 'Tanggal bayar', prioritas: 'rendah' },
        cell: ({ row }) => (row.original.TanggalBayar === null ? '—' : FormatTanggal(row.original.TanggalBayar)),
    },
];

/**
 * Rekap gaji bulanan (F-18 bagian 3): satu rekap per bulan berisi gaji pokok, komisi bersih, tambahan, dan potongan
 * kasbon per karyawan. Draf bisa diubah; setelah dibayar dijurnal dan tidak bisa diubah lagi.
 */
export default function HalamanRekapGaji({ Rekap, OpsiPeriode, OpsiStatus }: PropsDaftarRekapGaji) {
    const [buat, AturBuat] = useState(false);
    const saring: DefinisiSaring[] = [
        {
            id: 'Status',
            label: 'Status',
            jenis: 'pilihanBanyak',
            opsi: OpsiStatus.map((s) => ({ nilai: s.Nilai, label: s.Label })),
        },
    ];

    return (
        <TataLetakAplikasi judul="Rekap gaji">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <p className="max-w-3xl text-isi text-teks-sekunder">
                    Gaji pokok diambil dari data karyawan dan komisi dari laporan komisi bulan itu. Sisa kasbon dipotong
                    otomatis dan bisa diubah sebelum gaji dibayar.
                </p>
                {OpsiPeriode.length > 0 ? <Tombol onClick={() => AturBuat(true)}>Buat rekap gaji</Tombol> : null}
            </div>
            <TabelData
                id="karyawan-rekap-gaji"
                label="Daftar rekap gaji"
                kolom={kolom}
                sumber={{ mode: 'server', alamat, awal: Rekap }}
                ambilIdBaris={(r) => r.Uuid}
                labelBaris={(r) => `rekap gaji ${r.LabelPeriode}`}
                alamatDetail={(r) => `${alamat}/${r.Uuid}`}
                urutBawaan="-Periode"
                cari="Cari periode, misal 2026-09"
                saring={saring}
                kosong={{ judul: 'Belum ada rekap gaji.', ilustrasi: 'Laporan' }}
            />
            {buat ? <FormBuat opsiPeriode={OpsiPeriode} saatSelesai={() => AturBuat(false)} /> : null}
        </TataLetakAplikasi>
    );
}

function FormBuat({
    opsiPeriode,
    saatSelesai,
}: {
    opsiPeriode: PropsDaftarRekapGaji['OpsiPeriode'];
    saatSelesai: () => void;
}) {
    const formulir = useForm({ Periode: opsiPeriode[0]?.Nilai ?? '' });

    const Kirim = (peristiwa: FormEvent) => {
        peristiwa.preventDefault();
        formulir.post(alamat, { preserveScroll: true, onSuccess: saatSelesai });
    };

    return (
        <DialogFormulir
            judul="Buat rekap gaji"
            keterangan="Draf berisi karyawan aktif dan karyawan yang mendapat komisi di bulan itu."
            saatTutup={saatSelesai}
        >
            <form onSubmit={Kirim} className="flex flex-col gap-4" noValidate>
                <BidangPilihan
                    label="Periode"
                    nilai={formulir.data.Periode}
                    opsi={opsiPeriode}
                    saatBerubah={(nilai) => formulir.setData('Periode', nilai)}
                    galat={formulir.errors.Periode}
                    required
                />
                <DialogFooter className="sm:justify-start">
                    <Tombol type="submit" memproses={formulir.processing}>
                        Buat draf
                    </Tombol>
                    <Tombol varian="sekunder" onClick={saatSelesai}>
                        Batal
                    </Tombol>
                </DialogFooter>
            </form>
        </DialogFormulir>
    );
}
