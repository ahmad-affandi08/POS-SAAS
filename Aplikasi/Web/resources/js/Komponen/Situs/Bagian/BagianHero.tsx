import TombolSitus from '@/Komponen/Situs/TombolSitus';
import type { BagianSitus } from '@/Tipe/Situs';

import { GambarBagian } from './KepalaBagian';

type Props = { bagian: Extract<BagianSitus, { Jenis: 'Hero' }>; utama: boolean };

/** Pembuka halaman: judul besar (h1 bila blok pertama), pengantar, dua tombol, gambar produk opsional. */
export default function BagianHero({ bagian, utama }: Props) {
    const Judul = utama ? 'h1' : 'h2';
    const adaGambar = bagian.Gambar !== null;

    return (
        <section className="bg-permukaan">
            <div
                className={`mx-auto grid max-w-6xl items-center gap-10 px-4 py-14 sm:py-20 ${adaGambar ? 'lg:grid-cols-2' : ''}`}
            >
                <div className={`flex flex-col gap-5 ${adaGambar ? '' : 'mx-auto max-w-3xl items-center text-center'}`}>
                    {bagian.Label ? (
                        <p className="self-start rounded-full bg-brand-lembut px-3 py-1 text-label font-semibold text-brand-gelap max-lg:self-auto">
                            {bagian.Label}
                        </p>
                    ) : null}
                    <Judul className="text-sorotan-hp font-bold text-teks-utama sm:text-sorotan">{bagian.Judul}</Judul>
                    {bagian.Subjudul ? (
                        <p className="text-pengantar whitespace-pre-line text-teks-sekunder">{bagian.Subjudul}</p>
                    ) : null}
                    {bagian.TombolUtama || bagian.TombolKedua ? (
                        <div className={`flex flex-col gap-3 sm:flex-row ${adaGambar ? '' : 'sm:justify-center'}`}>
                            {bagian.TombolUtama ? (
                                <TombolSitus href={bagian.TombolUtama.Tautan} ukuran="besar">
                                    {bagian.TombolUtama.Label}
                                </TombolSitus>
                            ) : null}
                            {bagian.TombolKedua ? (
                                <TombolSitus href={bagian.TombolKedua.Tautan} ukuran="besar" varian="kedua">
                                    {bagian.TombolKedua.Label}
                                </TombolSitus>
                            ) : null}
                        </div>
                    ) : null}
                    {bagian.Catatan ? <p className="text-label text-teks-sekunder">{bagian.Catatan}</p> : null}
                </div>
                {bagian.Gambar ? (
                    <GambarBagian
                        gambar={bagian.Gambar}
                        prioritas={utama}
                        className="h-auto w-full rounded-panel border border-garis"
                    />
                ) : null}
            </div>
        </section>
    );
}
