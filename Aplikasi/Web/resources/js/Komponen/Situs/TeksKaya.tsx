import { Fragment, type ReactNode } from 'react';

/** Hanya tautan aman yang dirender sebagai `<a>` (tanpa javascript:, data:, dst.). */
function CekTautanAman(url: string): boolean {
    return /^(https:\/\/|\/(?!\/)|#|mailto:|tel:)/.test(url);
}

/** Inline: **tebal** dan [teks](tautan). Teks lain dirender apa adanya (React meng-escape HTML). */
function RenderInline(teks: string, kunci: string): ReactNode[] {
    const hasil: ReactNode[] = [];
    const pola = /\*\*([^*]+)\*\*|\[([^\]]+)\]\(([^)\s]+)\)/g;
    let akhir = 0;
    let cocok: RegExpExecArray | null;
    let i = 0;

    while ((cocok = pola.exec(teks)) !== null) {
        if (cocok.index > akhir) {
            hasil.push(teks.slice(akhir, cocok.index));
        }

        if (cocok[1] !== undefined) {
            hasil.push(<strong key={`${kunci}-b${i}`}>{cocok[1]}</strong>);
        } else if (cocok[2] !== undefined && cocok[3] !== undefined) {
            hasil.push(
                CekTautanAman(cocok[3]) ? (
                    <a key={`${kunci}-a${i}`} href={cocok[3]} className="font-semibold text-brand underline">
                        {cocok[2]}
                    </a>
                ) : (
                    cocok[2]
                ),
            );
        }

        akhir = cocok.index + cocok[0].length;
        i += 1;
    }

    if (akhir < teks.length) {
        hasil.push(teks.slice(akhir));
    }

    return hasil;
}

/**
 * Teks bebas situs (D-21) tanpa HTML: paragraf dipisah baris kosong, `## ` judul, `- ` butir daftar, baris baru di
 * dalam paragraf dipertahankan, **tebal**, dan [tautan](url).
 */
export default function TeksKaya({ teks, className }: { teks: string; className?: string }) {
    const blok = teks.split(/\n{2,}/);

    return (
        <div className={`flex flex-col gap-4 ${className ?? ''}`}>
            {blok.map((isi, i) => {
                const baris = isi.split('\n').filter((b) => b.trim() !== '');

                if (baris.length > 0 && baris.every((b) => b.trimStart().startsWith('- '))) {
                    return (
                        <ul key={i} className="flex list-disc flex-col gap-1 pl-5">
                            {baris.map((b, j) => (
                                <li key={j}>{RenderInline(b.trimStart().slice(2), `${i}-${j}`)}</li>
                            ))}
                        </ul>
                    );
                }

                if (baris.length === 1 && baris[0]?.startsWith('## ')) {
                    return (
                        <h3 key={i} className="text-judul font-bold text-teks-utama">
                            {baris[0].slice(3)}
                        </h3>
                    );
                }

                return (
                    <p key={i}>
                        {baris.map((b, j) => (
                            <Fragment key={j}>
                                {j > 0 ? <br /> : null}
                                {RenderInline(b, `${i}-${j}`)}
                            </Fragment>
                        ))}
                    </p>
                );
            })}
        </div>
    );
}
