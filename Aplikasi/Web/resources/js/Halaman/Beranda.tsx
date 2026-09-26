import { Head } from '@inertiajs/react';

import { Button } from '@/Komponen/Ui/button';

type PropsBeranda = {
    NamaAplikasi: string;
    /** D-20: halaman masuk & daftar di domain tenant (absolut bila domain terpisah). */
    UrlMasuk: string;
    UrlDaftar: string;
};

/**
 * Halaman sementara Fase 0. Diganti halaman sebenarnya oleh flow P-01 (Platform Pengelola)
 * dan F-00 (registrasi tenant).
 */
export default function Beranda({ NamaAplikasi, UrlMasuk, UrlDaftar }: PropsBeranda) {
    return (
        <>
            <Head title="Beranda" />
            <main className="mx-auto flex min-h-screen max-w-3xl flex-col justify-center gap-4 px-4 py-12">
                <h1 className="text-judul font-bold text-teks-utama">{NamaAplikasi}</h1>
                <p className="text-isi text-teks-sekunder">
                    Kerangka Fase 0 sudah berjalan. Fitur dikerjakan per flow sesuai PRD.
                </p>
                <p className="font-mono text-label text-teks-sekunder">INV/JKT1/260922/K02-0042 · IL1O0-8B5S</p>
                <div className="flex flex-wrap gap-2">
                    <Button asChild>
                        <a href={UrlDaftar}>Daftar gratis</a>
                    </Button>
                    <Button asChild variant="outline">
                        <a href={UrlMasuk}>Masuk</a>
                    </Button>
                </div>
            </main>
        </>
    );
}
