import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

import BidangPilihan from '@/Komponen/Formulir/BidangPilihan';
import BidangTeks from '@/Komponen/Formulir/BidangTeks';
import BidangTeksPanjang from '@/Komponen/Formulir/BidangTeksPanjang';
import KotakCentang from '@/Komponen/Formulir/KotakCentang';
import Tombol from '@/Komponen/Formulir/Tombol';
import DialogFormulir from '@/Komponen/Tindakan/DialogFormulir';
import { DialogFooter } from '@/Komponen/Ui/dialog';

export type DataMitraForm = {
    Uuid: string;
    Kode: string;
    Nama: string;
    Jenis: string;
    Status: string;
    PersenKomisi: string;
    KomisiBerulang: boolean;
    Email?: string | null;
    NamaBank?: string | null;
    NamaPemilikRekening?: string | null;
    Catatan?: string | null;
    NoHpTersamar?: string | null;
    NpwpTersamar?: string | null;
    RekeningTersamar?: string | null;
};

type Opsi = { Nilai: string; Label: string };

/**
 * Formulir mitra P-12 (tambah & ubah). Nomor HP, NPWP, dan nomor rekening tidak pernah dikirim balik ke peramban:
 * yang tampil hanya 4 digit terakhir, dan bidang kosong saat mengubah berarti nilainya tetap.
 */
