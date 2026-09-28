import { router } from '@inertiajs/react';
import { useState } from 'react';

import Tombol from '@/Komponen/Formulir/Tombol';
import Pemberitahuan from '@/Komponen/Umpan/Pemberitahuan';
import { KirimJson } from '@/Pustaka/PermintaanJson';

type OpsiSnap = {
    onSuccess: () => void;
    onPending: () => void;
    onError: () => void;
    onClose: () => void;
};

type Snap = { pay: (token: string, opsi: OpsiSnap) => void };

declare global {
    interface Window {
        snap?: Snap;
    }
}

function MuatSnap(alamatSkrip: string, kunciKlien: string): Promise<Snap> {
    return new Promise((selesai, gagal) => {
        if (window.snap) {
            selesai(window.snap);

            return;
        }

        const skripAda = document.querySelector<HTMLScriptElement>(`script[src="${alamatSkrip}"]`);
        const skrip = skripAda ?? document.createElement('script');
        skrip.addEventListener('load', () =>
            window.snap ? selesai(window.snap) : gagal(new Error('Skrip pembayaran termuat tetapi tidak siap.')),
        );
        skrip.addEventListener('error', () => gagal(new Error('Skrip pembayaran gagal dimuat.')));

        if (!skripAda) {
            skrip.src = alamatSkrip;
            skrip.async = true;
            skrip.dataset.clientKey = kunciKlien;
            document.head.appendChild(skrip);
        }
    });
}

export type PropsTombolBayarOnline = {
    uuidTagihan: string;
    kunciKlien: string;
    urlSnapJs: string;
};

/**
 * "Bayar online" untuk tagihan langganan (BR-P08.11): minta transaksi Snap ke server, lalu buka popup gerbang.
 *
 * Yang dilaporkan popup **tidak** dipakai untuk menyatakan tagihan lunas — itu hanya keterangan di peramban dan bisa
 * dipalsukan. Pelunasan datang dari notifikasi webhook bertanda tangan, jadi setelah popup selesai halaman cukup
 * dimuat ulang untuk menampilkan keadaan menurut server.
 */
export default function TombolBayarOnline({ uuidTagihan, kunciKlien, urlSnapJs }: PropsTombolBayarOnline) {
    const [memproses, AturMemproses] = useState(false);
    const [galat, AturGalat] = useState<string | null>(null);
    const [menungguKonfirmasi, AturMenungguKonfirmasi] = useState(false);

    const Bayar = async () => {
        AturGalat(null);
        AturMemproses(true);

        try {
            const [snap, hasil] = await Promise.all([
                MuatSnap(urlSnapJs, kunciKlien),
                KirimJson<{ Token: string; UrlRedirect: string }>(
                    `/kelola/langganan/tagihan/${uuidTagihan}/bayar-online`,
                ),
            ]);

            snap.pay(hasil.Token, {
                // Konfirmasi gerbang bisa tiba beberapa detik setelah popup tertutup, jadi keadaan "menunggu
                // konfirmasi" ditampilkan dan halaman dimuat ulang; bukan langsung diklaim lunas.
                onSuccess: () => {
                    AturMenungguKonfirmasi(true);
                    router.reload();
                },
                onPending: () => {
                    AturMenungguKonfirmasi(true);
                    router.reload();
                },
                onError: () => AturGalat('Pembayaran gagal di gerbang. Coba lagi atau pakai transfer manual.'),
                onClose: () => router.reload(),
            });
        } catch (kegagalan) {
            AturGalat(kegagalan instanceof Error ? kegagalan.message : 'Pembayaran online tidak bisa dimulai.');
        } finally {
            AturMemproses(false);
        }
    };

    return (
        <div className="flex flex-col gap-2">
            <div>
                <Tombol onClick={() => void Bayar()} memproses={memproses}>
                    Bayar online
                </Tombol>
            </div>
            <p className="text-keterangan text-teks-sekunder">
                Kartu, QRIS, virtual account, dan e-wallet. Tagihan otomatis lunas setelah pembayaran dikonfirmasi.
            </p>
            {menungguKonfirmasi ? (
                <Pemberitahuan jenis="info" judul="Menunggu konfirmasi gerbang">
                    Pembayaran Anda sedang dikonfirmasi. Halaman ini diperbarui sendiri; bila statusnya belum berubah
                    dalam beberapa menit, muat ulang halaman.
                </Pemberitahuan>
            ) : null}
            {galat ? (
                <Pemberitahuan jenis="bahaya" judul="Pembayaran online gagal dimulai">
                    {galat}
                </Pemberitahuan>
            ) : null}
        </div>
    );
}
