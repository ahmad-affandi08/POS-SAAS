import { useId, useState, type KeyboardEvent } from 'react';

import BidangTeks from '@/Komponen/Formulir/BidangTeks';

import { HitungKombinasiVarian, TambahUnik } from './BantuanKatalog';

export const MaksimalAtribut = 3;
export const MaksimalNilai = 20;
export const MaksimalKombinasi = 100;

type Atribut = { Nama: string; Nilai: string[] };

type PropsEditorAtributVarian = {
    nilai: Atribut[];
    saatBerubah: (nilai: Atribut[]) => void;
    /** Galat server relatif, misal {"0.Nilai": "…", "1.Nama": "…"}. */
    galat?: Record<string, string | undefined>;
    disabled?: boolean;
};

function BidangNilai({
    atribut,
    saatTambah,
    disabled,
}: {
    atribut: Atribut;
    saatTambah: (nilai: string) => void;
    disabled: boolean;
}) {
    const id = useId();
    const [ketikan, AturKetikan] = useState('');
    const penuh = atribut.Nilai.length >= MaksimalNilai;
    const Tambah = () => {
        if (ketikan.trim() !== '') {
            saatTambah(ketikan);
            AturKetikan('');
        }
    };

    return (
        <div className="flex flex-col gap-1">
            <label htmlFor={id} className="text-label font-semibold text-teks-utama">
                Nilai {atribut.Nama || 'atribut'}
            </label>
            <div className="flex gap-2">
                <input
                    id={id}
                    type="text"
                    value={ketikan}
                    maxLength={50}
                    disabled={disabled || penuh}
                    onChange={(peristiwa) => AturKetikan(peristiwa.target.value)}
                    onKeyDown={(peristiwa: KeyboardEvent<HTMLInputElement>) => {
                        if (peristiwa.key === 'Enter') {
                            peristiwa.preventDefault();
                            Tambah();
                        }
                    }}
                    aria-describedby={`${id}-keterangan`}
                    className="h-10 min-w-0 flex-1 rounded-kontrol border border-garis-input bg-permukaan px-3 text-isi text-teks-utama outline-none focus-visible:ring-2 focus-visible:ring-brand disabled:bg-latar"
                />
                <button
                    type="button"
                    onClick={Tambah}
                    disabled={disabled || penuh}
                    className="h-10 rounded-kontrol border border-garis-input bg-permukaan px-3 text-label font-semibold text-teks-utama outline-none focus-visible:ring-2 focus-visible:ring-brand"
                >
                    Tambah nilai
                </button>
            </div>
            <p id={`${id}-keterangan`} className="text-keterangan text-teks-sekunder">
                {penuh
                    ? `Maksimal ${String(MaksimalNilai)} nilai per atribut.`
                    : 'Tekan Enter untuk menambah, misal S, M, L.'}
            </p>
        </div>
    );
}

/**
 * Definisi atribut varian (maks 3 atribut × 20 nilai; ≤ 100 kombinasi per generasi, DesainF03 C.2).
 * Menampilkan jumlah kombinasi yang akan dihasilkan.
 */
export default function EditorAtributVarian({
    nilai,
    saatBerubah,
    galat = {},
    disabled = false,
}: PropsEditorAtributVarian) {
    const kombinasi = HitungKombinasiVarian(nilai);
    const Ubah = (indeks: number, perubahan: Partial<Atribut>) =>
        saatBerubah(nilai.map((item, i) => (i === indeks ? { ...item, ...perubahan } : item)));

    return (
        <div className="flex flex-col gap-3">
            {nilai.map((atribut, indeks) => (
                <fieldset
                    key={indeks}
                    className="flex flex-col gap-2 rounded-panel border border-garis bg-permukaan p-3"
                >
                    <legend className="px-1 text-label font-semibold text-teks-utama">Atribut {indeks + 1}</legend>
                    <BidangTeks
                        label="Nama atribut"
                        nilai={atribut.Nama}
                        saatBerubah={(teks) => Ubah(indeks, { Nama: teks })}
                        galat={galat[`${String(indeks)}.Nama`]}
                        keterangan="Misal Ukuran, Warna, atau Rasa."
                        maxLength={50}
                        disabled={disabled}
                    />
                    {atribut.Nilai.length > 0 ? (
                        <ul className="flex flex-wrap gap-2" aria-label={`Nilai ${atribut.Nama || 'atribut'}`}>
                            {atribut.Nilai.map((item) => (
                                <li
                                    key={item}
                                    className="flex items-center gap-2 rounded-kontrol border border-garis bg-latar px-2 py-1 text-label"
                                >
                                    <span className="break-all">{item}</span>
                                    {!disabled ? (
                                        <button
                                            type="button"
                                            onClick={() =>
                                                Ubah(indeks, { Nilai: atribut.Nilai.filter((n) => n !== item) })
                                            }
                                            className="font-semibold text-bahaya underline outline-none focus-visible:ring-2 focus-visible:ring-brand"
                                            aria-label={`Hapus nilai ${item}`}
                                        >
                                            Hapus
                                        </button>
                                    ) : null}
                                </li>
                            ))}
                        </ul>
                    ) : null}
                    <BidangNilai
                        atribut={atribut}
                        disabled={disabled}
                        saatTambah={(teks) => Ubah(indeks, { Nilai: TambahUnik(atribut.Nilai, teks) })}
                    />
                    {galat[`${String(indeks)}.Nilai`] ? (
                        <p className="text-keterangan font-semibold text-bahaya">{galat[`${String(indeks)}.Nilai`]}</p>
                    ) : null}
                    {!disabled ? (
                        <p>
                            <button
                                type="button"
                                onClick={() => saatBerubah(nilai.filter((_, i) => i !== indeks))}
                                className="text-label font-semibold text-bahaya underline outline-none focus-visible:ring-2 focus-visible:ring-brand"
                            >
                                Hapus atribut {atribut.Nama || String(indeks + 1)}
                            </button>
                        </p>
                    ) : null}
                </fieldset>
            ))}
            <div className="flex flex-wrap items-center gap-3">
                {!disabled && nilai.length < MaksimalAtribut ? (
                    <button
                        type="button"
                        onClick={() => saatBerubah([...nilai, { Nama: '', Nilai: [] }])}
                        className="h-10 rounded-kontrol border border-garis-input bg-permukaan px-3 text-label font-semibold text-teks-utama outline-none focus-visible:ring-2 focus-visible:ring-brand"
                    >
                        Tambah atribut
                    </button>
                ) : null}
                <p aria-live="polite" className="text-label text-teks-sekunder tabular-nums">
                    {kombinasi.length === 0
                        ? 'Belum ada kombinasi varian.'
                        : `${String(kombinasi.length)} kombinasi varian.`}
                    {kombinasi.length > MaksimalKombinasi
                        ? ` Maksimal ${String(MaksimalKombinasi)} varian baru sekali buat; kurangi nilai atribut.`
                        : ''}
                </p>
            </div>
        </div>
    );
}