export default function FormMitra({
    mitra,
    opsiJenis,
    saatSelesai,
}: {
    mitra: DataMitraForm | null;
    opsiJenis: Opsi[];
    saatSelesai: () => void;
}) {
    const formulir = useForm({
        Kode: mitra?.Kode ?? '',
        Nama: mitra?.Nama ?? '',
        Jenis: mitra?.Jenis ?? 'Referral',
        Status: mitra?.Status ?? 'Aktif',
        Email: mitra?.Email ?? '',
        NoHp: '',
        Npwp: '',
        NamaBank: mitra?.NamaBank ?? '',
        NomorRekening: '',
        NamaPemilikRekening: mitra?.NamaPemilikRekening ?? '',
        PersenKomisi: mitra?.PersenKomisi.replace(/\.00$/, '') ?? '0',
        KomisiBerulang: mitra?.KomisiBerulang ?? false,
        Catatan: mitra?.Catatan ?? '',
    });
    const KeteranganRahasia = (tersamar: string | null | undefined): string =>
        mitra === null
            ? 'Disimpan terenkripsi.'
            : tersamar
              ? `Tersimpan ${tersamar}. Kosongkan bila tidak diubah.`
              : 'Belum diisi.';

    const Kirim = (peristiwa: FormEvent) => {
        peristiwa.preventDefault();
        const opsi = { preserveScroll: true, onSuccess: saatSelesai };

        if (mitra === null) {
            formulir.post('/mitra', opsi);
        } else {
            formulir.put(`/mitra/${mitra.Uuid}`, opsi);
        }
    };

    return (
        <DialogFormulir
            judul={mitra === null ? 'Tambah mitra' : `Ubah mitra ${mitra.Kode}`}
            saatTutup={saatSelesai}
            lebar="lebar"
            galatUmum={(formulir.errors as Record<string, string | undefined>).Umum}
        >
            <form onSubmit={Kirim} className="grid gap-4 sm:grid-cols-2" noValidate>
                <BidangTeks
                    label="Kode mitra"
                    kode
                    keterangan="Dipakai di tautan pendaftaran. Huruf besar/angka/tanda hubung, tidak bisa diubah."
                    nilai={formulir.data.Kode}
                    saatBerubah={(nilai) => formulir.setData('Kode', nilai.toUpperCase())}
                    galat={formulir.errors.Kode}
                    required
                    disabled={mitra !== null}
                />
                <BidangTeks
                    label="Nama mitra"
                    nilai={formulir.data.Nama}
                    saatBerubah={(nilai) => formulir.setData('Nama', nilai)}
                    galat={formulir.errors.Nama}
                    required
                />
                <BidangPilihan
                    label="Jenis mitra"
                    nilai={formulir.data.Jenis}
                    opsi={opsiJenis}
                    saatBerubah={(nilai) => {
                        formulir.setData((lama) => ({ ...lama, Jenis: nilai, KomisiBerulang: nilai === 'Reseller' }));
                    }}
                    galat={formulir.errors.Jenis}
                    required
                />
                <BidangPilihan
                    label="Status"
                    nilai={formulir.data.Status}
                    opsi={[
                        { Nilai: 'Aktif', Label: 'Aktif' },
                        { Nilai: 'Ditangguhkan', Label: 'Ditangguhkan' },
                    ]}
                    saatBerubah={(nilai) => formulir.setData('Status', nilai)}
                    galat={formulir.errors.Status}
                    required
                />
                <BidangTeks
                    label="Komisi (%)"
                    inputMode="decimal"
                    keterangan="Dari tagihan langganan lunas, sebelum PPN."
                    nilai={formulir.data.PersenKomisi}
                    saatBerubah={(nilai) => formulir.setData('PersenKomisi', nilai)}
                    galat={formulir.errors.PersenKomisi}
                    required
                />
                <div className="self-end">
                    <KotakCentang
                        label="Komisi berulang (setiap tagihan lunas, bukan hanya yang pertama)"
                        nilai={formulir.data.KomisiBerulang}
                        saatBerubah={(nilai) => formulir.setData('KomisiBerulang', nilai)}
                    />
                </div>
                <BidangTeks
                    label="Email"
                    nilai={formulir.data.Email}
                    saatBerubah={(nilai) => formulir.setData('Email', nilai)}
                    galat={formulir.errors.Email}
                />
                <BidangTeks
                    label="Nomor HP"
                    inputMode="tel"
                    keterangan={KeteranganRahasia(mitra?.NoHpTersamar)}
                    nilai={formulir.data.NoHp}
                    saatBerubah={(nilai) => formulir.setData('NoHp', nilai)}
                    galat={formulir.errors.NoHp}
                />
                <BidangTeks
                    label="NPWP"
                    kode
                    keterangan={KeteranganRahasia(mitra?.NpwpTersamar)}
                    nilai={formulir.data.Npwp}
                    saatBerubah={(nilai) => formulir.setData('Npwp', nilai)}
                    galat={formulir.errors.Npwp}
                />
                <BidangTeks
                    label="Nama bank"
                    nilai={formulir.data.NamaBank}
                    saatBerubah={(nilai) => formulir.setData('NamaBank', nilai)}
                    galat={formulir.errors.NamaBank}
                />
                <BidangTeks
                    label="Nomor rekening"
                    kode
                    keterangan={KeteranganRahasia(mitra?.RekeningTersamar)}
                    nilai={formulir.data.NomorRekening}
                    saatBerubah={(nilai) => formulir.setData('NomorRekening', nilai)}
                    galat={formulir.errors.NomorRekening}
                />
                <BidangTeks
                    label="Nama pemilik rekening"
                    nilai={formulir.data.NamaPemilikRekening}
                    saatBerubah={(nilai) => formulir.setData('NamaPemilikRekening', nilai)}
                    galat={formulir.errors.NamaPemilikRekening}
                />
                <div className="sm:col-span-2">
                    <BidangTeksPanjang
                        label="Catatan internal"
                        nilai={formulir.data.Catatan}
                        saatBerubah={(nilai) => formulir.setData('Catatan', nilai)}
                        galat={formulir.errors.Catatan}
                    />
                </div>
                <DialogFooter className="sm:col-span-2 sm:justify-start">
                    <Tombol type="submit" memproses={formulir.processing}>
                        Simpan mitra
                    </Tombol>
                    <Tombol varian="sekunder" onClick={saatSelesai}>
                        Batal
                    </Tombol>
                </DialogFooter>
            </form>
        </DialogFormulir>
    );
}
