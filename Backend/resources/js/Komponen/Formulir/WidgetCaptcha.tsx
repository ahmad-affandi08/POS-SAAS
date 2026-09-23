import { useEffect, useRef } from 'react';

type Turnstile = {
    render: (
        elemen: HTMLElement,
        opsi: { sitekey: string; callback: (token: string) => void; language: string },
    ) => string;
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

type PropsWidgetCaptcha = { kunciSitus: string; saatBerhasil: (token: string) => void; galat?: string | undefined };

/** Cloudflare Turnstile untuk formulir publik (BR-00.4). */
export default function WidgetCaptcha({ kunciSitus, saatBerhasil, galat }: PropsWidgetCaptcha) {
    const wadah = useRef<HTMLDivElement>(null);

    useEffect(() => {
        let idWidget: string | null = null;
        let batal = false;

        void MuatSkrip().then(() => {
            if (!batal && wadah.current && window.turnstile) {
                idWidget = window.turnstile.render(wadah.current, {
                    sitekey: kunciSitus,
                    callback: saatBerhasil,
                    language: 'id',
                });
            }
        });

        return () => {
            batal = true;

            if (idWidget && window.turnstile) {
                window.turnstile.remove(idWidget);
            }
        };
    }, [kunciSitus, saatBerhasil]);

    return (
        <div className="flex flex-col gap-1">
            <div ref={wadah} />
            {galat ? <p className="text-keterangan font-semibold text-bahaya">{galat}</p> : null}
        </div>
    );
}
