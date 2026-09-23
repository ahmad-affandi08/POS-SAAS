import { Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

import BidangTeks from '@/Komponen/Formulir/BidangTeks';
import Tombol from '@/Komponen/Formulir/Tombol';
import TataLetakAutentikasi from '@/TataLetak/TataLetakAutentikasi';

/** Langkah kedua masuk untuk akun ber-2FA (BR-00.8). Kode pemulihan bisa dipakai bila ponsel tidak ada. */
export default function HalamanVerifikasiDuaFaktor() {
    const formulir = useForm({ Kode: '' });

    const Kirim = (peristiwa: FormEvent) => {
        peristiwa.preventDefault();
        formulir.post('/masuk/dua-faktor', { onFinish: () => formulir.reset('Kode') });
    };

    return (
        <TataLetakAutentikasi
            judul="Verifikasi dua langkah"
            keterangan="Masukkan 6 digit kode dari aplikasi autentikator, atau salah satu kode pemulihan Anda."
        >
            <form onSubmit={Kirim} className="flex flex-col gap-4" noValidate>
                <BidangTeks
                    label="Kode"
                    kode
                    autoComplete="one-time-code"
                    maxLength={11}
                    nilai={formulir.data.Kode}
                    saatBerubah={(nilai) => formulir.setData('Kode', nilai)}
                    galat={formulir.errors.Kode}
                    autoFocus
                    required
                />
                <Tombol type="submit" memproses={formulir.processing}>
                    Verifikasi dan masuk
                </Tombol>
                <p className="text-keterangan text-teks-sekunder">
                    Bukan Anda, atau ingin memakai akun lain?{' '}
                    <Link href="/masuk" className="font-semibold text-brand underline">
                        Kembali ke halaman masuk
                    </Link>
                </p>
            </form>
        </TataLetakAutentikasi>
    );
}
