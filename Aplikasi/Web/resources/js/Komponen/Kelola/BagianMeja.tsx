import { router, useForm } from '@inertiajs/react';
import { useMemo, useState, type FormEvent } from 'react';

import BidangPilihan from '@/Komponen/Formulir/BidangPilihan';
import BidangTeks from '@/Komponen/Formulir/BidangTeks';
import Tombol from '@/Komponen/Formulir/Tombol';
import TabelData from '@/Komponen/TabelData/TabelData';
import type { KolomTabel } from '@/Komponen/TabelData/Tipe';
import DialogFormulir from '@/Komponen/Tindakan/DialogFormulir';
import { ItemAksiBaris } from '@/Komponen/Tindakan/MenuAksiBaris';
import LabelStatus from '@/Komponen/Umpan/LabelStatus';
import Pemberitahuan from '@/Komponen/Umpan/Pemberitahuan';
import type { Pilihan, StatusOrganisasi } from '@/Tipe/Organisasi';

export type AreaMeja = { Uuid: string; Nama: string; Urutan: number; Status: StatusOrganisasi; JumlahMeja: number };

export type Meja = {
    Uuid: string;
    Nama: string;
    UuidArea: string | null;
    NamaArea: string | null;
    Kapasitas: number;
    Bentuk: string;
    Urutan: number;
    Status: StatusOrganisasi;
};

export type ModeMejaOutlet = { Aktif: boolean; Area: AreaMeja[]; Meja: Meja[] };

type PropsBagianMeja = {
    alamatOutlet: string;
    modeMeja: ModeMejaOutlet;
    bentuk: Pilihan[];
    bolehKelola: boolean;
};

function LabelStatusOrganisasi({ status }: { status: StatusOrganisasi }) {
    return status === 'Aktif' ? (
        <LabelStatus jenis="sukses" teks="Aktif" />
    ) : (
        <LabelStatus jenis="netral" teks="Diarsipkan" />
    );
}

function TindakanStatus(alamat: string, status: StatusOrganisasi) {
    return {
        label: status === 'Aktif' ? 'Arsipkan' : 'Pulihkan',
        bahaya: status === 'Aktif',
        saatPilih: () =>
            router.post(`${alamat}/${status === 'Aktif' ? 'arsipkan' : 'pulihkan'}`, {}, { preserveScroll: true }),
    };
}

/**
 * F-10a: area & meja outlet untuk mode meja. Tampil bila fitur mode meja aktif di outlet atau sudah ada data meja
 * (data lama tetap bisa diubah/diarsipkan setelah turun paket; menambah butuh fitur aktif).
 */
