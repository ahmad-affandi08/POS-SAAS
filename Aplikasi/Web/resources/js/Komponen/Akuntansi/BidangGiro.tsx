import BidangPilihan from '@/Komponen/Formulir/BidangPilihan';
import BidangTeks from '@/Komponen/Formulir/BidangTeks';
import PemilihTanggal from '@/Komponen/Tanggal/PemilihTanggal';

export type CaraBayar = 'KasBank' | 'Giro';

export type IsianGiro = { NomorGiro: string; NamaBank: string; TanggalJatuhTempo: string };

export const GiroKosong: IsianGiro = { NomorGiro: '', NamaBank: '', TanggalJatuhTempo: '' };

/** Galat lokal isian giro (server memeriksa ulang): nomor & bank wajib, tanggal efektif ≥ tanggal bayar. */
export function PeriksaGiro(giro: IsianGiro, tanggal: string): Partial<Record<keyof IsianGiro, string>> {
    const galat: Partial<Record<keyof IsianGiro, string>> = {};

    if (giro.NomorGiro.trim() === '') {
        galat.NomorGiro = 'Isi nomor bilyet giro/cek.';
    }

    if (giro.NamaBank.trim() === '') {
        galat.NamaBank = 'Isi bank penerbit.';
    }

    if (giro.TanggalJatuhTempo === '') {
        galat.TanggalJatuhTempo = 'Isi tanggal efektif giro.';
    } else if (giro.TanggalJatuhTempo < tanggal) {
        galat.TanggalJatuhTempo = 'Tanggal efektif tidak boleh sebelum tanggal bayar.';
    }

    return galat;
}

/**
 * v3.42 (F-12): pilihan cara bayar kas/bank atau giro/cek mundur, plus isian bilyetnya. Giro tidak langsung
 * mengubah saldo rekening: nilainya ditampung sampai dicatat cair (atau ditolak) di halaman Giro.
 */
export function BidangCaraBayar({
    cara,
    saatCara,
    label,
}: {
    cara: CaraBayar;
    saatCara: (cara: CaraBayar) => void;
    label: string;
}) {
    return (
        <BidangPilihan
            label={label}
            nilai={cara}
            opsi={[
                { Nilai: 'KasBank', Label: 'Tunai / transfer (kas & bank)' },
                { Nilai: 'Giro', Label: 'Giro / cek mundur' },
            ]}
            saatBerubah={(nilai) => saatCara(nilai === 'Giro' ? 'Giro' : 'KasBank')}
            required
        />
    );
}

export function BidangIsianGiro({
    giro,
    saatBerubah,
    tanggalMin,
    galat,
}: {
    giro: IsianGiro;
    saatBerubah: (giro: IsianGiro) => void;
    tanggalMin: string;
    galat: Partial<Record<keyof IsianGiro, string | undefined>>;
}) {
    return (
        <>
            <BidangTeks
                label="Nomor giro/cek"
                nilai={giro.NomorGiro}
                kode
                saatBerubah={(nilai) => saatBerubah({ ...giro, NomorGiro: nilai })}
                galat={galat.NomorGiro}
                required
            />
            <BidangTeks
                label="Bank penerbit"
                nilai={giro.NamaBank}
                saatBerubah={(nilai) => saatBerubah({ ...giro, NamaBank: nilai })}
                galat={galat.NamaBank}
                required
            />
            <PemilihTanggal
                id="tanggal-efektif-giro"
                label="Tanggal efektif (jatuh tempo)"
                nilai={giro.TanggalJatuhTempo}
                min={tanggalMin}
                saatBerubah={(nilai) => saatBerubah({ ...giro, TanggalJatuhTempo: nilai })}
                galat={galat.TanggalJatuhTempo}
                required
            />
        </>
    );
}
