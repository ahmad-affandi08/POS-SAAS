import { SPESIMEN } from '@/Komponen/Situs/SpesimenSitus';
import TombolSitus from '@/Komponen/Situs/TombolSitus';
import { cn } from '@/Komponen/Ui/utils';
import type { BagianSitus } from '@/Tipe/Situs';

import { GambarBagian } from './KepalaBagian';

type Props = { bagian: Extract<BagianSitus, { Jenis: 'Hero' }>; utama: boolean };

const KELAS_LATAR = {
    Terang: 'bg-permukaan',
    Merek: 'bg-brand-gelap',
    Navy: 'bg-teks-utama',
} as const;

/**
 * Pembuka halaman (D-25): judul besar **rata kiri**, pengantar, satu tombol utama berisi + satu tombol
 * bergaris tipis, gambar produk opsional. Latar bisa terang, merek, atau Navy sehingga halaman pemasaran
 * punya jangkar gelap penuh tanpa gradien.
 *
 * Label memakai latar `Aksen` dengan teks `TeksUtama` (8,98:1) — satu-satunya pemakaian kuning di hero,
 * dan tidak pernah berteks putih (1,67:1, gagal WCAG).
 */
export default function BagianHero({ bagian, utama }: Props) {
    const Judul = utama ? 'h1' : 'h2';
    const latar = bagian.Latar ?? 'Terang';
    const gelap = latar !== 'Terang';
    // Spesimen keluaran produk dipakai sebagai jangkar visual selama belum ada gambar (D-25).
    const Spesimen = bagian.Gambar === null && bagian.Spesimen ? SPESIMEN[bagian.Spesimen] : null;
    const adaGambar = bagian.Gambar !== null || Spesimen !== null;

    return (
        <section className={KELAS_LATAR[latar]}>
            <div
                className={cn(
                    'muncul-saat-gulir mx-auto grid max-w-6xl items-center gap-10 px-4 py-16 sm:py-24',
                    adaGambar && 'lg:grid-cols-[1fr_1.1fr]',
                )}
            >
                <div className={cn('flex flex-col items-start gap-6', adaGambar ? '' : 'max-w-3xl')}>
                    {bagian.Label ? (
                        <p className="rounded-full bg-aksen px-3 py-1 text-label font-semibold text-teks-utama">
                            {bagian.Label}
                        </p>
                    ) : null}
                    <Judul
                        className={cn(
                            'text-sorotan-besar-hp font-bold sm:text-sorotan-besar',
                            gelap ? 'text-permukaan' : 'text-teks-utama',
                        )}
                    >
                        {bagian.Judul}
                    </Judul>
                    {bagian.Subjudul ? (
                        <p
                            className={cn(
                                'text-pengantar max-w-xl whitespace-pre-line',
                                gelap ? 'text-brand-gelap-teks' : 'text-teks-sekunder',
                            )}
                        >
                            {bagian.Subjudul}
                        </p>
                    ) : null}
                    {bagian.TombolUtama || bagian.TombolKedua ? (
                        <div className="flex w-full flex-col gap-3 sm:w-auto sm:flex-row">
                            {bagian.TombolUtama ? (
                                <TombolSitus
                                    href={bagian.TombolUtama.Tautan}
                                    ukuran="besar"
                                    varian={gelap ? 'terang' : 'utama'}
                                >
                                    {bagian.TombolUtama.Label}
                                </TombolSitus>
                            ) : null}
                            {bagian.TombolKedua ? (
                                <TombolSitus
                                    href={bagian.TombolKedua.Tautan}
                                    ukuran="besar"
                                    varian={gelap ? 'garis-terang' : 'garis-merek'}
                                >
                                    {bagian.TombolKedua.Label}
                                </TombolSitus>
                            ) : null}
                        </div>
                    ) : null}
                    {bagian.Catatan ? (
                        <p className={cn('text-label', gelap ? 'text-brand-gelap-teks' : 'text-teks-sekunder')}>
                            {bagian.Catatan}
                        </p>
                    ) : null}
                </div>
                {bagian.Gambar ? (
                    <GambarBagian
                        gambar={bagian.Gambar}
                        prioritas={utama}
                        className={cn(
                            'h-auto w-full rounded-panel border',
                            gelap ? 'border-brand-gelap-garis' : 'border-garis',
                        )}
                    />
                ) : Spesimen ? (
                    <Spesimen className="justify-self-center lg:justify-self-end" />
                ) : null}
            </div>
        </section>
    );
}
