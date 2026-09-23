import { Link } from '@inertiajs/react';

import { jenisLabelStatusTiket, type StatusTiket } from '@/Komponen/Dukungan/StatusTiket';
import LabelStatus from '@/Komponen/Umpan/LabelStatus';
import Paginasi from '@/Komponen/Umpan/Paginasi';
import { FormatTanggalWaktu } from '@/Pustaka/FormatWaktu';
import TataLetakAplikasi from '@/TataLetak/TataLetakAplikasi';

type RingkasanTiket = {
    Uuid: string;
    Nomor: string;
    Judul: string;
    LabelKategori: string;
    Status: StatusTiket;
    LabelStatus: string;
    DibuatPada: string;
    PesanTerakhirPada: string | null;
};

type PropsDaftar = {
    Tiket: { Data: RingkasanTiket[]; HalamanSaatIni: number; HalamanTerakhir: number; Total: number };
    Saring: { Status: 'terbuka' | 'semua' };
};

/** Daftar tiket bantuan tenant (P-09). */
export default function DaftarBantuan({ Tiket, Saring }: PropsDaftar) {
    const tab = [
        { nilai: 'terbuka', label: 'Masih terbuka', href: '/kelola/bantuan' },
        { nilai: 'semua', label: 'Semua tiket', href: '/kelola/bantuan?status=semua' },
    ] as const;

    return (
        <TataLetakAplikasi judul="Bantuan">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <p className="text-isi text-teks-sekunder">
                    Ada kendala? Kirim tiket ke Tim Dukungan dan pantau balasannya di sini.
                </p>
                <Link
                    href="/kelola/bantuan/buat"
                    className="inline-flex h-10 items-center rounded-kontrol border border-brand bg-brand px-4 text-label font-semibold text-permukaan outline-none focus-visible:ring-2 focus-visible:ring-brand focus-visible:ring-offset-2"
                >
                    Buat tiket
                </Link>
            </div>

            <nav aria-label="Saring tiket" className="flex gap-1 border-b border-garis">
                {tab.map((item) => (
                    <Link
                        key={item.nilai}
                        href={item.href}
                        aria-current={Saring.Status === item.nilai ? 'page' : undefined}
                        className={`border-b-2 px-3 py-2 text-label font-semibold outline-none focus-visible:ring-2 focus-visible:ring-brand ${
                            Saring.Status === item.nilai
                                ? 'border-brand text-teks-utama'
                                : 'border-transparent text-teks-sekunder'
                        }`}
                    >
                        {item.label}
                    </Link>
                ))}
            </nav>

            {Tiket.Data.length === 0 ? (
                <p className="rounded-panel border border-garis bg-permukaan px-4 py-6 text-isi text-teks-sekunder">
                    {Saring.Status === 'terbuka'
                        ? 'Tidak ada tiket yang masih terbuka.'
                        : 'Belum ada tiket. Buat tiket bila Anda butuh bantuan.'}
                </p>
            ) : (
                <section className="overflow-x-auto rounded-panel border border-garis bg-permukaan">
                    <table className="w-full min-w-[720px] text-left text-isi">
                        <caption className="sr-only">Tiket bantuan, terbaru di atas</caption>
                        <thead className="border-b border-garis text-label text-teks-sekunder">
                            <tr>
                                <th scope="col" className="px-4 py-2 font-semibold">
                                    Nomor
                                </th>
                                <th scope="col" className="px-4 py-2 font-semibold">
                                    Judul
                                </th>
                                <th scope="col" className="px-4 py-2 font-semibold">
                                    Status
                                </th>
                                <th scope="col" className="px-4 py-2 font-semibold">
                                    Pesan terakhir
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {Tiket.Data.map((tiket) => (
                                <tr key={tiket.Uuid} className="border-b border-garis align-top last:border-b-0">
                                    <td className="whitespace-nowrap px-4 py-3 font-mono text-label">
                                        <Link
                                            href={`/kelola/bantuan/${tiket.Uuid}`}
                                            className="font-semibold text-brand underline outline-none focus-visible:ring-2 focus-visible:ring-brand"
                                        >
                                            {tiket.Nomor}
                                        </Link>
                                    </td>
                                    <td className="px-4 py-3 text-teks-utama">
                                        <span className="block break-words">{tiket.Judul}</span>
                                        <span className="text-keterangan text-teks-sekunder">
                                            {tiket.LabelKategori}
                                        </span>
                                    </td>
                                    <td className="px-4 py-3">
                                        <LabelStatus
                                            jenis={jenisLabelStatusTiket[tiket.Status]}
                                            teks={tiket.LabelStatus}
                                        />
                                    </td>
                                    <td className="whitespace-nowrap px-4 py-3 text-teks-sekunder">
                                        {FormatTanggalWaktu(tiket.PesanTerakhirPada ?? tiket.DibuatPada)}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </section>
            )}

            <Paginasi
                alamat="/kelola/bantuan"
                saring={Saring.Status === 'semua' ? { status: 'semua' } : {}}
                halamanSaatIni={Tiket.HalamanSaatIni}
                halamanTerakhir={Tiket.HalamanTerakhir}
                total={Tiket.Total}
                label="Halaman tiket"
            />
        </TataLetakAplikasi>
    );
}
