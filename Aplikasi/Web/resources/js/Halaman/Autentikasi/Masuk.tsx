import { Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

import BidangTeks from '@/Komponen/Formulir/BidangTeks';
import KotakCentang from '@/Komponen/Formulir/KotakCentang';
import Tombol from '@/Komponen/Formulir/Tombol';
import TataLetakAutentikasi from '@/TataLetak/TataLetakAutentikasi';

/** Masuk back-office tenant. */
export default function HalamanMasuk() {
    const formulir = useForm({ Email: '', KataSandi: '', Ingat: false });

    const Kirim = (peristiwa: FormEvent) => {
        peristiwa.preventDefault();
        formulir.post('/masuk', { onFinish: () => formulir.reset('KataSandi') });
    };

    return (
        <TataLetakAutentikasi judul="Masuk">
            <form onSubmit={Kirim} className="flex flex-col gap-4" noValidate>
                <BidangTeks
                    label="Email"
                    jenis="email"
                    autoComplete="email"
                    nilai={formulir.data.Email}
                    saatBerubah={(nilai) => formulir.setData('Email', nilai)}
                    galat={formulir.errors.Email}
                    required
                />
                <BidangTeks
                    label="Kata sandi"
                    jenis="password"
                    autoComplete="current-password"
                    nilai={formulir.data.KataSandi}
                    saatBerubah={(nilai) => formulir.setData('KataSandi', nilai)}
                    galat={formulir.errors.KataSandi}
                    required
                />
                <Link href="/lupa-kata-sandi" className="self-start text-label font-semibold text-brand underline">
                    Lupa kata sandi?
                </Link>
                <KotakCentang
                    label="Ingat saya di perangkat ini"
                    nilai={formulir.data.Ingat}
                    saatBerubah={(nilai) => formulir.setData('Ingat', nilai)}
                />
                <Tombol type="submit" memproses={formulir.processing}>
                    Masuk
                </Tombol>
                <p className="text-keterangan text-teks-sekunder">
                    Belum punya akun?{' '}
                    <Link href="/daftar" className="font-semibold text-brand underline">
                        Daftar gratis
                    </Link>
                </p>
            </form>
        </TataLetakAutentikasi>
    );
}