export default function BagianMeja({ alamatOutlet, modeMeja, bentuk, bolehKelola }: PropsBagianMeja) {
    const [suntingArea, AturSuntingArea] = useState<AreaMeja | 'baru' | null>(null);
    const [suntingMeja, AturSuntingMeja] = useState<Meja | 'baru' | null>(null);
    const bolehTambah = bolehKelola && modeMeja.Aktif;
    const labelBentuk = useMemo(() => new Map(bentuk.map((b) => [b.Nilai, b.Label])), [bentuk]);
    const opsiArea: Pilihan[] = [
        { Nilai: '', Label: 'Tanpa area' },
        ...modeMeja.Area.filter((a) => a.Status === 'Aktif').map((a) => ({ Nilai: a.Uuid, Label: a.Nama })),
    ];

    if (!modeMeja.Aktif && modeMeja.Area.length === 0 && modeMeja.Meja.length === 0) {
        return null;
    }

    const kolomArea: KolomTabel<AreaMeja>[] = [
        { id: 'Nama', accessorKey: 'Nama', header: 'Area', meta: { label: 'Area', prioritas: 'utama', wajib: true } },
        {
            id: 'JumlahMeja',
            accessorKey: 'JumlahMeja',
            header: 'Meja aktif',
            meta: { label: 'Meja aktif', prioritas: 'penting', angka: true },
        },
        {
            id: 'Status',
            accessorKey: 'Status',
            header: 'Status',
            meta: { label: 'Status', prioritas: 'penting' },
            cell: ({ row }) => <LabelStatusOrganisasi status={row.original.Status} />,
        },
    ];

    const kolomMeja: KolomTabel<Meja>[] = [
        {
            id: 'Nama',
            accessorKey: 'Nama',
            header: 'Meja',
            meta: { label: 'Meja', prioritas: 'utama', wajib: true, kelasSel: 'font-semibold text-teks-utama' },
        },
        {
            id: 'NamaArea',
            accessorFn: (m) => m.NamaArea ?? 'Tanpa area',
            header: 'Area',
            meta: { label: 'Area', prioritas: 'penting' },
        },
        {
            id: 'Kapasitas',
            accessorKey: 'Kapasitas',
            header: 'Kapasitas',
            meta: { label: 'Kapasitas', prioritas: 'penting', angka: true },
            cell: ({ row }) => `${String(row.original.Kapasitas)} orang`,
        },
        {
            id: 'Bentuk',
            accessorFn: (m) => labelBentuk.get(m.Bentuk) ?? m.Bentuk,
            header: 'Bentuk',
            meta: { label: 'Bentuk', prioritas: 'rendah', kelasSel: 'text-teks-sekunder' },
        },
        {
            id: 'Status',
            accessorKey: 'Status',
            header: 'Status',
            meta: { label: 'Status', prioritas: 'penting' },
            cell: ({ row }) => <LabelStatusOrganisasi status={row.original.Status} />,
        },
    ];

    return (
        <section className="flex flex-col gap-3" aria-labelledby="judul-meja-outlet">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <h2 id="judul-meja-outlet" className="text-subjudul font-semibold text-teks-utama">
                    Meja & area
                </h2>
                {bolehTambah ? (
                    <div className="flex flex-wrap gap-2">
                        <Tombol varian="sekunder" onClick={() => AturSuntingArea('baru')}>
                            Tambah area
                        </Tombol>
                        <Tombol varian="sekunder" onClick={() => AturSuntingMeja('baru')}>
                            Tambah meja
                        </Tombol>
                    </div>
                ) : null}
            </div>
            <p className="text-keterangan text-teks-sekunder">
                Meja dipilih kasir atau pelayan saat membuka pesanan makan di tempat. Area (misal Indoor, Teras, VIP)
                mengelompokkan meja di layar kasir.
            </p>
            {modeMeja.Aktif ? null : (
                <Pemberitahuan jenis="info" judul="Mode meja tidak aktif">
                    Paket atau template outlet ini tidak memakai mode meja. Data meja lama tetap bisa diubah atau
                    diarsipkan, tetapi meja baru tidak bisa ditambah.
                </Pemberitahuan>
            )}

            {suntingArea !== null ? (
                <DialogFormulir
                    judul={suntingArea === 'baru' ? 'Tambah area meja' : `Ubah area ${suntingArea.Nama}`}
                    saatTutup={() => AturSuntingArea(null)}
                >
                    <FormArea
                        key={suntingArea === 'baru' ? 'baru' : suntingArea.Uuid}
                        alamatOutlet={alamatOutlet}
                        area={suntingArea === 'baru' ? null : suntingArea}
                        saatSelesai={() => AturSuntingArea(null)}
                    />
                </DialogFormulir>
            ) : null}
            {suntingMeja !== null ? (
                <DialogFormulir
                    judul={suntingMeja === 'baru' ? 'Tambah meja' : `Ubah meja ${suntingMeja.Nama}`}
                    saatTutup={() => AturSuntingMeja(null)}
                >
                    <FormMeja
                        key={suntingMeja === 'baru' ? 'baru' : suntingMeja.Uuid}
                        alamatOutlet={alamatOutlet}
                        meja={suntingMeja === 'baru' ? null : suntingMeja}
                        opsiArea={opsiArea}
                        bentuk={bentuk}
                        saatSelesai={() => AturSuntingMeja(null)}
                    />
                </DialogFormulir>
            ) : null}

            <TabelData
                id="organisasi-area-meja"
                label="Area meja"
                kolom={kolomArea}
                sumber={{ mode: 'lokal', data: modeMeja.Area }}
                ambilIdBaris={(a) => a.Uuid}
                cari={false}
                labelBaris={(a) => `area ${a.Nama}`}
                {...(bolehKelola
                    ? {
                          aksiBaris: (a: AreaMeja) => (
                              <ItemAksiBaris
                                  aksi={[
                                      { label: 'Ubah', saatPilih: () => AturSuntingArea(a) },
                                      TindakanStatus(`${alamatOutlet}/area-meja/${a.Uuid}`, a.Status),
                                  ]}
                              />
                          ),
                      }
                    : {})}
                kosong={{ judul: 'Belum ada area. Area opsional; meja tanpa area tetap bisa dipakai.' }}
            />
            <TabelData
                id="organisasi-meja"
                label="Meja"
                kolom={kolomMeja}
                sumber={{ mode: 'lokal', data: modeMeja.Meja }}
                ambilIdBaris={(m) => m.Uuid}
                cari="Cari nama meja"
                labelBaris={(m) => `meja ${m.Nama}`}
                {...(bolehKelola
                    ? {
                          aksiBaris: (m: Meja) => (
                              <ItemAksiBaris
                                  aksi={[
                                      { label: 'Ubah', saatPilih: () => AturSuntingMeja(m) },
                                      TindakanStatus(`${alamatOutlet}/meja/${m.Uuid}`, m.Status),
                                  ]}
                              />
                          ),
                      }
                    : {})}
                kosong={{ judul: 'Belum ada meja. Tambah meja agar kasir bisa membuka pesanan per meja.' }}
            />
        </section>
    );
}

type PropsFormArea = { alamatOutlet: string; area: AreaMeja | null; saatSelesai: () => void };

