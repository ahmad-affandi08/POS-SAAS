import { useQuery } from '@tanstack/react-query';
import { useEffect, useId, useState, type KeyboardEvent } from 'react';

import { KunciKueri } from '@/Pustaka/KunciKueri';
import type { HasilCariProduk, JenisProduk } from '@/Tipe/Katalog';

export type ProdukTerpilih = HasilCariProduk['Data'][number];

/** URL pencarian produk untuk pemilih bahan/komponen (DesainF03 D.2). */
export function BuatUrlCariProduk(kata: string, jenis: readonly JenisProduk[], batas = 20): string {
    const parameter = new URLSearchParams({ kata });

    jenis.forEach((item) => parameter.append('jenis[]', item));
    parameter.set('batas', String(batas));

    return `/kelola/produk/cari?${parameter.toString()}`;
}

async function AmbilHasilCari(url: string, sinyal: AbortSignal): Promise<HasilCariProduk> {
    const respons = await fetch(url, {
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin',
        signal: sinyal,
    });

    if (!respons.ok) {
        throw new Error(`Pencarian produk gagal (${String(respons.status)})`);
    }

    return (await respons.json()) as HasilCariProduk;
}

/** Nilai yang baru berubah setelah pengguna berhenti mengetik `jeda` ms. */
function useNilaiTertunda(nilai: string, jeda: number): string {
    const [tertunda, AturTertunda] = useState(nilai);

    useEffect(() => {
        const pewaktu = window.setTimeout(() => AturTertunda(nilai), jeda);

        return () => window.clearTimeout(pewaktu);
    }, [nilai, jeda]);

    return tertunda;
}

type PropsPemilihProduk = {
    label: string;
    /** Jenis produk yang boleh dipilih, misal bahan resep: BahanBaku, Stok, Produksi. */
    jenis: readonly JenisProduk[];
    saatPilih: (produk: ProdukTerpilih) => void;
    /** Uuid produk yang tidak boleh dipilih (produk itu sendiri, bahan yang sudah ada). */
    kecuali?: string[];
    keterangan?: string;
    galat?: string | undefined;
    disabled?: boolean;
};

/**
 * Pemilih produk dengan pencarian server (TanStack Query, KunciKueri.Produk.Cari). Combobox ARIA:
 * panah atas/bawah memilih, Enter memasukkan, Escape menutup. Keadaan memuat, kosong, dan galat tertulis.
 */
