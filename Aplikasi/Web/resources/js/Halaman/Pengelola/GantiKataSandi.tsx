import { useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';

import BidangTeks from '@/Komponen/Formulir/BidangTeks';
import Tombol from '@/Komponen/Formulir/Tombol';
import Pemberitahuan from '@/Komponen/Umpan/Pemberitahuan';
import TataLetakAutentikasiPengelola from '@/TataLetak/TataLetakAutentikasiPengelola';
import type { PropsBersamaPengelola } from '@/Tipe/Pengelola';

/** D-22: ganti kata sandi anggota tim (wajib setelah kata sandi awal dibuat Super Admin). */
export default function GantiKataSandi({ Wajib }: { Wajib: boolean }) {
    const { props } = usePage<PropsBersamaPengelola>();
    const formulir = useForm({ KataSandiLama: '', KataSandi: '', KonfirmasiKataSandi: '' });

    const Kirim = (peristiwa: FormEvent) => {
        peristiwa.preventDefault();
        formulir.post('/ganti-kata-sandi', { onFinish: () => formulir.reset() });
    };

    return (
        <TataLetakAutentikasiPengelola
            judul="Ganti kata sandi"
            keterangan={
                Wajib
                    ? 'Kata sandi Anda dibuat oleh Super Admin. Ganti dengan kata sandi yang hanya Anda ketahui sebelum melanjutkan.'
                    : 'Ganti kata sandi akun Platform Pengelola Anda.'
            }
        >
            <form onSubmit={Kirim} className="flex flex-col gap-4" noValidate>
                {props.errors.Umum ? <Pemberitahuan jenis="bahaya">{props.errors.Umum}</Pemberitahuan> : null}
                <BidangTeks
                    label={Wajib ? 'Kata sandi awal' : 'Kata sandi saat ini'}
                    jenis="password"
                    nilai={formulir.data.KataSandiLama}
                    saatBerubah={(nilai) => formulir.setData('KataSandiLama', nilai)}
                    galat={formulir.errors.KataSandiLama}
                    autoComplete="current-password"
                    autoFocus
                    required
                />
                <BidangTeks
                    label="Kata sandi baru"
                    jenis="password"
                    keterangan="Minimal 12 karakter, berisi huruf dan angka."
                    nilai={formulir.data.KataSandi}
                    saatBerubah={(nilai) => formulir.setData('KataSandi', nilai)}
                    galat={formulir.errors.KataSandi}
                    autoComplete="new-password"
                    required
                />
                <BidangTeks
                    label="Ulangi kata sandi baru"
                    jenis="password"
                    nilai={formulir.data.KonfirmasiKataSandi}
                    saatBerubah={(nilai) => formulir.setData('KonfirmasiKataSandi', nilai)}
                    galat={formulir.errors.KonfirmasiKataSandi}
                    autoComplete="new-password"
                    required
                />
                <Tombol type="submit" memproses={formulir.processing}>
                    Simpan kata sandi baru
                </Tombol>
            </form>
        </TataLetakAutentikasiPengelola>
    );
}
