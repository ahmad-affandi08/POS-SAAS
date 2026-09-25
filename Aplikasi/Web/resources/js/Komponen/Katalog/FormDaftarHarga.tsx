import { useForm } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';

import BidangPilihan from '@/Komponen/Formulir/BidangPilihan';
import BidangTeks from '@/Komponen/Formulir/BidangTeks';
import GrupCentang from '@/Komponen/Formulir/GrupCentang';
import Tombol from '@/Komponen/Formulir/Tombol';
import RingkasanGalatFormulir from '@/Komponen/PanduanAwal/RingkasanGalatFormulir';
import type { FormDaftarHarga as DataFormDaftarHarga, KanalPenjualan } from '@/Tipe/Katalog';
import type { Pilihan } from '@/Tipe/Organisasi';
import PemilihTanggalWaktu from '@/Komponen/Tanggal/PemilihTanggalWaktu';

/** Rentang waktu lokal 'YYYY-MM-DDTHH:mm': selesai harus setelah mulai (RentangWaktuTidakValid). Perbandingan teks. */
export function PeriksaRentangWaktu(mulai: string, selesai: string): string | null {
    return mulai !== '' && selesai !== '' && selesai <= mulai ? 'Waktu selesai harus setelah waktu mulai.' : null;
}

export const DaftarHargaKosong: DataFormDaftarHarga = {
    Nama: '',
    UuidOutlet: [],
    Kanal: '',
    TierPelanggan: '',
    MulaiPada: '',
    SelesaiPada: '',
    Prioritas: '0',
};

function BidangWaktu({
    label,
    nilai,
    saatBerubah,
    galat,
    zonaWaktu,
}: {
    label: string;
    nilai: string;
    saatBerubah: (nilai: string) => void;
    galat: string | undefined;
    zonaWaktu: string;
}) {
    return (
        <PemilihTanggalWaktu
            label={label}
            nilai={nilai}
            saatBerubah={saatBerubah}
            galat={galat}
            keterangan={`Zona waktu ${zonaWaktu}. Kosongkan bila tanpa batas.`}
        />
    );
}

type PropsFormDaftarHarga = {
    uuid: string | null;
    awal: DataFormDaftarHarga;
    outlet: Pilihan[];
    kanal: Pilihan[];
    /** F-16b: tier pelanggan aktif (Nilai = Kode). Kosong = isian kode bebas seperti sebelumnya. */
    tier?: Pilihan[];
    zonaWaktu: string;
    /** Dipanggil setelah tersimpan (panel ubah menutup diri). Halaman buat tidak memakainya: server mengarahkan. */
    saatSelesai?: () => void;
    saatBatal: () => void;
};

