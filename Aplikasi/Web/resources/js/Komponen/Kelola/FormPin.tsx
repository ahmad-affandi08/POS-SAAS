import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

import BidangTeks from '@/Komponen/Formulir/BidangTeks';
import Tombol from '@/Komponen/Formulir/Tombol';

type PropsFormPin = { alamat: string; labelTombol: string; saatSelesai?: () => void };

/** PIN kasir 6 angka + ulangi (F-02 langkah 4); dipakai di halaman PIN kasir dan langkah Perangkat panduan awal. */
export default function FormPin({ alamat, labelTombol, saatSelesai }: PropsFormPin) {
    const formulir = useForm({ Pin: '', KonfirmasiPin: '' });

    const Kirim = (peristiwa: FormEvent) => {
        peristiwa.preventDefault();
        formulir.put(alamat, {
            preserveScroll: true,
            onFinish: () => formulir.reset(),
            ...(saatSelesai ? { onSuccess: saatSelesai } : {}),
        });
    };

    return (
        <form onSubmit={Kirim} className="flex flex-col gap-4" noValidate>
            <BidangTeks
                label="PIN baru (6 angka)"
                jenis="password"
                inputMode="numeric"
                autoComplete="new-password"
                maxLength={6}
                kode
                nilai={formulir.data.Pin}
                saatBerubah={(nilai) => formulir.setData('Pin', nilai.replace(/\D/g, ''))}
                galat={formulir.errors.Pin}
                required
            />
            <BidangTeks
                label="Ulangi PIN"
                jenis="password"
                inputMode="numeric"
                autoComplete="new-password"
                maxLength={6}
                kode
                nilai={formulir.data.KonfirmasiPin}
                saatBerubah={(nilai) => formulir.setData('KonfirmasiPin', nilai.replace(/\D/g, ''))}
                galat={formulir.errors.KonfirmasiPin}
                required
            />
            <div className="flex gap-2">
                <Tombol type="submit" memproses={formulir.processing}>
                    {labelTombol}
                </Tombol>
                {saatSelesai ? (
                    <Tombol varian="sekunder" onClick={saatSelesai}>
                        Batal
                    </Tombol>
                ) : null}
            </div>
        </form>
    );
}
