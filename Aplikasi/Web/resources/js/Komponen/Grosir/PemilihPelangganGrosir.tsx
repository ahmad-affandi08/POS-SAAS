import { useState } from 'react';

import BidangTeks from '@/Komponen/Formulir/BidangTeks';
import Tombol from '@/Komponen/Formulir/Tombol';
import { FormatRupiah } from '@/Pustaka/Format';

type HasilPelanggan = {
    Uuid: string;
    Nama: string;
    NoHp: string | null;
    LimitKredit: string | null;
    SisaPiutang?: string | null;
};

/**
 * Pemilih pelanggan untuk pesanan grosir. Limit kredit & sisa piutangnya ditampilkan di hasil pencarian, karena itulah
 * yang menentukan apakah pesanan besar nanti lolos BR-12.6 — lebih baik operator tahu sebelum menyusun barisnya
 * daripada ditolak di akhir.
 */
export default function PemilihPelangganGrosir({
    uuidTerpilih,
    namaTerpilih,
    saatPilih,
    galat,
}: {
    uuidTerpilih: string;
    namaTerpilih: string;
    saatPilih: (uuid: string, nama: string) => void;
    galat?: string | undefined;
}) {
    const [kata, AturKata] = useState('');
    const [hasil, AturHasil] = useState<HasilPelanggan[]>([]);
    const [memuat, AturMemuat] = useState(false);
    const [pesan, AturPesan] = useState<string | null>(null);

    const Cari = async () => {
        AturMemuat(true);
        AturPesan(null);

        try {
            const jawaban = await fetch(`/kelola/grosir/pelanggan/cari?kata=${encodeURIComponent(kata.trim())}`, {
                headers: { Accept: 'application/json' },
            });

            if (!jawaban.ok) {
                throw new Error('gagal');
            }

            const isi = (await jawaban.json()) as { Data?: HasilPelanggan[] };
            const data = isi.Data ?? [];
            AturHasil(data);
            AturPesan(data.length === 0 ? `Tidak ada pelanggan yang cocok dengan "${kata.trim()}".` : null);
        } catch {
            AturPesan('Pencarian gagal. Periksa koneksi lalu coba lagi.');
        } finally {
            AturMemuat(false);
        }
    };

    return (
        <div className="flex flex-col gap-2">
            {uuidTerpilih === '' ? null : (
                <p className="text-isi text-teks-utama">
                    Pelanggan: <span className="font-semibold">{namaTerpilih}</span>
                </p>
            )}
            <div className="flex flex-wrap items-end gap-2">
                <div className="min-w-56 flex-1">
                    <BidangTeks
                        label="Cari pelanggan"
                        nilai={kata}
                        saatBerubah={AturKata}
                        keterangan="Nama atau nomor WhatsApp, minimal 3 huruf."
                        galat={galat}
                    />
                </div>
                <Tombol
                    type="button"
                    varian="sekunder"
                    onClick={() => void Cari()}
                    memproses={memuat}
                    disabled={kata.trim().length < 3}
                >
                    Cari
                </Tombol>
            </div>
            {pesan === null ? null : <p className="text-keterangan text-teks-sekunder">{pesan}</p>}
            {hasil.length === 0 ? null : (
                <ul className="flex flex-col gap-1">
                    {hasil.map((p) => (
                        <li key={p.Uuid}>
                            <button
                                type="button"
                                onClick={() => {
                                    saatPilih(p.Uuid, p.Nama);
                                    AturHasil([]);
                                    AturKata('');
                                }}
                                className="w-full rounded-panel border border-garis px-3 py-2 text-left text-isi hover:bg-permukaan-2"
                            >
                                <span className="font-semibold break-words text-teks-utama">{p.Nama}</span>
                                <span className="block text-keterangan text-teks-sekunder">
                                    {p.NoHp ?? 'Tanpa nomor'}
                                    {p.LimitKredit === null
                                        ? ' · tanpa limit kredit'
                                        : ` · limit ${FormatRupiah(p.LimitKredit)}`}
                                </span>
                            </button>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}
