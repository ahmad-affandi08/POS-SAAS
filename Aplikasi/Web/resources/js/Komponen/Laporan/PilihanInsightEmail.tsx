import { router } from '@inertiajs/react';
import { useState } from 'react';

import { Switch } from '@/Komponen/Ui/switch';
import type { InsightEmailLaporan } from '@/Tipe/Laporan';

/** X6 (v3.79): berlangganan insight penjualan mingguan lewat email (pilihan pribadi; Owner bawaan aktif). */
export default function PilihanInsightEmail({ insight }: { insight: InsightEmailLaporan }) {
    const [memproses, AturMemproses] = useState(false);

    return (
        <div className="flex max-w-3xl items-start gap-3 rounded-panel border border-garis bg-permukaan p-4">
            <Switch
                id="InsightEmail"
                checked={insight.Aktif}
                disabled={!insight.BisaEmail || memproses}
                onCheckedChange={(aktif) =>
                    router.put(
                        '/kelola/laporan/penjualan/insight-email',
                        { Aktif: aktif },
                        {
                            preserveScroll: true,
                            onStart: () => AturMemproses(true),
                            onFinish: () => AturMemproses(false),
                        },
                    )
                }
            />
            <div className="flex min-w-0 flex-col gap-0.5">
                <label htmlFor="InsightEmail" className="text-isi font-semibold text-teks-utama">
                    Kirim insight mingguan ke email saya
                </label>
                <span className="text-label text-teks-sekunder">
                    {insight.BisaEmail
                        ? 'Tiap Senin pukul 07.15 WIB: penjualan minggu lalu dibanding minggu sebelumnya, produk naik & turun, dan stok yang segera habis.'
                        : 'Akun Anda belum punya email, jadi insight tidak bisa dikirim.'}
                </span>
            </div>
        </div>
    );
}
