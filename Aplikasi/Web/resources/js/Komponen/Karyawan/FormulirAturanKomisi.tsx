import { router, usePage } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';

import BidangPilihan from '@/Komponen/Formulir/BidangPilihan';
import BidangTeks from '@/Komponen/Formulir/BidangTeks';
import BidangUang from '@/Komponen/Formulir/BidangUang';
import PemilihProduk from '@/Komponen/Katalog/PemilihProduk';
import DialogFormulir from '@/Komponen/Tindakan/DialogFormulir';
import { Button } from '@/Komponen/Ui/button';
import type { PropsBersamaAplikasi } from '@/Tipe/Aplikasi';
import type { BarisAturanKomisi, CakupanKomisi, JenisKomisi, OpsiUuidNama } from '@/Tipe/Karyawan';

export const AlamatAturanKomisi = '/kelola/karyawan/komisi';

/** Kunci galat yang tampil di bawah isian formulir ini (sisanya ditampilkan `DaftarGalatServer` halaman). */
export const IsianAturanKomisi = ['Nama', 'UuidKategori', 'UuidProduk', 'LevelStaf', 'Nilai'];

type Isian = {
    Nama: string;
    Cakupan: CakupanKomisi;
    UuidProduk: string;
    NamaProduk: string;
    UuidKategori: string;
    LevelStaf: string;
    Jenis: JenisKomisi;
    Nilai: string;
};

type PropsIsiFormulirAturanKomisi = {
    aturan: BarisAturanKomisi | null;
    opsiKategori: OpsiUuidNama[];
    /** Dipanggil setelah tersimpan (panel ubah menutup diri). Halaman tambah tidak memakainya: server mengarahkan. */
    saatSelesai?: () => void;
    saatBatal: () => void;
};

