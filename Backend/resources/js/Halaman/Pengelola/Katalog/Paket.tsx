import { Link, router, useForm, usePage } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';

import BidangTeks from '@/Komponen/Formulir/BidangTeks';
import GrupCentang from '@/Komponen/Formulir/GrupCentang';
import KotakCentang from '@/Komponen/Formulir/KotakCentang';
import Tombol from '@/Komponen/Formulir/Tombol';
import TabKatalog from '@/Komponen/Pengelola/TabKatalog';
import LabelStatus from '@/Komponen/Umpan/LabelStatus';
import Pemberitahuan from '@/Komponen/Umpan/Pemberitahuan';
import { FormatRupiah } from '@/Pustaka/Format';
import TataLetakPengelola from '@/TataLetak/TataLetakPengelola';
import { IzinPengelola, PunyaIzin, type PropsBersamaPengelola } from '@/Tipe/Pengelola';

type Paket = {
    Uuid: string;
    Kode: string;
    Nama: string;
    Keterangan: string | null;
    Status: 'Draf' | 'Aktif' | 'Diarsipkan';
    HargaNegosiasi: boolean;
    MasaTrialHari: number;
    Urutan: number;
    Batas: Record<string, number | null>;
    KunciFitur: string[];
    HargaBulananBerlaku: string | null;
};

type Fitur = { Kunci: string; Nama: string; Modul: string };

type PropsPaket = { Paket: Paket[]; Fitur: Fitur[]; KolomBatas: string[] };

const labelBatas: Record<string, string> = {
    BatasOutlet: 'Outlet',
    BatasPerangkatPerOutlet: 'Perangkat per outlet',
    BatasPengguna: 'Pengguna',
    BatasSku: 'SKU',
    KuotaPesanWaBulanan: 'Pesan WA per bulan',
    BatasPenyimpananMb: 'Penyimpanan (MB)',
};

const labelStatus = {
    Draf: { jenis: 'netral', teks: 'Draf' },
    Aktif: { jenis: 'sukses', teks: 'Aktif' },
    Diarsipkan: { jenis: 'peringatan', teks: 'Diarsipkan' },
} as const;

