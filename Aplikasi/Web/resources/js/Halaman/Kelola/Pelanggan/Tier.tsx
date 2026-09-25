import { router, usePage } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';

import BidangTeks from '@/Komponen/Formulir/BidangTeks';
import BidangUang from '@/Komponen/Formulir/BidangUang';
import BidangJumlah from '@/Komponen/Katalog/BidangJumlah';
import PesanHanyaLihat from '@/Komponen/Katalog/PesanHanyaLihat';
import PesanFiturLoyalti from '@/Komponen/Pelanggan/PesanFiturLoyalti';
import TabelData from '@/Komponen/TabelData/TabelData';
import type { KolomTabel } from '@/Komponen/TabelData/Tipe';
import DialogFormulir from '@/Komponen/Tindakan/DialogFormulir';
import { ItemAksiBaris } from '@/Komponen/Tindakan/MenuAksiBaris';
import { Button } from '@/Komponen/Ui/button';
import LabelStatus from '@/Komponen/Umpan/LabelStatus';
import { FormatRupiah } from '@/Pustaka/Format';
import TataLetakAplikasi from '@/TataLetak/TataLetakAplikasi';
import type { PropsBersamaAplikasi } from '@/Tipe/Aplikasi';
import type { BarisTier, PropsTierPelanggan } from '@/Tipe/Pelanggan';

const alamat = '/kelola/pelanggan/tier';

type IsianTier = { Kode: string; Nama: string; MinimalBelanja: string; PengaliPoin: string; Urutan: string };

const kosong: IsianTier = { Kode: '', Nama: '', MinimalBelanja: '', PengaliPoin: '1', Urutan: '0' };

/** `1.50` → `×1,5`. */
export function FormatPengali(nilai: string): string {
    const [bulat = '0', pecahan = ''] = nilai.split('.');
    const sisa = pecahan.replace(/0+$/, '');

    return `×${bulat}${sisa === '' ? '' : `,${sisa}`}`;
}

const kolom: KolomTabel<BarisTier>[] = [
    {
        id: 'Nama',
        accessorKey: 'Nama',
        header: 'Tier',
        meta: { label: 'Tier', prioritas: 'utama', wajib: true },
        cell: ({ row: { original: t } }) => (
            <span className="flex flex-col">
                <span className="font-semibold text-teks-utama">{t.Nama}</span>
                <span className="font-mono text-keterangan text-teks-sekunder">{t.Kode}</span>
            </span>
        ),
    },
    {
        id: 'MinimalBelanja',
        accessorKey: 'MinimalBelanja',
        header: 'Minimal belanja',
        meta: { label: 'Minimal belanja', prioritas: 'penting', angka: true },
        cell: ({ row }) => FormatRupiah(row.original.MinimalBelanja),
    },
    {
        id: 'PengaliPoin',
        accessorKey: 'PengaliPoin',
        header: 'Pengali poin',
        meta: { label: 'Pengali poin', prioritas: 'penting', angka: true },
        cell: ({ row }) => FormatPengali(row.original.PengaliPoin),
    },
    {
        id: 'JumlahPelanggan',
        accessorKey: 'JumlahPelanggan',
        header: 'Pelanggan',
        meta: { label: 'Pelanggan', prioritas: 'rendah', angka: true },
        cell: ({ row }) => row.original.JumlahPelanggan.toLocaleString('id-ID'),
    },
    {
        id: 'Status',
        accessorKey: 'Status',
        header: 'Status',
        meta: { label: 'Status', prioritas: 'penting' },
        cell: ({ row }) =>
            row.original.Status === 'Aktif' ? (
                <LabelStatus jenis="sukses" teks="Aktif" />
            ) : (
                <LabelStatus jenis="netral" teks="Diarsipkan" />
            ),
    },
];

