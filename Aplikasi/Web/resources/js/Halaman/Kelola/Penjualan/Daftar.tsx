import { kolomPenjualan } from '@/Komponen/Penjualan/KolomPenjualan';
import TabelData from '@/Komponen/TabelData/TabelData';
import type { DefinisiSaring } from '@/Komponen/TabelData/Tipe';
import TataLetakAplikasi from '@/TataLetak/TataLetakAplikasi';
import type { PropsDaftarPenjualan } from '@/Tipe/Penjualan';

const alamat = '/kelola/penjualan';

/** F-07b: daftar penjualan dari aplikasi POS (baca saja). Koreksi lewat void/retur menyusul (F-09). */
export default function HalamanDaftarPenjualan({ Penjualan, OpsiOutlet, OpsiStatus, OpsiKanal }: PropsDaftarPenjualan) {
    const saring: DefinisiSaring[] = [
        ...(OpsiOutlet.length > 1
            ? [
                  {
                      id: 'Outlet',
                      label: 'Outlet',
                      jenis: 'pilihanBanyak' as const,
                      opsi: OpsiOutlet.map((o) => ({ nilai: o.Uuid, label: o.Nama })),
                  },
              ]
            : []),
        {
            id: 'Status',
            label: 'Status',
            jenis: 'pilihanBanyak',
            opsi: OpsiStatus.map((o) => ({ nilai: o.Nilai, label: o.Label })),
        },
        {
            id: 'Kanal',
            label: 'Kanal',
            jenis: 'pilihanBanyak',
            opsi: OpsiKanal.map((o) => ({ nilai: o.Nilai, label: o.Label })),
        },
        { id: 'TanggalBisnis', label: 'Hari bisnis', jenis: 'rentangTanggal' },
        { id: 'PerluTinjauan', label: 'Perlu ditinjau', jenis: 'ya', labelAktif: 'Hanya yang perlu ditinjau' },
    ];

    return (
        <TataLetakAplikasi judul="Penjualan">
            <p className="max-w-3xl text-isi text-teks-sekunder">
                Penjualan dibuat kasir di aplikasi POS, termasuk saat offline, lalu terkirim ke sini begitu perangkat
                online. Stok dan jurnal tercatat otomatis saat penjualan diterima.
            </p>

            <TabelData
                id="penjualan"
                label="Daftar penjualan"
                kolom={kolomPenjualan}
                sumber={{ mode: 'server', alamat, awal: Penjualan }}
                ambilIdBaris={(baris) => baris.Uuid}
                urutBawaan="-DibuatOfflinePada"
                cari="Cari nomor atau nama kasir"
                saring={saring}
                alamatDetail={(baris) => `${alamat}/${baris.Uuid}`}
                kosong={{
                    ilustrasi: 'Penjualan',
                    judul: 'Belum ada penjualan. Penjualan muncul di sini setelah kasir berjualan di aplikasi POS dan perangkatnya tersinkron.',
                }}
            />
        </TataLetakAplikasi>
    );
}
