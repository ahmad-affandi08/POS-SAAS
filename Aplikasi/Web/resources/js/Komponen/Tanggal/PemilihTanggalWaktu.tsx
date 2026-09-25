import { ClockIcon } from 'lucide-react';
import { useId, useState } from 'react';

import { InputGroup, InputGroupAddon, InputGroupInput } from '@/Komponen/Ui/input-group';
import { Label } from '@/Komponen/Ui/label';
import { UraiTeksJam } from '@/Pustaka/Tanggal';

import PemilihTanggal from './PemilihTanggal';

type PropsPemilihTanggalWaktu = {
    label: string;
    /** `TTTT-BB-HHTjj:mm` (format `datetime-local`) atau string kosong. */
    nilai: string;
    saatBerubah: (nilai: string) => void;
    galat?: string | undefined;
    keterangan?: string | undefined;
    disabled?: boolean;
    /** Jam yang dipakai saat tanggal dipilih tetapi jam belum diisi. */
    jamBawaan?: string;
};

const polaTanggalWaktu = /^(\d{4}-\d{2}-\d{2})T(\d{2}:\d{2})/;

function PecahNilai(nilai: string): [string, string] {
    const cocok = polaTanggalWaktu.exec(nilai);

    return cocok === null ? ['', ''] : [cocok[1] ?? '', cocok[2] ?? ''];
}

/**
 * Tanggal + jam (24 jam) untuk jadwal berlaku, pemeliharaan, dsb. Tanggal memakai `PemilihTanggal`
 * (juga menerima tempelan `2026-11-01T08:00`), jam diketik `jj:mm`. Nilai keluar setara `datetime-local`.
 */
export default function PemilihTanggalWaktu({
    label,
    nilai,
    saatBerubah,
    galat,
    keterangan,
    disabled = false,
    jamBawaan = '00:00',
}: PropsPemilihTanggalWaktu) {
    const idJam = useId();
    const [tanggal, jam] = PecahNilai(nilai);
    const [teksJam, AturTeksJam] = useState(jam);
    const [jamTerakhir, AturJamTerakhir] = useState(jam);
    const [galatJam, AturGalatJam] = useState<string | null>(null);

    if (jam !== jamTerakhir) {
        AturJamTerakhir(jam);
        AturTeksJam(jam);
    }

    const Kirim = (tanggalBaru: string, jamBaru: string) => {
        const hasil = tanggalBaru === '' ? '' : `${tanggalBaru}T${jamBaru === '' ? jamBawaan : jamBaru}`;
        const [, jamHasil] = PecahNilai(hasil);
        AturJamTerakhir(jamHasil);
        AturTeksJam(jamHasil);
        saatBerubah(hasil);
    };

    return (
        <div className="flex flex-col gap-1">
            <div className="grid grid-cols-[minmax(0,1fr)_7.5rem] items-start gap-2">
                <PemilihTanggal
                    label={label}
                    nilai={tanggal}
                    saatBerubah={(baru) => {
                        const tempelan = polaTanggalWaktu.exec(baru);

                        if (tempelan) {
                            Kirim(tempelan[1] ?? '', tempelan[2] ?? '');
                        } else {
                            Kirim(baru, UraiTeksJam(teksJam) ?? '');
                        }
                    }}
                    terimaTanggalWaktu
                    galat={galat}
                    keterangan={keterangan}
                    disabled={disabled}
                    className="[&_input]:min-w-0"
                />
                <div className="flex flex-col gap-1">
                    <Label htmlFor={idJam} className="text-label font-semibold text-teks-utama">
                        Jam
                    </Label>
                    <InputGroup className="h-8 border-garis-input bg-permukaan pointer-coarse:h-11">
                        <InputGroupInput
                            id={idJam}
                            value={teksJam}
                            inputMode="numeric"
                            autoComplete="off"
                            placeholder="jj:mm"
                            aria-label={`Jam ${label}`}
                            aria-invalid={galatJam ? true : undefined}
                            disabled={disabled || tanggal === ''}
                            onChange={(peristiwa) => {
                                AturTeksJam(peristiwa.target.value);
                                const sah = UraiTeksJam(peristiwa.target.value);
                                if (sah !== undefined) {
                                    AturGalatJam(null);
                                    Kirim(tanggal, sah);
                                }
                            }}
                            onBlur={() =>
                                AturGalatJam(UraiTeksJam(teksJam) === undefined ? 'Jam jj:mm, misal 08:00.' : null)
                            }
                            className="text-isi tabular-nums placeholder:text-teks-sekunder/70"
                        />
                        <InputGroupAddon align="inline-end">
                            <ClockIcon aria-hidden="true" className="text-teks-sekunder" />
                        </InputGroupAddon>
                    </InputGroup>
                    {galatJam ? <p className="text-keterangan font-semibold text-bahaya">{galatJam}</p> : null}
                </div>
            </div>
        </div>
    );
}
