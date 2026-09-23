import { useId } from 'react';

import { FormatTanggalWaktu } from '@/Pustaka/FormatWaktu';
import type { KodeAktivasiBaru } from '@/Tipe/PanduanAwal';

/** Kode aktivasi 8 karakter + QR untuk perangkat baru (F-02b BR-02.3; dipakai ulang di langkah 6 F-01). */
export default function KartuKodeAktivasi({ kode }: { kode: KodeAktivasiBaru }) {
    const idJudul = useId();
    const sumberQr = `data:image/svg+xml;charset=utf-8,${encodeURIComponent(kode.QrSvg)}`;
    const kodeTampil = `${kode.Kode.slice(0, 4)}-${kode.Kode.slice(4)}`;

    return (
        <section
            aria-labelledby={idJudul}
            className="flex flex-col gap-4 rounded-panel border border-garis bg-permukaan p-6 md:flex-row md:items-center"
        >
            <img src={sumberQr} alt={`Kode QR aktivasi ${kode.NamaPerangkat}`} width={200} height={200} />
            <div className="flex flex-col gap-2">
                <h2 id={idJudul} className="text-subjudul font-bold text-teks-utama">
                    Aktifkan {kode.NamaPerangkat} ({kode.KodePerangkat})
                </h2>
                <p className="text-isi text-teks-sekunder">
                    Buka aplikasi kasir, pilih &quot;Aktifkan perangkat&quot;, lalu pindai QR atau ketik kode ini:
                </p>
                <p className="font-mono text-judul font-bold tracking-widest text-teks-utama">{kodeTampil}</p>
                <p className="text-keterangan text-teks-sekunder">
                    Berlaku sampai {FormatTanggalWaktu(kode.KedaluwarsaPada)} dan hanya bisa dipakai sekali. Kode tidak
                    ditampilkan lagi setelah halaman ini ditutup; buat kode baru bila perlu.
                </p>
            </div>
        </section>
    );
}