function FormArea({ alamatOutlet, area, saatSelesai }: PropsFormArea) {
    const formulir = useForm({ Nama: area?.Nama ?? '', Urutan: String(area?.Urutan ?? 0) });

    const Kirim = (peristiwa: FormEvent) => {
        peristiwa.preventDefault();
        const opsi = { preserveScroll: true, onSuccess: saatSelesai };

        if (area === null) {
            formulir.post(`${alamatOutlet}/area-meja`, opsi);
        } else {
            formulir.put(`${alamatOutlet}/area-meja/${area.Uuid}`, opsi);
        }
    };

    return (
        <form onSubmit={Kirim} className="flex flex-col gap-4" noValidate>
            <BidangTeks
                label="Nama area"
                nilai={formulir.data.Nama}
                saatBerubah={(nilai) => formulir.setData('Nama', nilai)}
                galat={formulir.errors.Nama}
                keterangan="Misal Indoor, Teras, atau VIP"
                maxLength={60}
                autoFocus
                required
            />
            <BidangTeks
                label="Urutan tampil"
                nilai={formulir.data.Urutan}
                saatBerubah={(nilai) => formulir.setData('Urutan', nilai.replace(/\D/g, ''))}
                galat={formulir.errors.Urutan}
                keterangan="Angka kecil tampil lebih dulu di layar kasir"
                inputMode="numeric"
                maxLength={3}
            />
            <div className="flex flex-wrap gap-2">
                <Tombol type="submit" memproses={formulir.processing}>
                    Simpan area
                </Tombol>
                <Tombol varian="sekunder" onClick={saatSelesai}>
                    Batal
                </Tombol>
            </div>
        </form>
    );
}

type PropsFormMeja = {
    alamatOutlet: string;
    meja: Meja | null;
    opsiArea: Pilihan[];
    bentuk: Pilihan[];
    saatSelesai: () => void;
};

function FormMeja({ alamatOutlet, meja, opsiArea, bentuk, saatSelesai }: PropsFormMeja) {
    const formulir = useForm({
        Nama: meja?.Nama ?? '',
        Area: meja?.UuidArea ?? '',
        Kapasitas: String(meja?.Kapasitas ?? 4),
        Bentuk: meja?.Bentuk ?? 'Persegi',
        Urutan: String(meja?.Urutan ?? 0),
    });
    // Area meja yang diarsipkan tetap bisa dipertahankan saat mengubah meja lama.
    const opsi =
        meja?.UuidArea && !opsiArea.some((o) => o.Nilai === meja.UuidArea)
            ? [...opsiArea, { Nilai: meja.UuidArea, Label: `${meja.NamaArea ?? 'Area'} (diarsipkan)` }]
            : opsiArea;

    const Kirim = (peristiwa: FormEvent) => {
        peristiwa.preventDefault();
        const pilihan = { preserveScroll: true, onSuccess: saatSelesai };

        if (meja === null) {
            formulir.post(`${alamatOutlet}/meja`, pilihan);
        } else {
            formulir.put(`${alamatOutlet}/meja/${meja.Uuid}`, pilihan);
        }
    };

    return (
        <form onSubmit={Kirim} className="flex flex-col gap-4" noValidate>
            <BidangTeks
                label="Nama atau nomor meja"
                nilai={formulir.data.Nama}
                saatBerubah={(nilai) => formulir.setData('Nama', nilai)}
                galat={formulir.errors.Nama}
                keterangan="Misal 7 atau VIP 2. Unik di outlet ini."
                maxLength={30}
                autoFocus
                required
            />
            <BidangPilihan
                label="Area"
                nilai={formulir.data.Area}
                opsi={opsi}
                saatBerubah={(nilai) => formulir.setData('Area', nilai)}
                galat={formulir.errors.Area}
            />
            <div className="grid gap-4 sm:grid-cols-2">
                <BidangTeks
                    label="Kapasitas (orang)"
                    nilai={formulir.data.Kapasitas}
                    saatBerubah={(nilai) => formulir.setData('Kapasitas', nilai.replace(/\D/g, ''))}
                    galat={formulir.errors.Kapasitas}
                    inputMode="numeric"
                    maxLength={2}
                    required
                />
                <BidangPilihan
                    label="Bentuk"
                    nilai={formulir.data.Bentuk}
                    opsi={bentuk}
                    saatBerubah={(nilai) => formulir.setData('Bentuk', nilai)}
                    galat={formulir.errors.Bentuk}
                    required
                />
            </div>
            <BidangTeks
                label="Urutan tampil"
                nilai={formulir.data.Urutan}
                saatBerubah={(nilai) => formulir.setData('Urutan', nilai.replace(/\D/g, ''))}
                galat={formulir.errors.Urutan}
                inputMode="numeric"
                maxLength={3}
            />
            <div className="flex flex-wrap gap-2">
                <Tombol type="submit" memproses={formulir.processing}>
                    Simpan meja
                </Tombol>
                <Tombol varian="sekunder" onClick={saatSelesai}>
                    Batal
                </Tombol>
            </div>
        </form>
    );
}
