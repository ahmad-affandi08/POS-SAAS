import { useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';

import BidangTeks from '@/Komponen/Formulir/BidangTeks';
import Tombol from '@/Komponen/Formulir/Tombol';
import TataLetakAutentikasiPengelola from '@/TataLetak/TataLetakAutentikasiPengelola';
import type { PropsBersamaPengelola } from '@/Tipe/Pengelola';

type PropsAktifkan = {
    QrSvg: string;
    Rahasia: string;
};

/** Aktivasi 2FA wajib sebelum menu apa pun bisa dibuka (P-01 langkah 4, BR-P01.2). */
export default function Aktifkan({ QrSvg, Rahasia }: PropsAktifkan) {
    const { props } = usePage<PropsBersamaPengelola>();
    const formulir = useForm({ Kode: '' });
    const sumberQr = `data:image/svg+xml;charset=utf-8,${encodeURIComponent(QrSvg)}`;

    const Kirim = (peristiwa: FormEvent) => {
        peristiwa.preventDefault();
        formulir.post('/dua-faktor/aktifkan', { onFinish: () => formulir.reset('Kode') });
    };

    return (
        <TataLetakAutentikasiPengelola
            judul="Aktifkan verifikasi dua langkah"
            keterangan="Wajib untuk semua akun pengelola. Pakai aplikasi autentikator seperti Google Authenticator atau Aegis."
        >
            <div className="flex flex-col gap-4">
                <ol className="flex list-decimal flex-col gap-1 pl-5 text-isi text-teks-utama">
                    <li>Pindai kode QR di bawah dengan aplikasi autentikator.</li>
                    <li>Masukkan 6 digit kode yang muncul di aplikasi.</li>
                </ol>
                <img
                    src={sumberQr}
                    alt="Kode QR verifikasi dua langkah"
                    width={192}
                    height={192}
                    className="self-center"
                />
                <p className="text-keterangan text-teks-sekunder">
                    Tidak bisa memindai? Masukkan kunci ini secara manual:
                    <span className="mt-1 block font-mono text-label text-teks-utama">{Rahasia}</span>
                </p>
                <form onSubmit={Kirim} className="flex flex-col gap-4" noValidate>
                    <BidangTeks
                        label="Kode 6 digit"
                        kode
                        inputMode="numeric"
                        autoComplete="one-time-code"
                        maxLength={6}
                        nilai={formulir.data.Kode}
                        saatBerubah={(nilai) => formulir.setData('Kode', nilai)}
                        galat={formulir.errors.Kode ?? props.errors.Umum}
                        autoFocus
                        required
                    />
                    <Tombol type="submit" memproses={formulir.processing}>
                        Aktifkan verifikasi
                    </Tombol>
                </form>
            </div>
        </TataLetakAutentikasiPengelola>
    );
}
