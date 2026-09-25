import { Link, router } from '@inertiajs/react';
import { useState } from 'react';

import FormulirKaryawan, { AlamatKaryawan } from '@/Komponen/Karyawan/FormulirKaryawan';
import PesanHanyaLihat from '@/Komponen/Katalog/PesanHanyaLihat';
import TabelData from '@/Komponen/TabelData/TabelData';
import type { KolomTabel } from '@/Komponen/TabelData/Tipe';
import { ItemAksiBaris } from '@/Komponen/Tindakan/MenuAksiBaris';
import { Button } from '@/Komponen/Ui/button';
import LabelStatus from '@/Komponen/Umpan/LabelStatus';
import { FormatRupiah } from '@/Pustaka/Format';
import TataLetakAplikasi from '@/TataLetak/TataLetakAplikasi';
import type { BarisKaryawan, PropsDaftarKaryawan } from '@/Tipe/Karyawan';

function BuatKolom(lihatGaji: boolean): KolomTabel<BarisKaryawan>[] {
    return [
        {
            id: 'Nama',
            accessorKey: 'Nama',
            header: 'Nama karyawan',
            meta: { label: 'Nama karyawan', prioritas: 'utama', wajib: true },
            cell: ({ row: { original: k } }) => (
                <span className="flex flex-col">
                    <span className="font-semibold break-words">{k.Nama}</span>
                    {k.LevelStaf ? <span className="text-keterangan text-teks-sekunder">{k.LevelStaf}</span> : null}
                </span>
            ),
        },
        {
            id: 'Jabatan',
            accessorKey: 'Jabatan',
            header: 'Jabatan',
            meta: { label: 'Jabatan', prioritas: 'penting' },
            cell: ({ row }) => row.original.Jabatan ?? '—',
        },
        {
            id: 'Outlet',
            header: 'Outlet utama',
            enableSorting: false,
            meta: { label: 'Outlet utama', prioritas: 'rendah' },
            cell: ({ row }) => row.original.NamaOutlet ?? 'Semua outlet',
        },
        {
            id: 'Akun',
            header: 'Akun POS',
            enableSorting: false,
            meta: { label: 'Akun POS', prioritas: 'penting' },
            cell: ({ row }) => row.original.NamaPengguna ?? 'Tanpa akun (tidak bisa absen)',
        },
        ...(lihatGaji
            ? [
                  {
                      id: 'GajiPokok',
                      header: 'Gaji pokok',
                      enableSorting: false,
                      meta: { label: 'Gaji pokok', prioritas: 'rendah' as const, angka: true },
                      cell: ({ row }: { row: { original: BarisKaryawan } }) =>
                          row.original.GajiPokok ? FormatRupiah(row.original.GajiPokok) : '—',
                  },
              ]
            : []),
        {
            id: 'Status',
            header: 'Status',
            enableSorting: false,
            meta: { label: 'Status', prioritas: 'penting' },
            cell: ({ row }) => (
                <LabelStatus
                    jenis={row.original.Status === 'Aktif' ? 'sukses' : 'netral'}
                    teks={row.original.LabelStatus}
                />
            ),
        },
    ];
}

/** F-18 EMP-01: daftar karyawan; tambah (halaman penuh), ubah (panel), nonaktifkan & aktifkan. */
export default function HalamanDaftarKaryawan({ Karyawan, OpsiPengguna, OpsiOutlet, Izin }: PropsDaftarKaryawan) {
    const [ubah, AturUbah] = useState<BarisKaryawan | null>(null);
    const tombol = Izin.Kelola ? (
        <Button asChild>
            <Link href={`${AlamatKaryawan}/buat`}>Tambah karyawan</Link>
        </Button>
    ) : null;

    return (
        <TataLetakAplikasi judul="Karyawan">
            <p className="max-w-3xl text-isi text-teks-sekunder">
                Karyawan dijadwalkan per outlet dan absen masuk/keluar di aplikasi kasir dengan PIN akunnya. Karyawan
                tanpa akun tetap bisa dijadwalkan, tetapi tidak bisa absen di POS.
            </p>
            {Izin.Kelola ? <div>{tombol}</div> : <PesanHanyaLihat izin="karyawan.kelola" objek="karyawan" />}

            <TabelData
                id="karyawan"
                label="Daftar karyawan"
                kolom={BuatKolom(Izin.Kelola)}
                sumber={{ mode: 'server', alamat: AlamatKaryawan, awal: Karyawan }}
                ambilIdBaris={(k) => k.Uuid}
                urutBawaan="Nama"
                cari="Cari nama atau jabatan"
                saring={[
                    {
                        id: 'Status',
                        label: 'Status',
                        jenis: 'pilihanBanyak',
                        opsi: [
                            { nilai: 'Aktif', label: 'Aktif' },
                            { nilai: 'Nonaktif', label: 'Nonaktif' },
                        ],
                    },
                    {
                        id: 'Outlet',
                        label: 'Outlet utama',
                        jenis: 'pilihanBanyak',
                        opsi: OpsiOutlet.map((o) => ({ nilai: o.Uuid, label: o.Nama })),
                    },
                ]}
                labelBaris={(k) => `untuk karyawan ${k.Nama}`}
                {...(Izin.Kelola
                    ? {
                          aksiBaris: (k: BarisKaryawan) => (
                              <ItemAksiBaris
                                  aksi={[
                                      { label: 'Ubah karyawan', saatPilih: () => AturUbah(k) },
                                      k.Status === 'Aktif'
                                          ? {
                                                label: 'Nonaktifkan karyawan',
                                                bahaya: true,
                                                saatPilih: () =>
                                                    router.post(
                                                        `${AlamatKaryawan}/${k.Uuid}/nonaktifkan`,
                                                        {},
                                                        { preserveScroll: true },
                                                    ),
                                            }
                                          : {
                                                label: 'Aktifkan karyawan',
                                                saatPilih: () =>
                                                    router.post(
                                                        `${AlamatKaryawan}/${k.Uuid}/aktifkan`,
                                                        {},
                                                        { preserveScroll: true },
                                                    ),
                                            },
                                  ]}
                              />
                          ),
                      }
                    : {})}
                kosong={{
                    judul: 'Belum ada karyawan. Tambahkan di sini; staf yang absen di aplikasi kasir juga tercatat otomatis.',
                }}
            />

            {ubah !== null ? (
                <FormulirKaryawan
                    karyawan={ubah}
                    opsiPengguna={OpsiPengguna}
                    opsiOutlet={OpsiOutlet}
                    saatTutup={() => AturUbah(null)}
                />
            ) : null}
        </TataLetakAplikasi>
    );
}