/** Formulir daftar harga: outlet × kanal × tingkat pelanggan × periode, dengan prioritas (price engine lapis 3–4). */
export default function FormDaftarHarga({
    uuid,
    awal,
    outlet,
    kanal,
    tier = [],
    zonaWaktu,
    saatSelesai,
    saatBatal,
}: PropsFormDaftarHarga) {
    const formulir = useForm<DataFormDaftarHarga>(awal);
    const data = formulir.data;
    const galat = formulir.errors as Record<string, string | undefined>;
    const [periksa, AturPeriksa] = useState(false);
    const galatRentang = periksa ? PeriksaRentangWaktu(data.MulaiPada, data.SelesaiPada) : null;

    const Kirim = (peristiwa: FormEvent) => {
        peristiwa.preventDefault();
        AturPeriksa(true);

        if (PeriksaRentangWaktu(data.MulaiPada, data.SelesaiPada) !== null) {
            return;
        }

        const opsi = { preserveScroll: true, onSuccess: () => saatSelesai?.() };

        if (uuid === null) {
            formulir.post('/kelola/daftar-harga', opsi);
        } else {
            formulir.put(`/kelola/daftar-harga/${uuid}`, opsi);
        }
    };

    return (
        <form
            onSubmit={Kirim}
            noValidate
            aria-label={uuid === null ? 'Buat daftar harga' : 'Ubah pengaturan daftar harga'}
            className="flex flex-col gap-4"
        >
            <RingkasanGalatFormulir galat={{ ...galat, ...(galatRentang ? { SelesaiPada: galatRentang } : {}) }} />
            <div className="grid gap-4 sm:grid-cols-2">
                <BidangTeks
                    label="Nama daftar harga"
                    nilai={data.Nama}
                    saatBerubah={(nilai) => formulir.setData('Nama', nilai)}
                    galat={galat.Nama}
                    keterangan="Misal Harga GoFood, Harga Bandara, atau Grosir."
                    maxLength={100}
                    required
                    autoFocus
                />
                <BidangTeks
                    label="Prioritas"
                    nilai={data.Prioritas}
                    saatBerubah={(nilai) => formulir.setData('Prioritas', nilai.replace(/\D/g, ''))}
                    galat={galat.Prioritas}
                    inputMode="numeric"
                    maxLength={4}
                    keterangan="Bila beberapa daftar cocok, prioritas lebih besar dipakai lebih dulu."
                />
                <BidangPilihan
                    label="Kanal penjualan"
                    nilai={data.Kanal}
                    kosong="Semua kanal"
                    opsi={kanal}
                    saatBerubah={(nilai) => formulir.setData('Kanal', nilai as KanalPenjualan | '')}
                    galat={galat.Kanal}
                />
                {tier.length > 0 ? (
                    <BidangPilihan
                        label="Tier pelanggan (opsional)"
                        nilai={data.TierPelanggan}
                        kosong="Semua pelanggan"
                        opsi={
                            data.TierPelanggan !== '' && !tier.some((t) => t.Nilai === data.TierPelanggan)
                                ? [...tier, { Nilai: data.TierPelanggan, Label: data.TierPelanggan }]
                                : tier
                        }
                        saatBerubah={(nilai) => formulir.setData('TierPelanggan', nilai)}
                        galat={galat.TierPelanggan}
                    />
                ) : (
                    <BidangTeks
                        label="Tingkat pelanggan (opsional)"
                        nilai={data.TierPelanggan}
                        saatBerubah={(nilai) => formulir.setData('TierPelanggan', nilai)}
                        galat={galat.TierPelanggan}
                        keterangan="Kode persis seperti tier pelanggan, misal GROSIR. Kosongkan untuk semua pelanggan."
                        maxLength={30}
                        kode
                    />
                )}
                <BidangWaktu
                    label="Mulai berlaku (opsional)"
                    nilai={data.MulaiPada}
                    saatBerubah={(nilai) => formulir.setData('MulaiPada', nilai)}
                    galat={galat.MulaiPada}
                    zonaWaktu={zonaWaktu}
                />
                <BidangWaktu
                    label="Selesai berlaku (opsional)"
                    nilai={data.SelesaiPada}
                    saatBerubah={(nilai) => formulir.setData('SelesaiPada', nilai)}
                    galat={galat.SelesaiPada ?? galatRentang ?? undefined}
                    zonaWaktu={zonaWaktu}
                />
            </div>
            {outlet.length > 0 ? (
                <div className="flex flex-col gap-1">
                    <GrupCentang
                        legenda="Berlaku di outlet"
                        opsi={outlet.map((item) => ({ nilai: item.Nilai, label: item.Label }))}
                        terpilih={data.UuidOutlet}
                        saatBerubah={(nilai) => formulir.setData('UuidOutlet', nilai)}
                        galat={galat.UuidOutlet}
                    />
                    <p className="text-keterangan text-teks-sekunder">
                        {data.UuidOutlet.length === 0
                            ? 'Tidak ada yang dicentang: berlaku di semua outlet.'
                            : `Berlaku di ${String(data.UuidOutlet.length)} outlet.`}
                    </p>
                </div>
            ) : null}
            <div className="flex flex-wrap gap-2">
                <Tombol type="submit" memproses={formulir.processing}>
                    {uuid === null ? 'Buat daftar harga' : 'Simpan pengaturan'}
                </Tombol>
                <Tombol varian="sekunder" onClick={saatBatal}>
                    Batal
                </Tombol>
            </div>
        </form>
    );
}
