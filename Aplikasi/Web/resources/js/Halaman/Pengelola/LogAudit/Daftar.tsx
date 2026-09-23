import { Link, router } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';

import BidangTeks from '@/Komponen/Formulir/BidangTeks';
import Tombol from '@/Komponen/Formulir/Tombol';
import { FormatTanggalWaktu } from '@/Pustaka/FormatWaktu';
import TataLetakPengelola from '@/TataLetak/TataLetakPengelola';

type Log = {
    Id: number;
    Aksi: string;
    Pelaku: string;
    JenisObjek: string | null;
    IdObjek: number | null;
    IdTenant: number | null;
    NilaiLama: Record<string, unknown> | null;
    NilaiBaru: Record<string, unknown> | null;
    Alasan: string | null;
    Ip: string | null;
    DibuatPada: string;
};

type PropsDaftar = {
    Log: { Data: Log[]; HalamanSaatIni: number; HalamanTerakhir: number; Total: number };
    Saring: { Kata: string };
};

/** Log audit Platform Pengelola, hanya baca (BR-P01.3). */
export default function Daftar({ Log, Saring }: PropsDaftar) {
    const [kata, AturKata] = useState(Saring.Kata);

    const Cari = (peristiwa: FormEvent) => {
        peristiwa.preventDefault();
        router.get('/log-audit', kata ? { kata } : {}, { preserveState: true });
    };
    const BuatTautanHalaman = (halaman: number) =>
        `/log-audit?${new URLSearchParams({ ...(Saring.Kata ? { kata: Saring.Kata } : {}), halaman: String(halaman) }).toString()}`;

    return (
        <TataLetakPengelola judul="Log audit">
            <form onSubmit={Cari} className="flex flex-wrap items-end gap-2">
                <div className="w-full max-w-sm">
                    <BidangTeks
                        label="Cari aksi"
                        nilai={kata}
                        saatBerubah={AturKata}
                        keterangan="Misal: tim.anggota atau sesi.masuk"
                    />
                </div>
                <Tombol type="submit" varian="sekunder">
                    Cari
                </Tombol>
            </form>

            {Log.Data.length === 0 ? (
                <p className="rounded-panel border border-garis bg-permukaan px-4 py-6 text-isi text-teks-sekunder">
                    {Saring.Kata ? `Tidak ada log dengan aksi "${Saring.Kata}".` : 'Belum ada aktivitas yang tercatat.'}
                </p>
            ) : (
                <section className="overflow-x-auto rounded-panel border border-garis bg-permukaan">
                    <table className="w-full min-w-[860px] text-left text-isi">
                        <caption className="sr-only">Log audit pengelola, terbaru di atas</caption>
                        <thead className="border-b border-garis text-label text-teks-sekunder">
                            <tr>
                                <th scope="col" className="px-4 py-2 font-semibold">
                                    Waktu
                                </th>
                                <th scope="col" className="px-4 py-2 font-semibold">
                                    Pelaku
                                </th>
                                <th scope="col" className="px-4 py-2 font-semibold">
                                    Aksi
                                </th>
                                <th scope="col" className="px-4 py-2 font-semibold">
                                    Objek
                                </th>
                                <th scope="col" className="px-4 py-2 font-semibold">
                                    Perubahan
                                </th>
                                <th scope="col" className="px-4 py-2 font-semibold">
                                    IP
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {Log.Data.map((log) => (
                                <tr key={log.Id} className="border-b border-garis align-top last:border-b-0">
                                    <td className="whitespace-nowrap px-4 py-3 text-teks-sekunder">
                                        {FormatTanggalWaktu(log.DibuatPada)}
                                    </td>
                                    <td className="px-4 py-3 text-teks-utama">{log.Pelaku}</td>
                                    <td className="px-4 py-3 font-mono text-label text-teks-utama">{log.Aksi}</td>
                                    <td className="px-4 py-3 text-teks-sekunder">
                                        {log.JenisObjek ? `${log.JenisObjek} #${String(log.IdObjek ?? '')}` : '—'}
                                        {log.IdTenant !== null ? (
                                            <span className="block">Tenant #{log.IdTenant}</span>
                                        ) : null}
                                    </td>
                                    <td className="px-4 py-3 text-keterangan text-teks-sekunder">
                                        {log.NilaiLama ? (
                                            <p>
                                                Lama: <code className="font-mono">{JSON.stringify(log.NilaiLama)}</code>
                                            </p>
                                        ) : null}
                                        {log.NilaiBaru ? (
                                            <p>
                                                Baru: <code className="font-mono">{JSON.stringify(log.NilaiBaru)}</code>
                                            </p>
                                        ) : null}
                                        {log.Alasan ? <p>Alasan: {log.Alasan}</p> : null}
                                    </td>
                                    <td className="px-4 py-3 font-mono text-keterangan text-teks-sekunder">
                                        {log.Ip ?? '—'}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </section>
            )}

            {Log.HalamanTerakhir > 1 ? (
                <nav aria-label="Halaman log audit" className="flex items-center justify-between text-label">
                    <span className="text-teks-sekunder">
                        Halaman {Log.HalamanSaatIni} dari {Log.HalamanTerakhir} · {Log.Total} entri
                    </span>
                    <div className="flex gap-2">
                        {Log.HalamanSaatIni > 1 ? (
                            <Link
                                href={BuatTautanHalaman(Log.HalamanSaatIni - 1)}
                                className="font-semibold text-brand underline"
                            >
                                Sebelumnya
                            </Link>
                        ) : null}
                        {Log.HalamanSaatIni < Log.HalamanTerakhir ? (
                            <Link
                                href={BuatTautanHalaman(Log.HalamanSaatIni + 1)}
                                className="font-semibold text-brand underline"
                            >
                                Berikutnya
                            </Link>
                        ) : null}
                    </div>
                </nav>
            ) : null}
        </TataLetakPengelola>
    );
}
