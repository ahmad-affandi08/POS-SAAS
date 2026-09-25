import { router, usePage } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';

import BidangDaftarTeks from '@/Komponen/Formulir/BidangDaftarTeks';
import BidangTeks from '@/Komponen/Formulir/BidangTeks';
import BidangTeksPanjang from '@/Komponen/Formulir/BidangTeksPanjang';
import BidangUang from '@/Komponen/Formulir/BidangUang';
import KotakCentang from '@/Komponen/Formulir/KotakCentang';
import PemilihTanggal from '@/Komponen/Tanggal/PemilihTanggal';
import DialogFormulir from '@/Komponen/Tindakan/DialogFormulir';
import { Button } from '@/Komponen/Ui/button';
import type { PropsBersamaAplikasi } from '@/Tipe/Aplikasi';
import type { BarisPelanggan } from '@/Tipe/Pelanggan';

export const AlamatPelanggan = '/kelola/pelanggan';

type IsianPelanggan = {
    Nama: string;
    NoHp: string;
    Email: string;
    TanggalLahir: string;
    Alamat: string;
    Tag: string[];
    Catatan: string;
    SetujuPemasaran: boolean;
    LimitKredit: string;
    TerminHari: string;
};

function BuatIsian(p: BarisPelanggan | null): IsianPelanggan {
    return {
        Nama: p?.Nama ?? '',
        NoHp: p?.NoHp ?? '',
        Email: p?.Email ?? '',
        TanggalLahir: p?.TanggalLahir ?? '',
        Alamat: p?.Alamat ?? '',
        Tag: p?.Tag ?? [],
        Catatan: p?.Catatan ?? '',
        SetujuPemasaran: p?.SetujuPemasaran ?? false,
        LimitKredit: (p?.LimitKredit ?? '').replace(/\.00$/, ''),
        TerminHari: String(p?.TerminHari ?? 30),
    };
}

/** Formulir tambah/ubah pelanggan (F-16a) sebagai panel; `pelanggan` null = tambah. */
export default function FormulirPelanggan({
    pelanggan,
    saatTutup,
}: {
    pelanggan: BarisPelanggan | null;
    saatTutup: () => void;
}) {
    const { props } = usePage<PropsBersamaAplikasi>();
    const galat = props.errors;
    const [isian, AturIsian] = useState<IsianPelanggan>(() => BuatIsian(pelanggan));
    const [memproses, AturMemproses] = useState(false);
    const hariIni = new Date().toISOString().slice(0, 10);

    const Ubah = (ubah: Partial<IsianPelanggan>) => AturIsian({ ...isian, ...ubah });

    const Simpan = (peristiwa: FormEvent) => {
        peristiwa.preventDefault();
        const data = {
            ...isian,
            TanggalLahir: isian.TanggalLahir === '' ? null : isian.TanggalLahir,
            Tag: isian.Tag.map((t) => t.trim()).filter((t) => t !== ''),
            TerminHari: isian.TerminHari === '' ? null : isian.TerminHari,
        };
        const opsi = {
            preserveScroll: true,
            onStart: () => AturMemproses(true),
            onFinish: () => AturMemproses(false),
            onSuccess: saatTutup,
        };

        if (pelanggan === null) {
            router.post(AlamatPelanggan, data, opsi);
        } else {
            router.put(`${AlamatPelanggan}/${pelanggan.Uuid}`, data, opsi);
        }
    };

    return (
        <DialogFormulir
            judul={pelanggan === null ? 'Tambah pelanggan' : `Ubah pelanggan ${pelanggan.Nama}`}
            jenis="panel"
            galatUmum={galat.Umum}
            saatTutup={saatTutup}
        >
            <form onSubmit={Simpan} className="flex flex-col gap-4" aria-label="Formulir pelanggan">
                <BidangTeks
                    label="Nama pelanggan"
                    nilai={isian.Nama}
                    saatBerubah={(nilai) => Ubah({ Nama: nilai })}
                    galat={galat.Nama}
                    maxLength={150}
                    required
                />
                <BidangTeks
                    label="No. HP/WA"
                    nilai={isian.NoHp}
                    saatBerubah={(nilai) => Ubah({ NoHp: nilai })}
                    galat={galat.NoHp}
                    keterangan="Kunci pelanggan: satu nomor untuk satu pelanggan. Contoh 0812-3456-7890."
                    inputMode="tel"
                    maxLength={30}
                    required
                />
                <BidangTeks
                    label="Email (opsional)"
                    jenis="email"
                    nilai={isian.Email}
                    saatBerubah={(nilai) => Ubah({ Email: nilai })}
                    galat={galat.Email}
                />
                <PemilihTanggal
                    label="Tanggal lahir (opsional)"
                    nilai={isian.TanggalLahir}
                    saatBerubah={(nilai) => Ubah({ TanggalLahir: nilai })}
                    galat={galat.TanggalLahir}
                    max={hariIni}
                />
                <BidangTeksPanjang
                    label="Alamat (opsional)"
                    nilai={isian.Alamat}
                    saatBerubah={(nilai) => Ubah({ Alamat: nilai })}
                    galat={galat.Alamat}
                    maksimal={500}
                    baris={2}
                />
                <BidangDaftarTeks
                    label="Tag (opsional)"
                    nilai={isian.Tag}
                    saatBerubah={(nilai) => Ubah({ Tag: nilai })}
                    keterangan="Satu tag per baris, misal Reseller atau Langganan. Maksimal 10."
                    galat={galat.Tag ?? galat['Tag.0']}
                />
                <BidangTeksPanjang
                    label="Catatan (opsional)"
                    nilai={isian.Catatan}
                    saatBerubah={(nilai) => Ubah({ Catatan: nilai })}
                    galat={galat.Catatan}
                    maksimal={500}
                    baris={2}
                />
                <fieldset className="grid gap-3 sm:grid-cols-2">
                    <legend className="mb-1 text-label font-semibold">Kredit (bayar tempo)</legend>
                    <BidangUang
                        label="Limit kredit (opsional)"
                        nilai={isian.LimitKredit}
                        saatBerubah={(nilai) => Ubah({ LimitKredit: nilai })}
                        galat={galat.LimitKredit}
                        keterangan="Kosongkan bila pelanggan tidak boleh bayar tempo."
                    />
                    <BidangTeks
                        label="Termin (hari)"
                        nilai={isian.TerminHari}
                        saatBerubah={(nilai) => Ubah({ TerminHari: nilai.replace(/\D/g, '') })}
                        galat={galat.TerminHari}
                        keterangan="Jatuh tempo = tanggal penjualan + termin."
                        inputMode="numeric"
                        maxLength={3}
                    />
                </fieldset>
                <KotakCentang
                    label="Pelanggan setuju menerima info promo (WA/email)"
                    nilai={isian.SetujuPemasaran}
                    saatBerubah={(nilai) => Ubah({ SetujuPemasaran: nilai })}
                />
                <div className="flex flex-wrap justify-end gap-2">
                    <Button type="button" variant="outline" onClick={saatTutup}>
                        Batal
                    </Button>
                    <Button type="submit" disabled={memproses}>
                        Simpan pelanggan
                    </Button>
                </div>
            </form>
        </DialogFormulir>
    );
}
