/**
 * Render test untuk halaman yang memakai `TabelData`/TanStack Query: dibungkus `QueryClientProvider` baru per render
 * (tanpa retry) agar cache tidak bocor antar-test. Bukan kode produksi.
 */
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render } from '@testing-library/react';
import type { ReactElement } from 'react';

export function RenderDenganKueri(elemen: ReactElement) {
    const klien = new QueryClient({ defaultOptions: { queries: { retry: false } } });

    return render(<QueryClientProvider client={klien}>{elemen}</QueryClientProvider>);
}
