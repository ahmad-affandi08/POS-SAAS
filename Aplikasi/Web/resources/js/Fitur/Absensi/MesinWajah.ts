import type { Human, Config } from '@vladmandic/human';

import { KeSidikWajah, NilaiWajah, type PenilaianWajah } from '@/Fitur/Absensi/SidikWajah';

/**
 * Pembungkus model wajah di browser (pustaka Human, MIT) untuk absensi web (F-18 bagian 4, D-37). Dimuat hanya di
 * halaman absen (impor dinamis), model dari `/model-wajah/` milik server sendiri, tanpa layanan pihak ketiga.
 * Keputusan cocok-tidaknya wajah dibuat server; di sini hanya deteksi, cek wajah hidup, dan sidik wajah.
 */
const KONFIGURASI: Partial<Config> = {
    backend: 'humangl',
    async: true,
    warmup: 'none',
    cacheSensitivity: 0,
    debug: false,
    face: {
        enabled: true,
        detector: { enabled: true, rotation: false, maxDetected: 2, return: false },
        mesh: { enabled: true },
        iris: { enabled: false },
        description: { enabled: true },
        emotion: { enabled: false },
        antispoof: { enabled: true },
        liveness: { enabled: true },
        attention: { enabled: false },
    },
    body: { enabled: false },
    hand: { enabled: false },
    object: { enabled: false },
    gesture: { enabled: true },
    segmentation: { enabled: false },
};

export type HasilPemindaian = { Penilaian: PenilaianWajah; Sidik: number[] | null };

let mesin: Promise<Human> | null = null;

/** Memuat model sekali per halaman (±10 MB pertama kali; sesudahnya dari cache service worker). */
export function MuatMesinWajah(alamatModel: string): Promise<Human> {
    mesin ??= import('@vladmandic/human').then(async ({ Human: KelasHuman }) => {
        const human = new KelasHuman({ ...KONFIGURASI, modelBasePath: `${alamatModel.replace(/\/$/, '')}/` });
        await human.load();

        return human;
    });

    return mesin;
}

/** Satu kali deteksi dari video kamera; [sudahKedip] dibawa dari pemindaian sebelumnya. */
export async function PindaiWajah(
    human: Human,
    video: HTMLVideoElement,
    sudahKedip: boolean,
): Promise<HasilPemindaian & { Kedip: boolean }> {
    const hasil = await human.detect(video);
    const kedip = sudahKedip || hasil.gesture.some((g) => 'face' in g && g.gesture.startsWith('blink'));
    const penilaian = NilaiWajah(hasil.face, kedip);
    const deskriptor = hasil.face[0]?.embedding;

    return {
        Penilaian: penilaian,
        Kedip: kedip,
        Sidik: penilaian.Lolos && deskriptor ? KeSidikWajah(deskriptor) : null,
    };
}

/** Swafoto JPEG base64 (tanpa awalan data URL) maksimal 480 px, sesuai batas ukuran swafoto server. */
export function AmbilSwafoto(video: HTMLVideoElement): string {
    const skala = Math.min(1, 480 / Math.max(video.videoWidth, video.videoHeight, 1));
    const kanvas = document.createElement('canvas');
    kanvas.width = Math.round(video.videoWidth * skala);
    kanvas.height = Math.round(video.videoHeight * skala);
    kanvas.getContext('2d')?.drawImage(video, 0, 0, kanvas.width, kanvas.height);

    return kanvas.toDataURL('image/jpeg', 0.7).replace(/^data:image\/jpeg;base64,/, '');
}
