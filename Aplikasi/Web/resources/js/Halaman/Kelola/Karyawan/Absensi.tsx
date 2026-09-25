import TabelData from '@/Komponen/TabelData/TabelData';
import type { DefinisiSaring, KolomTabel } from '@/Komponen/TabelData/Tipe';
import LabelStatus from '@/Komponen/Umpan/LabelStatus';
import { FormatTanggal } from '@/Pustaka/FormatWaktu';
import TataLetakAplikasi from '@/TataLetak/TataLetakAplikasi';
import type { BarisAbsensi, PropsAbsensi, StatusKehadiran } from '@/Tipe/Karyawan';

const alamat = '/kelola/karyawan/absensi';

/** Durasi menit → `7 j 45 m`. */
export function FormatDurasiMenit(menit: number | null): string {
    if (menit === null) {
        return '—';
    }

    const jam = Math.floor(menit / 60);
    const sisa = menit % 60;

    return jam === 0 ? `${sisa} m` : `${jam} j ${sisa} m`;
}

function JenisStatus(status: StatusKehadiran): 'sukses' | 'peringatan' | 'bahaya' | 'netral' {
    return status === 'TepatWaktu'
        ? 'sukses'
        : status === 'Terlambat'
          ? 'peringatan'
          : status === 'BelumKeluar'
            ? 'bahaya'
            : 'netral';
}

function TautanSwafoto({ a, jenis }: { a: BarisAbsensi; jenis: 'masuk' | 'keluar' }) {
    const ada = jenis === 'masuk' ? a.AdaSwafotoMasuk : a.AdaSwafotoKeluar;

    return ada ? (
        <a
            href={`${alamat}/${a.Uuid}/swafoto/${jenis}`}
            target="_blank"
            rel="noreferrer"
            className="text-keterangan text-brand underline"
        >
            Swafoto {jenis}
        </a>
    ) : null;
}

const kolom: KolomTabel<BarisAbsensi>[] = [
    {
        id: 'TanggalBisnis',
        accessorKey: 'TanggalBisnis',
        header: 'Tanggal',
        meta: { label: 'Tanggal', prioritas: 'penting', kelasSel: 'whitespace-nowrap' },
        cell: ({ row }) => FormatTanggal(row.original.TanggalBisnis),
    },
    {
        id: 'Karyawan',
        header: 'Karyawan',
        enableSorting: false,
        meta: { label: 'Karyawan', prioritas: 'utama', wajib: true },
        cell: ({ row: { original: a } }) => (
            <span className="flex flex-col">
                <span className="font-semibold break-words">{a.NamaKaryawan ?? '—'}</span>
                {a.NamaOutlet ? <span className="text-keterangan text-teks-sekunder">{a.NamaOutlet}</span> : null}
            </span>
        ),
    },
    {
        id: 'MasukPada',
        accessorKey: 'MasukPada',
        header: 'Masuk',
        meta: { label: 'Masuk', prioritas: 'penting' },
        cell: ({ row: { original: a } }) => (
            <span className="flex flex-col">
                <span className="font-mono tabular-nums">{a.JamMasuk}</span>
                <TautanSwafoto a={a} jenis="masuk" />
            </span>
        ),
    },
    {
        id: 'Keluar',
        header: 'Keluar',
        enableSorting: false,
        meta: { label: 'Keluar', prioritas: 'penting' },
        cell: ({ row: { original: a } }) => (
            <span className="flex flex-col">
                <span className="font-mono tabular-nums">
                    {a.JamKeluar ?? '—'}
                    {a.KeluarBeda ? ' (+1 hari)' : ''}
                </span>
                <TautanSwafoto a={a} jenis="keluar" />
            </span>
        ),
    },
    {
        id: 'Durasi',
        header: 'Durasi',
        enableSorting: false,
        meta: { label: 'Durasi', prioritas: 'rendah', angka: true },
        cell: ({ row }) => FormatDurasiMenit(row.original.DurasiMenit),
    },
    {
        id: 'Jadwal',
        header: 'Jadwal',
        enableSorting: false,
        meta: { label: 'Jadwal', prioritas: 'rendah' },
        cell: ({ row: { original: a } }) => (
            <span className="flex flex-col">
                <span className="font-mono tabular-nums">{a.Jadwal ?? '—'}</span>
                {a.TerlambatMenit > 0 ? (
                    <span className="text-keterangan text-teks-sekunder">Terlambat {a.TerlambatMenit} menit</span>
                ) : null}
            </span>
        ),
    },
    {
        id: 'Status',
        header: 'Status',
        enableSorting: false,
        meta: { label: 'Status', prioritas: 'penting' },
        cell: ({ row }) => <LabelStatus jenis={JenisStatus(row.original.Status)} teks={row.original.LabelStatus} />,
    },
];

/** F-18 EMP-03: rekap absensi dari aplikasi kasir: jam masuk/keluar waktu outlet, durasi, keterlambatan, swafoto. */
export default function HalamanAbsensi({ Absensi, OpsiKaryawan, OpsiOutlet }: PropsAbsensi) {
    const saring: DefinisiSaring[] = [
        { id: 'TanggalBisnis', label: 'Tanggal', jenis: 'rentangTanggal' },
        {
            id: 'Karyawan',
            label: 'Karyawan',
            jenis: 'pilihanBanyak',
            opsi: OpsiKaryawan.map((k) => ({ nilai: k.Uuid, label: k.Nama })),
        },
        {
            id: 'Outlet',
            label: 'Outlet',
            jenis: 'pilihanBanyak',
            opsi: OpsiOutlet.map((o) => ({ nilai: o.Uuid, label: o.Nama })),
        },
        {
            id: 'BelumKeluar',
            label: 'Belum absen keluar',
            jenis: 'pilihan',
            opsi: [{ nilai: '1', label: 'Ya' }],
        },
    ];

    return (
        <TataLetakAplikasi judul="Absensi">
            <p className="max-w-3xl text-isi text-teks-sekunder">
                Absen masuk & keluar dari aplikasi kasir dengan PIN dan swafoto (bila perangkat berkamera). Jam memakai
                zona waktu outlet; terlambat dihitung dari jadwal kerja.
            </p>
            <TabelData
                id="karyawan-absensi"
                label="Rekap absensi"
                kolom={kolom}
                sumber={{ mode: 'server', alamat, awal: Absensi }}
                ambilIdBaris={(a) => a.Uuid}
                urutBawaan="-MasukPada"
                cari="Cari nama karyawan"
                saring={saring}
                kosong={{ judul: 'Belum ada absensi. Karyawan absen dari aplikasi kasir di outlet.' }}
            />
        </TataLetakAplikasi>
    );
}
