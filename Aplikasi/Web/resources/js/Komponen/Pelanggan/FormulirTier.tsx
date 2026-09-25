import { router, usePage } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';

import BidangTeks from '@/Komponen/Formulir/BidangTeks';
import BidangUang from '@/Komponen/Formulir/BidangUang';
import BidangJumlah from '@/Komponen/Katalog/BidangJumlah';
import { Button } from '@/Komponen/Ui/button';
import type { PropsBersamaAplikasi } from '@/Tipe/Aplikasi';

export const AlamatTier = '/kelola/pelanggan/tier';

export type IsianTier = { Kode: string; Nama: string; MinimalBelanja: string; PengaliPoin: string; Urutan: string };

// Minimal belanja 0 = tier berlaku untuk semua pelanggan; ditampilkan sebagai isian awal yang terlihat, bukan diisi diam-diam.
export const TierKosong: IsianTier = { Kode: '', Nama: '', MinimalBelanja: '0', PengaliPoin: '1', Urutan: '0' };

/**
 * Isi formulir tambah/ubah tier pelanggan (F-16b); `uuid` null = tambah. Dipakai di halaman penuh "Tambah tier" dan
 * di panel ubah. `saatSelesai` dipanggil setelah tersimpan (panel ubah menutup diri); halaman buat tidak memakainya
 * karena server mengarahkan kembali ke daftar tier.
 */
export default function FormulirTier({
    uuid,
    awal,
    saatSelesai,
    saatBatal,
}: {
    uuid: string | null;
    awal: IsianTier;
    saatSelesai?: () => void;
    saatBatal: () => void;
}) {
    const { props } = usePage<PropsBersamaAplikasi>();
    const galat = props.errors;
    const [isian, AturIsian] = useState<IsianTier>(awal);
    const [memproses, AturMemproses] = useState(false);

    const Ubah = (ubah: Partial<IsianTier>) => AturIsian({ ...isian, ...ubah });

    const Simpan = (peristiwa: FormEvent) => {
        peristiwa.preventDefault();
        const data = {
            ...isian,
            Urutan: isian.Urutan === '' ? 0 : Number(isian.Urutan),
        };
        const opsi = {
            preserveScroll: true,
            onStart: () => AturMemproses(true),
            onFinish: () => AturMemproses(false),
            onSuccess: () => saatSelesai?.(),
        };

        if (uuid === null) {
            router.post(AlamatTier, data, opsi);
        } else {
            router.put(`${AlamatTier}/${uuid}`, data, opsi);
        }
    };

    return (
        <form onSubmit={Simpan} className="flex flex-col gap-4" aria-label="Formulir tier" noValidate>
            <BidangTeks
                label="Kode tier"
                nilai={isian.Kode}
                saatBerubah={(nilai) => Ubah({ Kode: nilai })}
                galat={galat.Kode}
                keterangan={
                    uuid === null
                        ? 'Misal SILVER atau RESELLER. Tidak bisa diubah setelah disimpan.'
                        : 'Kode tidak bisa diubah karena dipakai daftar harga.'
                }
                maxLength={30}
                disabled={uuid !== null}
                kode
                required={uuid === null}
            />
            <BidangTeks
                label="Nama tier"
                nilai={isian.Nama}
                saatBerubah={(nilai) => Ubah({ Nama: nilai })}
                galat={galat.Nama}
                maxLength={60}
                required
            />
            <BidangUang
                label="Minimal belanja dalam periode evaluasi"
                nilai={isian.MinimalBelanja}
                saatBerubah={(nilai) => Ubah({ MinimalBelanja: nilai })}
                galat={galat.MinimalBelanja}
                required
                keterangan="Rp 0 = semua pelanggan yang pernah belanja."
            />
            <BidangJumlah
                label="Pengali poin"
                nilai={isian.PengaliPoin}
                saatBerubah={(nilai) => Ubah({ PengaliPoin: nilai })}
                desimal={2}
                digitBulat={2}
                akhiran="×"
                required
                keterangan="1 = poin normal, 1,5 = poin 50% lebih banyak. Antara 0,1 dan 10."
                galat={galat.PengaliPoin}
            />
            <BidangJumlah
                label="Urutan tampil"
                nilai={isian.Urutan}
                saatBerubah={(nilai) => Ubah({ Urutan: nilai })}
                desimal={0}
                digitBulat={3}
                galat={galat.Urutan}
            />
            <div className="flex flex-wrap justify-end gap-2">
                <Button type="button" variant="outline" onClick={saatBatal}>
                    Batal
                </Button>
                <Button type="submit" disabled={memproses}>
                    Simpan tier
                </Button>
            </div>
        </form>
    );
}
