import { router, useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';

import BidangTeks from '@/Komponen/Formulir/BidangTeks';
import Tombol from '@/Komponen/Formulir/Tombol';
import TataLetakAutentikasiPengelola from '@/TataLetak/TataLetakAutentikasiPengelola';
import type { PropsBersamaPengelola } from '@/Tipe/Pengelola';

/** Verifikasi 2FA setiap kali masuk (BR-P01.2). Kode pemulihan bisa dipakai bila ponsel tidak ada. */
export default function Verifikasi() {
    const { props } = usePage<PropsBersamaPengelola>();
    const formulir = useForm({ Kode: '' });

    const Kirim = (peristiwa: FormEvent) => {
        peristiwa.preventDefault();
        formulir.post('/dua-faktor/verifikasi', { onFinish: () => formulir.reset('Kode') });
    };
    const Keluar = () => router.post('/keluar');

    return (
        <TataLetakAutentikasiPengelola
            judul="Verifikasi dua langkah"
            keterangan="Masukkan 6 digit kode dari aplikasi autentikator, atau salah satu kode pemulihan."
        >
            <form onSubmit={Kirim} className="flex flex-col gap-4" noValidate>
                <BidangTeks
                    label="Kode"
                    kode
                    autoComplete="one-time-code"
                    maxLength={11}
                    nilai={formulir.data.Kode}
                    saatBerubah={(nilai) => formulir.setData('Kode', nilai)}
                    galat={formulir.errors.Kode ?? props.errors.Umum}
                    autoFocus
                    required
                />
                <Tombol type="submit" memproses={formulir.processing}>
                    Verifikasi
                </Tombol>
                <Tombol varian="sekunder" onClick={Keluar}>
                    Keluar
                </Tombol>
            </form>
        </TataLetakAutentikasiPengelola>
    );
}
