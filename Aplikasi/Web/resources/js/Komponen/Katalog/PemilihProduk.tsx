import { useQuery } from '@tanstack/react-query';
import { useCommandState } from 'cmdk';
import { useCallback, useEffect, useId, useRef, useState, type ComponentProps, type KeyboardEvent } from 'react';

import { Command, CommandItem, CommandList } from '@/Komponen/Ui/command';
import { Input } from '@/Komponen/Ui/input';
import { Label } from '@/Komponen/Ui/label';
import { Popover, PopoverAnchor, PopoverContent } from '@/Komponen/Ui/popover';
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

/** Id elemen opsi cmdk bernilai `nilai` di dalam daftar `idDaftar` (id opsi dibuat cmdk, bukan oleh kita). */
function CariIdOpsi(idDaftar: string | undefined, nilai: string): string | undefined {
    if (idDaftar === undefined || nilai === '') {
        return undefined;
    }

    const opsi = Array.from(document.getElementById(idDaftar)?.querySelectorAll('[cmdk-item]') ?? []).find(
        (elemen) => elemen.getAttribute('data-value') === nilai,
    );

    return opsi?.id || undefined;
}

/**
 * Input combobox di dalam `Command`: `aria-activedescendant` mengikuti opsi yang disorot cmdk.
 * Dipisah agar hook `useCommandState` berada di dalam konteks Command. Id dicari dari nilai yang disorot karena
 * cmdk belum mengisi `selectedItemId` saat opsi pertama disorot otomatis.
 */
