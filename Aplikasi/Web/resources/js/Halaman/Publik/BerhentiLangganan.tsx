import { Head, router } from '@inertiajs/react';
import { useState } from 'react';

import Tombol from '@/Komponen/Formulir/Tombol';
import JudulHalaman from '@/Komponen/Umpan/JudulHalaman';
import Pemberitahuan from '@/Komponen/Umpan/Pemberitahuan';

type PropsBerhentiLangganan = {
    Ditemukan: boolean;
    NamaToko: string | null;
    SudahBerhenti: boolean;
    /** Alamat bertanda tangan untuk POST; null setelah berhasil. */
    AlamatKirim: string | null;
};

/** CRM-07: pelanggan berhenti menerima pesan promosi dari satu toko (UU PDP, hak menarik persetujuan). */
export default function HalamanBerhentiLangganan({
    Ditemukan,
    NamaToko,
    SudahBerhenti,
    AlamatKirim,
}: PropsBerhentiLangganan) {
    const [memproses, AturMemproses] = useState(false);

    return (
        <main className="mx-auto flex min-h-screen w-full max-w-xl flex-col gap-4 bg-latar px-4 py-6 text-isi text-teks-utama">
            <Head title="Berhenti berlangganan" />
            {!Ditemukan ? (
                <>
                    <JudulHalaman>Tautan tidak berlaku</JudulHalaman>
                    <p className="text-teks-sekunder">
                        Tautan ini rusak atau sudah tidak dikenal. Hubungi toko yang mengirim pesan untuk berhenti
                        berlangganan.
                    </p>
                </>
            ) : SudahBerhenti ? (
                <>
                    <JudulHalaman>Anda sudah berhenti berlangganan</JudulHalaman>
                    <Pemberitahuan jenis="sukses">
                        {NamaToko} tidak akan mengirim pesan promosi lagi kepada Anda. Struk dan pemberitahuan transaksi
                        tetap dikirim seperti biasa.
                    </Pemberitahuan>
                </>
            ) : (
                <>
                    <JudulHalaman>Berhenti menerima promosi dari {NamaToko}?</JudulHalaman>
                    <p className="text-teks-sekunder">
                        Anda tidak akan menerima pesan promosi WhatsApp atau email dari {NamaToko}. Struk dan
                        pemberitahuan transaksi tetap dikirim. Anda bisa berlangganan lagi lewat kasir toko.
                    </p>
                    <div>
                        <Tombol
                            memproses={memproses}
                            onClick={() =>
                                AlamatKirim &&
                                router.post(
                                    AlamatKirim,
                                    {},
                                    { onStart: () => AturMemproses(true), onFinish: () => AturMemproses(false) },
                                )
                            }
                        >
                            Berhenti berlangganan
                        </Tombol>
                    </div>
                </>
            )}
        </main>
    );
}
