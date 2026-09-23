import { useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';

import BidangTeks from '@/Komponen/Formulir/BidangTeks';
import Tombol from '@/Komponen/Formulir/Tombol';
import Pemberitahuan from '@/Komponen/Umpan/Pemberitahuan';
import TataLetakAutentikasiPengelola from '@/TataLetak/TataLetakAutentikasiPengelola';
import type { PropsBersamaPengelola } from '@/Tipe/Pengelola';

type PropsTerima = {
    Token: string;
    Berlaku: boolean;
    Email: string | null;
};

/** Anggota baru membuat kata sandi dari undangan (P-01 langkah 4). */
export default function Terima({ Token, Berlaku, Email }: PropsTerima) {
    const { props } = usePage<PropsBersamaPengelola>();
    const formulir = useForm({ Nama: '', KataSandi: '', KonfirmasiKataSandi: '' });

    if (!Berlaku) {
        return (
            <TataLetakAutentikasiPengelola judul="Undangan tidak berlaku">
                <Pemberitahuan jenis="peringatan" judul="Tautan sudah tidak bisa dipakai">
                    Undangan berlaku 48 jam dan hanya sekali pakai. Minta Super Admin mengirim undangan baru.
                </Pemberitahuan>
            </TataLetakAutentikasiPengelola>
        );
    }

    const Kirim = (peristiwa: FormEvent) => {
        peristiwa.preventDefault();
        formulir.post(`/undangan/${encodeURIComponent(Token)}`, {
            onFinish: () => formulir.reset('KataSandi', 'KonfirmasiKataSandi'),
        });
    };

    return (
        <TataLetakAutentikasiPengelola
            judul="Gabung ke Platform Pengelola"
            keterangan={`Buat akun untuk ${Email ?? ''}. Setelah ini Anda wajib mengaktifkan verifikasi dua langkah.`}
        >
            <form onSubmit={Kirim} className="flex flex-col gap-4" noValidate>
                {props.errors.Umum ? <Pemberitahuan jenis="bahaya">{props.errors.Umum}</Pemberitahuan> : null}
                <BidangTeks
                    label="Nama lengkap"
                    nilai={formulir.data.Nama}
                    saatBerubah={(nilai) => formulir.setData('Nama', nilai)}
                    galat={formulir.errors.Nama}
                    autoComplete="name"
                    autoFocus
                    required
                />
                <BidangTeks
                    label="Kata sandi"
                    jenis="password"
                    keterangan="Minimal 12 karakter, berisi huruf dan angka."
                    nilai={formulir.data.KataSandi}
                    saatBerubah={(nilai) => formulir.setData('KataSandi', nilai)}
                    galat={formulir.errors.KataSandi}
                    autoComplete="new-password"
                    required
                />
                <BidangTeks
                    label="Ulangi kata sandi"
                    jenis="password"
                    nilai={formulir.data.KonfirmasiKataSandi}
                    saatBerubah={(nilai) => formulir.setData('KonfirmasiKataSandi', nilai)}
                    galat={formulir.errors.KonfirmasiKataSandi}
                    autoComplete="new-password"
                    required
                />
                <Tombol type="submit" memproses={formulir.processing}>
                    Buat akun
                </Tombol>
            </form>
        </TataLetakAutentikasiPengelola>
    );
}