/** Paket langganan: isi, batas, fitur, status (P-04, BR-P04.2, BR-P04.6). */
export default function HalamanPaket({ Paket, Fitur, KolomBatas }: PropsPaket) {
    const { props } = usePage<PropsBersamaPengelola>();
    const bolehAjukan = PunyaIzin(props.Pengguna, IzinPengelola.KatalogPaketAjukan);
    const bolehSetujui = PunyaIzin(props.Pengguna, IzinPengelola.KatalogPaketSetujui);
    const [sunting, AturSunting] = useState<Paket | 'baru' | null>(null);
    const [arsip, AturArsip] = useState<Paket | null>(null);
    const Aktifkan = (paket: Paket) =>
        router.post(`/katalog/paket/${paket.Uuid}/status`, { Status: 'Aktif' }, { preserveScroll: true });

    return (
        <TataLetakPengelola
            judul="Katalog"
            aksi={
                bolehAjukan && sunting === null ? <Tombol onClick={() => AturSunting('baru')}>Buat paket</Tombol> : null
            }
        >
            <TabKatalog />
            {props.errors.Umum ? <Pemberitahuan jenis="bahaya">{props.errors.Umum}</Pemberitahuan> : null}
            {sunting !== null ? (
                <FormPaket
                    key={sunting === 'baru' ? 'baru' : sunting.Uuid}
                    paket={sunting === 'baru' ? null : sunting}
                    fitur={Fitur}
                    kolomBatas={KolomBatas}
                    saatSelesai={() => AturSunting(null)}
                />
            ) : null}
            {arsip !== null ? <FormArsip key={arsip.Uuid} paket={arsip} saatSelesai={() => AturArsip(null)} /> : null}

            {Paket.length === 0 ? (
                <Pemberitahuan jenis="info" judul="Belum ada paket">
                    Jalankan seeder database untuk memuat paket awal (§21) sebagai draf.
                </Pemberitahuan>
            ) : (
                <section className="overflow-x-auto rounded-panel border border-garis bg-permukaan">
                    <table className="w-full text-left text-isi">
                        <caption className="sr-only">Daftar paket langganan</caption>
                        <thead className="border-b border-garis text-label text-teks-sekunder">
                            <tr>
                                <th scope="col" className="px-4 py-2 font-semibold">
                                    Paket
                                </th>
                                <th scope="col" className="px-4 py-2 text-right font-semibold">
                                    Harga/bulan
                                </th>
                                <th scope="col" className="px-4 py-2 font-semibold">
                                    Batas
                                </th>
                                <th scope="col" className="px-4 py-2 font-semibold">
                                    Status
                                </th>
                                <th scope="col" className="px-4 py-2 font-semibold">
                                    <span className="sr-only">Aksi</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {Paket.map((paket) => (
                                <tr key={paket.Uuid} className="border-b border-garis align-top last:border-b-0">
                                    <td className="px-4 py-3">
                                        <p className="font-semibold text-teks-utama">{paket.Nama}</p>
                                        <p className="font-mono text-keterangan text-teks-sekunder">{paket.Kode}</p>
                                        <p className="text-keterangan text-teks-sekunder">
                                            {paket.KunciFitur.length} fitur · trial {paket.MasaTrialHari} hari
                                        </p>
                                    </td>
                                    <td className="px-4 py-3 text-right tabular-nums text-teks-utama">
                                        {paket.HargaNegosiasi
                                            ? 'Negosiasi'
                                            : paket.HargaBulananBerlaku !== null
                                              ? FormatRupiah(paket.HargaBulananBerlaku)
                                              : 'Belum ada harga terbit'}
                                    </td>
                                    <td className="px-4 py-3 text-keterangan text-teks-sekunder">
                                        {KolomBatas.map((kolom) => (
                                            <span key={kolom} className="block">
                                                {labelBatas[kolom] ?? kolom}: {paket.Batas[kolom] ?? 'tak terbatas'}
                                            </span>
                                        ))}
                                    </td>
                                    <td className="px-4 py-3">
                                        <LabelStatus
                                            jenis={labelStatus[paket.Status].jenis}
                                            teks={labelStatus[paket.Status].teks}
                                        />
                                    </td>
                                    <td className="px-4 py-3">
                                        <div className="flex flex-col items-end gap-2">
                                            <Link
                                                href={`/katalog/paket/${paket.Uuid}/harga`}
                                                className="text-label font-semibold text-brand underline"
                                            >
                                                Harga
                                            </Link>
                                            {bolehAjukan && (paket.Status === 'Draf' || bolehSetujui) ? (
                                                <Tombol varian="sekunder" onClick={() => AturSunting(paket)}>
                                                    Ubah
                                                </Tombol>
                                            ) : null}
                                            {bolehSetujui && paket.Status !== 'Aktif' ? (
                                                <Tombol onClick={() => Aktifkan(paket)}>Aktifkan</Tombol>
                                            ) : null}
                                            {bolehSetujui && paket.Status === 'Aktif' ? (
                                                <Tombol varian="bahaya" onClick={() => AturArsip(paket)}>
                                                    Arsipkan
                                                </Tombol>
                                            ) : null}
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </section>
            )}
        </TataLetakPengelola>
    );
}

type PropsFormPaket = { paket: Paket | null; fitur: Fitur[]; kolomBatas: string[]; saatSelesai: () => void };

function FormPaket({ paket, fitur, kolomBatas, saatSelesai }: PropsFormPaket) {
    const formulir = useForm<{
        Kode: string;
        Nama: string;
        Keterangan: string;
        HargaNegosiasi: boolean;
        MasaTrialHari: string;
        Urutan: string;
        Batas: Record<string, string>;
        KunciFitur: string[];
        Alasan: string;
    }>({
        Kode: paket?.Kode ?? '',
        Nama: paket?.Nama ?? '',
        Keterangan: paket?.Keterangan ?? '',
        HargaNegosiasi: paket?.HargaNegosiasi ?? false,
        MasaTrialHari: String(paket?.MasaTrialHari ?? 14),
        Urutan: String(paket?.Urutan ?? 0),
        Batas: Object.fromEntries(
            kolomBatas.map((kolom) => [kolom, paket?.Batas[kolom] == null ? '' : String(paket.Batas[kolom])]),
        ),
        KunciFitur: paket?.KunciFitur ?? [],
        Alasan: '',
    });
    const perluAlasan = paket !== null && paket.Status !== 'Draf';
    const galat = formulir.errors as Record<string, string | undefined>;

    const Kirim = (peristiwa: FormEvent) => {
        peristiwa.preventDefault();
        const opsi = { preserveScroll: true, onSuccess: saatSelesai };

        if (paket === null) {
            formulir.post('/katalog/paket', opsi);
        } else {
            formulir.put(`/katalog/paket/${paket.Uuid}`, opsi);
        }
    };

    return (
        <form
            onSubmit={Kirim}
            className="grid gap-4 rounded-panel border border-garis bg-permukaan p-6 sm:grid-cols-2"
            noValidate
        >
            <h2 className="text-subjudul font-semibold text-teks-utama sm:col-span-2">
                {paket === null ? 'Buat paket' : `Ubah ${paket.Nama}`}
            </h2>
            {perluAlasan ? (
                <div className="sm:col-span-2">
                    <Pemberitahuan jenis="peringatan" judul="Paket ini sudah dipakai">
                        Perubahan fitur dan batas langsung berlaku untuk tenant yang memakai paket ini. Harga diubah
                        lewat halaman Harga.
                    </Pemberitahuan>
                </div>
            ) : null}
            <BidangTeks
                label="Kode"
                kode
                keterangan="Huruf besar, misal PRO. Tidak bisa diubah."
                nilai={formulir.data.Kode}
                saatBerubah={(nilai) => formulir.setData('Kode', nilai.toUpperCase())}
                galat={formulir.errors.Kode}
                disabled={paket !== null}
            />
            <BidangTeks
                label="Nama"
                nilai={formulir.data.Nama}
                saatBerubah={(nilai) => formulir.setData('Nama', nilai)}
                galat={formulir.errors.Nama}
            />
            <BidangTeks
                label="Masa trial (hari)"
                inputMode="numeric"
                nilai={formulir.data.MasaTrialHari}
                saatBerubah={(nilai) => formulir.setData('MasaTrialHari', nilai)}
                galat={formulir.errors.MasaTrialHari}
            />
            <BidangTeks
                label="Urutan tampil"
                inputMode="numeric"
                nilai={formulir.data.Urutan}
                saatBerubah={(nilai) => formulir.setData('Urutan', nilai)}
                galat={formulir.errors.Urutan}
            />
            <div className="sm:col-span-2">
                <BidangTeks
                    label="Keterangan (opsional)"
                    nilai={formulir.data.Keterangan}
                    saatBerubah={(nilai) => formulir.setData('Keterangan', nilai)}
                    galat={formulir.errors.Keterangan}
                />
            </div>
            <KotakCentang
                label="Harga negosiasi (tanpa harga tetap, misal Enterprise)"
                nilai={formulir.data.HargaNegosiasi}
                saatBerubah={(nilai) => formulir.setData('HargaNegosiasi', nilai)}
            />
            <fieldset className="grid gap-3 sm:col-span-2 sm:grid-cols-3">
                <legend className="mb-2 text-label font-semibold text-teks-utama">
                    Batas (kosongkan untuk tak terbatas)
                </legend>
                {kolomBatas.map((kolom) => (
                    <BidangTeks
                        key={kolom}
                        label={labelBatas[kolom] ?? kolom}
                        inputMode="numeric"
                        nilai={formulir.data.Batas[kolom] ?? ''}
                        saatBerubah={(nilai) => formulir.setData('Batas', { ...formulir.data.Batas, [kolom]: nilai })}
                        galat={galat[`Batas.${kolom}`] ?? galat[kolom]}
                    />
                ))}
            </fieldset>
            <div className="sm:col-span-2">
                <GrupCentang
                    legenda="Fitur termasuk"
                    opsi={fitur.map((item) => ({ nilai: item.Kunci, label: `${item.Nama} (${item.Modul})` }))}
                    terpilih={formulir.data.KunciFitur}
                    saatBerubah={(terpilih) => formulir.setData('KunciFitur', terpilih)}
                    galat={formulir.errors.KunciFitur}
                />
            </div>
            {perluAlasan ? (
                <div className="sm:col-span-2">
                    <BidangTeks
                        label="Alasan perubahan"
                        nilai={formulir.data.Alasan}
                        saatBerubah={(nilai) => formulir.setData('Alasan', nilai)}
                        galat={formulir.errors.Alasan}
                        maxLength={500}
                    />
                </div>
            ) : null}
            <div className="flex gap-2 sm:col-span-2">
                <Tombol type="submit" memproses={formulir.processing}>
                    Simpan paket
                </Tombol>
                <Tombol varian="sekunder" onClick={saatSelesai}>
                    Batal
                </Tombol>
            </div>
        </form>
    );
}

function FormArsip({ paket, saatSelesai }: { paket: Paket; saatSelesai: () => void }) {
    const formulir = useForm({ Status: 'Diarsipkan', Alasan: '' });

    const Kirim = (peristiwa: FormEvent) => {
        peristiwa.preventDefault();
        formulir.post(`/katalog/paket/${paket.Uuid}/status`, { preserveScroll: true, onSuccess: saatSelesai });
    };

    return (
        <form
            onSubmit={Kirim}
            className="flex flex-col gap-4 rounded-panel border border-bahaya bg-permukaan p-6"
            noValidate
        >
            <h2 className="text-subjudul font-semibold text-teks-utama">Arsipkan {paket.Nama}?</h2>
            <p className="text-isi text-teks-sekunder">
                Tenant baru tidak bisa memilih paket ini lagi. Tenant yang sudah memakainya tidak terdampak.
            </p>
            <BidangTeks
                label="Alasan"
                nilai={formulir.data.Alasan}
                saatBerubah={(nilai) => formulir.setData('Alasan', nilai)}
                galat={formulir.errors.Alasan}
                autoFocus
            />
            <div className="flex gap-2">
                <Tombol type="submit" varian="bahaya" memproses={formulir.processing}>
                    Arsipkan paket
                </Tombol>
                <Tombol varian="sekunder" onClick={saatSelesai}>
                    Batal
                </Tombol>
            </div>
        </form>
    );
}
