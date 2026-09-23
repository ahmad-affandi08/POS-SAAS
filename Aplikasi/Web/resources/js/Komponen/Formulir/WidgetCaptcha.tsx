import { useEffect, useRef, useState } from 'react';

type OpsiTurnstile = {
    sitekey: string;
    callback: (token: string) => void;
    'expired-callback': () => void;
    'error-callback': () => void;
    language: string;
};

type Turnstile = {
    render: (elemen: HTMLElement, opsi: OpsiTurnstile) => string;
    reset: (id: string) => void;
    remove: (id: string) => void;
};

declare global {
    interface Window {
        turnstile?: Turnstile;
    }
}

const URL_SKRIP = 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit';

function MuatSkrip(): Promise<void> {
    return new Promise((selesai, gagal) => {
        if (window.turnstile) {
            selesai();

            return;
        }

        const skripAda = document.querySelector<HTMLScriptElement>(`script[src="${URL_SKRIP}"]`);
        const skrip = skripAda ?? document.createElement('script');
        skrip.addEventListener('load', () => selesai());
        skrip.addEventListener('error', () => gagal(new Error('Skrip CAPTCHA gagal dimuat.')));

        if (!skripAda) {
            skrip.src = URL_SKRIP;
            skrip.async = true;
            document.head.appendChild(skrip);
        }
    });
}

type PropsWidgetCaptcha = {
    kunciSitus: string;
    /** Token baru, atau string kosong saat token kedaluwarsa/galat. */
    saatBerubah: (token: string) => void;
    /** Naikkan angka ini untuk meminta CAPTCHA baru (token Turnstile hanya berlaku sekali). */
    urutanReset: number;
    galat?: string | undefined;
};

/** Cloudflare Turnstile untuk formulir publik (BR-00.4), dengan keadaan memuat & galat (§17.6.6). */
export default function WidgetCaptcha({ kunciSitus, saatBerubah, urutanReset, galat }: PropsWidgetCaptcha) {
    const wadah = useRef<HTMLDivElement>(null);
    const idWidget = useRef<string | null>(null);
    const [keadaan, AturKeadaan] = useState<'memuat' | 'siap' | 'galat'>('memuat');

    useEffect(() => {
        let batal = false;

        MuatSkrip()
            .then(() => {
                if (!batal && wadah.current && window.turnstile) {
                    idWidget.current = window.turnstile.render(wadah.current, {
                        sitekey: kunciSitus,
                        callback: saatBerubah,
                        'expired-callback': () => saatBerubah(''),
                        'error-callback': () => saatBerubah(''),
                        language: 'id',
                    });
                    AturKeadaan('siap');
                }
            })
            .catch(() => {
                if (!batal) {
                    AturKeadaan('galat');
                }
            });

        return () => {
            batal = true;

            if (idWidget.current && window.turnstile) {
                window.turnstile.remove(idWidget.current);
                idWidget.current = null;
            }
        };
    }, [kunciSitus, saatBerubah]);

    useEffect(() => {
        if (urutanReset > 0 && idWidget.current && window.turnstile) {
            window.turnstile.reset(idWidget.current);
            saatBerubah('');
        }
    }, [urutanReset, saatBerubah]);

    return (
        <div className="flex flex-col gap-1" aria-live="polite">
            <div ref={wadah} />
            {keadaan === 'memuat' ? (
                <p className="text-keterangan text-teks-sekunder">Memuat verifikasi keamanan…</p>
            ) : null}
            {keadaan === 'galat' ? (
                <p className="text-keterangan font-semibold text-bahaya">
                    Verifikasi keamanan gagal dimuat. Periksa koneksi internet lalu muat ulang halaman.
                </p>
            ) : null}
            {galat ? <p className="text-keterangan font-semibold text-bahaya">{galat}</p> : null}
        </div>
    );
}
