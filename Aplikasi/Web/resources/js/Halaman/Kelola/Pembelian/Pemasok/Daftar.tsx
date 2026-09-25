import { router, usePage } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';

import BidangTeks from '@/Komponen/Formulir/BidangTeks';
import BidangTeksPanjang from '@/Komponen/Formulir/BidangTeksPanjang';
import KotakCentang from '@/Komponen/Formulir/KotakCentang';
import BidangJumlah from '@/Komponen/Katalog/BidangJumlah';
import PesanHanyaLihat from '@/Komponen/Katalog/PesanHanyaLihat';
import { AlamatPembelian } from '@/Komponen/Pembelian/BagianDokumenPembelian';
import TabelData from '@/Komponen/TabelData/TabelData';
import type { KolomTabel } from '@/Komponen/TabelData/Tipe';
import DialogFormulir from '@/Komponen/Tindakan/DialogFormulir';
import DialogKonfirmasi from '@/Komponen/Tindakan/DialogKonfirmasi';
import { ItemAksiBaris } from '@/Komponen/Tindakan/MenuAksiBaris';
import { Button } from '@/Komponen/Ui/button';
import LabelStatus from '@/Komponen/Umpan/LabelStatus';
import TataLetakAplikasi from '@/TataLetak/TataLetakAplikasi';
import type { PropsBersamaAplikasi } from '@/Tipe/Aplikasi';
import type { BarisPemasok, PropsDaftarPemasok } from '@/Tipe/Pembelian';

const alamat = `${AlamatPembelian}/pemasok`;

type IsianPemasok = Omit<BarisPemasok, 'Uuid' | 'Aktif' | 'TerminHari'> & { TerminHari: string };

const isianKosong: IsianPemasok = {
    Kode: '',
    Nama: '',
    NamaKontak: '',
    NoHp: '',
    Email: '',
    Alamat: '',
    Npwp: '',
    Pkp: false,
    TerminHari: '0',
    NamaBank: '',
    NomorRekening: '',
    AtasNamaRekening: '',
    Catatan: '',
};

/** Teks termin: 0 = tunai, N = tempo N hari. */
export function FormatTermin(hari: number): string {
    return hari === 0 ? 'Tunai' : `Tempo ${hari.toLocaleString('id-ID')} hari`;
}

const kolom: KolomTabel<BarisPemasok>[] = [
    {
        id: 'Kode',
        accessorKey: 'Kode',
        header: 'Kode',
        meta: { label: 'Kode', prioritas: 'penting', kelasSel: 'font-mono whitespace-nowrap' },
    },
    {
        id: 'Nama',
        accessorKey: 'Nama',
        header: 'Nama pemasok',
        meta: { label: 'Nama pemasok', prioritas: 'utama', wajib: true },
        cell: ({ row: { original: p } }) => (
            <span className="flex flex-col">
                <span className="font-semibold break-words text-teks-utama">{p.Nama}</span>
                {p.NamaKontak ? <span className="text-keterangan text-teks-sekunder">{p.NamaKontak}</span> : null}
            </span>
        ),
    },
    {
        id: 'Kontak',
        header: 'Kontak',
        enableSorting: false,
        meta: { label: 'Kontak', prioritas: 'rendah' },
        cell: ({ row: { original: p } }) => (
            <span className="flex flex-col break-words">
                <span>{p.NoHp ?? '—'}</span>
                {p.Email ? <span className="text-keterangan text-teks-sekunder">{p.Email}</span> : null}
            </span>
        ),
    },
    {
        id: 'TerminHari',
        accessorKey: 'TerminHari',
        header: 'Termin',
        meta: { label: 'Termin', prioritas: 'penting' },
        cell: ({ row }) => FormatTermin(row.original.TerminHari),
    },
    {
        id: 'Pajak',
        header: 'PKP',
        enableSorting: false,
        meta: { label: 'PKP', prioritas: 'rendah' },
        cell: ({ row: { original: p } }) => (p.Pkp ? `PKP${p.Npwp ? ` · ${p.Npwp}` : ''}` : 'Bukan PKP'),
    },
    {
        id: 'Status',
        header: 'Status',
        enableSorting: false,
        meta: { label: 'Status', prioritas: 'penting' },
        cell: ({ row }) =>
            row.original.Aktif ? (
                <LabelStatus jenis="sukses" teks="Aktif" />
            ) : (
                <LabelStatus jenis="netral" teks="Nonaktif" />
            ),
    },
];

