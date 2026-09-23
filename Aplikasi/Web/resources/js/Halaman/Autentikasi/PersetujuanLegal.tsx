import { router, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

import KotakCentang from '@/Komponen/Formulir/KotakCentang';
import Tombol from '@/Komponen/Formulir/Tombol';
import Pemberitahuan from '@/Komponen/Umpan/Pemberitahuan';
import { FormatTanggal } from '@/Pustaka/FormatWaktu';
import TataLetakAutentikasi from '@/TataLetak/TataLetakAutentikasi';

type DokumenTertunda = {
    Uuid: string;
    Label: string;
    Judul: string;
    Versi: number;
    BerlakuMulai: string;
    RingkasanPerubahan: string | null;
    Tautan: string;
};

/** Persetujuan ulang versi materiil dokumen legal sebelum membuka back-office (BR-P06.5). */
export default function HalamanPersetujuanLegal({ Dokumen }: { Dokumen: DokumenTertunda[] }) {
    const formulir = useForm({ Dokumen: Dokumen.map((dokumen) => dokumen.Uuid), Setuju: false });

    const Kirim = (peristiwa: FormEvent) => {
        peristiwa.preventDefault();
        formulir.post('/kelola/persetujuan-legal');
    };

    return (
        <TataLetakAutentikasi
            judul="Persetujuan dokumen yang diperbarui"
            keterangan="Kami memperbarui dokumen berikut. Baca perubahannya, lalu setujui untuk melanjutkan ke back-office."
            lebar="sedang"
        >
            <form onSubmit={Kirim} className="flex flex-col gap-4" noValidate>
                <ul className="flex flex-col divide-y divide-garis">
                    {Dokumen.map((dokumen) => (
                        <li key={dokumen.Uuid} className="flex flex-col gap-1 py-3 first:pt-0">
                            <p className="text-isi font-semibold text-teks-utama">
                                {dokumen.Label} versi {dokumen.Versi}
                            </p>
                            <p className="text-keterangan text-teks-sekunder">
                                Berlaku mulai {FormatTanggal(dokumen.BerlakuMulai)}
                            </p>
                            {dokumen.RingkasanPerubahan ? (
                                <p className="whitespace-pre-wrap text-isi text-teks-utama">
                                    {dokumen.RingkasanPerubahan}
                                </p>
                            ) : null}
                            <a
                                href={dokumen.Tautan}
                                target="_blank"
                                rel="noreferrer"
                                className="text-label font-semibold text-brand underline"
                            >
                                Baca {dokumen.Judul} lengkap
                            </a>
                        </li>
                    ))}
                </ul>
                <KotakCentang
                    label="Saya sudah membaca dan menyetujui dokumen di atas atas nama usaha ini."
                    nilai={formulir.data.Setuju}
                    saatBerubah={(nilai) => formulir.setData('Setuju', nilai)}
                />
                {formulir.errors.Setuju ? <Pemberitahuan jenis="bahaya">{formulir.errors.Setuju}</Pemberitahuan> : null}
                <Tombol type="submit" memproses={formulir.processing}>
                    Setujui dan lanjutkan
                </Tombol>
                <Tombol varian="sekunder" onClick={() => router.post('/keluar')}>
                    Keluar
                </Tombol>
            </form>
        </TataLetakAutentikasi>
    );
}
