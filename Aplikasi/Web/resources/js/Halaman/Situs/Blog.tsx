import { usePage } from '@inertiajs/react';

import KartuArtikel from '@/Komponen/Situs/Blog/KartuArtikel';
import TautanSitus from '@/Komponen/Situs/TautanSitus';
import TataLetakSitus from '@/TataLetak/TataLetakSitus';
import type { PropsBlogSitus } from '@/Tipe/Situs';

function BuatAlamat(halaman: number, kategori: string | null): string {
    const parameter = new URLSearchParams();

    if (kategori) {
        parameter.set('kategori', kategori);
    }

    if (halaman > 1) {
        parameter.set('halaman', String(halaman));
    }

    const kueri = parameter.toString();

    return kueri === '' ? '/blog' : `/blog?${kueri}`;
}

/** Daftar artikel blog situs pemasaran (bagian B2): saring kategori & halaman. */
export default function Blog() {
    const { Halaman, Artikel, Kategori, KategoriAktif, HalamanKe, JumlahHalaman } = usePage<PropsBlogSitus>().props;
    const BuatKelasChip = (aktif: boolean) =>
        `inline-flex min-h-11 items-center rounded-full border px-4 text-label font-semibold ${
            aktif
                ? 'border-brand bg-brand text-permukaan'
                : 'border-garis bg-permukaan text-teks-utama hover:bg-permukaan-sorot'
        }`;

    return (
        <TataLetakSitus judul={Halaman.Seo.Judul}>
            <section className="mx-auto max-w-6xl px-4 py-12 sm:py-16">
                <h1 className="text-judul-bagian-hp font-bold text-teks-utama sm:text-judul-bagian">Blog</h1>
                <p className="mt-2 max-w-2xl text-pengantar text-teks-sekunder">{Halaman.Seo.Deskripsi}</p>
                {Kategori.length > 0 ? (
                    <nav aria-label="Kategori artikel" className="mt-6 flex flex-wrap gap-2">
                        <TautanSitus href="/blog" className={BuatKelasChip(KategoriAktif === null)}>
                            Semua
                        </TautanSitus>
                        {Kategori.map((k) => (
                            <TautanSitus key={k} href={BuatAlamat(1, k)} className={BuatKelasChip(KategoriAktif === k)}>
                                {k}
                            </TautanSitus>
                        ))}
                    </nav>
                ) : null}
                {Artikel.length === 0 ? (
                    <p className="mt-10 text-isi text-teks-sekunder">Belum ada artikel. Kunjungi lagi nanti.</p>
                ) : (
                    <ul className="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                        {Artikel.map((a) => (
                            <li key={a.Slug}>
                                <KartuArtikel artikel={a} />
                            </li>
                        ))}
                    </ul>
                )}
                {JumlahHalaman > 1 ? (
                    <nav aria-label="Halaman artikel" className="mt-10 flex items-center justify-between gap-4">
                        {HalamanKe > 1 ? (
                            <TautanSitus
                                href={BuatAlamat(HalamanKe - 1, KategoriAktif)}
                                className={BuatKelasChip(false)}
                            >
                                Sebelumnya
                            </TautanSitus>
                        ) : (
                            <span />
                        )}
                        <span className="text-label text-teks-sekunder">
                            Halaman {HalamanKe} dari {JumlahHalaman}
                        </span>
                        {HalamanKe < JumlahHalaman ? (
                            <TautanSitus
                                href={BuatAlamat(HalamanKe + 1, KategoriAktif)}
                                className={BuatKelasChip(false)}
                            >
                                Berikutnya
                            </TautanSitus>
                        ) : (
                            <span />
                        )}
                    </nav>
                ) : null}
            </section>
        </TataLetakSitus>
    );
}
