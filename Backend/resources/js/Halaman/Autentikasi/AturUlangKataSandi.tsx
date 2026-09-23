import { Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

import BidangTeks from '@/Komponen/Formulir/BidangTeks';
import Tombol from '@/Komponen/Formulir/Tombol';
import TataLetakAutentikasi from '@/TataLetak/TataLetakAutentikasi';

type PropsAturUlang = { Token: string; Email: string };

/** Buat kata sandi baru dari tautan email (BR-00.9). Perangkat lain otomatis keluar setelah berhasil. */
export default function HalamanAturUlangKataSandi({ Token, Email }: PropsAturUlang) {
    const formulir = useForm({ Token, Email, KataSandi: '', KonfirmasiKataSandi: '' });

    const Kirim = (peristiwa: FormEvent) => {
        peristiwa.preventDefault();
        formulir.post('/atur-ulang-kata-sandi', { onFinish: () => formulir.reset('KataSandi', 'KonfirmasiKataSandi') });
    };

    return (
        <TataLetakAutentikasi
            judul="Buat kata sandi baru"
            keterangan="Setelah disimpan, semua perangkat lain yang masuk dengan akun ini akan keluar otomatis."
        >
            <form onSubmit={Kirim} className="flex flex-col gap-4" noValidate>
                <BidangTeks
                    label="Email"
                    jenis="email"
                    autoComplete="email"
                    nilai={formulir.data.Email}
                    saatBerubah={(nilai) => formulir.setData('Email', nilai)}
                    galat={formulir.errors.Email ?? formulir.errors.Token}
                    required
                />
                <BidangTeks
                    label="Kata sandi baru"
                    jenis="password"
                    autoComplete="new-password"
                    keterangan="Minimal 8 karakter, berisi huruf dan angka."
                    nilai={formulir.data.KataSandi}
                    saatBerubah={(nilai) => formulir.setData('KataSandi', nilai)}
                    galat={formulir.errors.KataSandi}
                    autoFocus
                    required
                />
                <BidangTeks
                    label="Ulangi kata sandi baru"
                    jenis="password"
                    autoComplete="new-password"
                    nilai={formulir.data.KonfirmasiKataSandi}
                    saatBerubah={(nilai) => formulir.setData('KonfirmasiKataSandi', nilai)}
                    galat={formulir.errors.KonfirmasiKataSandi}
                    required
                />
                <Tombol type="submit" memproses={formulir.processing}>
                    Simpan kata sandi baru
                </Tombol>
                <p className="text-keterangan text-teks-sekunder">
                    Tautan kedaluwarsa?{' '}
                    <Link href="/lupa-kata-sandi" className="font-semibold text-brand underline">
                        Minta tautan baru
                    </Link>
                </p>
            </form>
        </TataLetakAutentikasi>
    );
}
