import { Link, router } from '@inertiajs/react';
import { useState } from 'react';

import Tombol from '@/Komponen/Formulir/Tombol';
import IndikatorLangkah, { teksStatusLangkah } from '@/Komponen/PanduanAwal/IndikatorLangkah';
import LabelStatus from '@/Komponen/Umpan/LabelStatus';
import Pemberitahuan from '@/Komponen/Umpan/Pemberitahuan';
import { FormatTanggalWaktu } from '@/Pustaka/FormatWaktu';
import TataLetakAplikasi from '@/TataLetak/TataLetakAplikasi';
import { AlamatPanduan, type PropsIndeksPanduan, type StatusLangkahPanduan } from '@/Tipe/PanduanAwal';

const jenisLabel: Record<StatusLangkahPanduan, 'sukses' | 'peringatan' | 'netral'> = {
    Selesai: 'sukses',
    Dilewati: 'peringatan',
    Belum: 'netral',
};

/** Ringkasan panduan awal (F-01): 6 langkah, status masing-masing, lanjutkan dari langkah yang belum. */
export default function HalamanIndeksPanduanAwal({ Progres }: PropsIndeksPanduan) {
    const [memproses, AturMemproses] = useState(false);
    const belumSelesai = Progres.Langkah.filter((item) => item.Status !== 'Selesai');
    const langkahBerikutnya = Progres.Langkah.find((item) => item.Status === 'Belum') ?? belumSelesai[0];
    const jumlahDilewati = Progres.Langkah.filter((item) => item.Status === 'Dilewati').length;

    const Selesaikan = () =>
        router.post(
            AlamatPanduan.Selesai,
            {},
            { onStart: () => AturMemproses(true), onFinish: () => AturMemproses(false) },
        );

    return (
        <TataLetakAplikasi judul="Panduan awal">
            <IndikatorLangkah langkah={Progres.Langkah} aktif={null} />
            <p className="text-isi text-teks-sekunder">
                Siapkan outlet <span className="font-semibold text-teks-utama">{Progres.Outlet.Nama}</span>{' '}
                <span className="font-mono text-label">({Progres.Outlet.Kode})</span> sampai siap berjualan: profil
                usaha, jenis usaha, pajak, produk, metode pembayaran, dan perangkat kasir. Langkah yang dilewati bisa
                dikerjakan nanti.
            </p>

            {Progres.SelesaiPada ? (
                <Pemberitahuan jenis="sukses" judul="Panduan awal sudah selesai">
                    Diselesaikan {FormatTanggalWaktu(Progres.SelesaiPada)}. Anda tetap bisa membuka dan mengubah setiap
                    langkah di bawah.
                </Pemberitahuan>
            ) : null}

            <section className="overflow-x-auto rounded-panel border border-garis bg-permukaan">
                <table className="w-full min-w-[480px] text-left text-isi">
                    <caption className="sr-only">Langkah panduan awal</caption>
                    <thead className="border-b border-garis text-label text-teks-sekunder">
                        <tr>
                            <th scope="col" className="px-4 py-2 font-semibold">
                                Langkah
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
                        {Progres.Langkah.map((item, indeks) => (
                            <tr key={item.Kunci} className="border-b border-garis last:border-b-0">
                                <td className="px-4 py-2 text-teks-utama">
                                    {String(indeks + 1)}. {item.Judul}
                                </td>
                                <td className="px-4 py-2">
                                    <LabelStatus
                                        jenis={jenisLabel[item.Status]}
                                        teks={teksStatusLangkah[item.Status]}
                                    />
                                </td>
                                <td className="px-4 py-2 text-right">
                                    <Link
                                        href={item.Tautan}
                                        className="font-semibold text-brand underline outline-none focus-visible:ring-2 focus-visible:ring-brand"
                                    >
                                        {item.Status === 'Selesai' ? `Ubah ${item.Judul}` : `Buka ${item.Judul}`}
                                    </Link>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </section>

            <div className="flex flex-col gap-3 border-t border-garis pt-4 sm:flex-row sm:items-center sm:justify-between">
                <p className="text-isi text-teks-sekunder">
                    {belumSelesai.length === 0
                        ? 'Semua langkah selesai.'
                        : `${String(belumSelesai.length)} langkah belum selesai${
                              jumlahDilewati > 0 ? `, ${String(jumlahDilewati)} di antaranya dilewati` : ''
                          }. Langkah yang belum selesai tetap muncul di Beranda.`}
                </p>
                <div className="flex flex-col-reverse gap-2 sm:flex-row">
                    <Tombol
                        varian={langkahBerikutnya ? 'sekunder' : 'utama'}
                        onClick={Selesaikan}
                        memproses={memproses}
                    >
                        Selesaikan panduan
                    </Tombol>
                    {langkahBerikutnya ? (
                        <Link
                            href={langkahBerikutnya.Tautan}
                            className="inline-flex h-10 items-center justify-center rounded-kontrol border border-brand bg-brand px-4 text-label font-semibold text-permukaan outline-none focus-visible:ring-2 focus-visible:ring-brand focus-visible:ring-offset-2"
                        >
                            Lanjutkan: {langkahBerikutnya.Judul}
                        </Link>
                    ) : null}
                </div>
            </div>
        </TataLetakAplikasi>
    );
}
