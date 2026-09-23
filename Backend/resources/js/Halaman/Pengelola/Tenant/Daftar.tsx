import { Link, router } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';

import BidangPilihan from '@/Komponen/Formulir/BidangPilihan';
import BidangTeks from '@/Komponen/Formulir/BidangTeks';
import Tombol from '@/Komponen/Formulir/Tombol';
import { LabelPenanda, LabelStatusLangganan } from '@/Komponen/Pengelola/Tenant/LabelLangganan';
import Paginasi from '@/Komponen/Umpan/Paginasi';
import Pemberitahuan from '@/Komponen/Umpan/Pemberitahuan';
import { FormatTanggalWaktu } from '@/Pustaka/FormatWaktu';
import TataLetakPengelola from '@/TataLetak/TataLetakPengelola';
import type { DaftarBerhalaman, Pilihan } from '@/Tipe/Pengelola';
import type { BarisTenant } from '@/Tipe/TenantPengelola';

type PropsDaftar = {
    Tenant: DaftarBerhalaman<BarisTenant>;
    Saring: { Kata: string; Status: string; Penanda: string };
    PilihanStatus: Pilihan[];
    PilihanPenanda: Pilihan[];
};

/** Daftar tenant (P-07): cari nama, slug, atau email Owner; saring status langganan & penanda. */
export default function Daftar({ Tenant, Saring, PilihanStatus, PilihanPenanda }: PropsDaftar) {
    const [kata, AturKata] = useState(Saring.Kata);
    const [status, AturStatus] = useState(Saring.Status);
    const [penanda, AturPenanda] = useState(Saring.Penanda);
    const saringAktif = Object.fromEntries(
        Object.entries({ kata: Saring.Kata, status: Saring.Status, penanda: Saring.Penanda }).filter(
            ([, nilai]) => nilai,
        ),
    );
    const adaSaringan = Object.keys(saringAktif).length > 0;

    const Cari = (peristiwa: FormEvent) => {
        peristiwa.preventDefault();
        const kueri = Object.fromEntries(Object.entries({ kata, status, penanda }).filter(([, nilai]) => nilai));
        router.get('/tenant', kueri, { preserveState: true });
    };

    return (
        <TataLetakPengelola judul="Tenant">
            <form onSubmit={Cari} className="grid items-end gap-3 sm:grid-cols-[2fr_1fr_1fr_auto]">
                <BidangTeks
                    label="Cari tenant"
                    nilai={kata}
                    saatBerubah={AturKata}
                    keterangan="Nama usaha, slug, atau email Owner"
                />
                <BidangPilihan
                    label="Status langganan"
                    nilai={status}
                    opsi={PilihanStatus}
                    kosong="Semua status"
                    saatBerubah={AturStatus}
                />
                <BidangPilihan
                    label="Penanda"
                    nilai={penanda}
                    opsi={PilihanPenanda}
                    kosong="Semua tenant"
                    saatBerubah={AturPenanda}
                />
                <Tombol type="submit" varian="sekunder">
                    Terapkan
                </Tombol>
            </form>

            {Tenant.Data.length === 0 ? (
                <Pemberitahuan jenis="info" judul={adaSaringan ? 'Tidak ada tenant yang cocok' : 'Belum ada tenant'}>
                    {adaSaringan
                        ? 'Ubah kata kunci atau saringan, lalu terapkan lagi.'
                        : 'Tenant muncul di sini setelah calon pelanggan mendaftar dari halaman Daftar Gratis.'}
                </Pemberitahuan>
            ) : (
                <section className="overflow-x-auto rounded-panel border border-garis bg-permukaan">
                    <table className="w-full min-w-[860px] text-left text-isi">
                        <caption className="sr-only">Daftar tenant, terbaru di atas</caption>
                        <thead className="border-b border-garis text-label text-teks-sekunder">
                            <tr>
                                <th scope="col" className="px-4 py-2 font-semibold">
                                    Tenant
                                </th>
                                <th scope="col" className="px-4 py-2 font-semibold">
                                    Owner
                                </th>
                                <th scope="col" className="px-4 py-2 font-semibold">
                                    Paket & status
                                </th>
                                <th scope="col" className="px-4 py-2 font-semibold">
                                    Trial berakhir
                                </th>
                                <th scope="col" className="px-4 py-2 font-semibold">
                                    Terdaftar
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {Tenant.Data.map((tenant) => (
                                <tr key={tenant.Uuid} className="border-b border-garis align-top last:border-b-0">
                                    <td className="px-4 py-3">
                                        <Link
                                            href={`/tenant/${tenant.Uuid}`}
                                            className="font-semibold text-brand underline outline-none focus-visible:ring-2 focus-visible:ring-brand"
                                        >
                                            {tenant.Nama}
                                        </Link>
                                        <span className="block font-mono text-keterangan text-teks-sekunder">
                                            {tenant.Slug}
                                        </span>
                                    </td>
                                    <td className="break-all px-4 py-3 text-teks-sekunder">
                                        {tenant.EmailPemilik ?? '—'}
                                    </td>
                                    <td className="px-4 py-3">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <span className="font-mono text-label">{tenant.KodePaket ?? '—'}</span>
                                            <LabelStatusLangganan status={tenant.StatusLangganan} />
                                            <LabelPenanda penanda={tenant.Penanda} />
                                        </div>
                                    </td>
                                    <td className="whitespace-nowrap px-4 py-3 text-teks-sekunder">
                                        {tenant.StatusLangganan === 'Trial'
                                            ? FormatTanggalWaktu(tenant.TrialBerakhirPada)
                                            : '—'}
                                    </td>
                                    <td className="whitespace-nowrap px-4 py-3 text-teks-sekunder">
                                        {FormatTanggalWaktu(tenant.DibuatPada)}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </section>
            )}

            <Paginasi
                alamat="/tenant"
                saring={saringAktif}
                halamanSaatIni={Tenant.HalamanSaatIni}
                halamanTerakhir={Tenant.HalamanTerakhir}
                total={Tenant.Total}
                label="Halaman daftar tenant"
            />
        </TataLetakPengelola>
    );
}
