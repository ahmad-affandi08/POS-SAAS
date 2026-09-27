import { Check, Copy } from 'lucide-react';
import { useId, useState } from 'react';

import Tombol from '@/Komponen/Formulir/Tombol';
import { Card, CardContent } from '@/Komponen/Ui/card';
import { FormatTanggalWaktu } from '@/Pustaka/FormatWaktu';
import type { KodeAktivasiBaru } from '@/Tipe/PanduanAwal';

/** Kode aktivasi 8 karakter + QR untuk perangkat baru (F-02b BR-02.3; dipakai ulang di langkah 6 F-01). */
export default function KartuKodeAktivasi({ kode }: { kode: KodeAktivasiBaru }) {
    const idJudul = useId();
    const [tersalin, AturTersalin] = useState(false);
    const sumberQr = `data:image/svg+xml;charset=utf-8,${encodeURIComponent(kode.QrSvg)}`;

    const Salin = () => {
        void navigator.clipboard
            ?.writeText(kode.Kode)
            .then(() => AturTersalin(true))
            .catch(() => AturTersalin(false));
    };

    return (
        <Card role="region" aria-labelledby={idJudul} className="gap-0 rounded-panel py-0 shadow-none">
            <CardContent className="flex flex-col gap-4 p-6 md:flex-row md:items-center">
                <img
                    src={sumberQr}
                    alt={`Kode QR aktivasi ${kode.NamaPerangkat}`}
                    width={200}
                    height={200}
                    className="shrink-0 rounded-kontrol border border-garis bg-permukaan"
                />
                <div className="flex flex-col items-start gap-2">
                    <h2 id={idJudul} className="text-subjudul font-bold text-teks-utama">
                        Aktifkan {kode.NamaPerangkat} ({kode.KodePerangkat})
                    </h2>
                    <p className="text-isi text-teks-sekunder">
                        Buka aplikasi kasir, pilih &quot;Aktifkan perangkat&quot;, lalu ketik kode ini:
                    </p>
                    {/*
                     * Ditampilkan apa adanya tanpa tanda hubung supaya persis sama dengan yang diketik dan
                     * dengan isi QR; keterbacaannya dijaga jarak huruf, bukan pemisah yang ikut tersalin.
                     */}
                    <p className="font-mono text-judul font-bold tracking-widest text-teks-utama">{kode.Kode}</p>
                    <Tombol varian="sekunder" onClick={Salin}>
                        {tersalin ? (
                            <>
                                <Check className="size-4" aria-hidden /> Kode tersalin
                            </>
                        ) : (
                            <>
                                <Copy className="size-4" aria-hidden /> Salin kode
                            </>
                        )}
                    </Tombol>
                    <p aria-live="polite" className="sr-only">
                        {tersalin ? 'Kode aktivasi tersalin ke papan klip.' : ''}
                    </p>
                    <p className="text-keterangan text-teks-sekunder">
                        Berlaku sampai {FormatTanggalWaktu(kode.KedaluwarsaPada)} dan hanya bisa dipakai sekali. Kode
                        tidak ditampilkan lagi setelah halaman ini ditutup; buat kode baru bila perlu.
                    </p>
                </div>
            </CardContent>
        </Card>
    );
}