/** F-16b: tier pelanggan (ambang belanja untuk naik/turun otomatis, pengali poin, kode untuk daftar harga). */
export default function HalamanTierPelanggan({ Tier, FiturAktif, Izin }: PropsTierPelanggan) {
    const { props } = usePage<PropsBersamaAplikasi>();
    const galat = props.errors;
    const [form, AturForm] = useState<{ uuid: string | null; isian: IsianTier } | null>(null);
    const [memproses, AturMemproses] = useState(false);

    const Ubah = (ubah: Partial<IsianTier>) => {
        if (form !== null) {
            AturForm({ ...form, isian: { ...form.isian, ...ubah } });
        }
    };

    const Simpan = (peristiwa: FormEvent) => {
        peristiwa.preventDefault();

        if (form === null) {
            return;
        }

        const data = {
            ...form.isian,
            MinimalBelanja: form.isian.MinimalBelanja === '' ? '0' : form.isian.MinimalBelanja,
            Urutan: form.isian.Urutan === '' ? 0 : Number(form.isian.Urutan),
        };
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

    const Buka = (t: BarisTier | null) =>
        AturForm({
            uuid: t?.Uuid ?? null,
            isian:
                t === null
                    ? kosong
                    : {
                          Kode: t.Kode,
                          Nama: t.Nama,
                          MinimalBelanja: t.MinimalBelanja,
                          PengaliPoin: t.PengaliPoin,
                          Urutan: String(t.Urutan),
                      },
        });

    return (
        <TataLetakAplikasi judul="Tier pelanggan">
            <p className="max-w-3xl text-isi text-teks-sekunder">
                Tier dinilai ulang setiap malam dari total belanja pelanggan selama periode di Pengaturan loyalti: tier
                tertinggi yang ambangnya terpenuhi. Pengali poin melipatgandakan poin dari belanja. Kode tier dipakai di
                Daftar harga untuk harga khusus, misal harga reseller.
            </p>
            {FiturAktif ? null : <PesanFiturLoyalti />}
            {Izin.Kelola ? (
                <div>
                    <Button onClick={() => Buka(null)}>Tambah tier</Button>
                </div>
            ) : (
                <PesanHanyaLihat izin="pelanggan.kelola" objek="tier pelanggan" />
            )}

            <TabelData
                id="pelanggan-tier"
                label="Daftar tier pelanggan"
                kolom={kolom}
                sumber={{ mode: 'lokal', data: Tier }}
                ambilIdBaris={(t) => t.Uuid}
                urutBawaan="MinimalBelanja"
                labelBaris={(t) => `untuk tier ${t.Nama}`}
                {...(Izin.Kelola
                    ? {
                          aksiBaris: (t: BarisTier) => (
                              <ItemAksiBaris
                                  aksi={[
                                      { label: 'Ubah tier', saatPilih: () => Buka(t) },
                                      t.Status === 'Aktif'
                                          ? {
                                                label: 'Arsipkan tier',
                                                bahaya: true,
                                                saatPilih: () =>
                                                    router.post(
                                                        `${alamat}/${t.Uuid}/arsipkan`,
                                                        {},
                                                        { preserveScroll: true },
                                                    ),
                                            }
                                          : {
                                                label: 'Pulihkan tier',
                                                saatPilih: () =>
                                                    router.post(
                                                        `${alamat}/${t.Uuid}/pulihkan`,
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
                    judul: 'Belum ada tier. Contoh: Silver mulai Rp 1.000.000, Gold mulai Rp 5.000.000 per 12 bulan.',
                    ...(Izin.Kelola ? { aksi: <Button onClick={() => Buka(null)}>Tambah tier</Button> } : {}),
                }}
            />

            {form !== null ? (
                <DialogFormulir
                    judul={form.uuid === null ? 'Tambah tier' : `Ubah tier ${form.isian.Nama}`}
                    jenis="panel"
                    galatUmum={galat.Umum}
                    saatTutup={() => AturForm(null)}
                >
                    <form onSubmit={Simpan} className="flex flex-col gap-4" aria-label="Formulir tier">
                        <BidangTeks
                            label="Kode tier"
                            nilai={form.isian.Kode}
                            saatBerubah={(nilai) => Ubah({ Kode: nilai })}
                            galat={galat.Kode}
                            keterangan={
                                form.uuid === null
                                    ? 'Misal SILVER atau RESELLER. Tidak bisa diubah setelah disimpan.'
                                    : 'Kode tidak bisa diubah karena dipakai daftar harga.'
                            }
                            maxLength={30}
                            disabled={form.uuid !== null}
                            kode
                            required
                        />
                        <BidangTeks
                            label="Nama tier"
                            nilai={form.isian.Nama}
                            saatBerubah={(nilai) => Ubah({ Nama: nilai })}
                            galat={galat.Nama}
                            maxLength={60}
                            required
                        />
                        <BidangUang
                            label="Minimal belanja dalam periode evaluasi"
                            nilai={form.isian.MinimalBelanja}
                            saatBerubah={(nilai) => Ubah({ MinimalBelanja: nilai })}
                            galat={galat.MinimalBelanja}
                            keterangan="Rp 0 = semua pelanggan yang pernah belanja."
                        />
                        <BidangJumlah
                            label="Pengali poin"
                            nilai={form.isian.PengaliPoin}
                            saatBerubah={(nilai) => Ubah({ PengaliPoin: nilai })}
                            desimal={2}
                            digitBulat={2}
                            akhiran="×"
                            keterangan="1 = poin normal, 1,5 = poin 50% lebih banyak. Antara 0,1 dan 10."
                            galat={galat.PengaliPoin}
                        />
                        <BidangJumlah
                            label="Urutan tampil"
                            nilai={form.isian.Urutan}
                            saatBerubah={(nilai) => Ubah({ Urutan: nilai })}
                            desimal={0}
                            digitBulat={3}
                            galat={galat.Urutan}
                        />
                        <div className="flex flex-wrap justify-end gap-2">
                            <Button type="button" variant="outline" onClick={() => AturForm(null)}>
                                Batal
                            </Button>
                            <Button type="submit" disabled={memproses}>
                                Simpan tier
                            </Button>
                        </div>
                    </form>
                </DialogFormulir>
            ) : null}
        </TataLetakAplikasi>
    );
}
