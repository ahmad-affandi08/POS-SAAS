import { Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

import BidangTeks from '@/Komponen/Formulir/BidangTeks';
import Tombol from '@/Komponen/Formulir/Tombol';
import TataLetakAutentikasi from '@/TataLetak/TataLetakAutentikasi';

/** Minta tautan atur ulang kata sandi (BR-00.9). Pesan hasil selalu sama, terdaftar atau tidak (§25 no. 18). */
export default function HalamanLupaKataSandi({ MenitBerlaku }: { MenitBerlaku: number }) {
    const formulir = useForm({ Email: '' });

    const Kirim = (peristiwa: FormEvent) => {
        peristiwa.preventDefault();
        formulir.post('/lupa-kata-sandi', { onSuccess: () => formulir.reset('Email') });
    };

    return (
        <TataLetakAutentikasi
            judul="Lupa kata sandi"
            keterangan={`Masukkan email akun Anda. Kami kirim tautan untuk membuat kata sandi baru, berlaku ${MenitBerlaku} menit.`}
        >
            <form onSubmit={Kirim} className="flex flex-col gap-4" noValidate>
                <BidangTeks
                    label="Email"
                    jenis="email"
                    autoComplete="email"
                    nilai={formulir.data.Email}
                    saatBerubah={(nilai) => formulir.setData('Email', nilai)}
                    galat={formulir.errors.Email}
                    autoFocus
                    required
                />
                <Tombol type="submit" memproses={formulir.processing}>
                    Kirim tautan
                </Tombol>
                <p className="text-keterangan text-teks-sekunder">
                    Sudah ingat?{' '}
                    <Link href="/masuk" className="font-semibold text-brand underline">
                        Kembali ke halaman masuk
                    </Link>
                </p>
            </form>
        </TataLetakAutentikasi>
    );
}