function MasukanPemilih({
    daftarTerlihat,
    idDaftar,
    ...atribut
}: ComponentProps<typeof Input> & { daftarTerlihat: boolean; idDaftar: string | undefined }) {
    const idTersorot = useCommandState((keadaan) => CariIdOpsi(idDaftar, keadaan.value) ?? keadaan.selectedItemId);

    return (
        <Input
            type="search"
            role="combobox"
            aria-expanded={daftarTerlihat}
            aria-controls={daftarTerlihat ? idDaftar : undefined}
            aria-autocomplete="list"
            aria-activedescendant={daftarTerlihat ? idTersorot : undefined}
            autoComplete="off"
            {...atribut}
        />
    );
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
 * Pemilih produk dengan pencarian server (TanStack Query, KunciKueri.Produk.Cari), dibangun dari Command + Popover.
 * Combobox ARIA: panah atas/bawah memilih, Enter memasukkan, Escape menutup. Keadaan memuat, kosong, dan galat tertulis.
 * Penyaringan dilakukan server (`shouldFilter` mati); cmdk hanya mengatur sorotan (opsi pertama saat hasil baru) dan keyboard.
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
    const jangkar = useRef<HTMLDivElement>(null);
    const [kata, AturKata] = useState('');
    const [terbuka, AturTerbuka] = useState(false);
    const [idDaftar, AturIdDaftar] = useState<string | undefined>(undefined);
    const kataCari = useNilaiTertunda(kata.trim(), 300);
    const aktif = terbuka && kataCari.length >= 2;
    const kueri = useQuery({
        queryKey: KunciKueri.Produk.Cari(kataCari, jenis),
        queryFn: ({ signal }) => AmbilHasilCari(BuatUrlCariProduk(kataCari, jenis), signal),
        enabled: aktif,
        staleTime: 30_000,
    });
    const hasil = (kueri.data?.Data ?? []).filter((produk) => !kecuali.includes(produk.Uuid));
    const daftarTerlihat = aktif && hasil.length > 0;
    const AturRefDaftar = useCallback((elemen: HTMLDivElement | null) => AturIdDaftar(elemen?.id), []);

    const Pilih = (produk: ProdukTerpilih) => {
        saatPilih(produk);
        AturKata('');
        AturTerbuka(false);
    };

    /** Tombol yang tidak ditangani cmdk dihentikan di sini agar perilaku input teks tetap (caret, kirim form). */
    const TekanTombol = (peristiwa: KeyboardEvent<HTMLInputElement>) => {
        if (peristiwa.key === 'Home' || peristiwa.key === 'End') {
            peristiwa.stopPropagation();
        } else if (peristiwa.key === 'Escape') {
            AturTerbuka(false);
        } else if (!daftarTerlihat) {
            if (peristiwa.key === 'ArrowDown') {
                peristiwa.preventDefault();
                AturTerbuka(true);
            } else if (peristiwa.key === 'ArrowUp') {
                peristiwa.preventDefault();
            } else if (peristiwa.key === 'Enter') {
                peristiwa.stopPropagation();
            }
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

    return (
        <div className="flex flex-col gap-1">
            <Label htmlFor={id} className="text-label font-semibold text-teks-utama">
                {label}
            </Label>
            <Command
                shouldFilter={false}
                vimBindings={false}
                className="h-auto overflow-visible rounded-none bg-transparent"
            >
                <Popover
                    open={daftarTerlihat}
                    onOpenChange={(buka) => {
                        if (!buka) {
                            AturTerbuka(false);
                        }
                    }}
                >
                    <PopoverAnchor asChild>
                        <div ref={jangkar}>
                            <MasukanPemilih
                                id={id}
                                daftarTerlihat={daftarTerlihat}
                                idDaftar={idDaftar}
                                aria-invalid={galat ? true : undefined}
                                aria-describedby={[
                                    keterangan ? `${id}-keterangan` : null,
                                    galat ? `${id}-galat` : null,
                                    `${id}-status`,
                                ]
                                    .filter(Boolean)
                                    .join(' ')}
                                value={kata}
                                disabled={disabled}
                                placeholder="Cari nama, SKU, atau barcode"
                                onChange={(peristiwa) => {
                                    AturKata(peristiwa.target.value);
                                    AturTerbuka(true);
                                }}
                                onFocus={() => AturTerbuka(true)}
                                onBlur={() => window.setTimeout(() => AturTerbuka(false), 150)}
                                onKeyDown={TekanTombol}
                                className="h-8 pointer-coarse:h-11 text-isi"
                            />
                        </div>
                    </PopoverAnchor>
                    <PopoverContent
                        align="start"
                        className="w-(--radix-popover-trigger-width) p-0"
                        onOpenAutoFocus={(peristiwa) => peristiwa.preventDefault()}
                        onCloseAutoFocus={(peristiwa) => peristiwa.preventDefault()}
                        onInteractOutside={(peristiwa) => {
                            if (jangkar.current?.contains(peristiwa.target as Node)) {
                                peristiwa.preventDefault();
                            }
                        }}
                    >
                        <CommandList ref={AturRefDaftar} label={`Hasil ${label}`} className="max-h-72">
                            {hasil.map((produk) => (
                                <CommandItem
                                    key={produk.Uuid}
                                    value={produk.Uuid}
                                    onSelect={() => Pilih(produk)}
                                    onMouseDown={(peristiwa) => peristiwa.preventDefault()}
                                    className="flex cursor-pointer flex-col items-start gap-0 px-3 py-2 text-isi"
                                >
                                    <span className="font-semibold break-words text-teks-utama">{produk.Nama}</span>
                                    <span className="text-keterangan text-teks-sekunder">
                                        {produk.Sku ? <span className="font-mono">{produk.Sku}</span> : 'Tanpa SKU'} ·{' '}
                                        {produk.Satuan.map((satuan) => satuan.Simbol).join(', ')}
                                    </span>
                                </CommandItem>
                            ))}
                        </CommandList>
                    </PopoverContent>
                </Popover>
            </Command>
            {keterangan ? (
                <p id={`${id}-keterangan`} className="text-keterangan text-teks-sekunder">
                    {keterangan}
                </p>
            ) : null}
            <p id={`${id}-status`} aria-live="polite" className="text-keterangan text-teks-sekunder">
                {status ?? ''}
            </p>
            {galat ? (
                <p id={`${id}-galat`} className="text-keterangan font-semibold text-bahaya">
                    {galat}
                </p>
            ) : null}
        </div>
    );
}
