import { Head } from '@inertiajs/react';

import { FormatTanggal } from '@/Pustaka/FormatWaktu';

type PropsDokumenLegal = {
    Dokumen: { Label: string; Judul: string; Versi: number; BerlakuMulai: string; Isi: string };
};

/** Dokumen legal versi yang berlaku, ditampilkan sebagai teks (bukan HTML). */
export default function HalamanDokumenLegalPublik({ Dokumen }: PropsDokumenLegal) {
    return (
        <>
            <Head title={Dokumen.Label} />
            <main className="mx-auto flex max-w-3xl flex-col gap-4 px-4 py-10">
                <h1 className="text-judul font-bold text-teks-utama">{Dokumen.Judul}</h1>
                <p className="text-keterangan text-teks-sekunder">
                    Versi {Dokumen.Versi} · berlaku mulai {FormatTanggal(Dokumen.BerlakuMulai)}
                </p>
                <article className="whitespace-pre-wrap text-isi text-teks-utama">{Dokumen.Isi}</article>
            </main>
        </>
    );
}
