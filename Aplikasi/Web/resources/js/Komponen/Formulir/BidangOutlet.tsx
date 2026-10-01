import { useEffect, useRef } from 'react';

import BidangPilihan from './BidangPilihan';
import type { OpsiPilihan } from './PilihanCari';

type PropsBidangOutlet = {
    nilai: string;
    opsi: OpsiPilihan[];
    saatBerubah: (nilai: string) => void;
    galat?: string | undefined;
    label?: string;
    kosong?: string;
    /** Bawaan true. Outlet yang boleh dikosongkan (misal "tingkat usaha") tidak pernah diisi otomatis. */
    required?: boolean;
    /** Sembunyikan bidang saat hanya ada satu outlet (nilainya tetap diisi otomatis). */
    sembunyiBilaTunggal?: boolean;
    disabled?: boolean;
};

/**
 * Pilihan outlet untuk form input. Aturan tampilan: bidang ini **selalu bidang pertama** form (outlet menentukan isi
 * bidang lain: lokasi stok, staf, slot, daftar harga), dan bila tenant hanya punya satu outlet yang boleh dipilih,
 * nilainya diisi otomatis dan dikunci supaya pengguna tidak perlu memilih apa-apa. Aturan yang sama dipakai untuk
 * pilihan **lokasi stok** (gudang milik outlet) di form persediaan dan pembelian: [label] & [kosong] disesuaikan.
 */
export default function BidangOutlet({
    nilai,
    opsi,
    saatBerubah,
    galat,
    label = 'Outlet',
    kosong = 'Pilih outlet',
    required = true,
    sembunyiBilaTunggal = false,
    disabled,
}: PropsBidangOutlet) {
    const tunggal = opsi.length === 1 ? opsi[0] : undefined;
    const otomatis = required && tunggal !== undefined;
    const nilaiTunggal = tunggal?.Nilai;

    // Pemanggil biasanya meneruskan fungsi baru tiap render; hanya perubahan nilai yang boleh memicu pengisian ulang
    // (bila tidak, halaman yang memuat ulang dari server mengirim permintaan ganda saat menunggu respons).
    const terbaru = useRef(saatBerubah);

    useEffect(() => {
        terbaru.current = saatBerubah;
    });

    useEffect(() => {
        if (otomatis && nilaiTunggal !== undefined && nilai !== nilaiTunggal) {
            terbaru.current(nilaiTunggal);
        }
    }, [otomatis, nilaiTunggal, nilai]);

    if (otomatis && sembunyiBilaTunggal) {
        return null;
    }

    return (
        <BidangPilihan
            label={label}
            nilai={otomatis ? (nilaiTunggal ?? '') : nilai}
            opsi={opsi}
            saatBerubah={saatBerubah}
            galat={galat}
            kosong={kosong}
            disabled={disabled === true || otomatis}
            required={required}
        />
    );
}