/** Isian formulir aturan komisi (F-18 EMP-04), dipakai halaman "Tambah aturan komisi" dan panel ubah. */
export function IsiFormulirAturanKomisi({
    aturan,
    opsiKategori,
    saatSelesai,
    saatBatal,
}: PropsIsiFormulirAturanKomisi) {
    const { props } = usePage<PropsBersamaAplikasi>();
    const galat = props.errors;
    const [isian, AturIsian] = useState<Isian>({
        Nama: aturan?.Nama ?? '',
        Cakupan: aturan?.Cakupan ?? 'Semua',
        UuidProduk: aturan?.UuidProduk ?? '',
        NamaProduk: aturan?.Cakupan === 'Produk' ? aturan.NamaSasaran : '',
        UuidKategori: aturan?.UuidKategori ?? '',
        LevelStaf: aturan?.LevelStaf ?? '',
        Jenis: aturan?.Jenis ?? 'Persen',
        Nilai: (aturan?.Nilai ?? '').replace(/\.00$/, ''),
    });
    const Ubah = (ubah: Partial<Isian>) => AturIsian({ ...isian, ...ubah });
    const Simpan = (peristiwa: FormEvent) => {
        peristiwa.preventDefault();
        const data = {
            Nama: isian.Nama,
            Cakupan: isian.Cakupan,
            UuidProduk: isian.Cakupan === 'Produk' && isian.UuidProduk !== '' ? isian.UuidProduk : null,
            UuidKategori: isian.Cakupan === 'Kategori' && isian.UuidKategori !== '' ? isian.UuidKategori : null,
            LevelStaf: isian.LevelStaf.trim() === '' ? null : isian.LevelStaf.trim(),
            Jenis: isian.Jenis,
            Nilai: isian.Nilai,
        };
        const opsi = { preserveScroll: true, onSuccess: () => saatSelesai?.() };

        if (aturan === null) {
            router.post(AlamatAturanKomisi, data, opsi);
        } else {
            router.put(`${AlamatAturanKomisi}/${aturan.Uuid}`, data, opsi);
        }
    };

    return (
        <form onSubmit={Simpan} className="flex flex-col gap-4" aria-label="Formulir aturan komisi" noValidate>
            <BidangTeks
                label="Nama aturan"
                nilai={isian.Nama}
                saatBerubah={(nilai) => Ubah({ Nama: nilai })}
                galat={galat.Nama}
                keterangan="Misal Komisi potong rambut senior."
                maxLength={100}
                required
            />
            <BidangPilihan
                label="Berlaku untuk"
                nilai={isian.Cakupan}
                opsi={[
                    { Nilai: 'Semua', Label: 'Semua produk' },
                    { Nilai: 'Kategori', Label: 'Satu kategori' },
                    { Nilai: 'Produk', Label: 'Satu produk' },
                ]}
                saatBerubah={(nilai) => Ubah({ Cakupan: nilai as CakupanKomisi })}
                required
            />
            {isian.Cakupan === 'Kategori' ? (
                <BidangPilihan
                    label="Kategori"
                    nilai={isian.UuidKategori}
                    kosong="Pilih kategori"
                    opsi={opsiKategori.map((k) => ({ Nilai: k.Uuid, Label: k.Nama }))}
                    saatBerubah={(nilai) => Ubah({ UuidKategori: nilai })}
                    galat={galat.UuidKategori}
                    required
                />
            ) : null}
            {isian.Cakupan === 'Produk' ? (
                <div className="flex flex-col gap-1">
                    <PemilihProduk
                        label={isian.NamaProduk === '' ? 'Pilih produk' : `Produk: ${isian.NamaProduk} (ganti)`}
                        jenis={[]}
                        saatPilih={(produk) => Ubah({ UuidProduk: produk.Uuid, NamaProduk: produk.Nama })}
                        galat={galat.UuidProduk}
                    />
                </div>
            ) : null}
            <BidangTeks
                label="Level staf (opsional)"
                nilai={isian.LevelStaf}
                saatBerubah={(nilai) => Ubah({ LevelStaf: nilai })}
                galat={galat.LevelStaf}
                keterangan="Kosongkan agar berlaku untuk semua level. Aturan dengan level yang cocok lebih diutamakan."
                maxLength={40}
            />
            <BidangPilihan
                label="Jenis komisi"
                nilai={isian.Jenis}
                opsi={[
                    { Nilai: 'Persen', Label: 'Persen dari nilai penjualan (tanpa pajak)' },
                    { Nilai: 'Tetap', Label: 'Nominal per jumlah' },
                ]}
                saatBerubah={(nilai) => Ubah({ Jenis: nilai as JenisKomisi })}
                required
            />
            {isian.Jenis === 'Persen' ? (
                <BidangTeks
                    label="Persen komisi"
                    nilai={isian.Nilai}
                    saatBerubah={(nilai) => Ubah({ Nilai: nilai.replace(',', '.') })}
                    galat={galat.Nilai}
                    inputMode="decimal"
                    maxLength={6}
                    required
                />
            ) : (
                <BidangUang
                    label="Nominal per jumlah"
                    nilai={isian.Nilai}
                    saatBerubah={(nilai) => Ubah({ Nilai: nilai })}
                    galat={galat.Nilai}
                    required
                />
            )}
            <div className="flex flex-wrap justify-end gap-2">
                <Button type="button" variant="outline" onClick={saatBatal}>
                    Batal
                </Button>
                <Button type="submit">Simpan aturan</Button>
            </div>
        </form>
    );
}

/** Panel ubah aturan komisi. Tambah aturan memakai halaman penuh `/kelola/karyawan/komisi/buat`. */
export default function FormulirAturanKomisi({
    aturan,
    opsiKategori,
    saatTutup,
}: {
    aturan: BarisAturanKomisi;
    opsiKategori: OpsiUuidNama[];
    saatTutup: () => void;
}) {
    const { props } = usePage<PropsBersamaAplikasi>();

    return (
        <DialogFormulir
            judul={`Ubah aturan ${aturan.Nama}`}
            jenis="panel"
            galatUmum={props.errors.Umum}
            saatTutup={saatTutup}
        >
            <IsiFormulirAturanKomisi
                aturan={aturan}
                opsiKategori={opsiKategori}
                saatSelesai={saatTutup}
                saatBatal={saatTutup}
            />
        </DialogFormulir>
    );
}
