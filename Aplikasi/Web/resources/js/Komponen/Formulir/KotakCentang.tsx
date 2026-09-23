type PropsKotakCentang = { label: string; nilai: boolean; saatBerubah: (nilai: boolean) => void };

/** Satu kotak centang dengan label yang bisa diklik (target sentuh ≥ 40px). */
export default function KotakCentang({ label, nilai, saatBerubah }: PropsKotakCentang) {
    return (
        <label className="flex min-h-10 items-center gap-2 text-isi text-teks-utama">
            <input
                type="checkbox"
                className="size-4 accent-brand"
                checked={nilai}
                onChange={(peristiwa) => saatBerubah(peristiwa.target.checked)}
            />
            {label}
        </label>
    );
}
