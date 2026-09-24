import { TriangleAlertIcon } from 'lucide-react';
import type { ReactNode } from 'react';

import { Alert, AlertDescription, AlertTitle } from '@/Komponen/Ui/alert';

/**
 * Peringatan preset impor yang nama kolomnya masih asumsi (majoo, Moka, Pawoon; DesainF03 H.10).
 * Ikon + judul teks, jadi tetap terbaca tanpa warna.
 */
export default function PeringatanAsumsi({ children }: { children: ReactNode }) {
    return (
        <Alert className="rounded-panel border-l-4 border-peringatan">
            <TriangleAlertIcon aria-hidden="true" className="text-peringatan" />
            <AlertTitle className="text-label font-semibold text-teks-utama">
                Periksa pemetaan kolom sebelum mengimpor
            </AlertTitle>
            <AlertDescription className="text-isi text-teks-sekunder">{children}</AlertDescription>
        </Alert>
    );
}
