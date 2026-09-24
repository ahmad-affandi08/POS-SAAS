import type { ReactNode } from 'react';

type PropsKeadaanKosong = { judul: string; children?: ReactNode };

/** Keadaan kosong (PRD §17.6.6): kalimat yang menjelaskan langkah berikutnya, plus tombol/tautan aksi. */
export default function KeadaanKosong({ judul, children }: PropsKeadaanKosong) {
    return (
        <div className="flex flex-col items-start gap-3 rounded-panel border border-garis bg-permukaan px-4 py-6">
            <p className="text-isi font-semibold text-teks-utama">{judul}</p>
            {children ? (
                <div className="flex flex-wrap items-center gap-2 text-isi text-teks-sekunder">{children}</div>
            ) : null}
        </div>
    );
}
