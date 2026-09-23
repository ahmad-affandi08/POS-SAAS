import { Link } from '@inertiajs/react';

import TataLetakAutentikasiPengelola from '@/TataLetak/TataLetakAutentikasiPengelola';

type PropsGalat = { Status: number; Judul: string; Keterangan: string };

/** Keadaan "tanpa izin" dan galat Platform Pengelola (PRD §17.6.6). */
export default function Galat({ Status, Judul, Keterangan }: PropsGalat) {
    return (
        <TataLetakAutentikasiPengelola judul={Judul}>
            <div className="flex flex-col gap-4">
                <p className="text-isi text-teks-utama">{Keterangan}</p>
                <p className="font-mono text-keterangan text-teks-sekunder">Kode galat: {Status}</p>
                <Link href="/" className="font-semibold text-brand underline">
                    Kembali ke beranda
                </Link>
            </div>
        </TataLetakAutentikasiPengelola>
    );
}
