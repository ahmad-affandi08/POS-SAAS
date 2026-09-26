/**
 * D-23 A: urai daftar produk yang ditempel dari Excel, Google Sheets, atau pesan WhatsApp menjadi baris
 * { Nama, Harga, Kategori }. Satu baris satu produk. Bentuk yang dikenali:
 * - kolom dipisah Tab (salinan Excel), `;`, atau `|`: `Nama<Tab>Harga<Tab>Kategori`
 * - teks bebas dengan harga di akhir: `Kopi Susu 15.000`, `Es Teh - Rp5.000,-`, `Roti Bakar: 12rb`, `Nasi 2,5k`
 * Harga dikembalikan sebagai string desimal polos ("15000"); tidak ada perhitungan float (CLAUDE.md #7).
 */

export type BarisTempel = { Nama: string; Harga: string; Kategori: string };

export type HasilTempel = {
    Baris: BarisTempel[];
    /** Nomor baris (mulai 1) yang tidak bisa dibaca: tanpa nama atau tanpa harga. */
    Dilewati: number[];
    /** Nama kategori yang tidak ada di daftar (produk tetap dibuat tanpa kategori). */
    KategoriTakDikenal: string[];
};

const polaHargaAkhir = /^(.*?)[\s:=,\-–]*(?:rp\.?\s*)?(\d[\d.,]*)\s*(k|rb|ribu)?\s*(?:,-|\.-)?\s*$/i;

/** Ubah teks harga Indonesia menjadi string desimal polos, atau null bila tidak valid. */
export function BacaHargaTeks(teks: string, pengali: string | undefined = undefined): string | null {
    const bersih = teks
        .replace(/^rp\.?/i, '')
        .replace(/(,-|\.-)$/, '')
        .replace(/\s/g, '');
    let bulat: string;
    let pecahan = '';

    if (/^\d{1,3}([.,]\d{3})+$/.test(bersih) && !pengali) {
        bulat = bersih.replace(/[.,]/g, '');
    } else if (/^\d+([.,]\d{1,3})?$/.test(bersih)) {
        const [depan = '', belakang = ''] = bersih.split(/[.,]/);
        bulat = depan;
        pecahan = belakang;
    } else {
        return null;
    }

    if (pengali) {
        // 2,5rb = 2500; 12k = 12000.
        bulat = `${bulat}${pecahan.padEnd(3, '0')}`;
        pecahan = '';
    } else if (pecahan.length > 2) {
        return null;
    }

    bulat = bulat.replace(/^0+(?=\d)/, '');

    return pecahan === '' || /^0+$/.test(pecahan) ? bulat : `${bulat}.${pecahan}`;
}

function PisahKolom(baris: string): string[] | null {
    for (const pemisah of ['\t', ';', '|']) {
        if (baris.includes(pemisah)) {
            return baris.split(pemisah).map((sel) => sel.trim());
        }
    }

    return null;
}

/** Urai teks tempelan menjadi baris produk. */
export function UraiDaftarTempel(teks: string, kategori: { Uuid: string; Nama: string }[]): HasilTempel {
    const hasil: HasilTempel = { Baris: [], Dilewati: [], KategoriTakDikenal: [] };
    const petaKategori = new Map(kategori.map((item) => [item.Nama.trim().toLowerCase(), item.Uuid]));

    teks.split(/\r?\n/).forEach((mentah, indeks) => {
        const baris = mentah.trim();

        if (baris === '') {
            return;
        }

        const kolom = PisahKolom(baris);
        let nama = '';
        let harga: string | null = null;
        let namaKategori = '';

        if (kolom !== null) {
            nama = kolom[0] ?? '';
            const cocok = /^(.*?)\s*(k|rb|ribu)?$/i.exec(kolom[1] ?? '');
            harga = BacaHargaTeks(cocok?.[1] ?? '', cocok?.[2]);
            namaKategori = kolom[2] ?? '';
        } else {
            const cocok = polaHargaAkhir.exec(baris);

            if (cocok) {
                nama = (cocok[1] ?? '').trim();
                harga = BacaHargaTeks(cocok[2] ?? '', cocok[3]);
            }
        }

        // Baris judul kolom dari Excel (misal "Nama | Harga | Kategori") dilewati diam-diam.
        if (indeks === 0 && harga === null && /nama/i.test(baris) && /harga/i.test(baris)) {
            return;
        }

        nama = nama.replace(/^[-*•\d.)\s]+(?=\D)/, '').trim();

        if (nama === '' || harga === null) {
            hasil.Dilewati.push(indeks + 1);

            return;
        }

        let uuidKategori = '';

        if (namaKategori !== '') {
            uuidKategori = petaKategori.get(namaKategori.toLowerCase()) ?? '';

            if (uuidKategori === '' && !hasil.KategoriTakDikenal.includes(namaKategori)) {
                hasil.KategoriTakDikenal.push(namaKategori);
            }
        }

        hasil.Baris.push({ Nama: nama.slice(0, 150), Harga: harga, Kategori: uuidKategori });
    });

    return hasil;
}
