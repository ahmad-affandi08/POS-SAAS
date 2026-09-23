import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

import BidangTeks from '@/Komponen/Formulir/BidangTeks';
import Tombol from '@/Komponen/Formulir/Tombol';
import TataLetakAutentikasiPengelola from '@/TataLetak/TataLetakAutentikasiPengelola';

/** Masuk Platform Pengelola (P-01). Tidak ada tautan daftar: akun hanya lewat undangan. */
export default function Masuk() {
    const formulir = useForm({ Email: '', KataSandi: '' });

    const Kirim = (peristiwa: FormEvent) => {
        peristiwa.preventDefault();
        formulir.post('/masuk', { onFinish: () => formulir.reset('KataSandi') });
    };

    return (
        <TataLetakAutentikasiPengelola judul="Masuk" keterangan="Khusus tim internal. Akun baru dibuat lewat undangan.">
            <form onSubmit={Kirim} className="flex flex-col gap-4" noValidate>
                <BidangTeks
                    label="Email"
                    jenis="email"
                    nilai={formulir.data.Email}
                    saatBerubah={(nilai) => formulir.setData('Email', nilai)}
                    galat={formulir.errors.Email}
                    autoComplete="username"
                    autoFocus
                    required
                />
                <BidangTeks
                    label="Kata sandi"
                    jenis="password"
                    nilai={formulir.data.KataSandi}
                    saatBerubah={(nilai) => formulir.setData('KataSandi', nilai)}
                    galat={formulir.errors.KataSandi}
                    autoComplete="current-password"
                    required
                />
                <Tombol type="submit" memproses={formulir.processing}>
                    Masuk
                </Tombol>
            </form>
        </TataLetakAutentikasiPengelola>
    );
}
