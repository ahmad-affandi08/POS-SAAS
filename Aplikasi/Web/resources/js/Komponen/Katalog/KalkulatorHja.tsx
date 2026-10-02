import { useState } from 'react';

import BidangTeks from '@/Komponen/Formulir/BidangTeks';
import BidangUang from '@/Komponen/Formulir/BidangUang';
import Tombol from '@/Komponen/Formulir/Tombol';
import { FormatRupiah } from '@/Pustaka/Format';
import { HitungSaranHja } from '@/Pustaka/HargaApotek';

type PropsKalkulatorHja = {
    /** Satuan harga yang diisi, misal "strip". */
    simbolSatuan: string;
    saatPakai: (harga: string) => void;
    disabled?: boolean;
};

/**
 * Alat bantu harga apotek (§9.5): HNA (harga netto apotek dari faktur PBF, termasuk PPN) + margin % → saran HJA
 * dibulatkan ke Rp 100. Hanya mengisi harga jual; tidak disimpan sebagai harga tersendiri.
 */
export default function KalkulatorHja({ simbolSatuan, saatPakai, disabled = false }: PropsKalkulatorHja) {
    const [hna, AturHna] = useState('');
    const [margin, AturMargin] = useState('');
    const saran = HitungSaranHja(hna, margin);

    return (
        <div className="flex flex-col gap-3 rounded-kontrol border border-garis p-3">
            <p className="text-label font-semibold text-teks-utama">Hitung harga jual dari HNA + margin</p>
            <div className="grid gap-3 sm:grid-cols-2">
                <BidangUang
                    label={`HNA per ${simbolSatuan}`}
                    nilai={hna}
                    saatBerubah={AturHna}
                    keterangan="Harga netto apotek dari faktur PBF, sudah termasuk PPN."
                    disabled={disabled}
                />
                <BidangTeks
                    label="Margin (%)"
                    nilai={margin}
                    saatBerubah={(nilai) => AturMargin(nilai.replace(/[^\d.,]/g, ''))}
                    keterangan="Misal 20 untuk margin 20%."
                    inputMode="decimal"
                    maxLength={7}
                    disabled={disabled}
                />
            </div>
            <div className="flex flex-wrap items-center gap-3">
                <p className="text-isi text-teks-utama" aria-live="polite">
                    {saran === null ? (
                        <span className="text-teks-sekunder">Isi HNA dan margin untuk melihat saran harga.</span>
                    ) : (
                        <>
                            Saran HJA: <span className="font-semibold tabular-nums">{FormatRupiah(saran)}</span>
                        </>
                    )}
                </p>
                <Tombol
                    varian="sekunder"
                    disabled={disabled || saran === null}
                    onClick={() => {
                        if (saran !== null) saatPakai(saran);
                    }}
                >
                    Pakai sebagai harga
                </Tombol>
            </div>
        </div>
    );
}