/** F-04 fase 1: master pemasok (tambah, ubah, nonaktifkan, hapus bila belum dipakai). */
export default function HalamanDaftarPemasok({ Pemasok, Izin }: PropsDaftarPemasok) {
    const { props } = usePage<PropsBersamaAplikasi>();
    const galat = props.errors;
    const [form, AturForm] = useState<{ uuid: string | null; isian: IsianPemasok } | null>(null);
    const [hapus, AturHapus] = useState<BarisPemasok | null>(null);
    const [memproses, AturMemproses] = useState(false);

    const Buka = (p: BarisPemasok | null) =>
        AturForm({
            uuid: p?.Uuid ?? null,
            isian:
                p === null
                    ? isianKosong
                    : {
                          ...isianKosong,
                          ...Object.fromEntries(Object.entries(p).map(([k, v]) => [k, v ?? ''])),
                          Pkp: p.Pkp,
                          TerminHari: String(p.TerminHari),
                      },
        });

    const Ubah = (ubah: Partial<IsianPemasok>) => {
        if (form !== null) {
            AturForm({ ...form, isian: { ...form.isian, ...ubah } });
        }
    };

    const Simpan = (peristiwa: FormEvent) => {
        peristiwa.preventDefault();

        if (form === null) {
            return;
        }

        const data = { ...form.isian, TerminHari: form.isian.TerminHari === '' ? '0' : form.isian.TerminHari };
        const opsi = {
            preserveScroll: true,
            onStart: () => AturMemproses(true),
            onFinish: () => AturMemproses(false),
            onSuccess: () => AturForm(null),
        };

        if (form.uuid === null) {
            router.post(alamat, data, opsi);
        } else {
            router.put(`${alamat}/${form.uuid}`, data, opsi);
        }
    };

    return (
        <TataLetakAplikasi judul="Pemasok">
            <p className="max-w-3xl text-isi text-teks-sekunder">
                Daftar pemasok untuk pesanan pembelian, penerimaan barang, dan hutang. Termin bawaan dipakai untuk
                menghitung jatuh tempo faktur. Pemasok yang sudah dipakai tidak bisa dihapus; nonaktifkan saja.
            </p>
            {Izin.Kelola ? (
                <div>
                    <Button onClick={() => Buka(null)}>Tambah pemasok</Button>
                </div>
            ) : (
                <PesanHanyaLihat izin="pembelian.kelola" objek="pemasok" />
            )}

            <TabelData
                id="pembelian-pemasok"
                label="Daftar pemasok"
                kolom={kolom}
                sumber={{ mode: 'server', alamat, awal: Pemasok }}
                ambilIdBaris={(p) => p.Uuid}
                urutBawaan="Nama"
                cari="Cari kode, nama, kontak, no. HP, atau NPWP"
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
                ]}
                labelBaris={(p) => `untuk pemasok ${p.Nama}`}
                {...(Izin.Kelola
                    ? {
                          aksiBaris: (p: BarisPemasok) => (
                              <ItemAksiBaris
                                  aksi={[
                                      { label: 'Ubah pemasok', saatPilih: () => Buka(p) },
                                      {
                                          label: p.Aktif ? 'Nonaktifkan pemasok' : 'Aktifkan pemasok',
                                          saatPilih: () =>
                                              router.post(
                                                  `${alamat}/${p.Uuid}/status`,
                                                  { Aktif: !p.Aktif },
                                                  { preserveScroll: true },
                                              ),
                                          bahaya: p.Aktif,
                                      },
                                      { label: 'Hapus pemasok', saatPilih: () => AturHapus(p), bahaya: true },
                                  ]}
                              />
                          ),
                      }
                    : {})}
                kosong={{
                    judul: 'Belum ada pemasok. Tambahkan pemasok agar bisa membuat pesanan pembelian.',
                    ...(Izin.Kelola ? { aksi: <Button onClick={() => Buka(null)}>Tambah pemasok</Button> } : {}),
                }}
            />

            {form !== null ? (
                <DialogFormulir
                    judul={form.uuid === null ? 'Tambah pemasok' : `Ubah pemasok ${form.isian.Nama}`}
                    jenis="panel"
                    galatUmum={galat.Umum}
                    saatTutup={() => AturForm(null)}
                >
                    <form onSubmit={Simpan} className="flex flex-col gap-4" aria-label="Formulir pemasok">
                        <BidangTeks
                            label="Kode pemasok"
                            nilai={form.isian.Kode}
                            saatBerubah={(nilai) => Ubah({ Kode: nilai })}
                            galat={galat.Kode}
                            maxLength={30}
                            kode
                            required
                        />
                        <BidangTeks
                            label="Nama pemasok"
                            nilai={form.isian.Nama}
                            saatBerubah={(nilai) => Ubah({ Nama: nilai })}
                            galat={galat.Nama}
                            maxLength={150}
                            required
                        />
                        <BidangTeks
                            label="Nama kontak (opsional)"
                            nilai={form.isian.NamaKontak ?? ''}
                            saatBerubah={(nilai) => Ubah({ NamaKontak: nilai })}
                            galat={galat.NamaKontak}
                        />
                        <BidangTeks
                            label="No. HP/WA (opsional)"
                            nilai={form.isian.NoHp ?? ''}
                            saatBerubah={(nilai) => Ubah({ NoHp: nilai })}
                            galat={galat.NoHp}
                            inputMode="tel"
                        />
                        <BidangTeks
                            label="Email (opsional)"
                            jenis="email"
                            nilai={form.isian.Email ?? ''}
                            saatBerubah={(nilai) => Ubah({ Email: nilai })}
                            galat={galat.Email}
                        />
                        <BidangTeksPanjang
                            label="Alamat (opsional)"
                            nilai={form.isian.Alamat ?? ''}
                            saatBerubah={(nilai) => Ubah({ Alamat: nilai })}
                            galat={galat.Alamat}
                            maksimal={500}
                            baris={2}
                        />
                        <BidangJumlah
                            label="Termin bawaan (hari)"
                            nilai={form.isian.TerminHari}
                            saatBerubah={(nilai) => Ubah({ TerminHari: nilai })}
                            desimal={0}
                            digitBulat={3}
                            akhiran="hari"
                            keterangan="0 = tunai. Jatuh tempo faktur = tanggal faktur + termin."
                            galat={galat.TerminHari}
                        />
                        <KotakCentang
                            label="Pemasok PKP (menerbitkan faktur pajak, PPN masukan dihitung)"
                            nilai={form.isian.Pkp}
                            saatBerubah={(nilai) => Ubah({ Pkp: nilai })}
                        />
                        <BidangTeks
                            label="NPWP (opsional)"
                            nilai={form.isian.Npwp ?? ''}
                            saatBerubah={(nilai) => Ubah({ Npwp: nilai })}
                            galat={galat.Npwp}
                            kode
                        />
                        <div className="grid gap-3 sm:grid-cols-2">
                            <BidangTeks
                                label="Bank (opsional)"
                                nilai={form.isian.NamaBank ?? ''}
                                saatBerubah={(nilai) => Ubah({ NamaBank: nilai })}
                                galat={galat.NamaBank}
                            />
                            <BidangTeks
                                label="No. rekening (opsional)"
                                nilai={form.isian.NomorRekening ?? ''}
                                saatBerubah={(nilai) => Ubah({ NomorRekening: nilai })}
                                galat={galat.NomorRekening}
                                kode
                            />
                        </div>
                        <BidangTeks
                            label="Atas nama rekening (opsional)"
                            nilai={form.isian.AtasNamaRekening ?? ''}
                            saatBerubah={(nilai) => Ubah({ AtasNamaRekening: nilai })}
                            galat={galat.AtasNamaRekening}
                        />
                        <BidangTeksPanjang
                            label="Catatan (opsional)"
                            nilai={form.isian.Catatan ?? ''}
                            saatBerubah={(nilai) => Ubah({ Catatan: nilai })}
                            galat={galat.Catatan}
                            maksimal={500}
                            baris={2}
                        />
                        <div className="flex flex-wrap justify-end gap-2">
                            <Button type="button" variant="outline" onClick={() => AturForm(null)}>
                                Batal
                            </Button>
                            <Button type="submit" disabled={memproses}>
                                Simpan pemasok
                            </Button>
                        </div>
                    </form>
                </DialogFormulir>
            ) : null}

            {hapus !== null ? (
                <DialogKonfirmasi
                    judul={`Hapus pemasok ${hapus.Nama}?`}
                    labelAksi="Hapus pemasok"
                    memproses={memproses}
                    saatBatal={() => AturHapus(null)}
                    saatKonfirmasi={() =>
                        router.delete(`${alamat}/${hapus.Uuid}`, {
                            preserveScroll: true,
                            onStart: () => AturMemproses(true),
                            onFinish: () => {
                                AturMemproses(false);
                                AturHapus(null);
                            },
                        })
                    }
                >
                    <p>Pemasok yang sudah dipakai di dokumen pembelian tidak bisa dihapus; nonaktifkan saja.</p>
                </DialogKonfirmasi>
            ) : null}
        </TataLetakAplikasi>
    );
}
