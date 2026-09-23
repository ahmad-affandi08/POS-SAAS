import { router } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';

import BidangTeks from '@/Komponen/Formulir/BidangTeks';
import Tombol from '@/Komponen/Formulir/Tombol';
import Paginasi from '@/Komponen/Umpan/Paginasi';
import { FormatTanggalWaktu } from '@/Pustaka/FormatWaktu';
import TataLetakAplikasi from '@/TataLetak/TataLetakAplikasi';

type Log = {
    Id: number;
    Peristiwa: string;
    Pelaku: string;
    JenisObjek: string | null;
    IdObjek: number | null;
    NilaiLama: Record<string, unknown> | null;
    NilaiBaru: Record<string, unknown> | null;
    Ip: string | null;
    DibuatPada: string;
};

type PropsDaftar = {
    Log: { Data: Log[]; HalamanSaatIni: number; HalamanTerakhir: number; Total: number };
    Saring: { Kata: string };
};

/** Log audit usaha, hanya baca (aturan LogAudit §13.2): siapa melakukan apa, kapan, dari mana. */
export default function HalamanLogAudit({ Log, Saring }: PropsDaftar) {
    const [kata, AturKata] = useState(Saring.Kata);

    const Cari = (peristiwa: FormEvent) => {
        peristiwa.preventDefault();
        router.get('/kelola/log-audit', kata ? { kata } : {}, { preserveState: true });
    };

    return (
        <TataLetakAplikasi judul="Log audit">
            <form onSubmit={Cari} className="flex flex-wrap items-end gap-2">
                <div className="w-full max-w-sm">
                    <BidangTeks
                        label="Cari peristiwa"
                        nilai={kata}
                        saatBerubah={AturKata}
                        keterangan="Misal: outlet, pengguna.undang, atau sesi.masuk"
                    />
                </div>
                <Tombol type="submit" varian="sekunder">
                    Cari
                </Tombol>
            </form>

            {Log.Data.length === 0 ? (
                <p className="rounded-panel border border-garis bg-permukaan px-4 py-6 text-isi text-teks-sekunder">
                    {Saring.Kata
                        ? `Tidak ada log dengan peristiwa "${Saring.Kata}".`
                        : 'Belum ada aktivitas yang tercatat.'}
                </p>
            ) : (
                <section className="overflow-x-auto rounded-panel border border-garis bg-permukaan">
                    <table className="w-full min-w-[860px] text-left text-isi">
                        <caption className="sr-only">Log audit usaha, terbaru di atas</caption>
                        <thead className="border-b border-garis text-label text-teks-sekunder">
                            <tr>
                                <th scope="col" className="px-4 py-2 font-semibold">
                                    Waktu
                                </th>
                                <th scope="col" className="px-4 py-2 font-semibold">
                                    Pelaku
                                </th>
                                <th scope="col" className="px-4 py-2 font-semibold">
                                    Peristiwa
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
                                    <td className="whitespace-nowrap px-4 py-2 text-teks-sekunder">
                                        {FormatTanggalWaktu(log.DibuatPada)}
                                    </td>
                                    <td className="px-4 py-2 text-teks-utama">{log.Pelaku}</td>
                                    <td className="px-4 py-2 font-mono text-label text-teks-utama">{log.Peristiwa}</td>
                                    <td className="px-4 py-2 text-teks-sekunder">
                                        {log.JenisObjek ? `${log.JenisObjek} #${String(log.IdObjek ?? '')}` : '—'}
                                    </td>
                                    <td className="px-4 py-2 text-keterangan text-teks-sekunder">
                                        {log.NilaiLama ? (
                                            <p>
                                                Lama:{' '}
                                                <code className="font-mono break-all">
                                                    {JSON.stringify(log.NilaiLama)}
                                                </code>
                                            </p>
                                        ) : null}
                                        {log.NilaiBaru ? (
                                            <p>
                                                Baru:{' '}
                                                <code className="font-mono break-all">
                                                    {JSON.stringify(log.NilaiBaru)}
                                                </code>
                                            </p>
                                        ) : null}
                                    </td>
                                    <td className="px-4 py-2 font-mono text-keterangan text-teks-sekunder">
                                        {log.Ip ?? '—'}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </section>
            )}

            <Paginasi
                alamat="/kelola/log-audit"
                saring={Saring.Kata ? { kata: Saring.Kata } : {}}
                halamanSaatIni={Log.HalamanSaatIni}
                halamanTerakhir={Log.HalamanTerakhir}
                total={Log.Total}
                label="Halaman log audit"
            />
        </TataLetakAplikasi>
    );
}