export default function PemilihProduk({
    label,
    jenis,
    saatPilih,
    kecuali = [],
    keterangan,
    galat,
    disabled,
}: PropsPemilihProduk) {
    const id = useId();
    const idDaftar = `${id}-daftar`;
    const [kata, AturKata] = useState('');
    const [terbuka, AturTerbuka] = useState(false);
    const [sorot, AturSorot] = useState(0);
    const kataCari = useNilaiTertunda(kata.trim(), 300);
    const aktif = terbuka && kataCari.length >= 2;
    const kueri = useQuery({
        queryKey: KunciKueri.Produk.Cari(kataCari, jenis),
        queryFn: ({ signal }) => AmbilHasilCari(BuatUrlCariProduk(kataCari, jenis), signal),
        enabled: aktif,
        staleTime: 30_000,
    });
    const hasil = (kueri.data?.Data ?? []).filter((produk) => !kecuali.includes(produk.Uuid));
    const indeksSorot = Math.min(sorot, Math.max(hasil.length - 1, 0));

    const Pilih = (produk: ProdukTerpilih) => {
        saatPilih(produk);
        AturKata('');
        AturTerbuka(false);
        AturSorot(0);
    };

    const TekanTombol = (peristiwa: KeyboardEvent<HTMLInputElement>) => {
        if (peristiwa.key === 'ArrowDown') {
            peristiwa.preventDefault();
            AturTerbuka(true);
            AturSorot(Math.min(indeksSorot + 1, hasil.length - 1));
        } else if (peristiwa.key === 'ArrowUp') {
            peristiwa.preventDefault();
            AturSorot(Math.max(indeksSorot - 1, 0));
        } else if (peristiwa.key === 'Enter') {
            const produk = hasil[indeksSorot];

            if (terbuka && produk) {
                peristiwa.preventDefault();
                Pilih(produk);
            }
        } else if (peristiwa.key === 'Escape') {
            AturTerbuka(false);
        }
    };

    let status: string | null = null;

    if (aktif && kueri.isPending) {
        status = 'Mencari produk…';
    } else if (aktif && kueri.isError) {
        status = 'Pencarian gagal. Periksa koneksi lalu ketik ulang.';
    } else if (aktif && hasil.length === 0) {
        status = `Tidak ada produk yang cocok dengan "${kataCari}".`;
    } else if (terbuka && kata.trim().length > 0 && kata.trim().length < 2) {
        status = 'Ketik minimal 2 huruf.';
    }

    const daftarTerlihat = aktif && hasil.length > 0;

    return (
        <div className="relative flex flex-col gap-1">
            <label htmlFor={id} className="text-label font-semibold text-teks-utama">
                {label}
            </label>
            <input
                id={id}
                type="search"
                role="combobox"
                aria-expanded={daftarTerlihat}
                aria-controls={idDaftar}
                aria-autocomplete="list"
                aria-activedescendant={daftarTerlihat ? `${id}-opsi-${String(indeksSorot)}` : undefined}
                aria-invalid={galat ? true : undefined}
                aria-describedby={[keterangan ? `${id}-keterangan` : null, galat ? `${id}-galat` : null, `${id}-status`]
                    .filter(Boolean)
                    .join(' ')}
                value={kata}
                disabled={disabled}
                autoComplete="off"
                placeholder="Cari nama, SKU, atau barcode"
                onChange={(peristiwa) => {
                    AturKata(peristiwa.target.value);
                    AturTerbuka(true);
                    AturSorot(0);
                }}
                onFocus={() => AturTerbuka(true)}
                onBlur={() => window.setTimeout(() => AturTerbuka(false), 150)}
                onKeyDown={TekanTombol}
                className={`h-10 rounded-kontrol border bg-permukaan px-3 text-isi text-teks-utama outline-none focus-visible:ring-2 focus-visible:ring-brand ${
                    galat ? 'border-bahaya' : 'border-garis-input'
                }`}
            />
            {keterangan ? (
                <p id={`${id}-keterangan`} className="text-keterangan text-teks-sekunder">
                    {keterangan}
                </p>
            ) : null}
            <p id={`${id}-status`} aria-live="polite" className="text-keterangan text-teks-sekunder">
                {status ?? ''}
            </p>
            <ul
                id={idDaftar}
                role="listbox"
                aria-label={`Hasil ${label}`}
                hidden={!daftarTerlihat}
                className="absolute top-full right-0 left-0 z-10 max-h-72 overflow-y-auto rounded-kontrol border border-garis-input bg-permukaan"
            >
                {hasil.map((produk, indeks) => (
                    <li
                        key={produk.Uuid}
                        id={`${id}-opsi-${String(indeks)}`}
                        role="option"
                        aria-selected={indeks === indeksSorot}
                        onMouseDown={(peristiwa) => {
                            peristiwa.preventDefault();
                            Pilih(produk);
                        }}
                        className={`flex cursor-pointer flex-col px-3 py-2 text-isi ${
                            indeks === indeksSorot ? 'bg-latar' : ''
                        }`}
                    >
                        <span className="font-semibold break-words text-teks-utama">{produk.Nama}</span>
                        <span className="text-keterangan text-teks-sekunder">
                            {produk.Sku ? <span className="font-mono">{produk.Sku}</span> : 'Tanpa SKU'} ·{' '}
                            {produk.Satuan.map((satuan) => satuan.Simbol).join(', ')}
                        </span>
                    </li>
                ))}
            </ul>
            {galat ? (
                <p id={`${id}-galat`} className="text-keterangan font-semibold text-bahaya">
                    {galat}
                </p>
            ) : null}
        </div>
    );
}
